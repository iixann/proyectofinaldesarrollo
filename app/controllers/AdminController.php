<?php

namespace app\controllers;

use \Controller;
use \Response;

class AdminController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::soloAdministradores();
        Response::render($this->viewDir(__NAMESPACE__), 'index', ['usuario' => $_SESSION['usuario']]);
    }
}
