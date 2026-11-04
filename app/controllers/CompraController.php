<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CompraModel;
use app\models\ProveedorModel;
use app\models\ProductoModel;
use app\models\RecepcionModel;

class CompraController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $filtros = ['id_compra' => $this->leerFiltro('id_compra', 10, true),
            'numero_comprobante' => $this->leerFiltro('numero_comprobante', 50),
            'id_proveedor' => $this->leerFiltro('id_proveedor', 10, true)];
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'compras' => CompraModel::listarCompras($filtros['id_compra'], $filtros['numero_comprobante'], $filtros['id_proveedor']),
            'filtros' => $filtros, 'proveedores' => ProveedorModel::listarProveedores(),
            'mensaje' => ($_GET['resultado'] ?? '') === 'registrada' ? 'Compra registrada correctamente. Pendiente de recepción; no modificó stock.' : ''
        ]);
    }

    public function actionCrear()
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->mostrarFormulario();
    }

    public function actionGuardar()
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        if (!$this->validarPostCompra()) return;
        $datos = [];
        foreach (['id_proveedor', 'numero_comprobante', 'observacion'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        $renglones = [];
        $recibidos = $_POST['renglones'] ?? [];
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
        header('Location: /proyectofinaldesarrollo/compra/index?resultado=registrada', true, 303);
        exit;
    }

    public function actionCrearProveedorRapido()
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        if (!$this->validarPostCompra(true)) return;
        try {
            [$datos, $errores] = ProveedorModel::validarDatos($_POST);
            if ($errores) {
                $this->json(['ok' => false, 'mensaje' => implode(' ', $errores)], 422);
                return;
            }
            $id = ProveedorModel::crearProveedor($datos);
        } catch (\Throwable $excepcion) {
            $duplicado = (string) $excepcion->getCode() === '23000';
            $this->json(['ok' => false, 'mensaje' => $duplicado ? 'Ya existe un proveedor con ese CUIT.' : 'No se pudo crear el proveedor. Intentá nuevamente.'], $duplicado ? 422 : 500);
            return;
        }
        $this->json(['ok' => true, 'proveedor' => ['id_proveedor' => (int) $id, 'razon_social' => $datos['razon_social']]], 201);
    }

    public function actionCrearProductoRapido()
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        if (!$this->validarPostCompra(true)) return;
        try {
            $idProveedor = $_POST['id_proveedor'] ?? '';
            $proveedor = is_string($idProveedor) && ctype_digit($idProveedor) && (float) $idProveedor >= 1 && (float) $idProveedor <= 4294967295
                ? ProveedorModel::findId($idProveedor) : false;
            if (!$proveedor || (int) $proveedor->estado !== 1) {
                $this->json(['ok' => false, 'mensaje' => 'Seleccioná un proveedor activo antes de crear el producto.'], 422);
                return;
            }
            [$datos, $errores] = ProductoModel::validarDatos($_POST);
            if ($errores) {
                $this->json(['ok' => false, 'mensaje' => implode(' ', $errores)], 422);
                return;
            }
            // El modelo crea producto + asociación en su transacción habitual; stock inicial 0.
            $id = ProductoModel::crearProducto($datos, [$idProveedor]);
        } catch (\Throwable $excepcion) {
            $duplicado = (string) $excepcion->getCode() === '23000';
            $this->json(['ok' => false, 'mensaje' => $duplicado ? 'Ya existe ese Código EAN o nombre en la categoría seleccionada.' : 'No se pudo crear el producto. Intentá nuevamente.'], $duplicado ? 422 : 500);
            return;
        }
        $this->json(['ok' => true, 'producto' => ['id_producto' => (int) $id, 'nombre_producto' => $datos['nombre_producto'],
            'id_proveedor' => (int) $idProveedor]], 201);
    }

    private function validarPostCompra($json = false)
    {
        $estado = 0;
        $mensaje = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $estado = 405;
            $mensaje = 'Método no permitido.';
        } else {
            $token = $_POST['csrf_compra'] ?? '';
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

    private function json($datos, $estado = 200)
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function actionVer($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de compra inválido.';
            return;
        }
        $compra = CompraModel::buscarCompra($id);
        if (!$compra) {
            http_response_code(404);
            echo 'La compra no existe.';
            return;
        }
        Response::render($this->viewDir(__NAMESPACE__), 'ver', ['compra' => $compra, 'detalles' => CompraModel::obtenerDetalle($id), 'recepcion' => RecepcionModel::buscarPorCompra($id)]);
    }

    private function mostrarFormulario($datos = [], $renglones = [], $errores = [])
    {
        if (empty($_SESSION['csrf_compra'])) $_SESSION['csrf_compra'] = bin2hex(random_bytes(32));
        Response::render($this->viewDir(__NAMESPACE__), 'crear', [
            'datos' => $datos, 'renglones' => $renglones, 'errores' => $errores,
            'tokenCompra' => $_SESSION['csrf_compra'], 'categorias' => ProductoModel::obtenerCategoriasActivas(),
            'proveedores' => CompraModel::obtenerProveedoresActivos(), 'productos' => CompraModel::obtenerProductosPorProveedor()
        ]);
    }
}
