<?php

class Response
{
    private function __construct() {}

    public static function render($directorioVista, $vista, $variables = [])
    {
        foreach ($variables as $clave => $valor) {
            $$clave = $valor;
        }
        require APP_PATH . "views/" . $directorioVista . $vista . ".php";
    }
}
