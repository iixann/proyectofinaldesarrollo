<?php
// Prueba el controlador real con consultas simuladas que nunca escriben datos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo terminal'); }
require dirname(__DIR__) . '/app/core/Controller.php';
require dirname(__DIR__) . '/app/core/Model.php';

// Sustituye únicamente la base para capturar filtros y lecturas del controlador.
class DataBase
{
    public static $consultas = [];
    public static $producto;

    // Devuelve listas ficticias y rechaza cualquier SQL de escritura.
    public static function query($sql, $parametros = [])
    {
        if (!str_starts_with($sql, 'SELECT ')) throw new RuntimeException('Escritura inesperada');
        self::$consultas[] = [$sql, $parametros];
        if (!str_contains($sql, 'FROM productos p')) return [];
        if (($parametros['codigo'] ?? '') !== '0000000000001') return [];
        if (str_contains($sql, 'p.stock_actual <= p.stock_minimo') && self::$producto->stock_actual > self::$producto->stock_minimo) return [];
        return [self::$producto];
    }

    // Comparte la consulta simulada con los métodos de listado existentes.
    public static function getRecords($sql, $parametros = []) { return ['status' => true, 'resultado' => self::query($sql, $parametros)]; }
}

// Captura los datos de la vista para verificar el mensaje sin renderizar HTML.
class Response
{
    public static $variables;

    // Recibe lo que el controlador enviaría a la pantalla de reposición.
    public static function render($directorio, $vista, $variables = []) { self::$variables = $variables; }
}

// Mantiene la exigencia de rol y registra que el controlador la invoque.
class SesionEscanerFixture
{
    public static $roles;

    // Simula solo el control de entrada para aislar la consulta sin sesiones reales.
    public static function onlyUsers($roles) { self::$roles = $roles; }
}
class_alias(SesionEscanerFixture::class, 'app\\controllers\\SessionController');
foreach (['Reposicion', 'Stock', 'Categoria', 'Proveedor'] as $nombre) require dirname(__DIR__) . '/app/models/' . $nombre . 'Model.php';
require dirname(__DIR__) . '/app/controllers/ReposicionController.php';
DataBase::$producto = (object) ['stock_actual' => 2, 'stock_minimo' => 5];
$_GET = ['escaneado' => '1', 'codigo_barras' => '0000000000001'];
$controlador = new \app\controllers\ReposicionController();
$casos = 0;
// Detiene la prueba ante un resultado distinto del esperado.
function comprobarEan($valor) { global $casos; if (!$valor) throw new RuntimeException('Falló la comprobación ' . ($casos + 1)); ++$casos; }
$controlador->actionIndex();
comprobarEan(count(Response::$variables['productos']) === 1);
comprobarEan(Response::$variables['mensajeEscaneo'] === '');
comprobarEan(SesionEscanerFixture::$roles === ['RECEPCIONISTA']);
DataBase::$producto->stock_actual = 8;
$controlador->actionIndex();
comprobarEan(Response::$variables['productos'] === []);
comprobarEan(Response::$variables['mensajeEscaneo'] === 'El producto no requiere reposición actualmente.');
comprobarEan((bool) array_filter(DataBase::$consultas, static fn($consulta) => ($consulta[1]['codigo'] ?? null) === '0000000000001'));
$_GET['codigo_barras'] = '9999999999999';
$controlador->actionIndex();
comprobarEan(str_contains(Response::$variables['mensajeEscaneo'], 'No se encontró'));
$_GET['codigo_barras'] = '0000000000001';
$_GET['escaneado'] = '0';
$controlador->actionIndex();
comprobarEan(Response::$variables['mensajeEscaneo'] === '');
echo $casos . " comprobaciones OK; solo SELECT simulados, EAN conservado como string.\n";
