<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\CategoriaModel;

class CategoriaController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $mensajes = [
            'creada' => 'Categoría creada correctamente.',
            'actualizada' => 'Categoría actualizada correctamente.',
            'baja' => 'Categoría dada de baja.'
        ];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'categorias' => CategoriaModel::listarCategorias(),
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
        $categoria = $this->buscarCategoria($id);
        $this->mostrarFormulario('editar', $categoria->nombre_categoria, [], $categoria->id_categoria);
    }

    public function actionActualizar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $categoria = $this->buscarCategoria($id);
        $this->guardar($categoria->id_categoria);
    }

    public function actionBaja($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $categoria = $this->buscarCategoria($id);
        CategoriaModel::bajaCategoria($categoria->id_categoria);
        $this->volverAlListado('baja');
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

    private function buscarCategoria($id)
    {
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de categoría inválido.';
            exit;
        }
        $categoria = CategoriaModel::findId($id);
        if (!$categoria) {
            http_response_code(404);
            echo 'La categoría no existe.';
            exit;
        }
        return $categoria;
    }

    private function mostrarFormulario($vista, $nombre = '', $errores = [], $id = null)
    {
        Response::render($this->viewDir(__NAMESPACE__), $vista, [
            'nombre' => $nombre, 'errores' => $errores, 'id' => $id
        ]);
    }

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
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($vista, $nombre, $errores, $id);
            return;
        }
        try {
            if ($id === null) {
                CategoriaModel::crearCategoria($nombre);
            } else {
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

    private function volverAlListado($resultado)
    {
        header('Location: /proyectofinaldesarrollo/categoria/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
