<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de stock y sus movimientos en la base.
class StockModel extends Model
{
    protected $tabla = 'productos';
    protected $clavePrimaria = 'id_producto';

    // Devuelve el stock de productos activos aplicando los filtros.
    public static function listarProductosActivos($filtros = [])
    {
        $sql = 'SELECT p.*, c.nombre_categoria FROM productos p
            INNER JOIN categorias c ON c.id_categoria = p.id_categoria WHERE p.estado = 1';
        $parametros = [];
        if (($filtros['nombre'] ?? '') !== '') {
            $sql .= " AND p.nombre_producto LIKE :nombre ESCAPE '!'";
            $parametros['nombre'] = self::patronBusqueda($filtros['nombre']);
        }
        if (($filtros['codigo_barras'] ?? '') !== '') {
            $sql .= ' AND p.codigo_barras = :codigo';
            $parametros['codigo'] = $filtros['codigo_barras'];
        }
        if (($filtros['id_categoria'] ?? '') !== '') {
            $sql .= ' AND p.id_categoria = :categoria';
            $parametros['categoria'] = $filtros['id_categoria'];
        }
        if (($filtros['id_proveedor'] ?? '') !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM productos_proveedores pp
                WHERE pp.id_producto = p.id_producto AND pp.id_proveedor = :proveedor AND pp.estado = 1)';
            $parametros['proveedor'] = $filtros['id_proveedor'];
        }
        return DataBase::query($sql . ' ORDER BY p.nombre_producto', $parametros);
    }

    // Devuelve el historial de stock con producto y usuario responsable.
    public static function listarMovimientos($idProducto = null)
    {
        // Une cada movimiento con el producto y el usuario responsable.
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

    // Registra un ajuste que aumenta el stock del producto.
    public static function registrarEntrada($idProducto, $idUsuario, $cantidad, $observacion = '')
    {
        self::registrarAjuste($idProducto, $idUsuario, 'AJUSTE_ENTRADA', $cantidad, $observacion);
    }

    // Registra un ajuste que disminuye el stock del producto.
    public static function registrarSalida($idProducto, $idUsuario, $cantidad, $observacion = '')
    {
        self::registrarAjuste($idProducto, $idUsuario, 'AJUSTE_SALIDA', $cantidad, $observacion);
    }

    // Actualiza el stock y guarda el movimiento dentro de una transacción.
    private static function registrarAjuste($idProducto, $idUsuario, $tipo, $cantidad, $observacion)
    {
        // Rechaza cantidades o identificadores fuera del formato y rango permitido.
        if (filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
            throw new \DomainException('La cantidad debe ser un entero mayor a cero, hasta 2147483647.');
        }
        // Agrupa los cambios para evitar que se guarden incompletos.
        DataBase::iniciarTransaccion();
        try {
            // Leer y bloquear dentro de la transacción evita perder ajustes simultáneos.
            $producto = DataBase::getRecord('SELECT id_producto, stock_actual, estado FROM productos WHERE id_producto = :id FOR UPDATE', ['id' => $idProducto]);
            // Exige que el producto exista y esté activo.
            if (!$producto || (int) $producto->estado !== 1) {
                throw new \DomainException('El producto no existe o está inactivo.');
            }
            $anterior = (int) $producto->stock_actual;
            $nuevo = $tipo === 'AJUSTE_ENTRADA' ? $anterior + (int) $cantidad : $anterior - (int) $cantidad;
            // Si el stock no alcanza, cancela la salida.
            if ($nuevo < 0) {
                throw new \DomainException('El stock disponible no alcanza para realizar la salida.');
            }
            // Impide guardar un stock que supera el rango de la base.
            if ($nuevo > 2147483647) {
                throw new \DomainException('El stock resultante supera el máximo permitido.');
            }
            // Actualiza los datos de productos.
            DataBase::execute('UPDATE productos SET stock_actual = :stock WHERE id_producto = :id', ['stock' => $nuevo, 'id' => $idProducto]);
            // Guarda un registro en movimientos_stock.
            DataBase::insert('INSERT INTO movimientos_stock (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, observacion)
                VALUES (:producto, :usuario, :tipo, :cantidad, :anterior, :nuevo, :observacion)', [
                'producto' => $idProducto, 'usuario' => $idUsuario, 'tipo' => $tipo,
                'cantidad' => (int) $cantidad, 'anterior' => $anterior, 'nuevo' => $nuevo,
                'observacion' => $observacion === '' ? null : $observacion
            ]);
            // Confirma todos los cambios realizados en la base.
            DataBase::confirmarTransaccion();
        } catch (\Throwable $excepcion) {
            // Revierte los cambios porque la operación falló.
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
