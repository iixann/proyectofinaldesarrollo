<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ProveedorModel;

class ProveedorController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $mensajes = [
            'creado' => 'Proveedor creado correctamente.',
            'actualizado' => 'Proveedor actualizado correctamente.',
            'reactivada' => 'Proveedor reactivado correctamente.',
            'baja' => 'Proveedor dado de baja.'
        ];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'proveedores' => ProveedorModel::listarProveedores(),
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
        $proveedor = $this->buscarProveedor($id);
        $this->mostrarFormulario('editar', (array) $proveedor, [], $proveedor->id_proveedor);
    }

    public function actionActualizar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $proveedor = $this->buscarProveedor($id);
        $this->guardar($proveedor->id_proveedor);
    }

    public function actionBaja($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $proveedor = $this->buscarProveedor($id);
        ProveedorModel::bajaProveedor($proveedor->id_proveedor);
        $this->volverAlListado('baja');
    }

    public function actionReactivar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $proveedor = $this->buscarProveedor($id);
        ProveedorModel::reactivar($proveedor->id_proveedor);
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

    private function buscarProveedor($id)
    {
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de proveedor inválido.';
            exit;
        }
        $proveedor = ProveedorModel::findId($id);
        if (!$proveedor) {
            http_response_code(404);
            echo 'El proveedor no existe.';
            exit;
        }
        return $proveedor;
    }

    private function mostrarFormulario($vista, $datosProveedor = [], $errores = [], $id = null)
    {
        Response::render($this->viewDir(__NAMESPACE__), $vista, [
            'datosProveedor' => $datosProveedor, 'errores' => $errores, 'id' => $id
        ]);
    }

    private function guardar($id = null)
    {
        $datosProveedor = [];
        $errores = [];
        $campos = [
            'razon_social' => ['Razón social', 150],
            'cuit' => ['CUIT', 13],
            'nombre_contacto' => ['Nombre de contacto', 100],
            'telefono' => ['Teléfono', 50],
            'email' => ['Email', 150],
            'direccion' => ['Dirección', 200]
        ];
        foreach ($campos as $campo => [$etiqueta, $limite]) {
            $valor = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
            $datosProveedor[$campo] = $valor;
            if (mb_strlen($valor, 'UTF-8') > $limite) {
                $errores[] = $etiqueta . ': ingresá hasta ' . $limite . ' caracteres.';
            }
        }
        if ($datosProveedor['razon_social'] === '') {
            $errores[] = 'Ingresá una razón social.';
        }
        if ($datosProveedor['email'] !== '' && !filter_var($datosProveedor['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        if ($datosProveedor['cuit'] !== '' && ProveedorModel::cuitDuplicado($datosProveedor['cuit'], $id)) {
            $errores[] = 'Ya existe un proveedor con ese CUIT.';
        }
        $vista = $id === null ? 'crear' : 'editar';
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($vista, $datosProveedor, $errores, $id);
            return;
        }
        try {
            if ($id === null) {
                ProveedorModel::crearProveedor($datosProveedor);
            } else {
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

    private function volverAlListado($resultado)
    {
        header('Location: /proyectofinaldesarrollo/proveedor/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
