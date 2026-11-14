<?php

namespace app\controllers;

use \Controller;
use \Response;

// Controla las operaciones de administración.
class AdminController extends Controller
{
    // Muestra el panel de módulos para el dueño o administrador.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO']);
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', ['usuario' => $_SESSION['usuario']]);
    }
}
