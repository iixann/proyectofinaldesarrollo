<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ProveedorModel;

// Controla las operaciones de proveedores.
class ProveedorController extends Controller
{
    // Muestra el listado de proveedores con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $mensajes = [
            'creado' => 'Proveedor creado correctamente.',
            'actualizado' => 'Proveedor actualizado correctamente.',
            'reactivada' => 'Proveedor reactivado correctamente.',
            'baja' => 'Proveedor dado de baja.'
        ];
        $busqueda = $this->leerFiltro('busqueda');
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'proveedores' => ProveedorModel::listarProveedores($busqueda),
            'busqueda' => $busqueda,
            'mensaje' => $mensajes[$resultado] ?? ''
        ]);
    }

    // Muestra el formulario para crear el proveedor.
    public function actionCrear()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->mostrarFormulario('crear');
    }

    // Recibe el formulario por POST y solicita guardar el proveedor.
    public function actionGuardar()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $this->guardar();
    }

    // Busca el proveedor y muestra el formulario de edición.
    public function actionEditar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $proveedor = $this->buscarProveedor($id);
        $this->mostrarFormulario('editar', (array) $proveedor, [], $proveedor->id_proveedor);
    }

    // Recibe por POST los cambios del proveedor y solicita guardarlos.
    public function actionActualizar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $proveedor = $this->buscarProveedor($id);
        $this->guardar($proveedor->id_proveedor);
    }

    // Solicita la baja lógica del proveedor.
    public function actionBaja($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $proveedor = $this->buscarProveedor($id);
        // Pasa a ProveedorModel: da de baja sin eliminar el proveedor en la base de datos.
        ProveedorModel::bajaProveedor($proveedor->id_proveedor);
        $this->volverAlListado('baja');
    }

    // Solicita reactivar el proveedor.
    public function actionReactivar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $proveedor = $this->buscarProveedor($id);
        // Pasa a ProveedorModel: reactiva el proveedor en la base de datos.
        ProveedorModel::reactivar($proveedor->id_proveedor);
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

    // Valida el ID y busca el proveedor; detiene la solicitud si no existe.
    private function buscarProveedor($id)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de proveedor inválido.';
            exit;
        }
        // Pasa a ProveedorModel para buscar el registro por su ID.
        $proveedor = ProveedorModel::findId($id);
        // Si no encuentra el proveedor, detiene la solicitud.
        if (!$proveedor) {
            http_response_code(404);
            echo 'El proveedor no existe.';
            exit;
        }
        return $proveedor;
    }

    // Carga la vista del formulario con los datos y errores del proveedor.
    private function mostrarFormulario($vista, $datosProveedor = [], $errores = [], $id = null)
    {
        // Envía los datos a la vista solicitada.
        Response::render($this->viewDir(__NAMESPACE__), $vista, [
            'datosProveedor' => $datosProveedor, 'errores' => $errores, 'id' => $id
        ]);
    }

    // Valida los campos por POST y pasa el proveedor al modelo para guardar.
    private function guardar($id = null)
    {
        // Pasa a ProveedorModel: valida los campos recibidos y devuelve los datos junto con los errores.
        [$datosProveedor, $errores] = ProveedorModel::validarDatos($_POST, $id);
        $vista = $id === null ? 'crear' : 'editar';
        // Si hay errores, los devuelve para corregir el formulario.
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($vista, $datosProveedor, $errores, $id);
            return;
        }
        try {
            // Sin ID crea un registro; con ID actualiza el existente.
            if ($id === null) {
                // Pasa a ProveedorModel: inserta el proveedor en la base de datos.
                ProveedorModel::crearProveedor($datosProveedor);
            } else {
                // Pasa a ProveedorModel: actualiza el proveedor en la base de datos.
                ProveedorModel::actualizarProveedor($id, $datosProveedor);
            }
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            // El índice UNIQUE también protege ante dos envíos simultáneos.
            $duplicado = (string) $excepcion->getCode() === '23000';
            http_response_code($duplicado ? 422 : 500);
            $this->mostrarFormulario($vista, $datosProveedor, [
                $duplicado ? 'Ya existe un proveedor con ese CUIT.' : 'No se pudo guardar el proveedor. Intentá nuevamente.'
            ], $id);
            return;
        }
        $this->volverAlListado($id === null ? 'creado' : 'actualizado');
    }

    // Redirige al listado con el resultado de la operación.
    private function volverAlListado($resultado)
    {
        // Redirige al navegador a proveedor/index.
        header('Location: /proyectofinaldesarrollo/proveedor/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
