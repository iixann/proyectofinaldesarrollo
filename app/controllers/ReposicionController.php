<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ReposicionModel;
use app\models\CategoriaModel;
use app\models\ProveedorModel;
use app\models\StockModel;

// Controla las operaciones de reposición.
class ReposicionController extends Controller
{
    // Muestra los productos que necesitan reposición según los filtros.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $filtros = ['nombre' => $this->leerFiltro('nombre'), 'codigo_barras' => $this->leerFiltro('codigo_barras', 50),
            'id_categoria' => $this->leerFiltro('id_categoria', 10, true), 'id_proveedor' => $this->leerFiltro('id_proveedor', 10, true)];
        // Pide al modelo los productos que realmente necesitan reposición.
        $productos = ReposicionModel::listarProductos($filtros);
        $mensajeEscaneo = '';
        // Ante una lectura sin resultados, distingue stock suficiente de un EAN desconocido.
        if (($_GET['escaneado'] ?? '') === '1' && $filtros['codigo_barras'] !== '' && !$productos) {
            // Reutiliza la consulta exacta de Stock, con los mismos permisos de recepción.
            $encontrados = StockModel::listarProductosActivos(['codigo_barras' => $filtros['codigo_barras']]);
            if (!$encontrados) {
                $mensajeEscaneo = 'No se encontró un producto activo con ese EAN.';
            } elseif ((int) $encontrados[0]->stock_actual > (int) $encontrados[0]->stock_minimo) {
                $mensajeEscaneo = 'El producto no requiere reposición actualmente.';
            } else {
                $mensajeEscaneo = 'El producto requiere reposición, pero no coincide con los otros filtros. Limpiá los filtros para localizarlo.';
            }
        }
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'productos' => $productos, 'filtros' => $filtros, 'mensajeEscaneo' => $mensajeEscaneo,
            'categorias' => CategoriaModel::listarCategorias(), 'proveedores' => ProveedorModel::listarProveedores()
        ]);
    }
}
