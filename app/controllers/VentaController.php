<?php

namespace app\controllers;

use \Controller;
use app\models\VentaModel;

class VentaController extends Controller
{
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
