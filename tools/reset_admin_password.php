<?php

// Herramienta técnica local: nunca inicia una sesión ni ofrece un login alternativo.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso denegado');
}

chdir(dirname(__DIR__));
define('CORE_PATH', 'app/core/');
define('APP_PATH', 'app/');
require_once APP_PATH . 'librerias/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(getcwd())->safeLoad();
require_once CORE_PATH . 'Autoloader.php';

function leerEntradaLocal($mensaje, $secreta = false)
{
    $configuracion = null;
    if ($secreta && stream_isatty(STDIN)) {
        $configuracion = function_exists('shell_exec') ? trim((string) shell_exec('stty -g < /dev/tty 2>/dev/null')) : '';
        if ($configuracion === '') throw new DomainException('No se pudo desactivar el eco de la terminal.');
        exec('stty -echo < /dev/tty 2>/dev/null', $salida, $codigo);
        if ($codigo !== 0) throw new DomainException('No se pudo desactivar el eco de la terminal.');
    }
    try {
        // Ocultar antes del prompt evita mostrar una contraseña pegada rápidamente.
        fwrite(STDOUT, $mensaje);
        $valor = fgets(STDIN);
        if ($valor === false) throw new DomainException('Entrada cancelada.');
        return rtrim($valor, "\r\n");
    } finally {
        if ($configuracion !== null && $configuracion !== '') {
            exec('stty ' . escapeshellarg($configuracion) . ' < /dev/tty 2>/dev/null');
            fwrite(STDOUT, PHP_EOL);
        }
    }
}

try {
    $correo = trim(leerEntradaLocal('Email del administrador: '));
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) throw new DomainException('Ingresá un email válido.');
    $usuario = \app\models\UserModel::buscarPorCorreo($correo);
    if (!$usuario) throw new DomainException('El usuario no existe.');
    $roles = \app\models\UserModel::obtenerRolesUsuario($usuario->id_usuario);
    if (!in_array('ADMINISTRADOR', array_column($roles, 'nombre_rol'), true)) {
        throw new DomainException('El usuario no tiene un rol ADMINISTRADOR activo y vigente.');
    }
    $contrasena = leerEntradaLocal('Nueva contraseña temporal: ', true);
    $confirmacion = leerEntradaLocal('Confirmar contraseña temporal: ', true);
    \app\models\UserModel::validarContrasena($contrasena, $confirmacion);
    \app\models\UserModel::restablecerContrasena($usuario->id_usuario, $contrasena);
    unset($contrasena, $confirmacion);
    fwrite(STDOUT, "Contraseña restablecida. Deberá cambiarla al iniciar sesión. El estado y los roles no se modificaron." . PHP_EOL);
} catch (DomainException $excepcion) {
    fwrite(STDERR, $excepcion->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $excepcion) {
    // No mostrar ni registrar excepciones de base que puedan incluir parámetros.
    fwrite(STDERR, 'No se pudo restablecer la contraseña. Revisá la configuración y el esquema de la base.' . PHP_EOL);
    exit(1);
}
