<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CajaSesionModel;

// Controla las operaciones de sesiones de caja.
class SesioncajaController extends Controller
{
    // Muestra el listado de sesiones de caja con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        $mensajes = ['abierta' => 'Caja abierta correctamente.', 'cerrada' => 'Caja cerrada correctamente.'];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        // La vista operativa recibe solo disponibilidad, nunca sesiones de terceros.
        if (!$this->esSupervisor()) {
            Response::render('caja_sesion/', 'operativa', ['cajas'=>CajaSesionModel::disponibilidadOperativa($_SESSION['usuario']['id_usuario']), 'mensaje'=>$mensajes[$resultado] ?? '']);
            return;
        }
        // Envía los datos a la vista administrativa.
        Response::render('caja_sesion/', 'index', ['sesiones' => CajaSesionModel::listarSesiones(), 'mensaje' => $mensajes[$resultado] ?? '']);
    }

    // Muestra el formulario de apertura de caja.
    public function actionAbrir()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        $datos = ['id_caja' => is_string($_GET['caja'] ?? null) ? $_GET['caja'] : ''];
        $this->formularioAbrir($datos);
    }

    // Registra por POST la apertura de una sesión de caja.
    public function actionGuardarApertura()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        $this->exigirPost();
        $datos = $this->leerDatos(['id_caja', 'monto_inicial']);
        if (!$this->idValido($datos['id_caja'])) {
            http_response_code(422);
            $this->formularioAbrir($datos, ['Seleccioná una caja válida.']);
            return;
        }
        try {
            // Pasa a CajaSesionModel: registra la apertura si la caja está activa y no tiene sesión abierta.
            CajaSesionModel::abrir($datos['id_caja'], $_SESSION['usuario']['id_usuario'], $datos['monto_inicial']);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->formularioAbrir($datos, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            http_response_code(500);
            $this->formularioAbrir($datos, ['No se pudo abrir la caja. Intentá nuevamente.']);
            return;
        }
        $this->volver('abierta');
    }

    // Muestra el resumen para cerrar una sesión de caja.
    public function actionCerrar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        $sesion = $this->buscarSesion($id);
        $this->exigirPropietario($sesion);
        if ($sesion->estado !== 'ABIERTA') {
            http_response_code(409);
            echo 'La sesión ya está cerrada. Consultá su detalle desde el listado.';
            return;
        }
        $this->formularioCerrar($sesion);
    }

    // Registra por POST el cierre y el efectivo contado.
    public function actionGuardarCierre($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        $this->exigirPost();
        $sesion = $this->buscarSesion($id);
        $this->exigirPropietario($sesion);
        $datos = $this->leerDatos(['efectivo_contado', 'observacion_cierre']);
        try {
            // Pasa a CajaSesionModel: guarda el cierre y la diferencia entre efectivo contado y esperado.
            CajaSesionModel::cerrar($sesion->id_caja_sesion, $_SESSION['usuario']['id_usuario'], $datos['efectivo_contado'], $datos['observacion_cierre'], $_SESSION['usuario']['roles']);
        } catch (\DomainException $excepcion) {
            if ($excepcion->getCode() === 403) {
                http_response_code(403);
                echo 'No tenés permiso para cerrar esta sesión de caja.';
                return;
            }
            http_response_code(422);
            // Pasa a CajaSesionModel para buscar la sesión de caja.
            $sesion = CajaSesionModel::buscarSesion($id);
            if ($sesion->estado !== 'ABIERTA') {
                http_response_code(409);
                echo 'La sesión ya está cerrada.';
                return;
            }
            $this->formularioCerrar($sesion, $datos, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            http_response_code(500);
            $this->formularioCerrar($sesion, $datos, ['No se pudo cerrar la caja. Intentá nuevamente.']);
            return;
        }
        $this->volver('cerrada');
    }

    // Busca la sesión de caja y carga su vista de detalle.
    public function actionVer($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        $sesion = $this->buscarSesion($id);
        // Envía los datos a la vista ver.
        Response::render('caja_sesion/', 'ver', ['sesion' => $sesion, 'totalesMedios' => CajaSesionModel::totalesPorMedio($id)]);
    }

    // Dueño y administrador conservan la vista completa de sesiones.
    private function esSupervisor()
    {
        return (bool) array_intersect(['ADMINISTRADOR', 'DUEÑO'], $_SESSION['usuario']['roles']);
    }

    // Impide cerrar la caja si el usuario no tiene autorización.
    private function exigirPropietario($sesion)
    {
        // Impide cerrar una sesión de caja sin autorización.
        if (!CajaSesionModel::puedeCerrar($sesion->id_usuario_apertura, $_SESSION['usuario']['id_usuario'], $_SESSION['usuario']['roles'])) {
            http_response_code(403);
            exit('No tenés permiso para cerrar esta sesión de caja.');
        }
    }

    // Comprueba que el ID sea un entero positivo dentro del rango permitido.
    private function idValido($id)
    {
        return is_string($id) && ctype_digit($id) && (float) $id >= 1 && (float) $id <= 4294967295;
    }

    // Valida el ID y busca la sesión de caja; detiene la solicitud si no existe.
    private function buscarSesion($id)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!$this->idValido($id)) {
            http_response_code(400);
            echo 'ID de sesión inválido.';
            exit;
        }
        // Pasa a CajaSesionModel para buscar la sesión de caja.
        $alcance = $this->esSupervisor() ? null : $_SESSION['usuario']['id_usuario'];
        $sesion = CajaSesionModel::buscarSesion($id, $alcance);
        // Un vendedor no obtiene detalles de una sesión ajena.
        if (!$sesion && $alcance !== null) { http_response_code(403); exit('No tenés acceso a esta sesión de caja.'); }
        // Si no encuentra la sesión, detiene la solicitud.
        if (!$sesion) {
            http_response_code(404);
            echo 'La sesión no existe.';
            exit;
        }
        return $sesion;
    }

    // Recoge los campos del formulario enviados por POST.
    private function leerDatos($campos)
    {
        $datos = [];
        foreach ($campos as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        return $datos;
    }

    // Rechaza solicitudes que no usan POST.
    private function exigirPost()
    {
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido';
            exit;
        }
    }

    // Carga la apertura con las cajas activas y los errores recibidos.
    private function formularioAbrir($datos = [], $errores = [])
    {
        // Envía los datos a la vista abrir.
        Response::render('caja_sesion/', 'abrir', ['cajas' => CajaSesionModel::cajasDisponibles(), 'datos' => $datos, 'errores' => $errores]);
    }

    // Calcula el resumen y carga la vista para cerrar la sesión de caja.
    private function formularioCerrar($sesion, $datos = [], $errores = [])
    {
        try {
            // Pasa a CajaSesionModel para calcular los importes de la sesión.
            $resumen = CajaSesionModel::resumen($sesion);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            echo htmlspecialchars($excepcion->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }
        // Envía los datos a la vista cerrar.
        Response::render('caja_sesion/', 'cerrar', ['sesion' => $sesion, 'resumen' => $resumen, 'datos' => $datos, 'errores' => $errores,
            'totalesMedios' => CajaSesionModel::totalesPorMedio($sesion->id_caja_sesion)]);
    }

    // Redirige al listado de sesiones con el resultado de la operación.
    private function volver($resultado)
    {
        // Redirige al navegador a sesioncaja/index.
        header('Location: /proyectofinaldesarrollo/sesioncaja/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
