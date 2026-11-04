<?php

namespace app\models;

use \Model;
use \DataBase;

class ReposicionModel extends Model
{
    protected $tabla = 'productos';
    protected $clavePrimaria = 'id_producto';

    public static function listarProductos($filtros = [])
    {
        $sql = "SELECT p.id_producto, p.nombre_producto, p.codigo_barras,
            c.nombre_categoria, p.stock_actual, p.stock_minimo,
            (SELECT GROUP_CONCAT(pr.razon_social ORDER BY pr.razon_social SEPARATOR ', ')
                FROM productos_proveedores pp INNER JOIN proveedores pr ON pr.id_proveedor = pp.id_proveedor
                WHERE pp.id_producto = p.id_producto AND pp.estado = 1) AS proveedores
            FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.estado = 1 AND p.stock_actual <= p.stock_minimo";
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
            $sql .= ' AND EXISTS (SELECT 1 FROM productos_proveedores filtro
                WHERE filtro.id_producto = p.id_producto AND filtro.id_proveedor = :proveedor AND filtro.estado = 1)';
            $parametros['proveedor'] = $filtros['id_proveedor'];
        }
        return DataBase::query($sql . ' ORDER BY p.stock_actual ASC, p.nombre_producto, p.id_producto', $parametros);
    }
}
