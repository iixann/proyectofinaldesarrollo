<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ProductoModel;
use app\models\CategoriaModel;
use app\models\ProveedorModel;

// Controla las operaciones de productos.
class ProductoController extends Controller
{
    // Muestra el listado de productos con los datos del modelo.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA', 'VENDEDOR']);
        $mensajes = [
            'creado' => 'Producto creado correctamente.',
            'actualizado' => 'Producto actualizado correctamente.',
            'reactivada' => 'Producto reactivado correctamente.',
            'baja' => 'Producto dado de baja.',
            'registrado' => 'Movimiento registrado correctamente.'
        ];
        $filtros = ['nombre' => $this->leerFiltro('nombre'), 'codigo_barras' => $this->leerFiltro('codigo_barras', 50),
            'id_categoria' => $this->leerFiltro('id_categoria', 10, true),
            'id_proveedor' => $this->leerFiltro('id_proveedor', 10, true)];
        // El vendedor consulta; recepción también puede mantener el catálogo.
        $puedeGestionar = SessionController::esAdministrador() || (bool) array_intersect(['DUEÑO', 'RECEPCIONISTA'], $_SESSION['usuario']['roles']);
        $filtros['solo_activos'] = !$puedeGestionar;
        $resultado = is_string($_GET['resultado'] ?? null) ? $_GET['resultado'] : '';
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'productos' => ProductoModel::listarProductos($filtros), 'puedeGestionar' => $puedeGestionar,
            'filtros' => $filtros, 'categorias' => CategoriaModel::listarCategorias(),
            'proveedores' => ProveedorModel::listarProveedores(),
            'mensaje' => $mensajes[$resultado] ?? ''
        ]);
    }

    // Muestra el formulario para crear el producto.
    public function actionCrear()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA']);
        $this->mostrarFormulario('crear');
    }

    // Recibe el formulario por POST y solicita guardar el producto.
    public function actionGuardar()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA']);
        $this->exigirPost();
        $this->guardar();
    }

    // Busca el producto y muestra el formulario de edición.
    public function actionEditar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA']);
        $producto = $this->buscarProducto($id);
        $datosProducto = (array) $producto;
        // Pasa a ProductoModel: devuelve las asociaciones activas del producto con datos de proveedor.
        $asociados = ProductoModel::obtenerProveedoresProducto($producto->id_producto);
        $datosProducto['proveedores'] = array_map('strval', array_column($asociados, 'id_proveedor'));
        $this->mostrarFormulario('editar', $datosProducto, [], $producto->id_producto);
    }

    // Recibe por POST los cambios del producto y solicita guardarlos.
    public function actionActualizar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA']);
        $this->exigirPost();
        $producto = $this->buscarProducto($id);
        $this->guardar($producto->id_producto);
    }

    // Solicita la baja lógica del producto.
    public function actionBaja($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA']);
        $this->exigirPost();
        $producto = $this->buscarProducto($id);
        // Pasa a ProductoModel: da de baja sin eliminar el producto en la base de datos.
        ProductoModel::bajaProducto($producto->id_producto, $_SESSION['usuario']['id_usuario']);
        $this->volverAlListado('baja');
    }

    // Solicita reactivar el producto.
    public function actionReactivar($id = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA']);
        $this->exigirPost();
        $producto = $this->buscarProducto($id);
        // Pasa a ProductoModel: reactiva el producto en la base de datos.
        ProductoModel::reactivar($producto->id_producto, $_SESSION['usuario']['id_usuario']);
        $this->volverAlListado('reactivada');
    }

    // Muestra quién mantuvo el producto; movimientos conserva el historial físico.
    public function actionHistorial($id = null)
    {
        SessionController::onlyUsers(['DUEÑO', 'RECEPCIONISTA']);
        $producto = $this->buscarProducto($id);
        Response::render('producto/', 'historial', ['producto'=>$producto,
            'operaciones'=>\app\models\AuditoriaModel::listarProducto($producto->id_producto)]);
    }

    // Rechaza solicitudes que no usan POST.
    private function exigirPost()
    {
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Método no permitido';
            exit;
        }
    }

    // Valida el ID y busca el producto; detiene la solicitud si no existe.
    private function buscarProducto($id)
    {
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!is_string($id) || !ctype_digit($id) || (float) $id < 1 || (float) $id > 4294967295) {
            http_response_code(400);
            echo 'ID de producto inválido.';
            exit;
        }
        // Pasa a ProductoModel para buscar el registro por su ID.
        $producto = ProductoModel::findId($id);
        // Si no encuentra el producto, detiene la solicitud.
        if (!$producto) {
            http_response_code(404);
            echo 'El producto no existe.';
            exit;
        }
        return $producto;
    }

    // Carga la vista del formulario con los datos y errores del producto.
    private function mostrarFormulario($vista, $datosProducto = [], $errores = [], $id = null)
    {
        // Si está editando, recupera los datos actuales del registro.
        if ($id !== null) {
            // Pasa a ProductoModel para buscar el registro por su ID.
            $producto = ProductoModel::findId($id);
            $datosProducto['stock_actual'] = $producto ? $producto->stock_actual : 0;
        }
        // Envía los datos a la vista solicitada.
        Response::render($this->viewDir(__NAMESPACE__), $vista, [
            'datosProducto' => $datosProducto, 'errores' => $errores, 'id' => $id,
            'categorias' => ProductoModel::obtenerCategoriasActivas(),
            'proveedores' => ProductoModel::obtenerProveedoresActivos()
        ]);
    }

    // Valida los campos por POST y pasa el producto al modelo para guardar.
    private function guardar($id = null)
    {
        // Pasa a ProductoModel: valida los campos recibidos y devuelve los datos junto con los errores.
        [$datosProducto, $errores] = ProductoModel::validarDatos($_POST, $id);
        $seleccionados = $_POST['proveedores'] ?? [];
        $proveedores = [];
        // Pasa a ProductoModel: devuelve los proveedores habilitados para seleccionar.
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
        // Si hay errores, los devuelve para corregir el formulario.
        if ($errores) {
            http_response_code(422);
            $this->mostrarFormulario($vista, $datosProducto, $errores, $id);
            return;
        }
        unset($datosProducto['proveedores']);
        try {
            // Sin ID crea un registro; con ID actualiza el existente.
            if ($id === null) {
                // Pasa a ProductoModel: inserta el producto en la base de datos.
                ProductoModel::crearProducto($datosProducto, $proveedores, $_SESSION['usuario']['id_usuario']);
            } else {
                // Pasa a ProductoModel: actualiza el producto en la base de datos.
                ProductoModel::actualizarProducto($id, $datosProducto, $proveedores, $_SESSION['usuario']['id_usuario']);
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

    // Redirige al listado con el resultado de la operación.
    private function volverAlListado($resultado)
    {
        // Redirige al navegador a producto/index.
        header('Location: /proyectofinaldesarrollo/producto/index?resultado=' . $resultado, true, 303);
        exit;
    }
}
