<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\UserModel;

// Controla las operaciones de acceso y sesión de usuarios.
class SessionController extends Controller
{
    // Comprueba que la sesión tenga usuario y una lista de roles.
    public static function sesionValida()
    {
        return !empty($_SESSION['usuario']['id_usuario'])
            && isset($_SESSION['usuario']['roles'])
            && is_array($_SESSION['usuario']['roles']);
    }

    // Indica si el usuario conectado tiene el rol administrador.
    public static function esAdministrador()
    {
        return self::sesionValida()
            && in_array('ADMINISTRADOR', $_SESSION['usuario']['roles'], true);
    }

    // Elige la página inicial según la contraseña pendiente y los roles.
    public static function destinoInicio()
    {
        // Sin una sesión válida, impide continuar con el acceso.
        if (!self::sesionValida()) return null;
        // Si debe cambiar la clave, lo dirige al formulario obligatorio.
        if (!empty($_SESSION['usuario']['debe_cambiar_password'])) {
            return '/proyectofinaldesarrollo/session/cambiarPassword';
        }
        $roles = $_SESSION['usuario']['roles'];
        $rutas = [
            'ADMINISTRADOR' => 'admin/index',
            'DUEÑO' => 'admin/index',
            'VENDEDOR' => 'sesioncaja/index',
            'RECEPCIONISTA' => 'producto/index'
        ];
        foreach ($rutas as $rol => $ruta) {
            // El primer rol habilitado determina la página de inicio.
            if (in_array($rol, $roles, true)) return '/proyectofinaldesarrollo/' . $ruta;
        }
        return null;
    }

    // Restringe el acceso al rol administrador.
    public static function soloAdministradores()
    {
        self::onlyUsers(['ADMINISTRADOR']);
    }

    // Valida la sesión y permite entrar según los roles autorizados.
    public static function onlyUsers(array $rolesPermitidos)
    {
        self::exigirSesion();
        // El administrador puede acceder a todas las operaciones protegidas.
        if (self::esAdministrador()) {
            return;
        }

        $rolesUsuario = $_SESSION['usuario']['roles'];
        // La herencia funcional se calcula en memoria, sin guardar roles adicionales.
        if (in_array('DUEÑO', $rolesUsuario, true)) {
            $rolesUsuario = array_merge($rolesUsuario, ['RECEPCIONISTA', 'VENDEDOR']);
        }
        // Permite continuar si algún rol del usuario está autorizado.
        if (array_intersect($rolesPermitidos, $rolesUsuario)) {
            return;
        }

        http_response_code(403);
        echo 'Acceso denegado';
        exit;
    }

    // Verifica el usuario en la base y exige cambiar la clave si corresponde.
    public static function exigirSesion($permitirCambio = false)
    {
        // Sin una sesión válida, impide continuar con el acceso.
        if (!self::sesionValida()) {
            // Redirige al navegador a session/login.
            header('Location: /proyectofinaldesarrollo/session/login');
            exit;
        }
        // Consultar el estado real impide eludir un reset desde una sesión anterior.
        $usuario = UserModel::findId($_SESSION['usuario']['id_usuario']);
        $firma = $usuario ? hash('sha256', $usuario->password) : '';
        $firmaSesion = $_SESSION['usuario']['firma_password'] ?? '';
        // Invalida la sesión si el usuario está inactivo o cambió su contraseña.
        if (!$usuario || (int) $usuario->estado !== 1
            || ($firmaSesion !== '' && !hash_equals($firma, $firmaSesion))
            || ($firmaSesion === '' && (int) $usuario->debe_cambiar_password === 1)) {
            $_SESSION = [];
            // Redirige al navegador a session/login.
            header('Location: /proyectofinaldesarrollo/session/login');
            exit;
        }
        // Aplica los cambios de roles también a sesiones que ya estaban abiertas.
        $rolesActuales = array_column(UserModel::obtenerRolesUsuario($usuario->id_usuario), 'nombre_rol');
        if (!$rolesActuales) {
            $_SESSION = [];
            header('Location: /proyectofinaldesarrollo/session/login');
            exit;
        }
        $_SESSION['usuario']['roles'] = $rolesActuales;
        $_SESSION['usuario']['firma_password'] = $firma;
        $_SESSION['usuario']['debe_cambiar_password'] = (int) $usuario->debe_cambiar_password;
        // Si debe cambiar la clave, lo dirige al formulario obligatorio.
        if (!$permitirCambio && (int) $usuario->debe_cambiar_password === 1) {
            // Redirige al navegador a session/cambiarPassword.
            header('Location: /proyectofinaldesarrollo/session/cambiarPassword');
            exit;
        }
        return $usuario;
    }

    // Obtiene o crea el token que protege el formulario de contraseña.
    public static function tokenPassword()
    {
        // Crea el token del formulario si todavía no existe en la sesión.
        if (empty($_SESSION['csrf_password'])) $_SESSION['csrf_password'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_password'];
    }

    // Exige POST y verifica el token del formulario de contraseña.
    public static function validarPostPassword()
    {
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido');
        }
        $token = $_POST['csrf_password'] ?? '';
        // Rechaza el formulario si su token no coincide con la sesión.
        if (!is_string($token) || empty($_SESSION['csrf_password']) || !hash_equals($_SESSION['csrf_password'], $token)) {
            http_response_code(403);
            exit('El formulario venció. Recargá la página.');
        }
    }

