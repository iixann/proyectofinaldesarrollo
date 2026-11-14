<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\RecepcionModel;
use app\models\CompraModel;
use app\models\ProductoModel;

// Controla las operaciones de recepciones.
class RecepcionController extends Controller
{
    // Muestra las compras pendientes y el historial de recepciones.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Envía los datos a la vista index.
        Response::render('recepcion/', 'index', ['compras' => RecepcionModel::comprasPendientes(), 'recepciones' => RecepcionModel::listarRecepciones()]);
    }

    // Muestra el formulario para recibir una compra pendiente.
    public function actionCompra($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $compra = $this->buscarCompraPendiente($id);
        $this->formularioCompra($compra);
    }

    // Registra por POST la aceptación o el rechazo de una compra.
    public function actionGuardarCompra($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->exigirPost();
        $compra = $this->buscarCompraPendiente($id);
        $cantidades = $_POST['cantidades'] ?? [];
        $datos = $this->leerDatos();
        try {
            // Pasa a RecepcionModel para registrar la recepción de la compra.
            $idRecepcion = RecepcionModel::registrarDeCompra($compra->id_compra, $cantidades, $datos['decision'],
                $datos['observacion'], $datos['motivo_rechazo'], $_SESSION['usuario']['id_usuario']);
        } catch (\DomainException $excepcion) {
            http_response_code($excepcion->getCode() === 409 ? 409 : 422);
            // Si la compra ya cambió de estado, muestra el conflicto sin registrarla.
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

    // Muestra el formulario de recepción sin compra previa.
    public function actionDirecta()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->formularioDirecta();
    }

    // Registra por POST una recepción sin compra previa.
    public function actionGuardarDirecta()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->exigirPost();
        $datos = $this->leerDatos();
        $datos['id_proveedor'] = is_string($_POST['id_proveedor'] ?? null) ? trim($_POST['id_proveedor']) : '';
        $renglones = $_POST['renglones'] ?? [];
        try {
            // Pasa a RecepcionModel para registrar la recepción sin compra previa.
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

    // Busca la recepción y carga su vista de detalle.
    public function actionVer($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $this->validarId($id);
        // Pasa a RecepcionModel: busca una recepción con los datos de proveedor y usuario.
        $recepcion = RecepcionModel::buscarRecepcion($id);
        // Si no encuentra la recepción, detiene la solicitud.
        if (!$recepcion) {
            http_response_code(404);
            exit('La recepción no existe.');
        }
        // Envía los datos a la vista ver.
        Response::render('recepcion/', 'ver', ['recepcion' => $recepcion, 'detalles' => RecepcionModel::obtenerDetalle($id)]);
    }

    // Detiene la solicitud si el ID no es válido.
    private function validarId($id)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            exit('ID inválido.');
        }
    }

    // Valida el ID y busca la recepción; detiene la solicitud si no existe.
    private function buscarCompraPendiente($id)
    {
        $this->validarId($id);
        // Pasa a CompraModel para buscar la compra con su proveedor.
        $compra = CompraModel::buscarCompra($id);
        // Si no encuentra la compra, detiene la solicitud.
        if (!$compra) {
            http_response_code(404);
            exit('La compra no existe.');
        }
        // Solo permite recibir una compra activa que siga pendiente.
        if ((int) $compra->estado !== 1 || $compra->estado_recepcion !== 'PENDIENTE') {
            http_response_code(409);
            exit('La compra está inactiva o ya fue recepcionada/rechazada. Consultá su histórico.');
        }
        return $compra;
    }

    // Obtiene o crea el token que protege el formulario de recepción.
    private function token()
    {
        // Crea el token del formulario si todavía no existe en la sesión.
        if (empty($_SESSION['csrf_recepcion'])) $_SESSION['csrf_recepcion'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_recepcion'];
    }

    // Exige POST y valida el token del formulario de recepción.
    private function exigirPost()
    {
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Método no permitido');
        }
        $token = $_POST['csrf_recepcion'] ?? '';
        // Rechaza el formulario si su token no coincide con la sesión.
        if (!is_string($token) || empty($_SESSION['csrf_recepcion']) || !hash_equals($_SESSION['csrf_recepcion'], $token)) {
            http_response_code(403);
            exit('El formulario venció. Recargá la recepción.');
        }
    }

    // Recoge los campos del formulario enviados por POST.
    private function leerDatos()
    {
        $datos = [];
        foreach (['decision', 'observacion', 'motivo_rechazo'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        return $datos;
    }

    // Prepara las filas recibidas para volver a mostrar el formulario.
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

    // Carga la recepción de una compra con sus productos esperados.
    private function formularioCompra($compra, $cantidades = [], $datos = [], $errores = [])
    {
        // Envía los datos a la vista compra.
        Response::render('recepcion/', 'compra', ['compra' => $compra, 'detalles' => CompraModel::obtenerDetalle($compra->id_compra),
            'cantidades' => $cantidades, 'datos' => $datos, 'errores' => $errores, 'tokenRecepcion' => $this->token()]);
    }

    // Carga la recepción sin compra con proveedores y productos disponibles.
    private function formularioDirecta($datos = [], $renglones = [], $errores = [])
    {
        // Crea el token del formulario si todavía no existe en la sesión.
        if (empty($_SESSION['csrf_compra'])) $_SESSION['csrf_compra'] = bin2hex(random_bytes(32));
        // Envía los datos a la vista directa.
        Response::render('recepcion/', 'directa', ['esRecepcionDirecta' => true, 'datos' => $datos, 'renglones' => $renglones,
            'errores' => $errores, 'proveedores' => CompraModel::obtenerProveedoresActivos(), 'productos' => CompraModel::obtenerProductosPorProveedor(),
            'categorias' => ProductoModel::obtenerCategoriasActivas(), 'tokenCompra' => $_SESSION['csrf_compra'], 'tokenRecepcion' => $this->token()]);
    }

    // Redirige al detalle de la recepción registrada.
    private function verResultado($id)
    {
        // Redirige al navegador a recepcion/ver.
        header('Location: /proyectofinaldesarrollo/recepcion/ver/' . $id, true, 303);
        exit;
    }
}
