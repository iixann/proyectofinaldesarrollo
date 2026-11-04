<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ProductoModel;
use app\models\CategoriaModel;
use app\models\ProveedorModel;

class ProductoController extends Controller
{
    public function actionIndex($parametro = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $mensajes = [
            'creado' => 'Producto creado correctamente.',
            'actualizado' => 'Producto actualizado correctamente.',
            'reactivada' => 'Producto reactivado correctamente.',
            'baja' => 'Producto dado de baja.'
        ];
        $filtros = ['nombre' => $this->leerFiltro('nombre'),
            'id_categoria' => $this->leerFiltro('id_categoria', 10, true),
            'id_proveedor' => $this->leerFiltro('id_proveedor', 10, true)];
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'productos' => ProductoModel::listarProductos($filtros),
            'filtros' => $filtros, 'categorias' => CategoriaModel::listarCategorias(),
            'proveedores' => ProveedorModel::listarProveedores(),
            'mensaje' => $mensajes[$resultado] ?? ''
        ]);
    }

    public function actionCrear()
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->mostrarFormulario('crear');
    }

    public function actionGuardar()
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $this->guardar();
    }

    public function actionEditar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $producto = $this->buscarProducto($id);
        $datosProducto = (array) $producto;
        $asociados = ProductoModel::obtenerProveedoresProducto($producto->id_producto);
        $datosProducto['proveedores'] = array_map('strval', array_column($asociados, 'id_proveedor'));
        $this->mostrarFormulario('editar', $datosProducto, [], $producto->id_producto);
    }

    public function actionActualizar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $producto = $this->buscarProducto($id);
        $this->guardar($producto->id_producto);
    }

    public function actionBaja($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $producto = $this->buscarProducto($id);
        ProductoModel::bajaProducto($producto->id_producto);
        $this->volverAlListado('baja');
    }

    public function actionReactivar($id = null)
    {
        SessionController::onlyUsers(['DUEÑO']);
        $this->exigirPost();
        $producto = $this->buscarProducto($id);
        ProductoModel::reactivar($producto->id_producto);
        $this->volverAlListado('reactivada');
    }

    private function exigirPost()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido';
            exit;
        }
    }

    private function buscarProducto($id)
    {
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de producto inválido.';
            exit;
        }
        $producto = ProductoModel::findId($id);
        if (!$producto) {
            http_response_code(404);
            echo 'El producto no existe.';
            exit;
        }
        return $producto;
    }

    private function mostrarFormulario($vista, $datosProducto = [], $errores = [], $id = null)
    {
        if ($id !== null) {
            $producto = ProductoModel::findId($id);
            $datosProducto['stock_actual'] = $producto ? $producto->stock_actual : 0;
        }
        Response::render($this->viewDir(__NAMESPACE__), $vista, [
            'datosProducto' => $datosProducto, 'errores' => $errores, 'id' => $id,
            'categorias' => ProductoModel::obtenerCategoriasActivas(),
            'proveedores' => ProductoModel::obtenerProveedoresActivos()
        ]);
    }

    private function guardar($id = null)
    {
        [$datosProducto, $errores] = ProductoModel::validarDatos($_POST, $id);
        $seleccionados = $_POST['proveedores'] ?? [];
        $proveedores = [];
        $activos = array_map('strval', array_column(ProductoModel::obtenerProveedoresActivos(), 'id_proveedor'));
        if (!is_array($seleccionados)) {
            $errores[] = 'Seleccioná proveedores activos válidos.';
        } else {
            foreach ($seleccionados as $idProveedor) {
                if (!is_string($idProveedor) || !ctype_digit($idProveedor) || !in_array($idProveedor, $activos, true)) {
                    $errores[] = 'Seleccioná proveedores activos válidos.';
                    continue;
                }
                $proveedores[] = $idProveedor;
            }
        }
        $proveedores = array_values(array_unique($proveedores));
        $datosProducto['proveedores'] = $proveedores;
        $vista = $id === null ? 'crear' : 'editar';
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($vista, $datosProducto, $errores, $id);
            return;
        }
        unset($datosProducto['proveedores']);
        try {
            if ($id === null) {
                ProductoModel::crearProducto($datosProducto, $proveedores);
            } else {
                ProductoModel::actualizarProducto($id, $datosProducto, $proveedores);
            }
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            // El índice UNIQUE también protege ante dos envíos simultáneos.
            $codigoDuplicado = (string) $excepcion->getCode() === '23000'
                && ProductoModel::codigoDuplicado($datosProducto['codigo_barras'], $id);
            $duplicado = (string) $excepcion->getCode() === '23000'
                && ProductoModel::nombreDuplicado($datosProducto['nombre_producto'], $datosProducto['id_categoria'], $id);
            http_response_code($codigoDuplicado || $duplicado ? 422 : 500);
            $datosProducto['proveedores'] = $proveedores;
            $this->mostrarFormulario($vista, $datosProducto, [
                $codigoDuplicado ? 'Ya existe un producto con ese Código EAN.' : ($duplicado ? 'Ya existe ese producto en la categoría seleccionada.' : 'No se pudo guardar el producto. Intentá nuevamente.')
            ], $id);
            return;
        }
        $this->volverAlListado($id === null ? 'creado' : 'actualizado');
    }

    private function volverAlListado($resultado)
    {
        header('Location: /proyectofinaldesarrollo/producto/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
