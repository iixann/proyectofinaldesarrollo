<?php

namespace app\controllers;

use \Controller;
use \Response;

class AdminController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['ADMINISTRADOR']);
        Response::render($this->viewDir(__NAMESPACE__), 'index', ['usuario' => $_SESSION['usuario']]);
    }
}
