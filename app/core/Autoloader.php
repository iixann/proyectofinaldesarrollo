<?php

// Carga las clases cuando la aplicación las necesita.
class Autoloader
{
    // Activa la carga automática de clases de la aplicación.
    public function __construct()
    {
        $this->loadAppClasses();
    }

    // Registra cómo encontrar los archivos de las clases.
    private function loadAppClasses()
    {
        // Cuando se pide una clase, resuelve su archivo y lo carga.
        spl_autoload_register(function ($nombreClase) {
            $clasesParaCargaAutomatica = array('App', 'Controller', 'Model', 'Response', 'DataBase');
            foreach ($clasesParaCargaAutomatica as $clase) {
                if (is_string(strstr($nombreClase, $clase))) {
                    // Las clases globales del núcleo están dentro de CORE_PATH.
                    if (in_array($nombreClase, $clasesParaCargaAutomatica)) {
                        $archivo = CORE_PATH . $nombreClase . '.php';
                    } else {
                        $archivo = str_replace('\\', '/', $nombreClase) . '.php';
                    }
                    // Carga el archivo solo si existe en la ruta calculada.
                    if (file_exists($archivo)) {
                        require_once $archivo;
                    }
                    return;
                }
            }
        }, true, false);
    }
}

new Autoloader();
