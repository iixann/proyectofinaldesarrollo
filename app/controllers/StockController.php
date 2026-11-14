<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\StockModel;
use app\models\CategoriaModel;
use app\models\ProveedorModel;

// Controla las operaciones de stock y sus movimientos.
class StockController extends Controller
{
    // Muestra el listado de stock y sus movimientos con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $filtros = [
            'nombre' => $this->leerFiltro('nombre'),
            'codigo_barras' => $this->leerFiltro('codigo_barras', 50),
            'id_categoria' => $this->leerFiltro('id_categoria', 10, true),
            'id_proveedor' => $this->leerFiltro('id_proveedor', 10, true)
        ];
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'productos' => StockModel::listarProductosActivos($filtros),
            'filtros' => $filtros,
            'categorias' => CategoriaModel::listarCategorias(),
            'proveedores' => ProveedorModel::listarProveedores(),
            'mensaje' => ($_GET['resultado'] ?? '') === 'registrado' ? 'Movimiento registrado correctamente.' : ''
        ]);
    }

    // Muestra el formulario de ajuste de stock del producto.
    public function actionAjustar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $producto = $this->buscarProducto($id, true);
        $this->mostrarFormulario($producto);
    }

    // Valida el ajuste por POST y pide al modelo registrar el movimiento.
    public function actionGuardarAjuste($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido';
            return;
        }
        $producto = $this->buscarProducto($id, true);
        $datos = [];
        foreach (['tipo', 'cantidad', 'observacion'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
        }
        $errores = [];
        // Solo permite ajustes de entrada o salida de stock.
        if (!in_array($datos['tipo'], ['AJUSTE_ENTRADA', 'AJUSTE_SALIDA'], true)) {
            $errores[] = 'Seleccioná un tipo de movimiento válido.';
        }
        if (!ctype_digit($datos['cantidad']) || filter_var($datos['cantidad'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            $errores[] = 'La cantidad debe ser un entero mayor a cero, hasta 2147483647.';
        }
        if (mb_strlen($datos['observacion'], 'UTF-8') > 255) {
            $errores[] = 'La observación debe tener hasta 255 caracteres.';
        }
        // Si hay errores, los devuelve para corregir el formulario.
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($producto, $datos, $errores);
            return;
        }
        try {
            // Según el tipo de ajuste, pide al modelo aumentar o disminuir stock.
            if ($datos['tipo'] === 'AJUSTE_ENTRADA') {
                // Pasa a StockModel: registra un ajuste que aumenta el stock del producto.
                StockModel::registrarEntrada($producto->id_producto, $_SESSION['usuario']['id_usuario'], $datos['cantidad'], $datos['observacion']);
            } else {
                // Pasa a StockModel: registra un ajuste que disminuye el stock del producto.
                StockModel::registrarSalida($producto->id_producto, $_SESSION['usuario']['id_usuario'], $datos['cantidad'], $datos['observacion']);
            }
        } catch (\DomainException $excepcion) {
            http_response_code(422);
            $this->mostrarFormulario(StockModel::findId($id) ?: $producto, $datos, [$excepcion->getMessage()]);
            return;
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            http_response_code(500);
            $this->mostrarFormulario($producto, $datos, ['No se pudo registrar el movimiento. Intentá nuevamente.']);
            return;
        }
        // Redirige al navegador a stock/index.
        header('Location: /proyectofinaldesarrollo/stock/index?resultado=registrado', true, 303);
        exit;
    }

    // Muestra los movimientos de stock generales o de un producto.
    public function actionMovimientos($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['RECEPCIONISTA']);
        $producto = $id === null ? null : $this->buscarProducto($id);
        // Envía los datos a la vista movimientos.
        Response::render($this->viewDir(__NAMESPACE__), 'movimientos', [
            'producto' => $producto,
            'movimientos' => StockModel::listarMovimientos($producto ? $producto->id_producto : null)
        ]);
    }

    // Valida el ID y busca el registro; detiene la solicitud si no existe.
    private function buscarProducto($id, $exigirActivo = false)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de producto inválido.';
            exit;
        }
        // Pasa a StockModel para buscar el registro por su ID.
        $producto = StockModel::findId($id);
        // Si no encuentra el producto, detiene la solicitud.
        if (!$producto) {
            http_response_code(404);
            echo 'El producto no existe.';
            exit;
        }
        // Impide modificar un registro que está inactivo.
        if ($exigirActivo && (int) $producto->estado !== 1) {
            http_response_code(422);
            echo 'El producto está inactivo.';
            exit;
        }
        return $producto;
    }

    // Carga la vista del formulario con los datos y errores del registro.
    private function mostrarFormulario($producto, $datos = [], $errores = [])
    {
        // Envía los datos a la vista ajustar.
        Response::render($this->viewDir(__NAMESPACE__), 'ajustar', [
            'producto' => $producto, 'datos' => $datos, 'errores' => $errores
        ]);
    }
}
