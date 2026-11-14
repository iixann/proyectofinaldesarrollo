<?php

namespace app\controllers;

use \Controller;
use \Response;
use app\models\ProductoModel;
use app\models\CajaSesionModel;
use app\models\VentaModel;

// Controla las operaciones de punto de venta.
class PosController extends Controller
{
    // Carga el POS con las sesiones propias y los medios de pago.
    public function actionIndex($parametro = null)
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        // Pasa a CajaSesionModel para obtener las sesiones abiertas del vendedor.
        $sesiones = CajaSesionModel::sesionesDisponiblesPos($_SESSION['usuario']['id_usuario']);
        $seleccionada = null;
        $mensaje = '';
        // Si la URL indica una sesión, comprueba que esté entre las disponibles.
        if (array_key_exists('sesion', $_GET)) {
            $id = $_GET['sesion'];
            // Comprueba que el ID recibido sea válido antes de continuar.
            if ($this->idSesionValido($id)) {
                foreach ($sesiones as $sesion) {
                    if ((string) $sesion->id_caja_sesion === (string) (int) $id) {
                        $seleccionada = $sesion;
                        break;
                    }
                }
            }
            // Si no puede usar la sesión solicitada, pide elegir otra.
            if (!$seleccionada) {
                $mensaje = 'La sesión seleccionada no está disponible. Elegí una sesión abierta.';
            }
        } elseif (count($sesiones) === 1) {
            $seleccionada = $sesiones[0];
        }
        // Crea el token del formulario si todavía no existe en la sesión.
        if (empty($_SESSION['csrf_venta'])) {
            $_SESSION['csrf_venta'] = bin2hex(random_bytes(32));
        }
        // Envía los datos a la vista index.
        Response::render($this->viewDir(__NAMESPACE__), 'index', [
            'sesiones' => $sesiones, 'seleccionada' => $seleccionada, 'mensaje' => $mensaje,
            'mediosPago' => VentaModel::mediosActivos(), 'tokenVenta' => $_SESSION['csrf_venta']
        ]);
    }

    // Busca productos por GET para agregarlos al carrito del POS.
    public function actionBuscar()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR']);
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            header('Allow: GET');
            $this->json(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
            return;
        }
        $idSesion = $_GET['sesion'] ?? null;
        // Comprueba que el ID recibido sea válido antes de continuar.
        if (!$this->idSesionValido($idSesion)) {
            $this->json(['ok' => false, 'mensaje' => 'Seleccioná una sesión de caja abierta.', 'sesion_no_disponible' => true], 409);
            return;
        }
        $porCodigo = array_key_exists('codigo', $_GET);
        $porNombre = array_key_exists('nombre', $_GET);
        // Exige elegir una sola búsqueda: por código o por nombre.
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
            // Comprueba en el modelo que la sesión de caja siga disponible.
            if (!CajaSesionModel::sesionDisponiblePos($idSesion, $_SESSION['usuario']['id_usuario'])) {
                $this->json(['ok' => false, 'mensaje' => 'La sesión no es propia, ya no está abierta o la caja está inactiva. Volvé a sesiones de caja.', 'sesion_no_disponible' => true], 409);
                return;
            }
            // Por código busca un producto; por nombre devuelve coincidencias.
            if ($porCodigo) {
                // Pasa a ProductoModel para buscar el producto activo por EAN.
                $producto = ProductoModel::buscarActivoPorCodigo($entrada);
                $this->json($producto
                    ? ['ok' => true, 'producto' => $this->datosProducto($producto)]
                    : ['ok' => false, 'mensaje' => 'Producto no encontrado.'], $producto ? 200 : 404);
            } else {
                // Pasa a ProductoModel para buscar los productos activos por nombre.
                $productos = ProductoModel::buscarActivosPorNombre($entrada);
                $this->json(['ok' => true, 'productos' => array_map([$this, 'datosProducto'], $productos)]);
            }
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No se pudo consultar los productos. Intentá nuevamente.'], 500);
        }
    }

    // Devuelve por GET el producto y sus proveedores para la consulta rápida.
    public function actionConsultarProducto()
    {
        // Solo permite entrar a los roles autorizados.
        SessionController::onlyUsers(['VENDEDOR', 'RECEPCIONISTA']);
        // Rechaza la solicitud si usa un método HTTP distinto del permitido.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            header('Allow: GET');
            $this->json(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
            return;
        }
        $ean = $_GET['ean'] ?? null;
        if (!is_string($ean) || !mb_check_encoding($ean, 'UTF-8')
            || trim($ean) === '' || mb_strlen(trim($ean), 'UTF-8') > 50) {
            $this->json(['ok' => false, 'mensaje' => 'Ingresá un EAN de entre 1 y 50 caracteres.'], 400);
            return;
        }
        try {
            // Pasa a ProductoModel para buscar el producto activo por EAN.
            $producto = ProductoModel::buscarActivoPorCodigo(trim($ean));
            // Si no encuentra el producto, detiene la solicitud.
            if (!$producto) {
                $this->json(['ok' => false, 'mensaje' => 'Producto no encontrado'], 404);
                return;
            }
            $datos = $this->datosProducto($producto);
            // Convierte los proveedores del modelo a nombres para la consulta rápida.
            $datos['proveedores'] = array_map(static function ($proveedor) {
                return $proveedor->razon_social;
            }, ProductoModel::obtenerProveedoresProducto($producto->id_producto));
            $this->json(['ok' => true, 'producto' => $datos]);
        } catch (\Throwable $excepcion) {
            error_log($excepcion->getMessage());
            $this->json(['ok' => false, 'mensaje' => 'No se pudo consultar el producto. Intentá nuevamente.'], 500);
        }
    }

    // Comprueba el formato y rango del ID de sesión de caja.
    private function idSesionValido($id)
    {
        return is_string($id) && ctype_digit($id) && (float) $id >= 1 && (float) $id <= 4294967295;
    }

    // Prepara los datos del producto que recibirá JavaScript.
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

    // Devuelve los datos como JSON con el estado HTTP indicado.
    private function json($datos, $estado = 200)
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
