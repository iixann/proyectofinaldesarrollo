<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CompraModel;

class CompraController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'compras' => CompraModel::listarCompras(),
            'mensaje' => ($_GET['resultado'] ?? '') === 'registrada' ? 'Compra registrada correctamente.' : ''
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
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido';
            return;
        }
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
        Response::render($this->viewDir(__NAMESPACE__), 'ver', ['compra' => $compra, 'detalles' => CompraModel::obtenerDetalle($id)]);
    }

    private function mostrarFormulario($datos = [], $renglones = [], $errores = [])
    {
        Response::render($this->viewDir(__NAMESPACE__), 'crear', [
            'datos' => $datos, 'renglones' => $renglones, 'errores' => $errores,
            'proveedores' => CompraModel::obtenerProveedoresActivos(), 'productos' => CompraModel::obtenerProductosPorProveedor()
        ]);
    }
}
