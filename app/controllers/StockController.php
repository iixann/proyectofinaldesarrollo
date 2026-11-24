<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\StockModel;
use app\models\CategoriaModel;
use app\models\ProveedorModel;

// Controla las operaciones de stock y sus movimientos.
class StockController extends Controller
{
    // Muestra el listado de stock y sus movimientos con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA', 'VENDEDOR']);
        $filtros = [
            'nombre' => $this->leerFiltro('nombre'),
            'codigo_barras' => $this->leerFiltro('codigo_barras', 50),
            'id_categoria' => $this->leerFiltro('id_categoria', 10, true),
            'id_proveedor' => $this->leerFiltro('id_proveedor', 10, true)
        ];
        // Conserva filtros y URLs anteriores, pero muestra el catálogo con existencias.
        $filtros['escaneado'] = ($_GET['escaneado'] ?? '') === '1' ? '1' : '0';
        if (($_GET['resultado'] ?? '') === 'registrado') $filtros['resultado'] = 'registrado';
        header('Location: /proyectofinaldesarrollo/producto/index?' . http_build_query($filtros), true, 302);
        exit;
    }

    // Muestra el formulario de ajuste de stock del producto.
    public function actionAjustar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $producto = $this->buscarProducto($id, true);
        $this->mostrarFormulario($producto);
    }

    // Valida el ajuste por POST y pide al modelo registrar el movimiento.
    public function actionGuardarAjuste($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido';
            return;
        }
        $producto = $this->buscarProducto($id, true);
        $datos = [];
        foreach (['tipo', 'cantidad', 'observacion'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        $errores = [];
        // Solo permite ajustes de entrada o salida de stock.
        if (!in_array($datos['tipo'], ['AJUSTE_ENTRADA', 'AJUSTE_SALIDA'], true)) {
            $errores[] = 'Seleccioná un tipo de movimiento válido.';
        }
        if (!ctype_digit($datos['cantidad']) || filter_var($datos['cantidad'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            $errores[] = 'La cantidad debe ser un entero mayor a cero, hasta 2147483647.';
        }
        if (mb_strlen($datos['observacion'], 'UTF-8') > 255) {
            $errores[] = 'La observación debe tener hasta 255 caracteres.';
        }
        // Si hay errores, los devuelve para corregir el formulario.
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($producto, $datos, $errores);
            return;
        }
        try {
            // Según el tipo de ajuste, pide al modelo aumentar o disminuir stock.
            if ($datos['tipo'] === 'AJUSTE_ENTRADA') {
                // Pasa a StockModel: registra un ajuste que aumenta el stock del producto.
                StockModel::registrarEntrada($producto->id_producto, $_SESSION['usuario']['id_usuario'], $datos['cantidad'], $datos['observacion']);
            } else {
                // Pasa a StockModel: registra un ajuste que disminuye el stock del producto.
                StockModel::registrarSalida($producto->id_producto, $_SESSION['usuario']['id_usuario'], $datos['cantidad'], $datos['observacion']);
            }
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->mostrarFormulario(StockModel::findId($id) ?: $producto, $datos, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            http_response_code(500);
            $this->mostrarFormulario($producto, $datos, ['No se pudo registrar el movimiento. Intentá nuevamente.']);
            return;
        }
        // Redirige al navegador a stock/index.
        header('Location: /proyectofinaldesarrollo/stock/index?resultado=registrado', true, 303);
        exit;
    }

    // Prepara un decomiso sobre un producto activo para el personal operativo.
    public function actionDecomiso($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->formularioDecomiso($this->buscarProducto($id, true));
    }

    // Valida el formulario y deriva la salida física al modelo transaccional.
    public function actionGuardarDecomiso($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
        // El token evita que otra página provoque una salida de mercadería.
        $token = $_POST['csrf_decomiso'] ?? null;
        if (!is_string($token) || empty($_SESSION['csrf_decomiso']) || !hash_equals($_SESSION['csrf_decomiso'], $token)) {
            http_response_code(403); echo 'Formulario inválido.'; return;
        }
        $producto = $this->buscarProducto($id, true);
        $datos = ['cantidad'=>is_string($_POST['cantidad'] ?? null) ? $_POST['cantidad'] : '',
            'motivo'=>is_string($_POST['motivo'] ?? null) ? trim($_POST['motivo']) : ''];
        try {
            // El modelo bloquea stock y registra usuario, fecha y motivo en movimientos_stock.
            StockModel::registrarDecomiso($producto->id_producto, $_SESSION['usuario']['id_usuario'], $datos['cantidad'], $datos['motivo']);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->formularioDecomiso(StockModel::findId($id) ?: $producto, $datos, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage()); http_response_code(500);
            $this->formularioDecomiso($producto, $datos, ['No se pudo registrar el decomiso.']); return;
        }
        // Consume el token exitoso para evitar reenvíos accidentales del mismo decomiso.
        unset($_SESSION['csrf_decomiso']);
        header('Location: /proyectofinaldesarrollo/stock/movimientos/' . $producto->id_producto, true, 303);
        exit;
    }

    // Entrega el token y los errores a la vista de decomiso.
    private function formularioDecomiso($producto, $datos = [], $errores = [])
    {
        if (empty($_SESSION['csrf_decomiso'])) $_SESSION['csrf_decomiso'] = bin2hex(random_bytes(32));
        Response::render('stock/', 'decomiso', ['producto'=>$producto, 'datos'=>$datos,
            'errores'=>$errores, 'token'=>$_SESSION['csrf_decomiso']]);
    }

    // Muestra los movimientos de stock generales o de un producto.
    public function actionMovimientos($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $producto = $id === null ? null : $this->buscarProducto($id);
        // Envía los datos a la vista movimientos.
        Response::render($this->viewDir(__NAMESPACE__), 'movimientos', [
            'producto' => $producto,
            'movimientos' => StockModel::listarMovimientos($producto ? $producto->id_producto : null)
        ]);
    }

    // Valida el ID y busca el registro; detiene la solicitud si no existe.
    private function buscarProducto($id, $exigirActivo = false)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de producto inválido.';
            exit;
        }
        // Pasa a StockModel para buscar el registro por su ID.
        $producto = StockModel::findId($id);
        // Si no encuentra el producto, detiene la solicitud.
        if (!$producto) {
            http_response_code(404);
            echo 'El producto no existe.';
            exit;
        }
        // Impide modificar un registro que está inactivo.
        if ($exigirActivo && (int) $producto->estado !== 1) {
            http_response_code(422);
            echo 'El producto está inactivo.';
            exit;
        }
        return $producto;
    }

    // Carga la vista del formulario con los datos y errores del registro.
    private function mostrarFormulario($producto, $datos = [], $errores = [])
    {
        // Envía los datos a la vista ajustar.
        Response::render($this->viewDir(__NAMESPACE__), 'ajustar', [
            'producto' => $producto, 'datos' => $datos, 'errores' => $errores
        ]);
    }
}
