<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ReposicionModel;

class ReposicionController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        Response::render($this->viewDir(__NAMESPACE__), 'index', ['productos' => ReposicionModel::listarProductos()]);
    }
}
