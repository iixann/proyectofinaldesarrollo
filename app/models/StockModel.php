<?php

namespace app\models;

use \Model;
use \DataBase;

class StockModel extends Model
{
    protected $tabla = 'productos';
    protected $clavePrimaria = 'id_producto';

    public static function listarProductosActivos()
    {
        return DataBase::query('SELECT p.*, c.nombre_categoria FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria WHERE p.estado = 1 ORDER BY p.nombre_producto');
    }

    public static function listarMovimientos($idProducto = null)
    {
        $sql = 'SELECT m.*, p.nombre_producto, u.nombre, u.apellido FROM movimientos_stock m
            INNER JOIN productos p ON p.id_producto = m.id_producto
            INNER JOIN usuarios u ON u.id_usuario = m.id_usuario';
        $parametros = [];
        if ($idProducto !== null) {
            $sql .= ' WHERE m.id_producto = :producto';
            $parametros['producto'] = $idProducto;
        }
        return DataBase::query($sql . ' ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC', $parametros);
    }

    public static function registrarEntrada($idProducto, $idUsuario, $cantidad, $observacion = '')
    {
        self::registrarAjuste($idProducto, $idUsuario, 'AJUSTE_ENTRADA', $cantidad, $observacion);
    }

    public static function registrarSalida($idProducto, $idUsuario, $cantidad, $observacion = '')
    {
        self::registrarAjuste($idProducto, $idUsuario, 'AJUSTE_SALIDA', $cantidad, $observacion);
    }

    private static function registrarAjuste($idProducto, $idUsuario, $tipo, $cantidad, $observacion)
    {
        if (filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            throw new \DomainException('La cantidad debe ser un entero mayor a cero, hasta 2147483647.');
        }
        DataBase::iniciarTransaccion();
        try {
            // Leer y bloquear dentro de la transacción evita perder ajustes simultáneos.
            $producto = DataBase::getRecord('SELECT id_producto, stock_actual, estado FROM productos WHERE id_producto = :id FOR UPDATE', ['id' => $idProducto]);
            if (!$producto || (int) $producto->estado !== 1) {
                throw new \DomainException('El producto no existe o está inactivo.');
            }
            $anterior = (int) $producto->stock_actual;
            $nuevo = $tipo === 'AJUSTE_ENTRADA' ? $anterior + (int) $cantidad : $anterior - (int) $cantidad;
            if ($nuevo < 0) {
                throw new \DomainException('El stock disponible no alcanza para realizar la salida.');
            }
            if ($nuevo > 2147483647) {
                throw new \DomainException('El stock resultante supera el máximo permitido.');
            }
            DataBase::execute('UPDATE productos SET stock_actual = :stock WHERE id_producto = :id', ['stock' => $nuevo, 'id' => $idProducto]);
            DataBase::insert('INSERT INTO movimientos_stock (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, observacion)
                VALUES (:producto, :usuario, :tipo, :cantidad, :anterior, :nuevo, :observacion)', [
                'producto' => $idProducto, 'usuario' => $idUsuario, 'tipo' => $tipo,
                'cantidad' => (int) $cantidad, 'anterior' => $anterior, 'nuevo' => $nuevo,
                'observacion' => $observacion === '' ? null : $observacion
            ]);
            DataBase::confirmarTransaccion();
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
