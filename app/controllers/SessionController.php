<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\UserModel;

class SessionController extends Controller
{
    public static function sesionValida()
    {
        return !empty($_SESSION['usuario']['id_usuario'])
            && isset($_SESSION['usuario']['roles'])
            && is_array($_SESSION['usuario']['roles']);
    }

    public static function esAdministrador()
    {
        return self::sesionValida()
            && in_array('ADMINISTRADOR', $_SESSION['usuario']['roles'], true);
    }

    public static function soloAdministradores()
    {
        if (!self::sesionValida()) {
            header('Location: /proyectofinaldesarrollo/session/login');
            exit;
        }
        if (!self::esAdministrador()) {
            http_response_code(403);
            echo 'Acceso denegado';
            exit;
        }
    }

    public function actionLogin()
    {
        if (self::esAdministrador()) {
            header('Location: /proyectofinaldesarrollo/admin/index');
            exit;
        }
        Response::render($this->viewDir(__NAMESPACE__), 'login', ['mensaje' => '', 'correo' => '']);
    }

    public function actionIngresar()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            echo 'Método no permitido';
            return;
        }
        $correo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';
        $contrasena = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';
        $mensaje = 'Completá el correo electrónico y la contraseña.';
        if ($correo !== '' && $contrasena !== '') {
            $usuario = UserModel::buscarPorCorreo($correo);
            if ($usuario && (int) $usuario->estado === 1 && password_verify($contrasena, $usuario->password)) {
                $roles = UserModel::obtenerRolesUsuario($usuario->id_usuario);
                session_regenerate_id(true);
                $_SESSION['usuario'] = [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'email' => $usuario->email,
                    'roles' => array_column($roles, 'nombre_rol')
                ];
                if (self::esAdministrador()) {
                    header('Location: /proyectofinaldesarrollo/admin/index');
                    exit;
                }
                $mensaje = 'Sesión iniciada. Tu usuario no tiene acceso al panel de administración.';
            } else {
                $mensaje = 'Correo o contraseña incorrectos, o usuario inactivo.';
            }
        }
        Response::render($this->viewDir(__NAMESPACE__), 'login', ['mensaje' => $mensaje, 'correo' => $correo]);
    }

    public function actionLogout()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
        }
        session_destroy();
        header('Location: /proyectofinaldesarrollo/session/login');
        exit;
    }
}
