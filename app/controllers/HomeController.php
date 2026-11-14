<?php

namespace app\controllers;

use \Controller;

// Controla las operaciones del inicio.
class HomeController extends Controller
{
    // Redirige la URL de inicio al formulario de login.
    public function actionIndex($parametro = null)
    {
        // Redirige al navegador a session/login.
        header('Location: /proyectofinaldesarrollo/session/login');
        exit;
    }
}
