<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ReposicionModel;
use app\models\CategoriaModel;
use app\models\ProveedorModel;

class ReposicionController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $filtros = ['nombre' => $this->leerFiltro('nombre'), 'codigo_barras' => $this->leerFiltro('codigo_barras', 50),
            'id_categoria' => $this->leerFiltro('id_categoria', 10, true), 'id_proveedor' => $this->leerFiltro('id_proveedor', 10, true)];
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'productos' => ReposicionModel::listarProductos($filtros), 'filtros' => $filtros,
            'categorias' => CategoriaModel::listarCategorias(), 'proveedores' => ProveedorModel::listarProveedores()
        ]);
    }
}
