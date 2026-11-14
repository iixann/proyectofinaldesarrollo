<?php
// Renderiza vistas reales con datos ficticios, sin cargar .env ni conectar la base.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo terminal'); }
chdir(dirname(__DIR__));
define('APP_PATH', 'app/');
require APP_PATH . 'core/Controller.php';
require APP_PATH . 'controllers/SessionController.php';
$_SESSION = ['usuario' => ['id_usuario' => 1, 'roles' => ['ADMINISTRADOR'], 'nombre' => 'Prueba', 'apellido' => 'Visual']];
$rutas = ['producto' => 'producto/crear', 'compra' => 'compra/crear', 'directa' => 'recepcion/directa',
    'recepcion' => 'recepcion/compra', 'stock' => 'stock/index', 'reposicion' => 'reposicion/index'];
if (!isset($rutas[$argv[1] ?? ''])) exit(1);
$errores = $datos = $datosProducto = $cantidades = $renglones = [];
$mensaje = $mensajeEscaneo = '';
$filtros = ['nombre' => '', 'codigo_barras' => '', 'id_categoria' => '', 'id_proveedor' => ''];
$categorias = [(object) ['id_categoria' => 1, 'nombre_categoria' => 'Vinos']];
$proveedores = [(object) ['id_proveedor' => 1, 'razon_social' => 'Proveedor de prueba']];
$productos = [(object) ['id_producto' => 1, 'id_proveedor' => 1, 'nombre_producto' => 'Malbec de prueba',
    'codigo_barras' => '4006381333931', 'nombre_categoria' => 'Vinos', 'stock_actual' => 2, 'stock_minimo' => 5, 'proveedores' => 'Proveedor de prueba']];
$compra = (object) ['id_compra' => 1, 'razon_social' => 'Proveedor de prueba', 'fecha_compra' => '2026-11-14'];
$detalles = [(object) ['id_producto' => 1, 'nombre_producto' => 'Malbec de prueba', 'codigo_barras' => '4006381333931', 'cantidad' => 4]];
$tokenCompra = $tokenRecepcion = 'token-visual-sin-base';
$esRecepcionDirecta = ($argv[1] === 'directa');
// La consulta exacta permite revisar también la tarjeta móvil y el resaltado.
if (in_array($argv[1], ['stock', 'reposicion'], true)) $filtros['codigo_barras'] = '4006381333931';
require APP_PATH . 'views/' . $rutas[$argv[1]] . '.php';
