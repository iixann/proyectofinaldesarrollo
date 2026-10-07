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

    protected function viewDir($espacioNombres)
    {
        $reemplazos = array($espacioNombres, 'Controller');
        $directorioVista = str_replace($reemplazos, '', get_class($this)) . '/';
        $directorioVista = str_replace('\\', '', $directorioVista);
        return strtolower($directorioVista);
    }
}
