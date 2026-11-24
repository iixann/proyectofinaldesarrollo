<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\UserModel;

// Controla las operaciones de usuarios y roles.
class UserController extends Controller
{
    // Muestra el listado de usuarios y roles con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['ADMINISTRADOR']);
        // Pasa a UserModel para obtener los usuarios y sus roles.
        $usuarios = UserModel::listarUsuarios();
        $mensajes = ['baja' => 'Usuario dado de baja.', 'reactivado' => 'Usuario reactivado correctamente.',
            'password' => 'Contraseña restablecida correctamente. El usuario deberá cambiarla al próximo inicio de sesión.'];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'usuarios' => $usuarios, 'mensaje' => $mensajes[$resultado] ?? ''
        ]);
    }

    // Muestra al administrador el formulario para restablecer una clave.
    public function actionRestablecerPassword($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['ADMINISTRADOR']);
        $this->mostrarRestablecimiento($this->usuarioParaRestablecer($id));
    }

    // Restablece por POST la contraseña del usuario seleccionado.
    public function actionGuardarPassword($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['ADMINISTRADOR']);
        SessionController::validarPostPassword();
        $usuario = $this->usuarioParaRestablecer($id);
        try {
            $contrasena = $_POST['contrasena'] ?? '';
            // Pasa a UserModel: rechaza contraseñas inválidas o una confirmación distinta.
            UserModel::validarContrasena($contrasena, $_POST['confirmacion'] ?? '');
            // Pasa a UserModel: guarda una clave temporal y obliga a cambiarla al ingresar.
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

    // Valida el ID y devuelve el usuario cuya clave se restablecerá.
    private function usuarioParaRestablecer($id)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            exit('ID de usuario inválido.');
        }
        // Pasa a UserModel para buscar el registro por su ID.
        $usuario = UserModel::findId($id);
        // Si no encuentra el usuario, detiene la solicitud.
        if (!$usuario) {
            http_response_code(404);
            exit('El usuario no existe.');
        }
        return $usuario;
    }

    // Carga el formulario de clave temporal con su token y errores.
    private function mostrarRestablecimiento($usuario, $errores = [])
    {
        header('Cache-Control: no-store');
        // Envía los datos a la vista restablecer_password.
        Response::render($this->viewDir(__NAMESPACE__), 'restablecer_password', [
            'usuario' => $usuario, 'errores' => $errores, 'token' => SessionController::tokenPassword()
        ]);
    }

    // Solicita la baja lógica del usuario.
    public function actionBaja($id = null)
    {
        $this->cambiarEstado($id, false);
    }

    // Solicita reactivar el usuario.
    public function actionReactivar($id = null)
    {
        $this->cambiarEstado($id, true);
    }

    // Valida permisos y POST antes de cambiar el estado del usuario.
    private function cambiarEstado($id, $activo)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['ADMINISTRADOR']);
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido');
        }
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            exit('ID de usuario inválido.');
        }
        if (!UserModel::findId($id)) {
            http_response_code(404);
            exit('El usuario no existe.');
        }
        // Elige entre reactivar el registro o darlo de baja.
        try {
            if ($activo) UserModel::reactivar($id);
            else UserModel::baja($id);
        } catch (\DomainException $excepcion) {
            // Devuelve el rechazo de negocio sin ejecutar la baja.
            http_response_code(in_array($excepcion->getCode(), [403,409], true) ? $excepcion->getCode() : 422);
            echo htmlspecialchars($excepcion->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }
        // Redirige al navegador a user/index.
        header('Location: /proyectofinaldesarrollo/user/index?resultado=' . ($activo ? 'reactivado' : 'baja'), true, 303);
        exit;
    }

    // Muestra el formulario para crear el usuario.
    public function actionCrear()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['ADMINISTRADOR']);
        $this->mostrarFormulario();
    }

    // Carga la vista del formulario con los datos y errores del usuario.
    private function mostrarFormulario($errores = [], $datosUsuario = [])
    {
        // Pasa a UserModel para obtener los roles habilitados.
        $roles = UserModel::obtenerRolesActivos();
        // Envía los datos a la vista crear.
        Response::render($this->viewDir(__NAMESPACE__), 'crear', [
            'roles' => $roles, 'errores' => $errores, 'datosUsuario' => $datosUsuario
        ]);
    }

    // Recibe el formulario por POST y solicita guardar el usuario.
    public function actionGuardar()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['ADMINISTRADOR']);
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
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
            // Pasa a UserModel: rechaza contraseñas inválidas o una confirmación distinta.
            UserModel::validarContrasena($contrasena);
        } catch (\DomainException $excepcion) {
            $errores[] = $excepcion->getMessage();
        }
        $roles = [];
        // Pasa a UserModel para obtener los roles habilitados.
        $rolesActivos = array_column(UserModel::obtenerRolesActivos(), 'id_rol');
        // Exige seleccionar al menos un rol habilitado.
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
        // Si el correo ya está registrado, impide crear otro usuario con él.
        if (filter_var($correo, FILTER_VALIDATE_EMAIL) && UserModel::buscarPorCorreo($correo)) {
            $errores[] = 'El correo electrónico ya está registrado.';
        }
        $datosUsuario = ['nombre' => $nombre, 'apellido' => $apellido, 'correo' => $correo, 'roles' => $roles];
        // Si hay errores, los devuelve para corregir el formulario.
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($errores, $datosUsuario);
            return;
        }
        try {
            // Pasa a UserModel: guarda el usuario y sus roles dentro de una transacción.
            UserModel::crearUsuario($nombre, $apellido, $correo, $contrasena, $roles);
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            http_response_code(500);
            $this->mostrarFormulario(['No se pudo crear el usuario. Revisá los datos e intentá nuevamente.'], $datosUsuario);
            return;
        }
        // Redirige al navegador a user/index.
        header('Location: /proyectofinaldesarrollo/user/index', true, 303);
        exit;
    }
}
