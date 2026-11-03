<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\VentaModel;
use app\models\CajaSesionModel;

class VentaController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['VENDEDOR']);
        $filtros = ['id_venta' => $this->leerFiltro('id_venta', 10, true)];
        $error = '';
        foreach (['desde', 'hasta'] as $campo) {
            $fecha = $_GET[$campo] ?? '';
            if (!is_string($fecha) || ($fecha !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha)
                || !checkdate((int) substr($fecha, 5, 2), (int) substr($fecha, 8, 2), (int) substr($fecha, 0, 4))))) {
                $error = 'Ingresá fechas válidas.';
                $fecha = '';
            }
            $filtros[$campo] = $fecha;
        }
        if ($filtros['desde'] !== '' && $filtros['hasta'] !== '' && $filtros['desde'] > $filtros['hasta']) {
            $error = 'La fecha desde no puede superar la fecha hasta.';
        }
        if ($error !== '') http_response_code(400);
        Response::render('venta/', 'index', [
            'ventas' => $error === '' ? VentaModel::listarVentas($filtros['desde'], $filtros['hasta'], $filtros['id_venta']) : [],
            'filtros' => $filtros, 'error' => $error
        ]);
    }

    public function actionVer($id = null)
    {
        SessionController::onlyUsers(['VENDEDOR']);
        Response::render('venta/', 'ver', $this->datosVenta($id));
    }

    public function actionTicket($id = null)
    {
        SessionController::onlyUsers(['VENDEDOR']);
        Response::render('venta/', 'ticket', $this->datosVenta($id));
    }

    private function datosVenta($id)
    {
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de venta inválido.';
            exit;
        }
        $venta = VentaModel::buscarVenta($id);
        if (!$venta) {
            http_response_code(404);
            echo 'La venta no existe.';
            exit;
        }
        $pagos = VentaModel::pagosVenta($id);
        $totalPagado = 0;
        foreach ($pagos as $pago) $totalPagado += CajaSesionModel::centavos((string) $pago->monto);
        return ['venta' => $venta, 'detalles' => VentaModel::detallesVenta($id), 'pagos' => $pagos,
            'totalPagado' => CajaSesionModel::decimal($totalPagado)];
    }

    public function actionConfirmar()
    {
        SessionController::onlyUsers(['VENDEDOR']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $this->json(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
            return;
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || empty($_SESSION['csrf_venta']) || !hash_equals($_SESSION['csrf_venta'], $token)) {
            $this->json(['ok' => false, 'mensaje' => 'La sesión del formulario venció. Recargá el POS.'], 403);
            return;
        }
        try {
            $datos = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $excepcion) {
            $this->json(['ok' => false, 'mensaje' => 'Los datos de venta no son válidos.'], 400);
            return;
        }
        if (!is_array($datos)) {
            $this->json(['ok' => false, 'mensaje' => 'Los datos de venta no son válidos.'], 400);
            return;
        }
        try {
            $venta = VentaModel::confirmar($datos['id_caja_sesion'] ?? null, $datos['productos'] ?? null, $datos['pagos'] ?? null, $_SESSION['usuario']['id_usuario']);
        } catch (\DomainException $excepcion) {
            $cerrada = $excepcion->getCode() === 409;
            $this->json(['ok' => false, 'mensaje' => $excepcion->getMessage(), 'sesion_no_disponible' => $cerrada], $cerrada ? 409 : 422);
            return;
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No se pudo registrar la venta. Intentá nuevamente.'], 500);
            return;
        }
        $this->json(array_merge(['ok' => true, 'mensaje' => 'Venta registrada correctamente.'], $venta));
    }

    private function json($datos, $estado = 200)
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
