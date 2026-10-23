<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CajaModel;

class CajaController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $mensajes = [
            'creada' => 'Caja creada correctamente.',
            'actualizada' => 'Caja actualizada correctamente.',
            'reactivada' => 'Caja reactivada correctamente.',
            'baja' => 'Caja dada de baja.'
        ];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'cajas' => CajaModel::listarCajas(),
            'mensaje' => $mensajes[$resultado] ?? ''
        ]);
    }

    public function actionCrear()
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->mostrarFormulario('crear');
    }

    public function actionGuardar()
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $this->guardar();
    }

    public function actionEditar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $caja = $this->buscarCaja($id);
        $this->mostrarFormulario('editar', (array) $caja, [], $caja->id_caja);
    }

    public function actionActualizar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $caja = $this->buscarCaja($id);
        $this->guardar($caja->id_caja);
    }

    public function actionBaja($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $caja = $this->buscarCaja($id);
        CajaModel::bajaCaja($caja->id_caja);
        $this->volverAlListado('baja');
    }

    public function actionReactivar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $caja = $this->buscarCaja($id);
        CajaModel::reactivar($caja->id_caja);
        $this->volverAlListado('reactivada');
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

    private function buscarCaja($id)
    {
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de caja inválido.';
            exit;
        }
        $caja = CajaModel::findId($id);
        if (!$caja) {
            http_response_code(404);
            echo 'La caja no existe.';
            exit;
        }
        return $caja;
    }

    private function mostrarFormulario($vista, $datos = [], $errores = [], $id = null)
    {
        Response::render($this->viewDir(__NAMESPACE__), $vista, [
            'datos' => $datos, 'errores' => $errores, 'id' => $id
        ]);
    }

    private function guardar($id = null)
    {
        $nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
        $descripcion = is_string($_POST['descripcion'] ?? null) ? trim($_POST['descripcion']) : '';
        $datos = ['nombre' => $nombre, 'descripcion' => $descripcion];
        $errores = [];
        if ($nombre === '') {
            $errores[] = 'Ingresá el nombre de la caja.';
        } elseif (mb_strlen($nombre, 'UTF-8') > 100) {
            $errores[] = 'El nombre debe tener hasta 100 caracteres.';
        } elseif (CajaModel::nombreDuplicado($nombre, $id)) {
            $errores[] = 'Ya existe una caja con ese nombre.';
        }
        if (mb_strlen($descripcion, 'UTF-8') > 255) {
            $errores[] = 'La descripción debe tener hasta 255 caracteres.';
        }
        $vista = $id === null ? 'crear' : 'editar';
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($vista, $datos, $errores, $id);
            return;
        }
        try {
            if ($id === null) {
                CajaModel::crearCaja($datos);
            } else {
                CajaModel::actualizarCaja($id, $datos);
            }
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            // El índice UNIQUE también protege ante dos envíos simultáneos.
            $duplicado = (string) $excepcion->getCode() === '23000';
            http_response_code($duplicado ? 422 : 500);
            $this->mostrarFormulario($vista, $datos, [
                $duplicado ? 'Ya existe una caja con ese nombre.' : 'No se pudo guardar la caja. Intentá nuevamente.'
            ], $id);
            return;
        }
        $this->volverAlListado($id === null ? 'creada' : 'actualizada');
    }

    private function volverAlListado($resultado)
    {
        header('Location: /proyectofinaldesarrollo/caja/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
