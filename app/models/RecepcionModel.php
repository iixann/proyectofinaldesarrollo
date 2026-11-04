<?php

namespace app\models;

use \Model;
use \DataBase;

class RecepcionModel extends Model
{
    protected $tabla = 'recepciones';
    protected $clavePrimaria = 'id_recepcion';

    public static function comprasPendientes()
    {
        return DataBase::query("SELECT c.*, p.razon_social, u.nombre, u.apellido FROM compras c
            INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
            WHERE c.estado = 1 AND c.estado_recepcion = 'PENDIENTE' ORDER BY c.fecha_compra, c.id_compra");
    }

    public static function listarRecepciones()
    {
        return DataBase::query('SELECT r.*, p.razon_social, u.nombre, u.apellido FROM recepciones r
            INNER JOIN proveedores p ON p.id_proveedor = r.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = r.id_usuario ORDER BY r.fecha_recepcion DESC, r.id_recepcion DESC');
    }

    public static function buscarRecepcion($id)
    {
        return DataBase::getRecord('SELECT r.*, p.razon_social, u.nombre, u.apellido FROM recepciones r
            INNER JOIN proveedores p ON p.id_proveedor = r.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = r.id_usuario WHERE r.id_recepcion = :id', ['id' => $id]);
    }

    public static function obtenerDetalle($id)
    {
        return DataBase::query('SELECT d.*, p.nombre_producto, p.codigo_barras FROM detalle_recepciones d
            INNER JOIN productos p ON p.id_producto = d.id_producto
            WHERE d.id_recepcion = :id ORDER BY d.id_detalle_recepcion', ['id' => $id]);
    }

    public static function buscarPorCompra($idCompra)
    {
        return DataBase::getRecord('SELECT id_recepcion FROM recepciones WHERE id_compra = :compra', ['compra' => $idCompra]);
    }

    private static function idValido($id)
    {
        return (is_int($id) || is_string($id)) && ctype_digit((string) $id)
            && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]) !== false;
    }

    private static function cantidad($valor)
    {
        if ((!is_string($valor) && !is_int($valor)) || !ctype_digit((string) $valor)
            || filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]) === false) {
            throw new \DomainException('Ingresá cantidades enteras entre 0 y 2147483647 para todos los productos.');
        }
        return (int) $valor;
    }

    public static function registrarDeCompra($idCompra, $cantidades, $estado, $observacion, $motivo, $idUsuario)
    {
        if (!self::idValido($idCompra) || !is_array($cantidades)) {
            throw new \DomainException('La compra o las cantidades recibidas no son válidas.');
        }
        return self::registrar($idCompra, null, $cantidades, $estado, $observacion, $motivo, $idUsuario);
    }

    public static function registrarDirecta($idProveedor, $renglones, $estado, $observacion, $motivo, $idUsuario)
    {
        if (!self::idValido($idProveedor) || !is_array($renglones) || !$renglones) {
            throw new \DomainException('Seleccioná un proveedor y agregá al menos un producto.');
        }
        $detalles = [];
        foreach ($renglones as $renglon) {
            if (!is_array($renglon) || !self::idValido($renglon['id_producto'] ?? null)) {
                throw new \DomainException('Seleccioná un producto válido en cada renglón.');
            }
            $id = (int) $renglon['id_producto'];
            if (isset($detalles[$id])) throw new \DomainException('No se permite repetir un producto.');
            $detalles[$id] = ['esperada' => null, 'recibida' => self::cantidad($renglon['cantidad'] ?? null)];
        }
        return self::registrar(null, $idProveedor, $detalles, $estado, $observacion, $motivo, $idUsuario);
    }

