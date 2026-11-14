<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\VentaModel;
use app\models\CajaSesionModel;

// Controla las operaciones de ventas.
class VentaController extends Controller
{
    // Muestra el listado de ventas con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
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
        // Envía los datos a la vista index.
        Response::render('venta/', 'index', [
            'ventas' => $error === '' ? VentaModel::listarVentas($filtros['desde'], $filtros['hasta'], $filtros['id_venta']) : [],
            'filtros' => $filtros, 'error' => $error
        ]);
    }

    // Busca la venta y carga su vista de detalle.
    public function actionVer($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        // Envía los datos a la vista ver.
        Response::render('venta/', 'ver', $this->datosVenta($id));
    }

    // Carga la vista imprimible con el detalle de la venta.
    public function actionTicket($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        // Envía los datos a la vista ticket.
        Response::render('venta/', 'ticket', $this->datosVenta($id));
    }

    // Reúne la venta, sus productos y pagos para el detalle o ticket.
    private function datosVenta($id)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de venta inválido.';
            exit;
        }
        // Pasa a VentaModel para buscar la venta con su caja y vendedor.
        $venta = VentaModel::buscarVenta($id);
        // Si no encuentra la venta, detiene la solicitud.
        if (!$venta) {
            http_response_code(404);
            echo 'La venta no existe.';
            exit;
        }
        // Pasa a VentaModel para obtener los pagos de la venta.
        $pagos = VentaModel::pagosVenta($id);
        $totalPagado = 0;
        foreach ($pagos as $pago) $totalPagado += CajaSesionModel::centavos((string) $pago->monto);
        return ['venta' => $venta, 'detalles' => VentaModel::detallesVenta($id), 'pagos' => $pagos,
            'totalPagado' => CajaSesionModel::decimal($totalPagado)];
    }

    // Recibe la venta por POST y pasa su confirmación al modelo.
    public function actionConfirmar()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $this->json(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
            return;
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        // Rechaza el formulario si su token no coincide con la sesión.
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
        // Rechaza el JSON si no contiene los datos esperados de la venta.
        if (!is_array($datos)) {
            $this->json(['ok' => false, 'mensaje' => 'Los datos de venta no son válidos.'], 400);
            return;
        }
        try {
            // Pasa a VentaModel para guardar la venta, sus pagos y la salida de stock.
            $venta = VentaModel::confirmar($datos['id_caja_sesion'] ?? null, $datos['productos'] ?? null, $datos['pagos'] ?? null, $_SESSION['usuario']['id_usuario']);
        } catch (\DomainException $excepcion) {
            $cerrada = in_array($excepcion->getCode(), [403, 409], true);
            $this->json(['ok' => false, 'mensaje' => $excepcion->getMessage(), 'sesion_no_disponible' => $cerrada], $cerrada ? $excepcion->getCode() : 422);
            return;
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No se pudo registrar la venta. Intentá nuevamente.'], 500);
            return;
        }
        $this->json(array_merge(['ok' => true, 'mensaje' => 'Venta registrada correctamente.'], $venta));
    }

    // Devuelve los datos como JSON con el estado HTTP indicado.
    private function json($datos, $estado = 200)
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
