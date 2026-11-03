<?php

namespace app\models;

use \Model;
use \DataBase;

class VentaModel extends Model
{
    protected $tabla = 'ventas';
    protected $clavePrimaria = 'id_venta';

    public static function mediosActivos()
    {
        return DataBase::query('SELECT id_medio_pago, nombre FROM medios_pago WHERE estado = 1 ORDER BY id_medio_pago');
    }

    private static function idValido($id)
    {
        return (is_int($id) || is_string($id)) && ctype_digit((string) $id)
            && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]) !== false;
    }

    public static function confirmar($idSesion, $productos, $pagos, $idUsuario)
    {
        if (!self::idValido($idSesion)) {
            throw new \DomainException('Seleccioná una sesión de caja válida.');
        }
        if (!is_array($productos) || !$productos || !is_array($pagos) || !$pagos) {
            throw new \DomainException('Agregá al menos un producto y un pago.');
        }
        $detalles = [];
        foreach ($productos as $producto) {
            if (!is_array($producto) || !self::idValido($producto['id_producto'] ?? null)) {
                throw new \DomainException('El producto recibido no es válido.');
            }
            $id = (int) $producto['id_producto'];
            $cantidad = $producto['cantidad'] ?? null;
            if ((!is_string($cantidad) && !is_int($cantidad)) || !ctype_digit((string) $cantidad)
                || filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
                throw new \DomainException('La cantidad debe ser un entero mayor a cero.');
            }
            if (isset($detalles[$id])) {
                throw new \DomainException('No se permite repetir un producto.');
            }
            $detalles[$id] = ['cantidad' => (int) $cantidad];
        }
        ksort($detalles, SORT_NUMERIC);
        $medios = [];
        foreach ($pagos as $pago) {
            if (!is_array($pago) || !self::idValido($pago['id_medio_pago'] ?? null)) {
                throw new \DomainException('El medio de pago recibido no es válido.');
            }
            $id = (int) $pago['id_medio_pago'];
            if (isset($medios[$id])) {
                throw new \DomainException('No se permite repetir un medio de pago.');
            }
            $medios[$id] = $pago;
        }
        ksort($medios, SORT_NUMERIC);
        DataBase::iniciarTransaccion();
        try {
            $sesion = DataBase::getRecord('SELECT id_caja, estado FROM caja_sesiones WHERE id_caja_sesion = :id FOR UPDATE', ['id' => $idSesion]);
            if (!$sesion || $sesion->estado !== 'ABIERTA') {
                throw new \DomainException('La sesión de caja ya no está abierta.', 409);
            }
            $caja = DataBase::getRecord('SELECT estado FROM cajas WHERE id_caja = :id FOR UPDATE', ['id' => $sesion->id_caja]);
            if (!$caja || (int) $caja->estado !== 1) {
                throw new \DomainException('La caja está inactiva.', 409);
            }
            $maximo = 999999999999; // DECIMAL(12,2), en centavos.
            $total = 0;
            foreach ($detalles as $id => &$detalle) {
                $producto = DataBase::getRecord('SELECT estado, precio_venta, stock_actual FROM productos WHERE id_producto = :id FOR UPDATE', ['id' => $id]);
                if (!$producto || (int) $producto->estado !== 1) {
                    throw new \DomainException('Un producto no existe o está inactivo. Revisá el carrito.');
                }
                if ((int) $producto->stock_actual < $detalle['cantidad']) {
                    throw new \DomainException('El stock disponible no alcanza para completar la venta.');
                }
                $precio = CajaSesionModel::centavos((string) $producto->precio_venta);
                if ($precio > intdiv($maximo, $detalle['cantidad'])) {
                    throw new \DomainException('El subtotal supera el importe máximo permitido.');
                }
                $subtotal = $precio * $detalle['cantidad'];
                if ($total > $maximo - $subtotal) {
                    throw new \DomainException('El total supera el importe máximo permitido.');
                }
                $total += $subtotal;
                $detalle['precio'] = CajaSesionModel::decimal($precio);
                $detalle['subtotal'] = CajaSesionModel::decimal($subtotal);
                $detalle['anterior'] = (int) $producto->stock_actual;
                $detalle['nuevo'] = $detalle['anterior'] - $detalle['cantidad'];
            }
            unset($detalle);
            $totalPagos = 0;
            foreach ($medios as $id => &$pago) {
                $medio = DataBase::getRecord('SELECT nombre, estado FROM medios_pago WHERE id_medio_pago = :id FOR UPDATE', ['id' => $id]);
                if (!$medio || (int) $medio->estado !== 1) {
                    throw new \DomainException('Un medio de pago no existe o está inactivo.');
                }
                $monto = CajaSesionModel::centavos($pago['monto'] ?? null);
                if ($monto <= 0 || $totalPagos > $maximo - $monto) {
                    throw new \DomainException('Cada pago debe ser mayor a cero y estar dentro del importe permitido.');
                }
                $totalPagos += $monto;
                $entregado = null;
                $vuelto = null;
                if ($medio->nombre === 'EFECTIVO') {
                    $centavosEntregados = CajaSesionModel::centavos($pago['monto_entregado'] ?? null);
                    if ($centavosEntregados < $monto) {
                        throw new \DomainException('El efectivo entregado no alcanza para cubrir el monto aplicado.');
                    }
                    $entregado = CajaSesionModel::decimal($centavosEntregados);
                    $vuelto = CajaSesionModel::decimal($centavosEntregados - $monto);
                }
                $referencia = $pago['referencia'] ?? '';
                if (!is_string($referencia) || !mb_check_encoding($referencia, 'UTF-8') || mb_strlen(trim($referencia), 'UTF-8') > 150) {
                    throw new \DomainException('La referencia debe tener hasta 150 caracteres.');
                }
                $pago = ['monto' => CajaSesionModel::decimal($monto), 'entregado' => $entregado, 'vuelto' => $vuelto,
                    'referencia' => trim($referencia) === '' ? null : trim($referencia)];
            }
            unset($pago);
            if ($totalPagos !== $total) {
                throw new \DomainException('La suma de pagos debe coincidir exactamente con el total real de la venta: $ ' . CajaSesionModel::decimal($total) . '.');
            }
            $idVenta = DataBase::insert('INSERT INTO ventas (id_caja_sesion, id_usuario, total, estado) VALUES (:sesion, :usuario, :total, 1)', [
                'sesion' => $idSesion, 'usuario' => $idUsuario, 'total' => CajaSesionModel::decimal($total)
            ]);
            foreach ($detalles as $id => $detalle) {
                DataBase::insert('INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal)
                    VALUES (:venta, :producto, :cantidad, :precio, :subtotal)', [
                    'venta' => $idVenta, 'producto' => $id, 'cantidad' => $detalle['cantidad'], 'precio' => $detalle['precio'], 'subtotal' => $detalle['subtotal']
                ]);
                DataBase::execute('UPDATE productos SET stock_actual = :stock WHERE id_producto = :id', ['stock' => $detalle['nuevo'], 'id' => $id]);
                DataBase::insert("INSERT INTO movimientos_stock (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, observacion)
                    VALUES (:producto, :usuario, 'VENTA', :cantidad, :anterior, :nuevo, :observacion)", [
                    'producto' => $id, 'usuario' => $idUsuario, 'cantidad' => $detalle['cantidad'], 'anterior' => $detalle['anterior'],
                    'nuevo' => $detalle['nuevo'], 'observacion' => 'Venta #' . $idVenta
                ]);
            }
            foreach ($medios as $id => $pago) {
                DataBase::insert('INSERT INTO pagos_ventas (id_venta, id_medio_pago, monto, monto_entregado, vuelto, referencia)
                    VALUES (:venta, :medio, :monto, :entregado, :vuelto, :referencia)', array_merge($pago, ['venta' => $idVenta, 'medio' => $id]));
            }
            DataBase::confirmarTransaccion();
            return ['id_venta' => (int) $idVenta, 'total' => CajaSesionModel::decimal($total)];
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
