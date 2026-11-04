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

    public static function destinoInicio()
    {
        if (!self::sesionValida()) return null;
        if (!empty($_SESSION['usuario']['debe_cambiar_password'])) {
            return '/proyectofinaldesarrollo/session/cambiarPassword';
        }
        $roles = $_SESSION['usuario']['roles'];
        $rutas = [
            'ADMINISTRADOR' => 'admin/index',
            'DUEÑO' => 'admin/index',
            'VENDEDOR' => 'sesioncaja/index',
            'RECEPCIONISTA' => 'stock/index'
        ];
        foreach ($rutas as $rol => $ruta) {
            if (in_array($rol, $roles, true)) return '/proyectofinaldesarrollo/' . $ruta;
        }
        return null;
    }

    public static function soloAdministradores()
    {
        self::onlyUsers(['ADMINISTRADOR']);
    }

    public static function onlyUsers(array $rolesPermitidos)
    {
        self::exigirSesion();
        if (self::esAdministrador()) {
            return;
        }

        $rolesUsuario = $_SESSION['usuario']['roles'];
        // La herencia funcional se calcula en memoria, sin guardar roles adicionales.
        if (in_array('DUEÑO', $rolesUsuario, true)) {
            $rolesUsuario = array_merge($rolesUsuario, ['RECEPCIONISTA', 'VENDEDOR']);
        }
        if (array_intersect($rolesPermitidos, $rolesUsuario)) {
            return;
        }

        http_response_code(403);
        echo 'Acceso denegado';
        exit;
    }

    public static function exigirSesion($permitirCambio = false)
    {
        if (!self::sesionValida()) {
            header('Location: /proyectofinaldesarrollo/session/login');
            exit;
        }
        // Consultar el estado real impide eludir un reset desde una sesión anterior.
        $usuario = UserModel::findId($_SESSION['usuario']['id_usuario']);
        $firma = $usuario ? hash('sha256', $usuario->password) : '';
        $firmaSesion = $_SESSION['usuario']['firma_password'] ?? '';
        if (!$usuario || (int) $usuario->estado !== 1
            || ($firmaSesion !== '' && !hash_equals($firma, $firmaSesion))
            || ($firmaSesion === '' && (int) $usuario->debe_cambiar_password === 1)) {
            $_SESSION = [];
            header('Location: /proyectofinaldesarrollo/session/login');
            exit;
        }
        $_SESSION['usuario']['firma_password'] = $firma;
        $_SESSION['usuario']['debe_cambiar_password'] = (int) $usuario->debe_cambiar_password;
        if (!$permitirCambio && (int) $usuario->debe_cambiar_password === 1) {
            header('Location: /proyectofinaldesarrollo/session/cambiarPassword');
            exit;
        }
        return $usuario;
    }

    public static function tokenPassword()
    {
        if (empty($_SESSION['csrf_password'])) $_SESSION['csrf_password'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_password'];
    }

    public static function validarPostPassword()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido');
        }
        $token = $_POST['csrf_password'] ?? '';
        if (!is_string($token) || empty($_SESSION['csrf_password']) || !hash_equals($_SESSION['csrf_password'], $token)) {
            http_response_code(403);
            exit('El formulario venció. Recargá la página.');
        }
    }

    public function actionCambiarPassword()
    {
        self::exigirSesion(true);
        $this->mostrarCambioPassword();
    }

    public function actionGuardarPassword()
    {
        self::exigirSesion(true);
        self::validarPostPassword();
        if (empty($_SESSION['usuario']['debe_cambiar_password'])) {
            header('Location: ' . (self::destinoInicio() ?? '/proyectofinaldesarrollo/session/login'));
            exit;
        }
        try {
            $contrasena = $_POST['contrasena'] ?? '';
            UserModel::validarContrasena($contrasena, $_POST['confirmacion'] ?? '');
            $firma = UserModel::cambiarContrasenaObligatoria($_SESSION['usuario']['id_usuario'], $contrasena, $_SESSION['usuario']['firma_password']);
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->mostrarCambioPassword([$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            // No registrar payload, contraseña ni excepción SQL con sus parámetros.
            error_log('No se pudo guardar el cambio obligatorio de contraseña.');
            http_response_code(500);
            $this->mostrarCambioPassword(['No se pudo cambiar la contraseña. Intentá nuevamente.']);
            return;
        }
        session_regenerate_id(true);
        $_SESSION['usuario']['firma_password'] = $firma;
        $_SESSION['usuario']['debe_cambiar_password'] = 0;
        unset($_SESSION['csrf_password'], $_SESSION['csrf_venta']);
        header('Location: ' . (self::destinoInicio() ?? '/proyectofinaldesarrollo/session/login'), true, 303);
        exit;
    }

    private function mostrarCambioPassword($errores = [])
    {
        if (empty($_SESSION['usuario']['debe_cambiar_password'])) {
            header('Location: ' . (self::destinoInicio() ?? '/proyectofinaldesarrollo/session/login'));
            exit;
        }
        header('Cache-Control: no-store');
        Response::render($this->viewDir(__NAMESPACE__), 'cambiar_password', ['errores' => $errores, 'token' => self::tokenPassword()]);
    }

    public function actionLogin()
    {
        if (self::sesionValida()) self::exigirSesion();
        $destino = self::destinoInicio();
        if ($destino !== null) {
            header('Location: ' . $destino);
            exit;
        }
        Response::render($this->viewDir(__NAMESPACE__), 'login', ['mensaje' => '', 'correo' => '']);
    }

    public function actionIngresar()
    {
        if (self::sesionValida()) self::exigirSesion();
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
                    'roles' => array_column($roles, 'nombre_rol'),
                    'debe_cambiar_password' => (int) $usuario->debe_cambiar_password,
                    'firma_password' => hash('sha256', $usuario->password)
                ];
                unset($_SESSION['csrf_venta'], $_SESSION['csrf_password']);
                $destino = self::destinoInicio();
                if ($destino !== null) {
                    header('Location: ' . $destino);
                    exit;
                }
                $mensaje = 'Sesión iniciada. Tu usuario no tiene roles habilitados para operar.';
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
