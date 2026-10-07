<?php

class Autoloader
{
    public function __construct()
    {
        $this->loadAppClasses();
    }

    private function loadAppClasses()
    {
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
