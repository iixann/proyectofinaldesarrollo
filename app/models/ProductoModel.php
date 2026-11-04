<?php

namespace app\models;

use \Model;
use \DataBase;

class ProductoModel extends Model
{
    protected $tabla = 'productos';
    protected $clavePrimaria = 'id_producto';

    public static function listarProductos($filtros = [])
    {
        $sql = 'SELECT p.*, c.nombre_categoria FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria WHERE 1 = 1';
        $parametros = [];
        if (($filtros['nombre'] ?? '') !== '') {
            $sql .= " AND p.nombre_producto LIKE :nombre ESCAPE '!'";
            $parametros['nombre'] = self::patronBusqueda($filtros['nombre']);
        }
        if (($filtros['id_categoria'] ?? '') !== '') {
            $sql .= ' AND p.id_categoria = :categoria';
            $parametros['categoria'] = $filtros['id_categoria'];
        }
        if (($filtros['id_proveedor'] ?? '') !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM productos_proveedores pp WHERE pp.id_producto = p.id_producto AND pp.id_proveedor = :proveedor AND pp.estado = 1)';
            $parametros['proveedor'] = $filtros['id_proveedor'];
        }
        return DataBase::query($sql . ' ORDER BY p.id_producto', $parametros);
    }

    public static function nombreDuplicado($nombre, $idCategoria, $id = null)
    {
        $producto = DataBase::getRecord('SELECT id_producto FROM productos WHERE nombre_producto = :nombre AND id_categoria = :categoria', ['nombre' => $nombre, 'categoria' => $idCategoria]);
        return $producto && (string) $producto->id_producto !== (string) $id;
    }

    public static function codigoDuplicado($codigo, $id = null)
    {
        if ($codigo === null || $codigo === '') {
            return false;
        }
        // También reserva los códigos de productos inactivos.
        $producto = DataBase::getRecord('SELECT id_producto FROM productos WHERE codigo_barras = :codigo', ['codigo' => $codigo]);
        return $producto && (string) $producto->id_producto !== (string) $id;
    }

    public static function buscarActivoPorCodigo($codigo)
    {
        return DataBase::getRecord('SELECT p.id_producto, p.codigo_barras, p.nombre_producto, p.precio_venta,
            p.stock_actual, c.nombre_categoria AS categoria FROM productos p
            INNER JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.estado = 1 AND p.codigo_barras = :codigo', ['codigo' => $codigo]);
    }

    public static function buscarActivosPorNombre($nombre)
    {
        // Tratar % y _ ingresados como texto, no como comodines adicionales.
        $nombre = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $nombre);
        return DataBase::query("SELECT p.id_producto, p.codigo_barras, p.nombre_producto, p.precio_venta,
            p.stock_actual, c.nombre_categoria AS categoria FROM productos p
            INNER JOIN categorias c ON c.id_categoria = p.id_categoria
            WHERE p.estado = 1 AND p.nombre_producto LIKE :nombre ESCAPE '!'
            ORDER BY p.nombre_producto, p.id_producto LIMIT 30", ['nombre' => '%' . $nombre . '%']);
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

    public static function validarDatos($entrada, $id = null)
    {
        $datosProducto = [];
        $errores = [];
        foreach (['nombre_producto', 'codigo_barras', 'id_categoria', 'descripcion', 'precio_venta', 'stock_minimo'] as $campo) {
            $datosProducto[$campo] = is_string($entrada[$campo] ?? null) ? trim($entrada[$campo]) : '';
            if (!mb_check_encoding($datosProducto[$campo], 'UTF-8') || (isset($entrada[$campo]) && !is_string($entrada[$campo]))) {
                $errores[] = 'Ingresá un texto válido para ' . $campo . '.';
            }
        }
        if ($datosProducto['nombre_producto'] === '') {
            $errores[] = 'Ingresá el nombre del producto.';
        } elseif (mb_strlen($datosProducto['nombre_producto'], 'UTF-8') > 150) {
            $errores[] = 'El nombre debe tener hasta 150 caracteres.';
        }
        if (mb_strlen($datosProducto['descripcion'], 'UTF-8') > 255) {
            $errores[] = 'La descripción debe tener hasta 255 caracteres.';
        }
        if (isset($entrada['codigo_barras']) && !is_string($entrada['codigo_barras'])) {
            $errores[] = 'Ingresá un Código EAN válido como texto.';
        } elseif ($datosProducto['codigo_barras'] === '') {
            $errores[] = 'Ingresá el Código EAN. Es obligatorio.';
        } elseif (!mb_check_encoding($datosProducto['codigo_barras'], 'UTF-8') || mb_strlen($datosProducto['codigo_barras'], 'UTF-8') > 50) {
            $errores[] = 'El Código EAN debe tener hasta 50 caracteres válidos.';
        } elseif (self::codigoDuplicado($datosProducto['codigo_barras'], $id)) {
            $errores[] = 'Ya existe un producto con ese Código EAN, incluso si está inactivo.';
        }
        $categorias = array_map('strval', array_column(self::obtenerCategoriasActivas(), 'id_categoria'));
        $categoriaValida = ctype_digit($datosProducto['id_categoria']) && in_array($datosProducto['id_categoria'], $categorias, true);
        if (!$categoriaValida) {
            $errores[] = 'Seleccioná una categoría activa válida.';
        }
        $precio = $datosProducto['precio_venta'];
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $precio)) {
            $errores[] = 'Ingresá un precio de venta mayor o igual a cero, con hasta dos decimales y diez dígitos enteros.';
        }
        foreach (['stock_minimo' => 'Stock mínimo'] as $campo => $etiqueta) {
            if ($datosProducto[$campo] === '') {
                $datosProducto[$campo] = '0';
            }
            if (!ctype_digit($datosProducto[$campo]) || filter_var($datosProducto[$campo], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]) === false) {
                $errores[] = $etiqueta . ': ingresá un entero mayor o igual a cero.';
            }
        }
        if ($categoriaValida && $datosProducto['nombre_producto'] !== '' && mb_check_encoding($datosProducto['nombre_producto'], 'UTF-8') && self::nombreDuplicado($datosProducto['nombre_producto'], $datosProducto['id_categoria'], $id)) {
            $errores[] = 'Ya existe ese producto en la categoría seleccionada.';
        }
        return [$datosProducto, $errores];
    }

    public static function crearProducto($datosProducto, $proveedores)
    {
        unset($datosProducto['stock_actual']);
        $datosProducto['codigo_barras'] = self::validarCodigo($datosProducto['codigo_barras'] ?? null);
        DataBase::iniciarTransaccion();
        try {
            $id = DataBase::insert('INSERT INTO productos (nombre_producto, codigo_barras, id_categoria, descripcion, precio_venta, stock_actual, stock_minimo)
                VALUES (:nombre_producto, :codigo_barras, :id_categoria, :descripcion, :precio_venta, 0, :stock_minimo)', $datosProducto);
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
        $datosProducto['codigo_barras'] = self::validarCodigo($datosProducto['codigo_barras'] ?? null);
        DataBase::iniciarTransaccion();
        try {
            $datosProducto['id'] = $id;
            DataBase::execute('UPDATE productos SET nombre_producto = :nombre_producto, codigo_barras = :codigo_barras, id_categoria = :id_categoria,
                descripcion = :descripcion, precio_venta = :precio_venta,
                stock_minimo = :stock_minimo WHERE id_producto = :id', $datosProducto);
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

    private static function validarCodigo($codigo)
    {
        if (!is_string($codigo) || !mb_check_encoding($codigo, 'UTF-8') || trim($codigo) === '' || mb_strlen(trim($codigo), 'UTF-8') > 50) {
            throw new \DomainException('El Código EAN es obligatorio y debe tener hasta 50 caracteres válidos.');
        }
        return trim($codigo);
    }
    public static function reactivar($id)
    {
        if (!self::findId($id)) {
            throw new \DomainException('El registro no existe.');
        }
        return DataBase::execute('UPDATE productos SET estado = 1, fecha_baja = NULL WHERE id_producto = :id', ['id' => $id]);
    }

}
