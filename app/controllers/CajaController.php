<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CajaModel;

// Controla las operaciones de cajas.
class CajaController extends Controller
{
    // Muestra el listado de cajas con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $mensajes = [
            'creada' => 'Caja creada correctamente.',
            'actualizada' => 'Caja actualizada correctamente.',
            'reactivada' => 'Caja reactivada correctamente.',
            'baja' => 'Caja dada de baja.'
        ];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'cajas' => CajaModel::listarCajas(),
            'mensaje' => $mensajes[$resultado] ?? ''
        ]);
    }

    // Muestra el formulario para crear la caja.
    public function actionCrear()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->mostrarFormulario('crear');
    }

    // Recibe el formulario por POST y solicita guardar la caja.
    public function actionGuardar()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $this->guardar();
    }

    // Busca la caja y muestra el formulario de edición.
    public function actionEditar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $caja = $this->buscarCaja($id);
        $this->mostrarFormulario('editar', (array) $caja, [], $caja->id_caja);
    }

    // Recibe por POST los cambios de la caja y solicita guardarlos.
    public function actionActualizar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $caja = $this->buscarCaja($id);
        $this->guardar($caja->id_caja);
    }

    // Solicita la baja lógica de la caja.
    public function actionBaja($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $caja = $this->buscarCaja($id);
        // Pasa a CajaModel: da de baja sin eliminar la caja en la base de datos.
        CajaModel::bajaCaja($caja->id_caja);
        $this->volverAlListado('baja');
    }

    // Solicita reactivar la caja.
    public function actionReactivar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $caja = $this->buscarCaja($id);
        // Pasa a CajaModel: reactiva la caja en la base de datos.
        CajaModel::reactivar($caja->id_caja);
        $this->volverAlListado('reactivada');
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

    // Valida el ID y busca la caja; detiene la solicitud si no existe.
    private function buscarCaja($id)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de caja inválido.';
            exit;
        }
        // Pasa a CajaModel para buscar el registro por su ID.
        $caja = CajaModel::findId($id);
        // Si no encuentra la caja, detiene la solicitud.
        if (!$caja) {
            http_response_code(404);
            echo 'La caja no existe.';
            exit;
        }
        return $caja;
    }

    // Carga la vista del formulario con los datos y errores de la caja.
    private function mostrarFormulario($vista, $datos = [], $errores = [], $id = null)
    {
        // Envía los datos a la vista solicitada.
        Response::render($this->viewDir(__NAMESPACE__), $vista, [
            'datos' => $datos, 'errores' => $errores, 'id' => $id
        ]);
    }

    // Valida los campos por POST y pasa la caja al modelo para guardar.
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
        // Si hay errores, los devuelve para corregir el formulario.
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($vista, $datos, $errores, $id);
            return;
        }
        try {
            // Sin ID crea un registro; con ID actualiza el existente.
            if ($id === null) {
                // Pasa a CajaModel: inserta la caja en la base de datos.
                CajaModel::crearCaja($datos);
            } else {
                // Pasa a CajaModel: actualiza la caja en la base de datos.
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

    // Redirige al listado con el resultado de la operación.
    private function volverAlListado($resultado)
    {
        // Redirige al navegador a caja/index.
        header('Location: /proyectofinaldesarrollo/caja/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
