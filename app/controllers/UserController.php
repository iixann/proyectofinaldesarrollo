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
        $mensajes = ['baja' => 'Usuario dado de baja.', 'reactivado' => 'Usuario reactivado correctamente.',
            'password' => 'Contraseña restablecida correctamente. El usuario deberá cambiarla al próximo inicio de sesión.'];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'usuarios' => $usuarios, 'mensaje' => $mensajes[$resultado] ?? ''
        ]);
    }

    public function actionRestablecerPassword($id = null)
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        $this->mostrarRestablecimiento($this->usuarioParaRestablecer($id));
    }

    public function actionGuardarPassword($id = null)
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        SessionController::validarPostPassword();
        $usuario = $this->usuarioParaRestablecer($id);
        try {
            $contrasena = $_POST['contrasena'] ?? '';
            UserModel::validarContrasena($contrasena, $_POST['confirmacion'] ?? '');
            UserModel::restablecerContrasena($usuario->id_usuario, $contrasena);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->mostrarRestablecimiento($usuario, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log('No se pudo restablecer la contraseña de usuario.');
            http_response_code(500);
            $this->mostrarRestablecimiento($usuario, ['No se pudo restablecer la contraseña. Intentá nuevamente.']);
            return;
        }
        unset($_SESSION['csrf_password']);
        // Si restablece su propia clave, deberá autenticar con la temporal.
        header('Location: /proyectofinaldesarrollo/user/index?resultado=password', true, 303);
        exit;
    }

    private function usuarioParaRestablecer($id)
    {
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            exit('ID de usuario inválido.');
        }
        $usuario = UserModel::findId($id);
        if (!$usuario) {
            http_response_code(404);
            exit('El usuario no existe.');
        }
        return $usuario;
    }

    private function mostrarRestablecimiento($usuario, $errores = [])
    {
        header('Cache-Control: no-store');
        Response::render($this->viewDir(__NAMESPACE__), 'restablecer_password', [
            'usuario' => $usuario, 'errores' => $errores, 'token' => SessionController::tokenPassword()
        ]);
    }

    public function actionBaja($id = null)
    {
        $this->cambiarEstado($id, false);
    }

    public function actionReactivar($id = null)
    {
        $this->cambiarEstado($id, true);
    }

    private function cambiarEstado($id, $activo)
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido');
        }
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            exit('ID de usuario inválido.');
        }
        if (!UserModel::findId($id)) {
            http_response_code(404);
            exit('El usuario no existe.');
        }
        if ($activo) UserModel::reactivar($id);
        else UserModel::baja($id);
        header('Location: /proyectofinaldesarrollo/user/index?resultado=' . ($activo ? 'reactivado' : 'baja'), true, 303);
        exit;
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
        try {
            UserModel::validarContrasena($contrasena);
        } catch (\DomainException $excepcion) {
            $errores[] = $excepcion->getMessage();
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
