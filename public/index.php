<?php

// Recupera la sesión del usuario para atender esta solicitud.
session_start();

error_reporting(E_ALL);
chdir(dirname(__DIR__));
define("CORE_PATH", "app/core/");
define("APP_PATH", "app/");
define("ROOT_PATH", "public/");

// Carga las dependencias externas instaladas con Composer.
require_once APP_PATH . 'librerias/vendor/autoload.php';
// Lee la configuración de conexión desde el archivo de entorno.
$entorno = Dotenv\Dotenv::createImmutable('.');
$entorno->safeLoad();

// Activa la carga automática de clases del MVC.
require_once CORE_PATH . "Autoloader.php";

try {
    // Pasa la URL a App para iniciar el recorrido Controller → Model → View.
    $aplicacion = new App;
} catch (Throwable $excepcion) {
    error_log($excepcion->getMessage());
    http_response_code(500);
    echo "No se pudo completar la solicitud. Revisá la configuración y el registro de errores de PHP.";
}
