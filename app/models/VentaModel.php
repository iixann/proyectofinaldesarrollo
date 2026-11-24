<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de ventas en la base.
class VentaModel extends Model
{
    protected $tabla = 'ventas';
    protected $clavePrimaria = 'id_venta';

    // Devuelve los medios de pago habilitados para el cobro.
    public static function mediosActivos()
    {
        return DataBase::query('SELECT id_medio_pago, nombre FROM medios_pago WHERE estado = 1 ORDER BY id_medio_pago');
    }

    // Devuelve hasta 100 ventas filtradas con su caja y vendedor.
    public static function listarVentas($desde = '', $hasta = '', $id = '', $idUsuario = null)
    {
        // Relaciona ventas con su sesión de caja, caja y vendedor.
        $sql = 'SELECT v.*, c.nombre AS caja, u.nombre, u.apellido FROM ventas v
            INNER JOIN caja_sesiones s ON s.id_caja_sesion = v.id_caja_sesion
            INNER JOIN cajas c ON c.id_caja = s.id_caja
            INNER JOIN usuarios u ON u.id_usuario = v.id_usuario WHERE 1 = 1';
        $parametros = [];
        // El vendedor solo recibe registros propios; el controlador decide este filtro.
        if ($idUsuario !== null) { $sql .= ' AND v.id_usuario=:usuario'; $parametros['usuario'] = $idUsuario; }
        if ($desde !== '') {
            $sql .= ' AND v.fecha_venta >= :desde';
            $parametros['desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= ' AND v.fecha_venta < DATE_ADD(:hasta, INTERVAL 1 DAY)';
            $parametros['hasta'] = $hasta;
        }
        if ($id !== '') {
            $sql .= ' AND v.id_venta = :id';
            $parametros['id'] = $id;
        }
        return DataBase::query($sql . ' ORDER BY v.fecha_venta DESC, v.id_venta DESC LIMIT 100', $parametros);
    }

    // Devuelve una venta con los datos de caja y vendedor.
    public static function buscarVenta($id, $idUsuario = null)
    {
        return DataBase::getRecord('SELECT v.*, s.estado AS estado_caja, c.nombre AS caja, u.nombre, u.apellido,
            (SELECT nueva.id_venta FROM ventas nueva WHERE nueva.id_venta_original=v.id_venta) AS id_venta_corregida FROM ventas v
            INNER JOIN caja_sesiones s ON s.id_caja_sesion = v.id_caja_sesion
            INNER JOIN cajas c ON c.id_caja = s.id_caja
            INNER JOIN usuarios u ON u.id_usuario = v.id_usuario WHERE v.id_venta = :id' . ($idUsuario !== null ? ' AND v.id_usuario=:usuario' : ''),
            $idUsuario !== null ? ['id'=>$id, 'usuario'=>$idUsuario] : ['id'=>$id]);
    }

    // Devuelve los productos y cantidades de la venta.
    public static function detallesVenta($id)
    {
        return DataBase::query('SELECT d.*, p.nombre_producto, p.codigo_barras FROM detalle_ventas d
            INNER JOIN productos p ON p.id_producto = d.id_producto WHERE d.id_venta = :id ORDER BY d.id_detalle_venta', ['id' => $id]);
    }

    // Devuelve los pagos de la venta con el nombre de cada medio.
    public static function pagosVenta($id)
    {
        return DataBase::query('SELECT p.*, m.nombre AS medio FROM pagos_ventas p
            INNER JOIN medios_pago m ON m.id_medio_pago = p.id_medio_pago WHERE p.id_venta = :id ORDER BY p.id_pago_venta', ['id' => $id]);
    }

    // Dueño y administrador pueden auditar y rectificar ventas de otros usuarios.
    public static function esSupervisor(array $roles)
    {
        return (bool) array_intersect(['ADMINISTRADOR', 'DUEÑO'], $roles);
    }

    // Una venta solo admite corrección mientras su caja está abierta y siga vigente.
    public static function exigirRectificable($venta, $estadoCaja, $actor, array $roles)
    {
        if (!self::esSupervisor($roles) && (string) $venta->id_usuario !== (string) $actor) throw new \DomainException('No podés rectificar la venta de otro vendedor.', 403);
        if ($estadoCaja !== 'ABIERTA') throw new \DomainException('Solo se pueden rectificar ventas de una caja ABIERTA.', 409);
        if ((int) $venta->estado !== 1 || $venta->estado_rectificacion !== 'VIGENTE') throw new \DomainException('La venta ya fue rectificada o no está vigente.', 409);
    }

    // Rectifica sin borrar: revierte stock y confirma la nueva venta de forma atómica.
    public static function rectificar($id, $productos, $pagos, $actor, array $roles, $motivo)
    {
        if (!self::idValido($id)) throw new \DomainException('ID de venta inválido.');
        if (!is_string($motivo) || !mb_check_encoding($motivo, 'UTF-8') || trim($motivo)==='' || mb_strlen(trim($motivo),'UTF-8')>255) throw new \DomainException('Ingresá el motivo de la rectificación, hasta 255 caracteres.');
        $venta = self::buscarVenta($id);
        if (!$venta) throw new \DomainException('La venta no existe.', 404);
        return self::confirmar($venta->id_caja_sesion, $productos, $pagos, $actor, $id, $roles, trim($motivo));
    }

    // Comprueba que el ID sea un entero positivo dentro del rango permitido.
    private static function idValido($id)
    {
        return (is_int($id) || is_string($id)) && ctype_digit((string) $id)
            && filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]) !== false;
    }

    // Valida y guarda la venta, sus pagos y la salida de stock juntos.
    public static function confirmar($idSesion, $productos, $pagos, $idUsuario, $rectifica = null, array $roles = [], $motivo = '')
    {
        if (!self::idValido($idSesion)) {
            throw new \DomainException('Seleccioná una sesión de caja válida.');
        }
        // Exige una lista de productos y otra de pagos antes de vender.
        if (!is_array($productos) || !$productos || !is_array($pagos) || !$pagos) {
            throw new \DomainException('Agregá al menos un producto y un pago.');
        }
        $detalles = [];
        foreach ($productos as $producto) {
            // Exige un producto válido en cada línea recibida.
            if (!is_array($producto) || !self::idValido($producto['id_producto'] ?? null)) {
                throw new \DomainException('El producto recibido no es válido.');
            }
            $id = (int) $producto['id_producto'];
            $cantidad = $producto['cantidad'] ?? null;
            // Rechaza cantidades o identificadores fuera del formato y rango permitido.
            if ((!is_string($cantidad) && !is_int($cantidad)) || !ctype_digit((string) $cantidad)
                || filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) === false) {
                throw new \DomainException('La cantidad debe ser un entero mayor a cero.');
            }
            // Evita repetir el mismo producto en la operación.
            if (isset($detalles[$id])) {
                throw new \DomainException('No se permite repetir un producto.');
            }
            $detalles[$id] = ['cantidad' => (int) $cantidad];
        }
        ksort($detalles, SORT_NUMERIC);
        $medios = [];
        foreach ($pagos as $pago) {
            // Exige un medio de pago válido en cada pago recibido.
            if (!is_array($pago) || !self::idValido($pago['id_medio_pago'] ?? null)) {
                throw new \DomainException('El medio de pago recibido no es válido.');
            }
            $id = (int) $pago['id_medio_pago'];
            // Evita repetir un medio de pago en la venta.
            if (isset($medios[$id])) {
                throw new \DomainException('No se permite repetir un medio de pago.');
            }
            $medios[$id] = $pago;
        }
        ksort($medios, SORT_NUMERIC);
        // Agrupa los cambios para evitar que se guarden incompletos.
        DataBase::iniciarTransaccion();
        try {
            // Bloquea el registro hasta terminar para evitar cambios simultáneos.
            $sesion = DataBase::getRecord('SELECT id_caja, estado, id_usuario_apertura FROM caja_sesiones WHERE id_caja_sesion = :id FOR UPDATE', ['id' => $idSesion]);
            // Si la sesión no está abierta, impide continuar la operación.
            if (!$sesion || $sesion->estado !== 'ABIERTA') {
                throw new \DomainException('La sesión de caja ya no está abierta.', 409);
            }
            $original = null;
            $idVendedor = $idUsuario;
            // La sesión se bloquea antes que la venta, igual que al cerrar la caja.
            if ($rectifica !== null) {
                $original = DataBase::getRecord('SELECT * FROM ventas WHERE id_venta=:id FOR UPDATE', ['id'=>$rectifica]);
                if (!$original || (string) $original->id_caja_sesion !== (string) $idSesion) throw new \DomainException('La venta no corresponde a esta caja.', 409);
                self::exigirRectificable($original, $sesion->estado, $idUsuario, $roles);
                $idVendedor = $original->id_usuario;
            }
            // Solo permite vender en una sesión abierta por el propio usuario.
            if ((string) $sesion->id_usuario_apertura !== (string) $idUsuario && !($original && self::esSupervisor($roles))) {
                throw new \DomainException('Solo podés vender en una sesión de caja que abriste.', 403);
            }
            // Bloquea el registro hasta terminar para evitar cambios simultáneos.
            $caja = DataBase::getRecord('SELECT estado FROM cajas WHERE id_caja = :id FOR UPDATE', ['id' => $sesion->id_caja]);
            // Solo permite operar con una caja existente y activa.
            if (!$caja || (int) $caja->estado !== 1) {
                throw new \DomainException('La caja está inactiva.', 409);
            }
            // Devuelve las existencias originales dentro de la misma transacción.
            if ($original) {
                $anteriores = DataBase::query('SELECT id_producto,cantidad FROM detalle_ventas WHERE id_venta=:id ORDER BY id_producto', ['id'=>$rectifica]);
                $devoluciones = [];
                foreach ($anteriores as $anterior) $devoluciones[(int) $anterior->id_producto] = (int) $anterior->cantidad;
                $ids = array_unique(array_merge(array_keys($detalles), array_keys($devoluciones)));
                sort($ids, SORT_NUMERIC);
                foreach ($ids as $id) {
                    // Bloquea la unión de productos en orden para evitar bloqueos cruzados.
                    $registro = DataBase::getRecord('SELECT stock_actual FROM productos WHERE id_producto=:id FOR UPDATE', ['id'=>$id]);
                    if (!$registro) throw new \DomainException('Un producto ya no existe.');
                    if (!isset($devoluciones[$id])) continue;
                    $anterior = (int) $registro->stock_actual;
                    $nuevo = $anterior + $devoluciones[$id];
                    if ($anterior < 0 || $nuevo > 2147483647) throw new \DomainException('La reversión supera el stock permitido.');
                    DataBase::execute('UPDATE productos SET stock_actual=:stock WHERE id_producto=:id', ['stock'=>$nuevo,'id'=>$id]);
                    DataBase::insert("INSERT INTO movimientos_stock (id_producto,id_usuario,tipo_movimiento,cantidad,stock_anterior,stock_nuevo,observacion)
                        VALUES (:producto,:usuario,'RECTIFICACION_ENTRADA',:cantidad,:anterior,:nuevo,:observacion)", [
                        'producto'=>$id,'usuario'=>$idUsuario,'cantidad'=>$devoluciones[$id],'anterior'=>$anterior,'nuevo'=>$nuevo,'observacion'=>'Reversión venta #'.$rectifica]);
                }
            }
            $maximo = 999999999999; // DECIMAL(12,2), en centavos.
            $total = 0;
            foreach ($detalles as $id => &$detalle) {
                // Bloquea el registro hasta terminar para evitar cambios simultáneos.
                $producto = DataBase::getRecord('SELECT estado, precio_venta, stock_actual FROM productos WHERE id_producto = :id FOR UPDATE', ['id' => $id]);
                // Exige que el producto exista y esté activo.
                if (!$producto || (int) $producto->estado !== 1) {
                    throw new \DomainException('Un producto no existe o está inactivo. Revisá el carrito.');
                }
                // Impide continuar si el stock no cumple el límite permitido.
                if ((int) $producto->stock_actual < $detalle['cantidad']) {
                    throw new \DomainException('El stock disponible no alcanza para completar la venta.');
                }
                // Usa el precio actual de la base para calcular el total real.
                $precio = CajaSesionModel::centavos((string) $producto->precio_venta);
                // Impide que el subtotal supere el importe máximo permitido.
                if ($precio > intdiv($maximo, $detalle['cantidad'])) {
                    throw new \DomainException('El subtotal supera el importe máximo permitido.');
                }
                $subtotal = $precio * $detalle['cantidad'];
                // Impide que el total acumulado supere el máximo permitido.
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
                // Bloquea el registro hasta terminar para evitar cambios simultáneos.
                $medio = DataBase::getRecord('SELECT nombre, estado FROM medios_pago WHERE id_medio_pago = :id FOR UPDATE', ['id' => $id]);
                // Exige que el medio de pago exista y esté habilitado.
                if (!$medio || (int) $medio->estado !== 1) {
                    throw new \DomainException('Un medio de pago no existe o está inactivo.');
                }
                $monto = CajaSesionModel::centavos($pago['monto'] ?? null);
                // Rechaza pagos sin importe o que superan el máximo permitido.
                if ($monto <= 0 || $totalPagos > $maximo - $monto) {
                    throw new \DomainException('Cada pago debe ser mayor a cero y estar dentro del importe permitido.');
                }
                $totalPagos += $monto;
                $entregado = null;
                $vuelto = null;
                // En efectivo, calcula el vuelto a partir del monto entregado.
                if ($medio->nombre === 'EFECTIVO') {
                    $centavosEntregados = CajaSesionModel::centavos($pago['monto_entregado'] ?? null);
                    // Si el efectivo no alcanza, rechaza el pago.
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
            // Solo confirma si los pagos cubren exactamente el total real.
            if ($totalPagos !== $total) {
                throw new \DomainException('La suma de pagos debe coincidir exactamente con el total real de la venta: $ ' . CajaSesionModel::decimal($total) . '.');
            }
            // Guarda un registro en ventas.
            $idVenta = DataBase::insert('INSERT INTO ventas (id_caja_sesion, id_usuario, total, estado, id_venta_original) VALUES (:sesion, :usuario, :total, 1, :original)', [
                'sesion' => $idSesion, 'usuario' => $idVendedor, 'total' => CajaSesionModel::decimal($total), 'original' => $rectifica
            ]);
            foreach ($detalles as $id => $detalle) {
                // Guarda un registro en detalle_ventas.
                DataBase::insert('INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal)
                    VALUES (:venta, :producto, :cantidad, :precio, :subtotal)', [
                    'venta' => $idVenta, 'producto' => $id, 'cantidad' => $detalle['cantidad'], 'precio' => $detalle['precio'], 'subtotal' => $detalle['subtotal']
                ]);
                // Actualiza los datos de productos.
                DataBase::execute('UPDATE productos SET stock_actual = :stock WHERE id_producto = :id', ['stock' => $detalle['nuevo'], 'id' => $id]);
                // Guarda un registro en movimientos_stock.
                DataBase::insert("INSERT INTO movimientos_stock (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, observacion)
                    VALUES (:producto, :usuario, 'VENTA', :cantidad, :anterior, :nuevo, :observacion)", [
                    'producto' => $id, 'usuario' => $idUsuario, 'cantidad' => $detalle['cantidad'], 'anterior' => $detalle['anterior'],
                    'nuevo' => $detalle['nuevo'], 'observacion' => 'Venta #' . $idVenta
                ]);
            }
            foreach ($medios as $id => $pago) {
                // Guarda un registro en pagos_ventas.
                DataBase::insert('INSERT INTO pagos_ventas (id_venta, id_medio_pago, monto, monto_entregado, vuelto, referencia)
                    VALUES (:venta, :medio, :monto, :entregado, :vuelto, :referencia)', array_merge($pago, ['venta' => $idVenta, 'medio' => $id]));
            }
            // Los pagos y detalles originales quedan intactos, pero dejan de sumar a caja.
            if ($original) {
                DataBase::execute("UPDATE ventas SET estado=0, estado_rectificacion='RECTIFICADA', id_usuario_rectifica=:actor,
                    fecha_rectificacion=CURRENT_TIMESTAMP, motivo_rectificacion=:motivo WHERE id_venta=:id", ['actor'=>$idUsuario,'motivo'=>$motivo,'id'=>$rectifica]);
            }
            // Confirma todos los cambios realizados en la base.
            DataBase::confirmarTransaccion();
            return ['id_venta' => (int) $idVenta, 'total' => CajaSesionModel::decimal($total)];
        } catch (\Throwable $excepcion) {
            // Revierte los cambios porque la operación falló.
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
