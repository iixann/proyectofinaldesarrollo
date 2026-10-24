<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ProductoModel;

class PosController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['VENDEDOR']);
        Response::render($this->viewDir(__NAMESPACE__), 'index');
    }

    public function actionBuscar()
    {
        SessionController::onlyUsers(['VENDEDOR']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            header('Allow: GET');
            $this->json(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
            return;
        }
        $porCodigo = array_key_exists('codigo', $_GET);
        $porNombre = array_key_exists('nombre', $_GET);
        if ($porCodigo === $porNombre) {
            $this->json(['ok' => false, 'mensaje' => 'Indicá solamente código o nombre para buscar.'], 400);
            return;
        }
        $entrada = $_GET[$porCodigo ? 'codigo' : 'nombre'];
        $limite = $porCodigo ? 50 : 150;
        if (!is_string($entrada) || !mb_check_encoding($entrada, 'UTF-8')) {
            $this->json(['ok' => false, 'mensaje' => 'La búsqueda debe ser un texto válido.'], 400);
            return;
        }
        $entrada = trim($entrada);
        if ($entrada === '' || mb_strlen($entrada, 'UTF-8') > $limite) {
            $this->json(['ok' => false, 'mensaje' => 'Ingresá entre 1 y ' . $limite . ' caracteres.'], 400);
            return;
        }
        try {
            if ($porCodigo) {
                $producto = ProductoModel::buscarActivoPorCodigo($entrada);
                $this->json($producto
                    ? ['ok' => true, 'producto' => $this->datosProducto($producto)]
                    : ['ok' => false, 'mensaje' => 'Producto no encontrado.'], $producto ? 200 : 404);
            } else {
                $productos = ProductoModel::buscarActivosPorNombre($entrada);
                $this->json(['ok' => true, 'productos' => array_map([$this, 'datosProducto'], $productos)]);
            }
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No se pudo consultar los productos. Intentá nuevamente.'], 500);
        }
    }

    private function datosProducto($producto)
    {
        return [
            'id_producto' => (int) $producto->id_producto,
            'codigo_barras' => $producto->codigo_barras === null ? null : (string) $producto->codigo_barras,
            'nombre_producto' => $producto->nombre_producto,
            'precio_venta' => (string) $producto->precio_venta,
            'stock_actual' => (int) $producto->stock_actual,
            'categoria' => $producto->categoria,
            'estado_stock' => (int) $producto->stock_actual <= 0 ? 'SIN STOCK' : 'DISPONIBLE'
        ];
    }

    private function json($datos, $estado = 200)
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
