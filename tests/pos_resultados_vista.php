<?php
// Renderiza el POS original con datos ficticios, sin conexión ni escrituras a la base.
namespace app\controllers {
    class SessionController {
        // Simula únicamente la identidad necesaria para dibujar la navegación.
        public static function sesionValida() { return true; }
        public static function esAdministrador() { return true; }
        public static function destinoInicio() { return '/proyectofinaldesarrollo/admin/index'; }
    }
}
namespace app\models {
    class RecepcionModel {
        // Simula el contador sin consultar compras reales.
        public static function contarPendientes() { return 0; }
    }
    class ReposicionModel {
        // Evita consultar stock real durante la prueba visual.
        public static function contarParaReposicion() { return 0; }
    }
}
namespace {
    chdir(getenv('POS_RESULTADOS_RAIZ') ?: dirname(__DIR__));
    define('APP_PATH', 'app/');
    $_SESSION['usuario'] = ['id_usuario'=>999, 'nombre'=>'Prueba', 'apellido'=>'Visual', 'roles'=>['ADMINISTRADOR'], 'contexto_carrito'=>'prueba-pos-visual', 'debe_cambiar_password'=>0];
    $seleccionada = (object) ['id_caja_sesion'=>999, 'caja'=>'Caja ficticia', 'nombre'=>'Prueba', 'apellido'=>'Visual'];
    $sesiones = [$seleccionada];
    $mensaje = '';
    $tokenVenta = 'no-permite-operaciones-reales';
    $mediosPago = array_map(static fn($n, $id) => (object) ['id_medio_pago'=>$id, 'nombre'=>$n], ['EFECTIVO','DÉBITO','CRÉDITO','TRANSFERENCIA','MERCADO PAGO'], range(1,5));
    require 'app/views/pos/index.php';
}
