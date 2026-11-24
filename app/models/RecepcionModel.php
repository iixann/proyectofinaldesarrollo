<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de recepciones en la base.
class RecepcionModel extends Model
{
    protected $tabla = 'recepciones';
    protected $clavePrimaria = 'id_recepcion';

    // Devuelve las compras activas que todavía esperan recepción.
    public static function comprasPendientes()
    {
        return DataBase::query("SELECT c.*, p.razon_social, u.nombre, u.apellido FROM compras c
            INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
            WHERE c.estado = 1 AND c.estado_recepcion = 'PENDIENTE' ORDER BY c.fecha_compra, c.id_compra");
    }

    // Cuenta exactamente las compras activas del listado de pendientes.
    public static function contarPendientes()
    {
        $fila = DataBase::getRecord("SELECT COUNT(*) AS total FROM compras c
            INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
            WHERE c.estado = 1 AND c.estado_recepcion = 'PENDIENTE'");
        return (int) $fila->total;
    }

    // Devuelve las recepciones con su proveedor y usuario responsable.
    public static function listarRecepciones()
    {
        return DataBase::query('SELECT r.*, p.razon_social, u.nombre, u.apellido FROM recepciones r
            INNER JOIN proveedores p ON p.id_proveedor = r.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = r.id_usuario ORDER BY r.fecha_recepcion DESC, r.id_recepcion DESC');
    }

    // Busca una recepción con los datos de proveedor y usuario.
    public static function buscarRecepcion($id)
    {
        return DataBase::getRecord('SELECT r.*, p.razon_social, u.nombre, u.apellido FROM recepciones r
            INNER JOIN proveedores p ON p.id_proveedor = r.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = r.id_usuario WHERE r.id_recepcion = :id', ['id' => $id]);
    }

    // Devuelve los renglones de la recepción con los datos del producto.
    public static function obtenerDetalle($id)
    {
        return DataBase::query('SELECT d.*, p.nombre_producto, p.codigo_barras FROM detalle_recepciones d
            INNER JOIN productos p ON p.id_producto = d.id_producto
            WHERE d.id_recepcion = :id ORDER BY d.id_detalle_recepcion', ['id' => $id]);
    }

    // Devuelve la recepción asociada a la compra, si existe.
    public static function buscarPorCompra($idCompra)
    {
        return DataBase::getRecord('SELECT id_recepcion FROM recepciones WHERE id_compra = :compra', ['compra' => $idCompra]);
    }

    // Comprueba que el ID sea un entero positivo dentro del rango permitido.
    private static function idValido($id)
    {
        return (is_int($id) || is_string($id)) && ctype_digit((string) $id)
            && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]) !== false;
    }

    // Valida la cantidad recibida y la devuelve como entero.
    private static function cantidad($valor)
    {
        // Rechaza cantidades o identificadores fuera del formato y rango permitido.
        if ((!is_string($valor) && !is_int($valor)) || !ctype_digit((string) $valor)
            || filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]) === false) {
            throw new \DomainException('Ingresá cantidades enteras entre 0 y 2147483647 para todos los productos.');
        }
        return (int) $valor;
    }

    // Valida la entrada y pasa la compra al registro de recepción.
    public static function registrarDeCompra($idCompra, $cantidades, $estado, $observacion, $motivo, $idUsuario)
    {
        if (!self::idValido($idCompra) || !is_array($cantidades)) {
            throw new \DomainException('La compra o las cantidades recibidas no son válidas.');
        }
        return self::registrar($idCompra, null, $cantidades, $estado, $observacion, $motivo, $idUsuario);
    }

    // Prepara los productos y registra una recepción sin compra previa.
    public static function registrarDirecta($idProveedor, $renglones, $estado, $observacion, $motivo, $idUsuario)
    {
        if (!self::idValido($idProveedor) || !is_array($renglones) || !$renglones) {
            throw new \DomainException('Seleccioná un proveedor y agregá al menos un producto.');
        }
        $detalles = [];
        foreach ($renglones as $renglon) {
            // Exige un producto válido en cada renglón.
            if (!is_array($renglon) || !self::idValido($renglon['id_producto'] ?? null)) {
                throw new \DomainException('Seleccioná un producto válido en cada renglón.');
            }
            $id = (int) $renglon['id_producto'];
            // Evita repetir el mismo producto en la operación.
            if (isset($detalles[$id])) throw new \DomainException('No se permite repetir un producto.');
            $detalles[$id] = ['esperada' => null, 'recibida' => self::cantidad($renglon['cantidad'] ?? null)];
        }
        return self::registrar(null, $idProveedor, $detalles, $estado, $observacion, $motivo, $idUsuario);
    }

    // Guarda la recepción y actualiza stock solamente si fue aceptada.
    private static function registrar($idCompra, $idProveedor, $recibidos, $estado, $observacion, $motivo, $idUsuario)
    {
        // Solo admite aceptar o rechazar la recepción.
        if (!in_array($estado, ['ACEPTADA', 'RECHAZADA'], true)) {
            throw new \DomainException('Elegí aceptar o rechazar la recepción.');
        }
        foreach ([$observacion, $motivo] as $texto) {
            if (!is_string($texto) || !mb_check_encoding($texto, 'UTF-8') || mb_strlen($texto, 'UTF-8') > 255) {
                throw new \DomainException('La observación y el motivo admiten hasta 255 caracteres válidos.');
            }
        }
        $observacion = trim($observacion);
        $motivo = trim($motivo);
        // Para rechazar la recepción, exige indicar un motivo.
        if ($estado === 'RECHAZADA' && $motivo === '') throw new \DomainException('Ingresá el motivo de rechazo.');
        // Agrupa los cambios para evitar que se guarden incompletos.
        DataBase::iniciarTransaccion();
        try {
            // Si proviene de una compra, usa sus datos para registrar la recepción.
            if ($idCompra !== null) {
                // Serializar la decisión de recepción impide aceptar/rechazar dos veces una compra.
                $compra = DataBase::getRecord('SELECT * FROM compras WHERE id_compra = :id FOR UPDATE', ['id' => $idCompra]);
                // Impide recibir una compra inactiva o que ya fue procesada.
                if (!$compra || (int) $compra->estado !== 1 || $compra->estado_recepcion !== 'PENDIENTE') {
                    throw new \DomainException('La compra no existe, está inactiva o ya fue recepcionada/rechazada.', 409);
                }
                $idProveedor = $compra->id_proveedor;
            }
            // Bloquea el registro hasta terminar para evitar cambios simultáneos.
            $proveedor = DataBase::getRecord('SELECT id_proveedor, estado FROM proveedores WHERE id_proveedor = :id FOR UPDATE', ['id' => $idProveedor]);
            // Solo una recepción aceptada puede ingresar mercadería al stock.
            if (!$proveedor || ($estado === 'ACEPTADA' && (int) $proveedor->estado !== 1)) {
                throw new \DomainException('El proveedor no existe o está inactivo.');
            }
            $detalles = $recibidos;
            // Si proviene de una compra, usa sus datos para registrar la recepción.
            if ($idCompra !== null) {
                $esperados = DataBase::query('SELECT id_producto, cantidad FROM detalle_compras WHERE id_compra = :compra ORDER BY id_producto', ['compra' => $idCompra]);
                $detalles = [];
                foreach ($esperados as $esperado) {
                    $id = (int) $esperado->id_producto;
                    // Exige declarar una cantidad por cada producto esperado.
                    if (isset($detalles[$id]) || !array_key_exists($id, $recibidos)) {
                        throw new \DomainException('Declará la cantidad recibida de cada producto de la compra.');
                    }
                    $detalles[$id] = ['esperada' => (int) $esperado->cantidad, 'recibida' => self::cantidad($recibidos[$id])];
                }
                // Exige recibir exactamente los productos de la compra.
                if (!$detalles || count($detalles) !== count($recibidos)) {
                    throw new \DomainException('Los productos recibidos deben corresponder exactamente a la compra.');
                }
            }
            ksort($detalles, SORT_NUMERIC);
            foreach ($detalles as $id => &$detalle) {
                // Solo una recepción aceptada puede ingresar mercadería al stock.
                if ($estado === 'ACEPTADA' && ($detalle['recibida'] < 1
                    || ($detalle['esperada'] !== null && $detalle['recibida'] !== $detalle['esperada']))) {
                    throw new \DomainException('Para aceptar deben coincidir todas las cantidades recibidas y esperadas; no hay recepción parcial.');
                }
                // Bloquea el registro hasta terminar para evitar cambios simultáneos.
                $producto = DataBase::getRecord('SELECT id_producto, estado, stock_actual FROM productos WHERE id_producto = :id FOR UPDATE', ['id' => $id]);
                // Solo una recepción aceptada puede ingresar mercadería al stock.
                if (!$producto || ($estado === 'ACEPTADA' && (int) $producto->estado !== 1)) {
                    throw new \DomainException('Uno de los productos no existe o está inactivo.');
                }
                // Sin compra previa, exige que el producto pertenezca al proveedor.
                if ($idCompra === null && !DataBase::getRecord('SELECT id_producto_proveedor FROM productos_proveedores
                    WHERE id_producto = :producto AND id_proveedor = :proveedor AND estado = 1 FOR UPDATE', ['producto' => $id, 'proveedor' => $idProveedor])) {
                    throw new \DomainException('El producto no está asociado al proveedor seleccionado.');
                }
                // Solo una recepción aceptada puede ingresar mercadería al stock.
                if ($estado === 'ACEPTADA') {
                    $detalle['anterior'] = (int) $producto->stock_actual;
                    $detalle['nuevo'] = $detalle['anterior'] + $detalle['recibida'];
                    // Impide guardar un stock fuera del rango permitido.
                    if ($detalle['anterior'] < 0 || $detalle['nuevo'] > 2147483647) {
                        throw new \DomainException('El stock resultante supera el rango permitido.');
                    }
                }
            }
            unset($detalle);
            // Guarda un registro en recepciones.
            $idRecepcion = DataBase::insert('INSERT INTO recepciones (id_compra, id_proveedor, id_usuario, estado, observacion, motivo_rechazo)
                VALUES (:compra, :proveedor, :usuario, :estado, :observacion, :motivo)', [
                'compra' => $idCompra, 'proveedor' => $idProveedor, 'usuario' => $idUsuario, 'estado' => $estado,
                'observacion' => $observacion === '' ? null : $observacion, 'motivo' => $estado === 'RECHAZADA' ? $motivo : null
            ]);
            foreach ($detalles as $id => $detalle) {
                // Guarda un registro en detalle_recepciones.
                DataBase::insert('INSERT INTO detalle_recepciones (id_recepcion, id_producto, cantidad_esperada, cantidad_recibida)
                    VALUES (:recepcion, :producto, :esperada, :recibida)', [
                    'recepcion' => $idRecepcion, 'producto' => $id, 'esperada' => $detalle['esperada'], 'recibida' => $detalle['recibida']
                ]);
                // Solo una recepción aceptada puede ingresar mercadería al stock.
                if ($estado === 'ACEPTADA') {
                    // Actualiza los datos de productos.
                    DataBase::execute('UPDATE productos SET stock_actual = :stock WHERE id_producto = :id', ['stock' => $detalle['nuevo'], 'id' => $id]);
                    // Guarda un registro en movimientos_stock.
                    DataBase::insert("INSERT INTO movimientos_stock (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, observacion)
                        VALUES (:producto, :usuario, 'RECEPCION', :cantidad, :anterior, :nuevo, :observacion)", [
                        'producto' => $id, 'usuario' => $idUsuario, 'cantidad' => $detalle['recibida'],
                        'anterior' => $detalle['anterior'], 'nuevo' => $detalle['nuevo'],
                        'observacion' => $idCompra === null ? 'Recepción directa #' . $idRecepcion : 'Recepción #' . $idRecepcion . ' - Compra #' . $idCompra
                    ]);
                }
            }
            // Si proviene de una compra, usa sus datos para registrar la recepción.
            if ($idCompra !== null) {
                // Actualiza los datos de compras.
                DataBase::execute('UPDATE compras SET estado_recepcion = :estado WHERE id_compra = :id', [
                    'estado' => $estado === 'ACEPTADA' ? 'RECEPCIONADA' : 'RECHAZADA', 'id' => $idCompra
                ]);
            }
            // Confirma todos los cambios realizados en la base.
            DataBase::confirmarTransaccion();
            return $idRecepcion;
        } catch (\Throwable $excepcion) {
            // Revierte los cambios porque la operación falló.
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