    private static function registrar($idCompra, $idProveedor, $recibidos, $estado, $observacion, $motivo, $idUsuario)
    {
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
        if ($estado === 'RECHAZADA' && $motivo === '') throw new \DomainException('Ingresá el motivo de rechazo.');
        DataBase::iniciarTransaccion();
        try {
            if ($idCompra !== null) {
                // Serializar la decisión de recepción impide aceptar/rechazar dos veces una compra.
                $compra = DataBase::getRecord('SELECT * FROM compras WHERE id_compra = :id FOR UPDATE', ['id' => $idCompra]);
                if (!$compra || (int) $compra->estado !== 1 || $compra->estado_recepcion !== 'PENDIENTE') {
                    throw new \DomainException('La compra no existe, está inactiva o ya fue recepcionada/rechazada.', 409);
                }
                $idProveedor = $compra->id_proveedor;
            }
            $proveedor = DataBase::getRecord('SELECT id_proveedor, estado FROM proveedores WHERE id_proveedor = :id FOR UPDATE', ['id' => $idProveedor]);
            if (!$proveedor || ($estado === 'ACEPTADA' && (int) $proveedor->estado !== 1)) {
                throw new \DomainException('El proveedor no existe o está inactivo.');
            }
            $detalles = $recibidos;
            if ($idCompra !== null) {
                $esperados = DataBase::query('SELECT id_producto, cantidad FROM detalle_compras WHERE id_compra = :compra ORDER BY id_producto', ['compra' => $idCompra]);
                $detalles = [];
                foreach ($esperados as $esperado) {
                    $id = (int) $esperado->id_producto;
                    if (isset($detalles[$id]) || !array_key_exists($id, $recibidos)) {
                        throw new \DomainException('Declará la cantidad recibida de cada producto de la compra.');
                    }
                    $detalles[$id] = ['esperada' => (int) $esperado->cantidad, 'recibida' => self::cantidad($recibidos[$id])];
                }
                if (!$detalles || count($detalles) !== count($recibidos)) {
                    throw new \DomainException('Los productos recibidos deben corresponder exactamente a la compra.');
                }
            }
            ksort($detalles, SORT_NUMERIC);
            foreach ($detalles as $id => &$detalle) {
                if ($estado === 'ACEPTADA' && ($detalle['recibida'] < 1
                    || ($detalle['esperada'] !== null && $detalle['recibida'] !== $detalle['esperada']))) {
                    throw new \DomainException('Para aceptar deben coincidir todas las cantidades recibidas y esperadas; no hay recepción parcial.');
                }
                $producto = DataBase::getRecord('SELECT id_producto, estado, stock_actual FROM productos WHERE id_producto = :id FOR UPDATE', ['id' => $id]);
                if (!$producto || ($estado === 'ACEPTADA' && (int) $producto->estado !== 1)) {
                    throw new \DomainException('Uno de los productos no existe o está inactivo.');
                }
                if ($idCompra === null && !DataBase::getRecord('SELECT id_producto_proveedor FROM productos_proveedores
                    WHERE id_producto = :producto AND id_proveedor = :proveedor AND estado = 1 FOR UPDATE', ['producto' => $id, 'proveedor' => $idProveedor])) {
                    throw new \DomainException('El producto no está asociado al proveedor seleccionado.');
                }
                if ($estado === 'ACEPTADA') {
                    $detalle['anterior'] = (int) $producto->stock_actual;
                    $detalle['nuevo'] = $detalle['anterior'] + $detalle['recibida'];
                    if ($detalle['anterior'] < 0 || $detalle['nuevo'] > 2147483647) {
                        throw new \DomainException('El stock resultante supera el rango permitido.');
                    }
                }
            }
            unset($detalle);
            $idRecepcion = DataBase::insert('INSERT INTO recepciones (id_compra, id_proveedor, id_usuario, estado, observacion, motivo_rechazo)
                VALUES (:compra, :proveedor, :usuario, :estado, :observacion, :motivo)', [
                'compra' => $idCompra, 'proveedor' => $idProveedor, 'usuario' => $idUsuario, 'estado' => $estado,
                'observacion' => $observacion === '' ? null : $observacion, 'motivo' => $estado === 'RECHAZADA' ? $motivo : null
            ]);
            foreach ($detalles as $id => $detalle) {
                DataBase::insert('INSERT INTO detalle_recepciones (id_recepcion, id_producto, cantidad_esperada, cantidad_recibida)
                    VALUES (:recepcion, :producto, :esperada, :recibida)', [
                    'recepcion' => $idRecepcion, 'producto' => $id, 'esperada' => $detalle['esperada'], 'recibida' => $detalle['recibida']
                ]);
                if ($estado === 'ACEPTADA') {
                    DataBase::execute('UPDATE productos SET stock_actual = :stock WHERE id_producto = :id', ['stock' => $detalle['nuevo'], 'id' => $id]);
                    DataBase::insert("INSERT INTO movimientos_stock (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, observacion)
                        VALUES (:producto, :usuario, 'RECEPCION', :cantidad, :anterior, :nuevo, :observacion)", [
                        'producto' => $id, 'usuario' => $idUsuario, 'cantidad' => $detalle['recibida'],
                        'anterior' => $detalle['anterior'], 'nuevo' => $detalle['nuevo'],
                        'observacion' => $idCompra === null ? 'Recepción directa #' . $idRecepcion : 'Recepción #' . $idRecepcion . ' - Compra #' . $idCompra
                    ]);
                }
            }
            if ($idCompra !== null) {
                DataBase::execute('UPDATE compras SET estado_recepcion = :estado WHERE id_compra = :id', [
                    'estado' => $estado === 'ACEPTADA' ? 'RECEPCIONADA' : 'RECHAZADA', 'id' => $idCompra
                ]);
            }
            DataBase::confirmarTransaccion();
            return $idRecepcion;
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
