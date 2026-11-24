<?php
// Dibuja la vista original por rol con productos ficticios, sin conexión a la base.
namespace app\controllers {
    class SessionController {
        public static $rol;
        // Simula solamente las preguntas de identidad de la navegación.
        public static function sesionValida() { return true; }
        // Distingue el administrador sin ampliar los roles simulados.
        public static function esAdministrador() { return self::$rol === 'ADMINISTRADOR'; }
        // Conserva un destino de consulta para la navegación de prueba.
        public static function destinoInicio() { return '/proyectofinaldesarrollo/producto/index'; }
    }
}
namespace app\models {
    class RecepcionModel {
        // Simula el contador sin consultar compras reales.
        public static function contarPendientes() { return 0; }
    }
    class ReposicionModel {
        // El badge de prueba no consulta existencias personales.
        public static function contarParaReposicion() { return 0; }
    }
}
namespace {
    chdir(getenv('PRODUCTO_ACCIONES_RAIZ') ?: dirname(__DIR__));
    define('APP_PATH', 'app/');
    $rol = $argv[1] ?? 'ADMINISTRADOR';
    \app\controllers\SessionController::$rol = $rol;
    $_SESSION['usuario'] = ['id_usuario'=>999,'nombre'=>'Prueba','apellido'=>'Visual','roles'=>[$rol],'contexto_carrito'=>'productos-visual','debe_cambiar_password'=>0];
    $puedeGestionar = $rol !== 'VENDEDOR';
    $mensaje = '';
    $filtros = ['codigo_barras'=>'','nombre'=>'','id_categoria'=>'','id_proveedor'=>''];
    $categorias = [(object) ['id_categoria'=>1,'nombre_categoria'=>'Vinos']];
    $proveedores = [(object) ['id_proveedor'=>1,'razon_social'=>'Proveedor de prueba']];
    $productos = [];
    for ($i=1; $i<=9; $i++) {
        $productos[] = (object) ['id_producto'=>$i,'nombre_producto'=>'Vino Malbec '.$i,'codigo_barras'=>str_pad((string) $i,13,'0',STR_PAD_LEFT),'nombre_categoria'=>'Vinos','proveedores'=>'Proveedor de prueba','precio_venta'=>'12500.00','stock_actual'=>12,'stock_minimo'=>3,'estado'=>$i===9?0:1];
    }
    require 'app/views/producto/index.php';
}
