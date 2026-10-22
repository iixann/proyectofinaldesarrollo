<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\StockModel;

class StockController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'productos' => StockModel::listarProductosActivos(),
            'mensaje' => ($_GET['resultado'] ?? '') === 'registrado' ? 'Movimiento registrado correctamente.' : ''
        ]);
    }

    public function actionAjustar($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $producto = $this->buscarProducto($id, true);
        $this->mostrarFormulario($producto);
    }

    public function actionGuardarAjuste($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
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
        if (!in_array($datos['tipo'], ['AJUSTE_ENTRADA', 'AJUSTE_SALIDA'], true)) {
            $errores[] = 'Seleccioná un tipo de movimiento válido.';
        }
        if (!ctype_digit($datos['cantidad']) || filter_var($datos['cantidad'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            $errores[] = 'La cantidad debe ser un entero mayor a cero, hasta 2147483647.';
        }
        if (mb_strlen($datos['observacion'], 'UTF-8') > 255) {
            $errores[] = 'La observación debe tener hasta 255 caracteres.';
        }
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($producto, $datos, $errores);
            return;
        }
        try {
            if ($datos['tipo'] === 'AJUSTE_ENTRADA') {
                StockModel::registrarEntrada($producto->id_producto, $_SESSION['usuario']['id_usuario'], $datos['cantidad'], $datos['observacion']);
            } else {
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
        header('Location: /proyectofinaldesarrollo/stock/index?resultado=registrado', true, 303);
        exit;
    }

    public function actionMovimientos($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $producto = $id === null ? null : $this->buscarProducto($id);
        Response::render($this->viewDir(__NAMESPACE__), 'movimientos', [
            'producto' => $producto,
            'movimientos' => StockModel::listarMovimientos($producto ? $producto->id_producto : null)
        ]);
    }

    private function buscarProducto($id, $exigirActivo = false)
    {
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de producto inválido.';
            exit;
        }
        $producto = StockModel::findId($id);
        if (!$producto) {
            http_response_code(404);
            echo 'El producto no existe.';
            exit;
        }
        if ($exigirActivo && (int) $producto->estado !== 1) {
            http_response_code(422);
            echo 'El producto está inactivo.';
            exit;
        }
        return $producto;
    }

    private function mostrarFormulario($producto, $datos = [], $errores = [])
    {
        Response::render($this->viewDir(__NAMESPACE__), 'ajustar', [
            'producto' => $producto, 'datos' => $datos, 'errores' => $errores
        ]);
    }
}
