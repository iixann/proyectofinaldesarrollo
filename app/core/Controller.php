<?php

class Controller
{
    public function actionIndex($parametro = null)
    {
        $this->action404();
    }

    public function action404()
    {
        http_response_code(404);
        echo "Error 404 - Página no encontrada";
    }

    protected function leerFiltro($nombre, $maximo = 150, $esId = false)
    {
        $valor = $_GET[$nombre] ?? '';
        if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8')) {
            http_response_code(400);
            exit('Filtro inválido.');
        }
        $valor = trim($valor);
        if (mb_strlen($valor, 'UTF-8') > $maximo || ($esId && $valor !== ''
            && (!ctype_digit($valor) || (float) $valor < 1 || (float) $valor > 4294967295))) {
            http_response_code(400);
            exit('Filtro inválido.');
        }
        return $valor;
    }

    protected function viewDir($espacioNombres)
    {
        $reemplazos = array($espacioNombres, 'Controller');
        $directorioVista = str_replace($reemplazos, '', get_class($this)) . '/';
        $directorioVista = str_replace('\\', '', $directorioVista);
        return strtolower($directorioVista);
    }
}
