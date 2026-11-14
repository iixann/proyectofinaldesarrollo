<?php

// Carga una vista PHP con los datos enviados por el controlador.
class Response
{
    // Impide crear instancias; se usan los métodos estáticos.
    private function __construct() {}

    // Convierte los datos en variables y carga la vista solicitada.
    public static function render($directorioVista, $vista, $variables = [])
    {
        // Cada dato del controlador queda disponible como variable en la vista.
        foreach ($variables as $clave => $valor) {
            $$clave = $valor;
        }
        // Continúa el flujo en el archivo de la vista solicitada.
        require APP_PATH . "views/" . $directorioVista . $vista . ".php";
    }
}
