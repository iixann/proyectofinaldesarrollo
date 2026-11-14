<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CompraModel;
use app\models\ProveedorModel;
use app\models\ProductoModel;
use app\models\RecepcionModel;

// Controla las operaciones de compras.
class CompraController extends Controller
{
    // Muestra el listado de compras con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $filtros = ['id_compra' => $this->leerFiltro('id_compra', 10, true),
            'numero_comprobante' => $this->leerFiltro('numero_comprobante', 50),
            'id_proveedor' => $this->leerFiltro('id_proveedor', 10, true)];
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'compras' => CompraModel::listarCompras($filtros['id_compra'], $filtros['numero_comprobante'], $filtros['id_proveedor']),
            'filtros' => $filtros, 'proveedores' => ProveedorModel::listarProveedores(),
            'mensaje' => ($_GET['resultado'] ?? '') === 'registrada' ? 'Compra registrada correctamente. Pendiente de recepción; no modificó stock.' : ''
        ]);
    }

    // Muestra el formulario para crear la compra.
    public function actionCrear()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->mostrarFormulario();
    }

    // Recibe el formulario por POST y solicita guardar la compra.
    public function actionGuardar()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Detiene la acción si el método POST o el token no son válidos.
        if (!$this->validarPostCompra()) return;
        $datos = [];
        foreach (['id_proveedor', 'numero_comprobante', 'observacion'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        $renglones = [];
        $recibidos = $_POST['renglones'] ?? [];
        // Rechaza los renglones si no llegan como una lista.
        if (!is_array($recibidos)) {
            http_response_code(422);
            $this->mostrarFormulario($datos, [], ['Agregá al menos un producto válido.']);
            return;
        }
        foreach ($recibidos as $renglon) {
            $datosRenglon = [];
            foreach (['id_producto', 'cantidad', 'precio_unitario'] as $campo) {
                $datosRenglon[$campo] = is_array($renglon) && is_string($renglon[$campo] ?? null) ? trim($renglon[$campo]) : '';
            }
            $renglones[] = $datosRenglon;
        }
        try {
            // Pasa a CompraModel: guarda la compra y sus renglones pendientes de recepción, sin mover stock.
            CompraModel::registrarCompra($datos, $renglones, $_SESSION['usuario']['id_usuario']);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->mostrarFormulario($datos, $renglones, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            $duplicado = isset($excepcion->errorInfo[1]) && (int) $excepcion->errorInfo[1] === 1062;
            http_response_code($duplicado ? 422 : 500);
            $this->mostrarFormulario($datos, $renglones, [$duplicado ? 'El número de comprobante ya existe para ese proveedor.' : 'No se pudo registrar la compra. Intentá nuevamente.']);
            return;
        }
        // Redirige al navegador a compra/index.
        header('Location: /proyectofinaldesarrollo/compra/index?resultado=registrada', true, 303);
        exit;
    }

    // Crea un proveedor desde el modal y devuelve sus datos como JSON.
    public function actionCrearProveedorRapido()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Detiene la acción si el método POST o el token no son válidos.
        if (!$this->validarPostCompra(true)) return;
        try {
            // Pasa a ProveedorModel: valida los campos recibidos y devuelve los datos junto con los errores.
            [$datos, $errores] = ProveedorModel::validarDatos($_POST);
            // Si hay errores, los devuelve para corregir el formulario.
            if ($errores) {
                $this->json(['ok' => false, 'mensaje' => implode(' ', $errores)], 422);
                return;
            }
            // Pasa a ProveedorModel para crear el proveedor.
            $id = ProveedorModel::crearProveedor($datos);
        } catch (\Throwable $excepcion) {
            $duplicado = (string) $excepcion->getCode() === '23000';
            $this->json(['ok' => false, 'mensaje' => $duplicado ? 'Ya existe un proveedor con ese CUIT.' : 'No se pudo crear el proveedor. Intentá nuevamente.'], $duplicado ? 422 : 500);
            return;
        }
        $this->json(['ok' => true, 'proveedor' => ['id_proveedor' => (int) $id, 'razon_social' => $datos['razon_social']]], 201);
    }

    // Crea un producto o informa que el EAN ya pertenece a uno existente.
    public function actionCrearProductoRapido()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Detiene la acción si el método POST o el token no son válidos.
        if (!$this->validarPostCompra(true)) return;
        try {
            $idProveedor = $this->proveedorRapido();
            // Pasa a ProductoModel para consultar el EAN y su asociación al proveedor.
            $existente = ProductoModel::consultarCodigoParaProveedor($_POST['codigo_barras'] ?? null, $idProveedor);
            // Si el EAN ya existe, informa el producto para poder asociarlo.
            if ($existente) {
                $this->json(['ok' => false, 'existe' => true, 'producto' => $this->datosProductoExistente($existente, $idProveedor),
                    'mensaje' => 'Este Código EAN ya pertenece a un producto existente.'], 409);
                return;
            }
            // Pasa a ProductoModel: valida los campos recibidos y devuelve los datos junto con los errores.
            [$datos, $errores] = ProductoModel::validarDatos($_POST);
            // Si hay errores, los devuelve para corregir el formulario.
            if ($errores) {
                $this->json(['ok' => false, 'mensaje' => implode(' ', $errores)], 422);
                return;
            }
            // El modelo crea producto + asociación en su transacción habitual; stock inicial 0.
            $id = ProductoModel::crearProducto($datos, [$idProveedor]);
        } catch (\DomainException $excepcion) {
            $this->json(['ok' => false, 'mensaje' => $excepcion->getMessage()], 422);
            return;
        } catch (\Throwable $excepcion) {
            $duplicado = (string) $excepcion->getCode() === '23000';
            $this->json(['ok' => false, 'mensaje' => $duplicado ? 'Ya existe ese Código EAN o nombre en la categoría seleccionada.' : 'No se pudo crear el producto. Intentá nuevamente.'], $duplicado ? 422 : 500);
            return;
        }
        $this->json(['ok' => true, 'producto' => ['id_producto' => (int) $id, 'nombre_producto' => $datos['nombre_producto'],
            'id_proveedor' => (int) $idProveedor]], 201);
    }

    // Consulta por POST si el EAN existe y está asociado al proveedor.
    public function actionConsultarProductoRapido()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Detiene la acción si el método POST o el token no son válidos.
        if (!$this->validarPostCompra(true)) return;
        try {
            $idProveedor = $this->proveedorRapido();
            // Pasa a ProductoModel para consultar el EAN y su asociación al proveedor.
            $producto = ProductoModel::consultarCodigoParaProveedor($_POST['codigo_barras'] ?? null, $idProveedor);
            $this->json(['ok' => true, 'existe' => (bool) $producto,
                'producto' => $producto ? $this->datosProductoExistente($producto, $idProveedor) : null]);
        } catch (\DomainException $excepcion) {
            $this->json(['ok' => false, 'mensaje' => $excepcion->getMessage()], 422);
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No se pudo consultar el EAN. Intentá nuevamente.'], 500);
        }
    }

    // Asocia el producto existente al proveedor y responde al modal.
    public function actionAsociarProductoRapido()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Detiene la acción si el método POST o el token no son válidos.
        if (!$this->validarPostCompra(true)) return;
        try {
            $idProveedor = $this->proveedorRapido();
            // Pasa a ProductoModel para asociar el producto al proveedor.
            $resultado = ProductoModel::asociarProveedorExistente($_POST['id_producto'] ?? null, $idProveedor, $_POST['codigo_barras'] ?? null);
            $producto = $resultado['producto'];
            $this->json(['ok' => true, 'ya_asociado' => (bool) $resultado['ya_asociado'],
                'producto' => ['id_producto' => (int) $producto->id_producto, 'nombre_producto' => $producto->nombre_producto,
                    'id_proveedor' => (int) $idProveedor]]);
        } catch (\DomainException $excepcion) {
            $this->json(['ok' => false, 'mensaje' => $excepcion->getMessage()], 422);
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No se pudo asociar el producto. Intentá nuevamente.'], 500);
        }
    }

    // Devuelve el ID enviado si pertenece a un proveedor activo.
    private function proveedorRapido()
    {
        $id = $_POST['id_proveedor'] ?? null;
        $proveedor = is_string($id) && ctype_digit($id) && (float) $id >= 1 && (float) $id <= 4294967295
            ? ProveedorModel::findId($id) : false;
        // Exige que el proveedor exista y esté activo.
        if (!$proveedor || (int) $proveedor->estado !== 1) {
            throw new \DomainException('Seleccioná un proveedor activo antes de continuar.');
        }
        return $id;
    }

    // Prepara para JavaScript el producto y su asociación al proveedor.
    private function datosProductoExistente($producto, $idProveedor)
    {
        return ['id_producto' => (int) $producto->id_producto, 'nombre_producto' => $producto->nombre_producto,
            'codigo_barras' => (string) $producto->codigo_barras, 'activo' => (int) $producto->estado === 1,
            'asociado' => (int) $producto->asociado === 1, 'id_proveedor' => (int) $idProveedor];
    }

    // Verifica POST y el token; devuelve false si rechaza la solicitud.
    private function validarPostCompra($json = false)
    {
        $estado = 0;
        $mensaje = '';
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $estado = 405;
            $mensaje = 'Método no permitido.';
        } else {
            $token = $_POST['csrf_compra'] ?? '';
            // Rechaza el formulario si su token no coincide con la sesión.
            if (!is_string($token) || empty($_SESSION['csrf_compra']) || !hash_equals($_SESSION['csrf_compra'], $token)) {
                $estado = 403;
                $mensaje = 'El formulario venció. Recargá Nueva compra antes de continuar.';
            }
        }
        if (!$estado) return true;
        if ($json) $this->json(['ok' => false, 'mensaje' => $mensaje], $estado);
        else {
            http_response_code($estado);
            echo $mensaje;
        }
        return false;
    }

    // Devuelve los datos como JSON con el estado HTTP indicado.
    private function json($datos, $estado = 200)
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    // Busca la compra y carga su vista de detalle.
    public function actionVer($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de compra inválido.';
            return;
        }
        // Pasa a CompraModel para buscar la compra con su proveedor.
        $compra = CompraModel::buscarCompra($id);
        // Si no encuentra la compra, detiene la solicitud.
        if (!$compra) {
            http_response_code(404);
            echo 'La compra no existe.';
            return;
        }
        // Envía los datos a la vista ver.
        Response::render($this->viewDir(__NAMESPACE__), 'ver', ['compra' => $compra, 'detalles' => CompraModel::obtenerDetalle($id), 'recepcion' => RecepcionModel::buscarPorCompra($id)]);
    }

    // Carga la vista del formulario con los datos y errores de la compra.
    private function mostrarFormulario($datos = [], $renglones = [], $errores = [])
    {
        // Crea el token del formulario si todavía no existe en la sesión.
        if (empty($_SESSION['csrf_compra'])) $_SESSION['csrf_compra'] = bin2hex(random_bytes(32));
        // Envía los datos a la vista crear.
        Response::render($this->viewDir(__NAMESPACE__), 'crear', [
            'datos' => $datos, 'renglones' => $renglones, 'errores' => $errores,
            'tokenCompra' => $_SESSION['csrf_compra'], 'categorias' => ProductoModel::obtenerCategoriasActivas(),
            'proveedores' => CompraModel::obtenerProveedoresActivos(), 'productos' => CompraModel::obtenerProductosPorProveedor()
        ]);
    }
}
