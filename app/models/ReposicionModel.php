<?php

namespace app\models;

use \Model;
use \DataBase;

class ReposicionModel extends Model
{
    protected $tabla = 'productos';
    protected $clavePrimaria = 'id_producto';

    public static function listarProductos()
    {
        return DataBase::query("SELECT p.id_producto, p.nombre_producto, p.codigo_barras,
            c.nombre_categoria, p.stock_actual, p.stock_minimo,
            (SELECT GROUP_CONCAT(pr.razon_social ORDER BY pr.razon_social SEPARATOR ', ')
                FROM productos_proveedores pp INNER JOIN proveedores pr ON pr.id_proveedor = pp.id_proveedor
                WHERE pp.id_producto = p.id_producto AND pp.estado = 1) AS proveedores
            FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.estado = 1 AND p.stock_actual <= p.stock_minimo
            ORDER BY p.stock_actual ASC, p.nombre_producto, p.id_producto");
    }
}
