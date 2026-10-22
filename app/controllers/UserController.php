<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\UserModel;

class UserController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        $usuarios = UserModel::listarUsuarios();
        Response::render($this->viewDir(__NAMESPACE__), 'index', ['usuarios' => $usuarios]);
    }

    public function actionCrear()
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        $this->mostrarFormulario();
    }

    private function mostrarFormulario($errores = [], $datosUsuario = [])
    {
        $roles = UserModel::obtenerRolesActivos();
        Response::render($this->viewDir(__NAMESPACE__), 'crear', [
            'roles' => $roles, 'errores' => $errores, 'datosUsuario' => $datosUsuario
        ]);
    }

    public function actionGuardar()
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            echo 'Método no permitido';
            return;
        }
        $nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
        $apellido = is_string($_POST['apellido'] ?? null) ? trim($_POST['apellido']) : '';
        $correo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';
        $contrasena = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';
        $rolesSeleccionados = $_POST['roles'] ?? [];
        $errores = [];
        if ($nombre === '') {
            $errores[] = 'Ingresá el nombre.';
        }
        if ($apellido === '') {
            $errores[] = 'Ingresá el apellido.';
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Ingresá un correo electrónico válido.';
        }
        if (trim($contrasena) === '' || strlen($contrasena) > 72 || strpos($contrasena, "\0") !== false) {
            $errores[] = 'Ingresá una contraseña no vacía de hasta 72 bytes, sin caracteres nulos.';
        }
        $roles = [];
        $rolesActivos = array_column(UserModel::obtenerRolesActivos(), 'id_rol');
        if (!is_array($rolesSeleccionados) || !$rolesSeleccionados) {
            $errores[] = 'Seleccioná al menos un rol.';
        } else {
            foreach ($rolesSeleccionados as $idRol) {
                if (!is_string($idRol) || !ctype_digit($idRol) || !in_array($idRol, $rolesActivos)) {
                    $errores[] = 'Seleccioná solamente roles activos válidos.';
                    break;
                }
                $roles[] = (int) $idRol;
            }
        }
        $roles = array_unique($roles);
        if (filter_var($correo, FILTER_VALIDATE_EMAIL) && UserModel::buscarPorCorreo($correo)) {
            $errores[] = 'El correo electrónico ya está registrado.';
        }
        $datosUsuario = ['nombre' => $nombre, 'apellido' => $apellido, 'correo' => $correo, 'roles' => $roles];
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($errores, $datosUsuario);
            return;
        }
        try {
            UserModel::crearUsuario($nombre, $apellido, $correo, $contrasena, $roles);
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            http_response_code(500);
            $this->mostrarFormulario(['No se pudo crear el usuario. Revisá los datos e intentá nuevamente.'], $datosUsuario);
            return;
        }
        header('Location: /proyectofinaldesarrollo/user/index', true, 303);
        exit;
    }
}
