<?php

namespace app\models;

use \Model;
use \DataBase;

class ProductoModel extends Model
{
    protected $tabla = 'productos';
    protected $clavePrimaria = 'id_producto';

    public static function listarProductos()
    {
        return DataBase::query('SELECT p.*, c.nombre_categoria FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria ORDER BY p.id_producto');
    }

    public static function nombreDuplicado($nombre, $idCategoria, $id = null)
    {
        $producto = DataBase::getRecord('SELECT id_producto FROM productos WHERE nombre_producto = :nombre AND id_categoria = :categoria', ['nombre' => $nombre, 'categoria' => $idCategoria]);
        return $producto && (string) $producto->id_producto !== (string) $id;
    }

    public static function obtenerCategoriasActivas()
    {
        return DataBase::query('SELECT id_categoria, nombre_categoria FROM categorias WHERE estado = 1 ORDER BY nombre_categoria');
    }

    public static function obtenerProveedoresActivos()
    {
        return DataBase::query('SELECT id_proveedor, razon_social FROM proveedores WHERE estado = 1 ORDER BY razon_social');
    }

    public static function obtenerProveedoresProducto($id)
    {
        return DataBase::query('SELECT pp.id_proveedor, pr.razon_social, pr.estado FROM productos_proveedores pp INNER JOIN proveedores pr ON pr.id_proveedor = pp.id_proveedor WHERE pp.id_producto = :id AND pp.estado = 1 ORDER BY pr.razon_social', ['id' => $id]);
    }

    public static function asignarProveedores($id, $proveedores)
    {
        foreach ($proveedores as $idProveedor) {
            // Recuperar una asociación existente evita violar el par único al volver a seleccionarla.
            DataBase::execute('INSERT INTO productos_proveedores (id_producto, id_proveedor) VALUES (:producto, :proveedor)
                ON DUPLICATE KEY UPDATE estado = 1, fecha_baja = NULL', ['producto' => $id, 'proveedor' => $idProveedor]);
        }
    }

    public static function actualizarProveedores($id, $proveedores)
    {
        DataBase::execute('UPDATE productos_proveedores SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_producto = :id AND estado = 1', ['id' => $id]);
        self::asignarProveedores($id, $proveedores);
    }

    public static function crearProducto($datosProducto, $proveedores)
    {
        unset($datosProducto['stock_actual']);
        DataBase::iniciarTransaccion();
        try {
            $id = DataBase::insert('INSERT INTO productos (nombre_producto, id_categoria, descripcion, precio_venta, stock_actual, stock_minimo, stock_deseable)
                VALUES (:nombre_producto, :id_categoria, :descripcion, :precio_venta, 0, :stock_minimo, :stock_deseable)', $datosProducto);
            self::asignarProveedores($id, $proveedores);
            DataBase::confirmarTransaccion();
            return $id;
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    public static function actualizarProducto($id, $datosProducto, $proveedores)
    {
        unset($datosProducto['stock_actual']);
        DataBase::iniciarTransaccion();
        try {
            $datosProducto['id'] = $id;
            DataBase::execute('UPDATE productos SET nombre_producto = :nombre_producto, id_categoria = :id_categoria,
                descripcion = :descripcion, precio_venta = :precio_venta,
                stock_minimo = :stock_minimo, stock_deseable = :stock_deseable WHERE id_producto = :id', $datosProducto);
            self::actualizarProveedores($id, $proveedores);
            DataBase::confirmarTransaccion();
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    public static function bajaProducto($id)
    {
        return DataBase::execute('UPDATE productos SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_producto = :id AND estado = 1', ['id' => $id]);
    }
}
