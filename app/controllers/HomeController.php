<?php

namespace app\controllers;

use \Controller;

class HomeController extends Controller
{
    public function actionIndex($parametro = null)
    {
        header('Location: /proyectofinaldesarrollo/session/login');
        exit;
    }
}
