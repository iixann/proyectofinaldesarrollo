<?php

session_start();

error_reporting(E_ALL);
chdir(dirname(__DIR__));
define("CORE_PATH", "app/core/");
define("APP_PATH", "app/");
define("ROOT_PATH", "public/");

require_once APP_PATH . 'librerias/vendor/autoload.php';
$entorno = Dotenv\Dotenv::createImmutable('.');
$entorno->safeLoad();

require_once CORE_PATH . "Autoloader.php";

try {
    $aplicacion = new App;
} catch (Throwable $excepcion) {
    error_log($excepcion->getMessage());
    http_response_code(500);
    echo "No se pudo completar la solicitud. Revisá la configuración y el registro de errores de PHP.";
}
