<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CajaSesionModel;

class SesioncajaController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['VENDEDOR']);
        $mensajes = ['abierta' => 'Caja abierta correctamente.', 'cerrada' => 'Caja cerrada correctamente.'];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        Response::render('caja_sesion/', 'index', ['sesiones' => CajaSesionModel::listarSesiones(), 'mensaje' => $mensajes[$resultado] ?? '']);
    }

    public function actionAbrir()
    {
        SessionController::onlyUsers(['VENDEDOR']);
        $this->formularioAbrir();
    }

    public function actionGuardarApertura()
    {
        SessionController::onlyUsers(['VENDEDOR']);
        $this->exigirPost();
        $datos = $this->leerDatos(['id_caja', 'monto_inicial']);
        if (!$this->idValido($datos['id_caja'])) {
            http_response_code(422);
            $this->formularioAbrir($datos, ['Seleccioná una caja válida.']);
            return;
        }
        try {
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

    public function actionCerrar($id = null)
    {
        SessionController::onlyUsers(['VENDEDOR']);
        $sesion = $this->buscarSesion($id);
        if ($sesion->estado !== 'ABIERTA') {
            http_response_code(409);
            echo 'La sesión ya está cerrada. Consultá su detalle desde el listado.';
            return;
        }
        $this->formularioCerrar($sesion);
    }

    public function actionGuardarCierre($id = null)
    {
        SessionController::onlyUsers(['VENDEDOR']);
        $this->exigirPost();
        $sesion = $this->buscarSesion($id);
        $datos = $this->leerDatos(['efectivo_contado', 'observacion_cierre']);
        try {
            CajaSesionModel::cerrar($sesion->id_caja_sesion, $_SESSION['usuario']['id_usuario'], $datos['efectivo_contado'], $datos['observacion_cierre']);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
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

    public function actionVer($id = null)
    {
        SessionController::onlyUsers(['VENDEDOR']);
        Response::render('caja_sesion/', 'ver', ['sesion' => $this->buscarSesion($id)]);
    }

    private function idValido($id)
    {
        return is_string($id) && ctype_digit($id) && (float) $id >= 1 && (float) $id <= 4294967295;
    }

    private function buscarSesion($id)
    {
        if (!$this->idValido($id)) {
            http_response_code(400);
            echo 'ID de sesión inválido.';
            exit;
        }
        $sesion = CajaSesionModel::buscarSesion($id);
        if (!$sesion) {
            http_response_code(404);
            echo 'La sesión no existe.';
            exit;
        }
        return $sesion;
    }

    private function leerDatos($campos)
    {
        $datos = [];
        foreach ($campos as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        return $datos;
    }

    private function exigirPost()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido';
            exit;
        }
    }

    private function formularioAbrir($datos = [], $errores = [])
    {
        Response::render('caja_sesion/', 'abrir', ['cajas' => CajaSesionModel::cajasActivas(), 'datos' => $datos, 'errores' => $errores]);
    }

    private function formularioCerrar($sesion, $datos = [], $errores = [])
    {
        try {
            $resumen = CajaSesionModel::resumen($sesion);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            echo htmlspecialchars($excepcion->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }
        Response::render('caja_sesion/', 'cerrar', ['sesion' => $sesion, 'resumen' => $resumen, 'datos' => $datos, 'errores' => $errores]);
    }

    private function volver($resultado)
    {
        header('Location: /proyectofinaldesarrollo/sesioncaja/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
