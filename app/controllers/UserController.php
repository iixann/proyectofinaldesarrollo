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
        $actor = UserModel::usuarioConRoles($_SESSION['usuario']['id_usuario']);
        // La vista recibe permisos calculados con los roles reales del administrador.
        foreach ($usuarios as $usuario) $usuario->puede_cambiar_roles = UserModel::puedeCambiarRoles($actor, $usuario);
        $mensajes = ['roles' => 'Roles actualizados correctamente.', 'baja' => 'Usuario dado de baja.', 'reactivado' => 'Usuario reactivado correctamente.',
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
        // Ninguna acción administrativa modifica al principal, ni desde su propia sesión.
        if ((int) $usuario->es_principal === 1) {
            http_response_code(403);
            exit('Administrador Principal protegido.');
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

    // Autoriza el objetivo real antes de mostrar o aceptar un cambio de roles.
    private function usuarioParaCambiarRoles($id)
    {
        $usuario = $this->usuarioParaRestablecer($id);
        $usuario->roles = UserModel::obtenerRolesUsuario($usuario->id_usuario);
        $actor = UserModel::usuarioConRoles($_SESSION['usuario']['id_usuario']);
        if (!UserModel::puedeCambiarRoles($actor, $usuario)) {
            http_response_code(403);
            exit('No tenés permiso para cambiar los roles de este usuario.');
        }
        return $usuario;
    }

    // Muestra los roles actuales únicamente a un administrador autorizado.
    public function actionCambiarRoles($id = null)
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        $this->mostrarCambioRoles($this->usuarioParaCambiarRoles($id));
    }

    // Verifica POST y CSRF antes de guardar roles y auditoría en el modelo.
    public function actionGuardarRoles($id = null)
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('Método no permitido');
        }
        $token = $_POST['csrf_roles'] ?? '';
        if (!is_string($token) || empty($_SESSION['csrf_roles']) || !hash_equals($_SESSION['csrf_roles'], $token)) {
            http_response_code(403);
            exit('El formulario venció. Recargá Cambiar roles.');
        }
        $usuario = $this->usuarioParaCambiarRoles($id);
        try {
            // El modelo vuelve a comprobar permisos y último administrador bajo bloqueo.
            UserModel::cambiarRoles($usuario->id_usuario, $_POST['roles'] ?? [], $_SESSION['usuario']['id_usuario']);
        } catch (\DomainException $excepcion) {
            http_response_code(in_array($excepcion->getCode(), [403,404,409], true) ? $excepcion->getCode() : 422);
            $this->mostrarCambioRoles($usuario, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log('No se pudo actualizar roles y auditoría.');
            http_response_code(500);
            $this->mostrarCambioRoles($usuario, ['No se pudo guardar el cambio completo. Intentá nuevamente.']);
            return;
        }
        unset($_SESSION['csrf_roles']);
        header('Location: /proyectofinaldesarrollo/user/index?resultado=roles', true, 303);
        exit;
    }

    // Prepara el formulario compartido de roles y un token propio de esta operación.
    private function mostrarCambioRoles($usuario, $errores = [])
    {
        header('Cache-Control: no-store');
        if (empty($_SESSION['csrf_roles'])) $_SESSION['csrf_roles'] = bin2hex(random_bytes(32));
        Response::render($this->viewDir(__NAMESPACE__), 'cambiar_roles', [
            'usuario'=>$usuario, 'roles'=>UserModel::obtenerRolesActivos(), 'errores'=>$errores,
            'seleccionados'=>array_column($usuario->roles, 'id_rol'), 'tokenRoles'=>$_SESSION['csrf_roles']
        ]);
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
        try {
            // La misma regla protege creación y cambio de roles, incluso sin JavaScript.
            $roles = UserModel::validarCombinacionRoles($rolesSeleccionados);
        } catch (\DomainException $excepcion) {
            $errores[] = $excepcion->getMessage();
        }
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
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->mostrarFormulario([$excepcion->getMessage()], $datosUsuario);
            return;
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
