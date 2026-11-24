<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CategoriaModel;

// Controla las operaciones de categorías.
class CategoriaController extends Controller
{
    // Muestra el listado de categorías con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA', 'VENDEDOR']);
        $mensajes = [
            'creada' => 'Categoría creada correctamente.',
            'actualizada' => 'Categoría actualizada correctamente.',
            'reactivada' => 'Categoría reactivada correctamente.',
            'baja' => 'Categoría dada de baja.'
        ];
        $busqueda = $this->leerFiltro('busqueda');
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            // Recepción y vendedor consultan; solo dueño/admin mantienen categorías.
            'puedeGestionar' => SessionController::esAdministrador() || in_array('DUEÑO', $_SESSION['usuario']['roles'], true),
            'categorias' => CategoriaModel::listarCategorias($busqueda),
            'busqueda' => $busqueda,
            'mensaje' => $mensajes[$resultado] ?? ''
        ]);
    }

    // Muestra el formulario para crear la categoría.
    public function actionCrear()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->mostrarFormulario('crear');
    }

    // Recibe el formulario por POST y solicita guardar la categoría.
    public function actionGuardar()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $this->guardar();
    }

    // Busca la categoría y muestra el formulario de edición.
    public function actionEditar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $categoria = $this->buscarCategoria($id);
        $this->mostrarFormulario('editar', $categoria->nombre_categoria, [], $categoria->id_categoria);
    }

    // Recibe por POST los cambios de la categoría y solicita guardarlos.
    public function actionActualizar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $categoria = $this->buscarCategoria($id);
        $this->guardar($categoria->id_categoria);
    }

    // Solicita la baja lógica de la categoría.
    public function actionBaja($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $categoria = $this->buscarCategoria($id);
        // Pasa a CategoriaModel: da de baja sin eliminar la categoría en la base de datos.
        CategoriaModel::bajaCategoria($categoria->id_categoria);
        $this->volverAlListado('baja');
    }

    // Solicita reactivar la categoría.
    public function actionReactivar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $categoria = $this->buscarCategoria($id);
        // Pasa a CategoriaModel: reactiva la categoría en la base de datos.
        CategoriaModel::reactivar($categoria->id_categoria);
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

    // Valida el ID y busca la categoría; detiene la solicitud si no existe.
    private function buscarCategoria($id)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de categoría inválido.';
            exit;
        }
        // Pasa a CategoriaModel para buscar el registro por su ID.
        $categoria = CategoriaModel::findId($id);
        // Si no encuentra la categoría, detiene la solicitud.
        if (!$categoria) {
            http_response_code(404);
            echo 'La categoría no existe.';
            exit;
        }
        return $categoria;
    }

    // Carga la vista del formulario con los datos y errores de la categoría.
    private function mostrarFormulario($vista, $nombre = '', $errores = [], $id = null)
    {
        // Envía los datos a la vista solicitada.
        Response::render($this->viewDir(__NAMESPACE__), $vista, [
            'nombre' => $nombre, 'errores' => $errores, 'id' => $id
        ]);
    }

    // Valida los campos por POST y pasa la categoría al modelo para guardar.
    private function guardar($id = null)
    {
        $nombre = is_string($_POST['nombre_categoria'] ?? null) ? trim($_POST['nombre_categoria']) : '';
        $errores = [];
        if ($nombre === '') {
            $errores[] = 'Ingresá el nombre de la categoría.';
        } elseif (mb_strlen($nombre, 'UTF-8') > 100) {
            $errores[] = 'El nombre debe tener hasta 100 caracteres.';
        } elseif (CategoriaModel::nombreDuplicado($nombre, $id)) {
            $errores[] = 'Ya existe una categoría con ese nombre.';
        }
        $vista = $id === null ? 'crear' : 'editar';
        // Si hay errores, los devuelve para corregir el formulario.
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($vista, $nombre, $errores, $id);
            return;
        }
        try {
            // Sin ID crea un registro; con ID actualiza el existente.
            if ($id === null) {
                // Pasa a CategoriaModel: inserta la categoría en la base de datos.
                CategoriaModel::crearCategoria($nombre);
            } else {
                // Pasa a CategoriaModel: actualiza la categoría en la base de datos.
                CategoriaModel::actualizarCategoria($id, $nombre);
            }
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            // El índice UNIQUE también protege ante dos envíos simultáneos.
            $duplicado = (string) $excepcion->getCode() === '23000';
            http_response_code($duplicado ? 422 : 500);
            $this->mostrarFormulario($vista, $nombre, [
                $duplicado ? 'Ya existe una categoría con ese nombre.' : 'No se pudo guardar la categoría. Intentá nuevamente.'
            ], $id);
            return;
        }
        $this->volverAlListado($id === null ? 'creada' : 'actualizada');
    }

    // Redirige al listado con el resultado de la operación.
    private function volverAlListado($resultado)
    {
        // Redirige al navegador a categoria/index.
        header('Location: /proyectofinaldesarrollo/categoria/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