    // Muestra el formulario de cambio obligatorio de contraseña.
    public function actionCambiarPassword()
    {
        self::exigirSesion(true);
        $this->mostrarCambioPassword();
    }

    // Valida y guarda la nueva contraseña enviada por POST.
    public function actionGuardarPassword()
    {
        self::exigirSesion(true);
        self::validarPostPassword();
        // Si ya cambió su contraseña, vuelve al inicio correspondiente.
        if (empty($_SESSION['usuario']['debe_cambiar_password'])) {
            // Vuelve al inicio del rol o al login si no hay un destino habilitado.
            header('Location: ' . (self::destinoInicio() ?? '/proyectofinaldesarrollo/session/login'));
            exit;
        }
        try {
            $contrasena = $_POST['contrasena'] ?? '';
            // Pasa a UserModel: rechaza contraseñas inválidas o una confirmación distinta.
            UserModel::validarContrasena($contrasena, $_POST['confirmacion'] ?? '');
            // Pasa a UserModel para guardar el cambio obligatorio de contraseña.
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
        // Vuelve al inicio del rol o al login si no hay un destino habilitado.
        header('Location: ' . (self::destinoInicio() ?? '/proyectofinaldesarrollo/session/login'), true, 303);
        exit;
    }

    // Carga la vista de cambio obligatorio con sus errores y token.
    private function mostrarCambioPassword($errores = [])
    {
        // Si ya cambió su contraseña, vuelve al inicio correspondiente.
        if (empty($_SESSION['usuario']['debe_cambiar_password'])) {
            // Vuelve al inicio del rol o al login si no hay un destino habilitado.
            header('Location: ' . (self::destinoInicio() ?? '/proyectofinaldesarrollo/session/login'));
            exit;
        }
        header('Cache-Control: no-store');
        // Envía los datos a la vista cambiar_password.
        Response::render($this->viewDir(__NAMESPACE__), 'cambiar_password', ['errores' => $errores, 'token' => self::tokenPassword()]);
    }

    // Muestra el login o dirige al inicio si ya hay una sesión válida.
    public function actionLogin()
    {
        if (self::sesionValida()) self::exigirSesion();
        $destino = self::destinoInicio();
        // Si tiene un destino habilitado, lo envía al inicio correspondiente.
        if ($destino !== null) {
            // Redirige al navegador al destino calculado.
            header('Location: ' . $destino);
            exit;
        }
        // Envía los datos a la vista login.
        Response::render($this->viewDir(__NAMESPACE__), 'login', ['mensaje' => '', 'correo' => '']);
    }

    // Valida las credenciales por POST y guarda el usuario en sesión.
    public function actionIngresar()
    {
        if (self::sesionValida()) self::exigirSesion();
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            echo 'Método no permitido';
            return;
        }
        $correo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';
        $contrasena = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';
        $mensaje = 'Completá el correo electrónico y la contraseña.';
        // Comprueba las credenciales solo cuando ambos campos están completos.
        if ($correo !== '' && $contrasena !== '') {
            // Pasa a UserModel para buscar el usuario por email.
            $usuario = UserModel::buscarPorCorreo($correo);
            // Solo inicia sesión si el usuario está activo y la contraseña coincide.
            if ($usuario && (int) $usuario->estado === 1 && password_verify($contrasena, $usuario->password)) {
                // Pasa a UserModel para consultar los roles del usuario.
                $roles = UserModel::obtenerRolesUsuario($usuario->id_usuario);
                session_regenerate_id(true);
                $_SESSION['usuario'] = [
                    'id_usuario' => $usuario->id_usuario,
                    // Una nueva autenticación no recupera borradores de la sesión anterior.
                    'contexto_carrito' => bin2hex(random_bytes(16)),
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'email' => $usuario->email,
                    'roles' => array_column($roles, 'nombre_rol'),
                    'debe_cambiar_password' => (int) $usuario->debe_cambiar_password,
                    'firma_password' => hash('sha256', $usuario->password)
                ];
                unset($_SESSION['csrf_venta'], $_SESSION['csrf_password']);
                $destino = self::destinoInicio();
                // Si tiene un destino habilitado, lo envía al inicio correspondiente.
                if ($destino !== null) {
                    // Redirige al navegador al destino calculado.
                    header('Location: ' . $destino);
                    exit;
                }
                $mensaje = 'Sesión iniciada. Tu usuario no tiene roles habilitados para operar.';
            } else {
                $mensaje = 'Correo o contraseña incorrectos, o usuario inactivo.';
            }
        }
        // Envía los datos a la vista login.
        Response::render($this->viewDir(__NAMESPACE__), 'login', ['mensaje' => $mensaje, 'correo' => $correo]);
    }

    // Elimina la sesión y su cookie antes de volver al login.
    public function actionLogout()
    {
        $_SESSION = [];
        // Si la sesión usa cookie, también la elimina del navegador.
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
        }
        session_destroy();
        // Redirige al navegador a session/login.
        header('Location: /proyectofinaldesarrollo/session/login');
        exit;
    }
}
