<?php
// Fixture exclusivamente en memoria. No carga .env ni la clase DataBase de producción.
// Ejecutar: /opt/lampp/bin/php tests/compra_producto_existente.php
require dirname(__DIR__) . '/app/core/Model.php';
require dirname(__DIR__) . '/app/core/Controller.php';

class DataBase
{
    private static $pdo;
    public static $escrituras = [];
    public static $consultas = [];
    public static $probarRecepcion = false;
    public static function connection()
    {
        return self::$pdo ?? (self::$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
    }
    private static function sentencia($sql, $parametros)
    {
        self::$consultas[] = $sql;
        // SQLite permite verificar datos/consultas preparadas, pero no los bloqueos concurrentes de InnoDB.
        $sql = str_replace(' FOR UPDATE', '', $sql);
        $sql = str_replace('ON DUPLICATE KEY UPDATE estado = 1, fecha_baja = NULL',
            'ON CONFLICT(id_producto, id_proveedor) DO UPDATE SET estado = 1, fecha_baja = NULL', $sql);
        $sentencia = self::connection()->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia;
    }
    public static function getRecord($sql, $parametros = []) { return self::sentencia($sql, $parametros)->fetch(PDO::FETCH_OBJ); }
    public static function query($sql, $parametros = []) { return self::sentencia($sql, $parametros)->fetchAll(PDO::FETCH_OBJ); }
    public static function execute($sql, $parametros = [])
    {
        $esRecepcion = self::$probarRecepcion && preg_match('/^(INSERT INTO (recepciones|detalle_recepciones|movimientos_stock)\b|UPDATE productos SET stock_actual\b)/', $sql);
        if (!$esRecepcion && !preg_match('/^(INSERT INTO (productos|productos_proveedores|compras|detalle_compras)\b|UPDATE compras\b)/', $sql)) {
            throw new RuntimeException('Escritura inesperada: ' . $sql);
        }
        self::$escrituras[] = $sql;
        return self::sentencia($sql, $parametros)->rowCount();
    }
    public static function insert($sql, $parametros = []) { self::execute($sql, $parametros); return self::connection()->lastInsertId(); }
    public static function iniciarTransaccion() { self::connection()->beginTransaction(); }
    public static function confirmarTransaccion() { self::connection()->commit(); }
    public static function cancelarTransaccion() { if (self::connection()->inTransaction()) self::connection()->rollBack(); }
}

foreach (['ProductoModel', 'ProveedorModel', 'CompraModel', 'UserModel', 'RecepcionModel'] as $modelo) require dirname(__DIR__) . '/app/models/' . $modelo . '.php';
foreach (['SessionController', 'CompraController', 'PosController'] as $controlador) require dirname(__DIR__) . '/app/controllers/' . $controlador . '.php';

DataBase::connection()->exec(<<<'SQL'
PRAGMA foreign_keys = ON;
CREATE TABLE categorias (id_categoria INTEGER PRIMARY KEY, nombre_categoria TEXT, estado INTEGER);
CREATE TABLE proveedores (id_proveedor INTEGER PRIMARY KEY, razon_social TEXT, estado INTEGER);
CREATE TABLE usuarios (id_usuario INTEGER PRIMARY KEY, nombre TEXT, apellido TEXT, email TEXT, password TEXT, estado INTEGER, debe_cambiar_password INTEGER);
CREATE TABLE productos (id_producto INTEGER PRIMARY KEY AUTOINCREMENT, nombre_producto TEXT, codigo_barras TEXT UNIQUE NOT NULL, id_categoria INTEGER REFERENCES categorias, descripcion TEXT, precio_venta TEXT, stock_actual INTEGER, stock_minimo INTEGER, estado INTEGER DEFAULT 1, fecha_baja TEXT, UNIQUE(nombre_producto, id_categoria));
CREATE TABLE productos_proveedores (id_producto_proveedor INTEGER PRIMARY KEY AUTOINCREMENT, id_producto INTEGER REFERENCES productos, id_proveedor INTEGER REFERENCES proveedores, estado INTEGER DEFAULT 1, fecha_baja TEXT, UNIQUE(id_producto, id_proveedor));
CREATE TABLE compras (id_compra INTEGER PRIMARY KEY AUTOINCREMENT, id_proveedor INTEGER REFERENCES proveedores, id_usuario INTEGER REFERENCES usuarios, numero_comprobante TEXT, observacion TEXT, estado_recepcion TEXT, total TEXT, estado INTEGER DEFAULT 1);
CREATE TABLE detalle_compras (id_detalle_compra INTEGER PRIMARY KEY AUTOINCREMENT, id_compra INTEGER REFERENCES compras, id_producto INTEGER REFERENCES productos, cantidad INTEGER, precio_unitario TEXT, subtotal TEXT);
CREATE TABLE recepciones (id_recepcion INTEGER PRIMARY KEY AUTOINCREMENT, id_compra INTEGER UNIQUE REFERENCES compras, id_proveedor INTEGER REFERENCES proveedores, id_usuario INTEGER REFERENCES usuarios, estado TEXT, observacion TEXT, motivo_rechazo TEXT);
CREATE TABLE detalle_recepciones (id_detalle_recepcion INTEGER PRIMARY KEY AUTOINCREMENT, id_recepcion INTEGER REFERENCES recepciones, id_producto INTEGER REFERENCES productos, cantidad_esperada INTEGER, cantidad_recibida INTEGER);
CREATE TABLE movimientos_stock (id_movimiento INTEGER PRIMARY KEY AUTOINCREMENT, id_producto INTEGER REFERENCES productos, id_usuario INTEGER REFERENCES usuarios, tipo_movimiento TEXT, cantidad INTEGER, stock_anterior INTEGER, stock_nuevo INTEGER, observacion TEXT);
INSERT INTO categorias VALUES (1, 'Vinos', 1);
INSERT INTO proveedores VALUES (1, 'Proveedor A', 1), (2, 'Proveedor B', 1), (3, 'Inactivo', 0);
INSERT INTO usuarios VALUES (1, 'Fixture', 'Local', 'fixture@local.test', 'hash-fixture', 1, 0);
INSERT INTO productos VALUES (1, 'loco un poco', '0000000000001', 1, 'Original', '8500.00', 14, 2, 1, NULL), (2, 'Inactivo', '0000000000002', 1, '', '9000.00', 7, 1, 0, '2026-01-01');
INSERT INTO productos_proveedores (id_producto,id_proveedor) VALUES (1,1), (2,1);
SQL);
$_SESSION = ['usuario' => ['id_usuario' => 1, 'roles' => ['RECEPCIONISTA'] ], 'csrf_compra' => 'fixture-token'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$casos = [];
function comprobar($condicion, $mensaje) { global $casos; if (!$condicion) throw new RuntimeException($mensaje); $casos[] = $mensaje; }
function invocar($accion, $datos, $token = 'fixture-token')
{
    $_POST = array_merge(['csrf_compra' => $token], $datos);
    http_response_code(200);
    ob_start();
    (new \app\controllers\CompraController())->$accion();
    $body = ob_get_clean();
    return [http_response_code(), json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
function falla($accion)
{
    try { $accion(); } catch (DomainException $excepcion) { return true; }
    return false;
}

// Estas ramas ejecutan la autorización real, que responde con exit().
if (isset($argv[1])) {
    register_shutdown_function(static function () { echo '|HTTP:' . http_response_code(); });
    $_POST = ['csrf_compra' => 'fixture-token', 'id_proveedor' => '2', 'id_producto' => '1', 'codigo_barras' => '0000000000001'];
    if ($argv[1] === 'sin-sesion') $_SESSION = [];
    else $_SESSION['usuario']['roles'] = ['VENDEDOR'];
    (new \app\controllers\CompraController())->actionAsociarProductoRapido();
    exit;
}

$base = ['id_proveedor' => '2', 'codigo_barras' => '0000000000001'];
$productoAntes = DataBase::getRecord('SELECT * FROM productos WHERE id_producto = :id', ['id' => 1]);
[$estado, $datos] = invocar('actionConsultarProductoRapido', $base);
comprobar($estado === 200 && $datos['existe'] && !$datos['producto']['asociado'] && $datos['producto']['activo'], 'EAN existente se identifica antes de completar nombre/categoría/precio');
comprobar($datos['producto']['codigo_barras'] === '0000000000001' && !DataBase::$escrituras, 'Conserva ceros del EAN y consultar/cancelar no escribe');
[$estado, $datos] = invocar('actionCrearProductoRapido', $base);
comprobar($estado === 409 && $datos['existe'] && !DataBase::$escrituras, 'Alta de EAN existente ofrece asociación sin insertar producto');
[$estado, $datos] = invocar('actionAsociarProductoRapido', $base + ['id_producto' => '1']);
comprobar($estado === 200 && $datos['ok'] && !$datos['ya_asociado'] && $datos['producto']['id_producto'] === 1, 'Confirmar asocia producto existente al proveedor B');
comprobar(count(DataBase::$escrituras) === 1 && str_starts_with(DataBase::$escrituras[0], 'INSERT INTO productos_proveedores'), 'Solo escribe la relación: no producto, stock, movimiento ni recepción');
comprobar(DataBase::getRecord('SELECT COUNT(*) AS n FROM productos')->n === 2 && $productoAntes == DataBase::getRecord('SELECT * FROM productos WHERE id_producto = :id', ['id' => 1]), 'No duplica ni cambia nombre, EAN, categoría, precio o stock');
$escrituras = count(DataBase::$escrituras);
[$estado, $datos] = invocar('actionAsociarProductoRapido', $base + ['id_producto' => '1']);
comprobar($estado === 200 && $datos['ya_asociado'] && count(DataBase::$escrituras) === $escrituras, 'Repetir confirmación es idempotente y no vuelve a escribir');
comprobar(DataBase::getRecord('SELECT COUNT(*) AS n FROM productos_proveedores WHERE id_producto=1 AND id_proveedor=2')->n === 1, 'Hay exactamente una fila por producto/proveedor');
[$estado, $datos] = invocar('actionConsultarProductoRapido', $base);
comprobar($datos['producto']['asociado'], 'Producto ya asociado se informa como tal');
comprobar(count(array_filter(\app\models\CompraModel::obtenerProductosPorProveedor(), static function ($p) { return (int) $p->id_producto === 1 && (int) $p->id_proveedor === 2; })) === 1, 'El selector de Compra encuentra inmediatamente el producto');
comprobar(array_column(\app\models\ProductoModel::obtenerProveedoresProducto(1), 'razon_social') === ['Proveedor A', 'Proveedor B'], 'Consulta de proveedores existente devuelve A y B');

foreach ([['id_producto' => '2', 'id_proveedor' => '2', 'codigo_barras' => '0000000000002'], ['id_producto' => '1', 'id_proveedor' => '3', 'codigo_barras' => '0000000000001'], ['id_producto' => '99', 'id_proveedor' => '2', 'codigo_barras' => '0000000000099'], ['id_producto' => '1', 'id_proveedor' => '2', 'codigo_barras' => 'otro'], ['id_producto' => ['1'], 'id_proveedor' => '2', 'codigo_barras' => '0000000000001']] as $invalido) {
    [$estado, $datos] = invocar('actionAsociarProductoRapido', $invalido);
    comprobar($estado === 422 && !$datos['ok'], 'Backend rechaza producto/proveedor/EAN inválido: ' . json_encode($invalido));
}
comprobar((int) DataBase::getRecord('SELECT estado FROM productos WHERE id_producto=2')->estado === 0, 'Producto inactivo no se reactiva');
[$estado, $datos] = invocar('actionConsultarProductoRapido', ['id_proveedor'=>'2','codigo_barras'=>'0000000000002']);
comprobar(!$datos['producto']['activo'], 'Consulta identifica producto inactivo');
[$estado] = invocar('actionAsociarProductoRapido', $base + ['id_producto'=>'1'], 'incorrecto');
comprobar($estado === 403, 'Asociación exige CSRF válido');
$_SERVER['REQUEST_METHOD'] = 'GET';
[$estado] = invocar('actionAsociarProductoRapido', $base + ['id_producto'=>'1']);
comprobar($estado === 405, 'Asociación exige POST');
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach (['vendedor'=>'403', 'sin-sesion'=>'302'] as $modo=>$esperado) {
    $proceso = proc_open([PHP_BINARY, __FILE__, $modo], [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    $salida = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($proceso);
    comprobar(str_ends_with($salida, '|HTTP:'.$esperado) && $error === '', 'Autorización real: '.$modo.' no puede asociar');
}

// Relación dada de baja: se recupera la misma fila, no se duplica ni se reactiva el producto.
DataBase::connection()->exec("UPDATE productos_proveedores SET estado=0,fecha_baja='2026-01-01' WHERE id_producto=1 AND id_proveedor=2");
$idRelacion = DataBase::getRecord('SELECT id_producto_proveedor FROM productos_proveedores WHERE id_producto=1 AND id_proveedor=2')->id_producto_proveedor;
invocar('actionAsociarProductoRapido', $base + ['id_producto'=>'1']);
$recuperada = DataBase::getRecord('SELECT * FROM productos_proveedores WHERE id_producto=1 AND id_proveedor=2');
comprobar($recuperada->id_producto_proveedor === $idRelacion && (int) $recuperada->estado === 1 && $recuperada->fecha_baja === null, 'Recupera relación histórica respetando la restricción única');

[$estado, $datos] = invocar('actionCrearProductoRapido', ['id_proveedor'=>'2','codigo_barras'=>'0000000000003','nombre_producto'=>'Nuevo','id_categoria'=>'1','precio_venta'=>'500.00','stock_minimo'=>'0','descripcion'=>'']);
comprobar($estado === 201 && $datos['ok'], 'Alta rápida de EAN nuevo sigue funcionando');
comprobar((int) DataBase::getRecord('SELECT stock_actual FROM productos WHERE codigo_barras=:codigo',['codigo'=>'0000000000003'])->stock_actual === 0, 'Producto nuevo nace con stock cero');
$idCompra = \app\models\CompraModel::registrarCompra(['id_proveedor'=>'2','numero_comprobante'=>'Fixture','observacion'=>''], [['id_producto'=>'1','cantidad'=>'3','precio_unitario'=>'100.50']], 1);
$compra = DataBase::getRecord('SELECT * FROM compras WHERE id_compra=:id',['id'=>$idCompra]);
comprobar($compra->estado_recepcion === 'PENDIENTE' && $compra->total === '301.50', 'Guardar Compra real funciona con el producto asociado y calcula el total');
comprobar($productoAntes == DataBase::getRecord('SELECT * FROM productos WHERE id_producto=1'), 'Registrar compra no cambia stock ni datos del producto');
comprobar(!array_filter(DataBase::$escrituras, static function ($sql) { return preg_match('/\b(recepciones|movimientos_stock|UPDATE productos\b)/', $sql); }), 'Ninguna operación escribe stock, movimientos o recepciones');
comprobar(count(array_filter(DataBase::$consultas, static function ($sql) { return str_contains($sql,'FOR UPDATE'); })) > 0, 'Las lecturas de confirmación solicitan bloqueos (concurrencia InnoDB pendiente de prueba real)');

// La acción real compartida por Alt+Q/F9 utiliza la misma relación, sin modificarla.
$_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['ean' => '0000000000001'];
ob_start(); (new \app\controllers\PosController())->actionConsultarProducto();
$consultaRapida = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
comprobar($consultaRapida['ok'] && $consultaRapida['producto']['proveedores'] === ['Proveedor A', 'Proveedor B'], 'Endpoint real de F9/Alt+Q muestra ambos proveedores sin cambiar su implementación');

// El modelo de Recepción permanece intacto y es quien ingresa físicamente las unidades.
DataBase::$probarRecepcion = true;
\app\models\RecepcionModel::registrarDeCompra($idCompra, [1 => '3'], 'ACEPTADA', '', '', 1);
comprobar((int) DataBase::getRecord('SELECT stock_actual FROM productos WHERE id_producto=1')->stock_actual === 17
    && DataBase::getRecord('SELECT tipo_movimiento FROM movimientos_stock')->tipo_movimiento === 'RECEPCION', 'Solo aceptar la Recepción suma las tres unidades y registra el movimiento');
comprobar(falla(static function () use ($idCompra) { \app\models\RecepcionModel::registrarDeCompra($idCompra, [1 => '3'], 'ACEPTADA', '', '', 1); })
    && DataBase::getRecord('SELECT COUNT(*) AS n FROM movimientos_stock')->n === 1, 'Recepción conserva la protección contra ingreso duplicado');
echo count($casos), " comprobaciones OK, con PDO SQLite en memoria. Sin .env, base real ni entornos del profesor.\n";
