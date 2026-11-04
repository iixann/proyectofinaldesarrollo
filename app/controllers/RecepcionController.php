<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\RecepcionModel;
use app\models\CompraModel;
use app\models\ProductoModel;

class RecepcionController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        Response::render('recepcion/', 'index', ['compras' => RecepcionModel::comprasPendientes(), 'recepciones' => RecepcionModel::listarRecepciones()]);
    }

    public function actionCompra($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $compra = $this->buscarCompraPendiente($id);
        $this->formularioCompra($compra);
    }

    public function actionGuardarCompra($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->exigirPost();
        $compra = $this->buscarCompraPendiente($id);
        $cantidades = $_POST['cantidades'] ?? [];
        $datos = $this->leerDatos();
        try {
            $idRecepcion = RecepcionModel::registrarDeCompra($compra->id_compra, $cantidades, $datos['decision'],
                $datos['observacion'], $datos['motivo_rechazo'], $_SESSION['usuario']['id_usuario']);
        } catch (\DomainException $excepcion) {
            http_response_code($excepcion->getCode() === 409 ? 409 : 422);
            if ($excepcion->getCode() === 409) {
                echo htmlspecialchars($excepcion->getMessage(), ENT_QUOTES, 'UTF-8');
                return;
            }
            $this->formularioCompra($compra, is_array($cantidades) ? $cantidades : [], $datos, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log('No se pudo registrar la recepción de una compra.');
            http_response_code(500);
            $this->formularioCompra($compra, is_array($cantidades) ? $cantidades : [], $datos, ['No se pudo registrar la recepción. Intentá nuevamente.']);
            return;
        }
        $this->verResultado($idRecepcion);
    }

    public function actionDirecta()
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->formularioDirecta();
    }

    public function actionGuardarDirecta()
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->exigirPost();
        $datos = $this->leerDatos();
        $datos['id_proveedor'] = is_string($_POST['id_proveedor'] ?? null) ? trim($_POST['id_proveedor']) : '';
        $renglones = $_POST['renglones'] ?? [];
        try {
            $idRecepcion = RecepcionModel::registrarDirecta($datos['id_proveedor'], $renglones, $datos['decision'],
                $datos['observacion'], $datos['motivo_rechazo'], $_SESSION['usuario']['id_usuario']);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->formularioDirecta($datos, $this->renglonesParaVista($renglones), [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log('No se pudo registrar la recepción directa.');
            http_response_code(500);
            $this->formularioDirecta($datos, $this->renglonesParaVista($renglones), ['No se pudo registrar la recepción. Intentá nuevamente.']);
            return;
        }
        $this->verResultado($idRecepcion);
    }

    public function actionVer($id = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->validarId($id);
        $recepcion = RecepcionModel::buscarRecepcion($id);
        if (!$recepcion) {
            http_response_code(404);
            exit('La recepción no existe.');
        }
        Response::render('recepcion/', 'ver', ['recepcion' => $recepcion, 'detalles' => RecepcionModel::obtenerDetalle($id)]);
    }

    private function validarId($id)
    {
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            exit('ID inválido.');
        }
    }

    private function buscarCompraPendiente($id)
    {
        $this->validarId($id);
        $compra = CompraModel::buscarCompra($id);
        if (!$compra) {
            http_response_code(404);
            exit('La compra no existe.');
        }
        if ((int) $compra->estado !== 1 || $compra->estado_recepcion !== 'PENDIENTE') {
            http_response_code(409);
            exit('La compra está inactiva o ya fue recepcionada/rechazada. Consultá su histórico.');
        }
        return $compra;
    }

    private function token()
    {
        if (empty($_SESSION['csrf_recepcion'])) $_SESSION['csrf_recepcion'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_recepcion'];
    }

    private function exigirPost()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Método no permitido');
        }
        $token = $_POST['csrf_recepcion'] ?? '';
        if (!is_string($token) || empty($_SESSION['csrf_recepcion']) || !hash_equals($_SESSION['csrf_recepcion'], $token)) {
            http_response_code(403);
            exit('El formulario venció. Recargá la recepción.');
        }
    }

    private function leerDatos()
    {
        $datos = [];
        foreach (['decision', 'observacion', 'motivo_rechazo'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        return $datos;
    }

    private function renglonesParaVista($renglones)
    {
        $filas = [];
        if (!is_array($renglones)) return [];
        foreach ($renglones as $renglon) {
            $fila = [];
            foreach (['id_producto', 'cantidad'] as $campo) {
                $fila[$campo] = is_array($renglon) && is_string($renglon[$campo] ?? null) ? $renglon[$campo] : '';
            }
            $filas[] = $fila;
        }
        return $filas;
    }

    private function formularioCompra($compra, $cantidades = [], $datos = [], $errores = [])
    {
        Response::render('recepcion/', 'compra', ['compra' => $compra, 'detalles' => CompraModel::obtenerDetalle($compra->id_compra),
            'cantidades' => $cantidades, 'datos' => $datos, 'errores' => $errores, 'tokenRecepcion' => $this->token()]);
    }

    private function formularioDirecta($datos = [], $renglones = [], $errores = [])
    {
        if (empty($_SESSION['csrf_compra'])) $_SESSION['csrf_compra'] = bin2hex(random_bytes(32));
        Response::render('recepcion/', 'directa', ['esRecepcionDirecta' => true, 'datos' => $datos, 'renglones' => $renglones,
            'errores' => $errores, 'proveedores' => CompraModel::obtenerProveedoresActivos(), 'productos' => CompraModel::obtenerProductosPorProveedor(),
            'categorias' => ProductoModel::obtenerCategoriasActivas(), 'tokenCompra' => $_SESSION['csrf_compra'], 'tokenRecepcion' => $this->token()]);
    }

    private function verResultado($id)
    {
        header('Location: /proyectofinaldesarrollo/recepcion/ver/' . $id, true, 303);
        exit;
    }
}
