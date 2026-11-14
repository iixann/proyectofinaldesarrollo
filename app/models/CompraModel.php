<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de compras en la base.
class CompraModel extends Model
{
    protected $tabla = 'compras';
    protected $clavePrimaria = 'id_compra';

    // Devuelve las compras filtradas con su proveedor y usuario.
    public static function listarCompras($id = '', $comprobante = '', $idProveedor = '')
    {
        // Relaciona compras con proveedores y usuarios para el listado.
        $sql = 'SELECT c.*, p.razon_social, u.nombre, u.apellido FROM compras c
            INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = c.id_usuario WHERE 1 = 1';
        $parametros = [];
        if ($id !== '') {
            $sql .= ' AND c.id_compra = :id';
            $parametros['id'] = $id;
        }
        if ($comprobante !== '') {
            $sql .= " AND c.numero_comprobante LIKE :comprobante ESCAPE '!'";
            $parametros['comprobante'] = self::patronBusqueda($comprobante);
        }
        if ($idProveedor !== '') {
            $sql .= ' AND c.id_proveedor = :proveedor';
            $parametros['proveedor'] = $idProveedor;
        }
        return DataBase::query($sql . ' ORDER BY c.fecha_compra DESC, c.id_compra DESC', $parametros);
    }

    // Busca una compra con los datos de proveedor y usuario.
    public static function buscarCompra($id)
    {
        return DataBase::getRecord('SELECT c.*, p.razon_social, u.nombre, u.apellido FROM compras c
            INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor
            INNER JOIN usuarios u ON u.id_usuario = c.id_usuario WHERE c.id_compra = :id', ['id' => $id]);
    }

    // Devuelve los renglones de la compra con los datos del producto.
    public static function obtenerDetalle($id)
    {
        return DataBase::query('SELECT d.*, p.nombre_producto, p.codigo_barras FROM detalle_compras d
            INNER JOIN productos p ON p.id_producto = d.id_producto WHERE d.id_compra = :id ORDER BY d.id_detalle_compra', ['id' => $id]);
    }

    // Devuelve los proveedores habilitados para seleccionar.
    public static function obtenerProveedoresActivos()
    {
        return DataBase::query('SELECT id_proveedor, razon_social FROM proveedores WHERE estado = 1 ORDER BY razon_social');
    }

    // Devuelve los productos habilitados para operar.
    public static function obtenerProductosActivos()
    {
        return DataBase::query('SELECT id_producto, nombre_producto FROM productos WHERE estado = 1 ORDER BY nombre_producto');
    }

    // Devuelve productos activos con sus asociaciones a proveedores activos.
    public static function obtenerProductosPorProveedor()
    {
        return DataBase::query('SELECT pp.id_proveedor, p.id_producto, p.nombre_producto FROM productos_proveedores pp
            INNER JOIN productos p ON p.id_producto = pp.id_producto
            INNER JOIN proveedores pr ON pr.id_proveedor = pp.id_proveedor
            WHERE pp.estado = 1 AND p.estado = 1 AND pr.estado = 1 ORDER BY p.nombre_producto');
    }

    // Comprueba que el ID sea un entero positivo dentro del rango permitido.
    private static function idValido($id)
    {
        return is_string($id) && ctype_digit($id) && (float) $id >= 1 && (float) $id <= 4294967295;
    }

    // Valida el precio y devuelve su valor en centavos.
    private static function precioEnCentavos($precio)
    {
        // DECIMAL(12,2): hasta diez dígitos enteros y dos decimales, sin float.
        if (!is_string($precio) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $precio)) {
            throw new \DomainException('Ingresá un precio unitario mayor o igual a cero, con hasta dos decimales.');
        }
        $partes = explode('.', $precio);
        return (int) $partes[0] * 100 + (int) str_pad($partes[1] ?? '', 2, '0');
    }

    // Convierte centavos al formato decimal usado por la base.
    private static function importeDecimal($centavos)
    {
        return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    // Guarda la compra y sus renglones pendientes de recepción, sin mover stock.
    public static function registrarCompra($datos, $renglones, $idUsuario)
    {
        if (!self::idValido($datos['id_proveedor'])) {
            throw new \DomainException('Seleccioná un proveedor.');
        }
        if (mb_strlen($datos['numero_comprobante'], 'UTF-8') > 50 || mb_strlen($datos['observacion'], 'UTF-8') > 255) {
            throw new \DomainException('El comprobante admite hasta 50 caracteres y la observación hasta 255.');
        }
        // Exige al menos un producto para registrar la compra.
        if (!$renglones) {
            throw new \DomainException('Agregá al menos un producto.');
        }
        $detalles = [];
        $total = 0;
        $maximo = 999999999999; // Máximo de DECIMAL(12,2), en centavos.
        foreach ($renglones as $renglon) {
            // Exige un producto válido en cada renglón.
            if (!is_array($renglon) || !self::idValido($renglon['id_producto'] ?? null)) {
                throw new \DomainException('Seleccioná un producto válido en cada renglón.');
            }
            $id = (int) $renglon['id_producto'];
            // Evita repetir el mismo producto en la operación.
            if (isset($detalles[$id])) {
                throw new \DomainException('No se permite repetir un producto en la misma compra.');
            }
            $cantidad = $renglon['cantidad'] ?? null;
            // Rechaza cantidades o identificadores fuera del formato y rango permitido.
            if (!is_string($cantidad) || !ctype_digit($cantidad) || filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
                throw new \DomainException('La cantidad debe ser un entero mayor a cero, hasta 2147483647.');
            }
            $cantidad = (int) $cantidad;
            $precio = self::precioEnCentavos($renglon['precio_unitario'] ?? null);
            // Impide que el subtotal supere el importe máximo permitido.
            if ($precio > intdiv($maximo, $cantidad)) {
                throw new \DomainException('El subtotal supera el importe máximo permitido.');
            }
            $subtotal = $cantidad * $precio;
            // Impide que el total acumulado supere el máximo permitido.
            if ($total > $maximo - $subtotal) {
                throw new \DomainException('El total supera el importe máximo permitido.');
            }
            $total += $subtotal;
            $detalles[$id] = ['cantidad' => $cantidad, 'precio' => self::importeDecimal($precio), 'subtotal' => self::importeDecimal($subtotal)];
        }
        // Bloquear siempre en el mismo orden reduce interbloqueos entre compras simultáneas.
        ksort($detalles, SORT_NUMERIC);
        // Agrupa los cambios para evitar que se guarden incompletos.
        DataBase::iniciarTransaccion();
        try {
            // Bloquea el registro hasta terminar para evitar cambios simultáneos.
            $proveedor = DataBase::getRecord('SELECT id_proveedor, estado FROM proveedores WHERE id_proveedor = :id FOR UPDATE', ['id' => $datos['id_proveedor']]);
            // Exige que el proveedor exista y esté activo.
            if (!$proveedor || (int) $proveedor->estado !== 1) {
                throw new \DomainException('El proveedor no existe o está inactivo.');
            }
            $comprobante = $datos['numero_comprobante'] === '' ? null : $datos['numero_comprobante'];
            if ($comprobante !== null && DataBase::getRecord('SELECT id_compra FROM compras WHERE id_proveedor = :proveedor AND numero_comprobante = :comprobante', ['proveedor' => $datos['id_proveedor'], 'comprobante' => $comprobante])) {
                throw new \DomainException('El número de comprobante ya existe para ese proveedor.');
            }
            foreach ($detalles as $id => $detalle) {
                // Bloquea el registro hasta terminar para evitar cambios simultáneos.
                $producto = DataBase::getRecord('SELECT id_producto, estado FROM productos WHERE id_producto = :id FOR UPDATE', ['id' => $id]);
                // Exige que el producto exista y esté activo.
                if (!$producto || (int) $producto->estado !== 1) {
                    throw new \DomainException('Uno de los productos no existe o está inactivo.');
                }
                // Bloquea el registro hasta terminar para evitar cambios simultáneos.
                $asociacion = DataBase::getRecord('SELECT id_producto_proveedor FROM productos_proveedores WHERE id_producto = :producto AND id_proveedor = :proveedor AND estado = 1 FOR UPDATE', ['producto' => $id, 'proveedor' => $datos['id_proveedor']]);
                // Impide comprar productos sin asociación activa con el proveedor.
                if (!$asociacion) {
                    throw new \DomainException('El producto no pertenece al proveedor seleccionado. Editá el producto para asociarlo primero.');
                }
            }
            // Guarda un registro en compras.
            $idCompra = DataBase::insert("INSERT INTO compras (id_proveedor, id_usuario, numero_comprobante, observacion, estado_recepcion)
                VALUES (:proveedor, :usuario, :comprobante, :observacion, 'PENDIENTE')", [
                'proveedor' => $datos['id_proveedor'], 'usuario' => $idUsuario, 'comprobante' => $comprobante,
                'observacion' => $datos['observacion'] === '' ? null : $datos['observacion']
            ]);
            foreach ($detalles as $id => $detalle) {
                // Guarda un registro en detalle_compras.
                DataBase::insert('INSERT INTO detalle_compras (id_compra, id_producto, cantidad, precio_unitario, subtotal)
                    VALUES (:compra, :producto, :cantidad, :precio, :subtotal)', [
                    'compra' => $idCompra, 'producto' => $id, 'cantidad' => $detalle['cantidad'], 'precio' => $detalle['precio'], 'subtotal' => $detalle['subtotal']
                ]);
            }
            // Actualiza los datos de compras.
            DataBase::execute('UPDATE compras SET total = :total WHERE id_compra = :id', ['total' => self::importeDecimal($total), 'id' => $idCompra]);
            // Confirma todos los cambios realizados en la base.
            DataBase::confirmarTransaccion();
            return $idCompra;
        } catch (\Throwable $excepcion) {
            // Revierte los cambios porque la operación falló.
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
