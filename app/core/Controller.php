<?php

// Comparte validaciones y ubicación de vistas entre controladores.
class Controller
{
    // Responde con 404 si el controlador no define su propia acción inicial.
    public function actionIndex($parametro = null)
    {
        $this->action404();
    }

    // Responde que la página solicitada no existe.
    public function action404()
    {
        http_response_code(404);
        echo "Error 404 - Página no encontrada";
    }

    // Lee y valida un filtro recibido por GET.
    protected function leerFiltro($nombre, $maximo = 150, $esId = false)
    {
        $valor = $_GET[$nombre] ?? '';
        // Rechaza filtros que no sean texto UTF-8 válido.
        if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8')) {
            http_response_code(400);
            exit('Filtro inválido.');
        }
        $valor = trim($valor);
        // Rechaza filtros demasiado largos o IDs fuera del rango permitido.
        if (mb_strlen($valor, 'UTF-8') > $maximo || ($esId && $valor !== ''
            && (!ctype_digit($valor) || (float) $valor < 1 || (float) $valor > 4294967295))) {
            http_response_code(400);
            exit('Filtro inválido.');
        }
        return $valor;
    }

    // Obtiene la carpeta de vistas del controlador actual.
    protected function viewDir($espacioNombres)
    {
        $reemplazos = array($espacioNombres, 'Controller');
        $directorioVista = str_replace($reemplazos, '', get_class($this)) . '/';
        $directorioVista = str_replace('\\', '', $directorioVista);
        return strtolower($directorioVista);
    }
}
