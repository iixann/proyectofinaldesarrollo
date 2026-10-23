<?php

namespace app\models;

use \Model;
use \DataBase;

class CajaSesionModel extends Model
{
    protected $tabla = 'caja_sesiones';
    protected $clavePrimaria = 'id_caja_sesion';

    public static function listarSesiones()
    {
        return DataBase::query('SELECT s.*, c.nombre AS caja, u.nombre, u.apellido FROM caja_sesiones s
            INNER JOIN cajas c ON c.id_caja = s.id_caja
            INNER JOIN usuarios u ON u.id_usuario = s.id_usuario_apertura
            ORDER BY s.fecha_apertura DESC, s.id_caja_sesion DESC');
    }

    public static function buscarSesion($id)
    {
        return DataBase::getRecord('SELECT s.*, c.nombre AS caja, u.nombre, u.apellido,
            uc.nombre AS nombre_cierre, uc.apellido AS apellido_cierre FROM caja_sesiones s
            INNER JOIN cajas c ON c.id_caja = s.id_caja
            INNER JOIN usuarios u ON u.id_usuario = s.id_usuario_apertura
            LEFT JOIN usuarios uc ON uc.id_usuario = s.id_usuario_cierre
            WHERE s.id_caja_sesion = :id', ['id' => $id]);
    }

    public static function cajasActivas()
    {
        return DataBase::query('SELECT id_caja, nombre FROM cajas WHERE estado = 1 ORDER BY nombre');
    }

    public static function centavos($importe)
    {
        if (!is_string($importe) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $importe)) {
            throw new \DomainException('Ingresá un importe mayor o igual a cero, con hasta dos decimales y diez dígitos enteros.');
        }
        $partes = explode('.', $importe);
        return (int) $partes[0] * 100 + (int) str_pad($partes[1] ?? '', 2, '0');
    }

    public static function decimal($centavos)
    {
        $signo = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);
        return $signo . intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function abrir($idCaja, $idUsuario, $monto)
    {
        $monto = self::decimal(self::centavos($monto));
        DataBase::iniciarTransaccion();
        try {
            $caja = DataBase::getRecord('SELECT id_caja, estado FROM cajas WHERE id_caja = :id FOR UPDATE', ['id' => $idCaja]);
            if (!$caja || (int) $caja->estado !== 1) {
                throw new \DomainException('La caja no existe o está inactiva.');
            }
            // El bloqueo de la caja serializa aperturas incluso cuando no hay sesiones.
            if (DataBase::getRecord("SELECT id_caja_sesion FROM caja_sesiones WHERE id_caja = :id AND estado = 'ABIERTA' FOR UPDATE", ['id' => $idCaja])) {
                throw new \DomainException('La caja ya tiene una sesión abierta.');
            }
            $id = DataBase::insert("INSERT INTO caja_sesiones (id_caja, id_usuario_apertura, monto_inicial, estado)
                VALUES (:caja, :usuario, :monto, 'ABIERTA')", ['caja' => $idCaja, 'usuario' => $idUsuario, 'monto' => $monto]);
            DataBase::confirmarTransaccion();
            return $id;
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    public static function resumen($sesion)
    {
        $ventas = DataBase::getRecord('SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE id_caja_sesion = :id AND estado = 1', ['id' => $sesion->id_caja_sesion]);
        $efectivo = DataBase::getRecord("SELECT COALESCE(SUM(p.monto), 0) AS total FROM pagos_ventas p
            INNER JOIN ventas v ON v.id_venta = p.id_venta INNER JOIN medios_pago m ON m.id_medio_pago = p.id_medio_pago
            WHERE v.id_caja_sesion = :id AND v.estado = 1 AND m.nombre = 'EFECTIVO'", ['id' => $sesion->id_caja_sesion]);
        $totalVentas = self::centavos((string) $ventas->total);
        $totalEfectivo = self::centavos((string) $efectivo->total);
        $esperado = self::centavos((string) $sesion->monto_inicial) + $totalEfectivo;
        if ($esperado > 999999999999) {
            throw new \DomainException('El efectivo esperado supera el máximo permitido.');
        }
        return ['total_ventas' => self::decimal($totalVentas), 'total_efectivo' => self::decimal($totalEfectivo), 'efectivo_esperado' => self::decimal($esperado)];
    }

    public static function cerrar($id, $idUsuario, $contado, $observacion)
    {
        DataBase::iniciarTransaccion();
        try {
            $sesion = DataBase::getRecord('SELECT * FROM caja_sesiones WHERE id_caja_sesion = :id FOR UPDATE', ['id' => $id]);
            if (!$sesion || $sesion->estado !== 'ABIERTA') {
                throw new \DomainException('La sesión no existe o ya está cerrada.');
            }
            // Las ventas futuras también deberán bloquear esta sesión antes de confirmarse.
            $resumen = self::resumen($sesion);
            $centavosContados = self::centavos($contado);
            if (mb_strlen($observacion, 'UTF-8') > 255) {
                throw new \DomainException('La observación debe tener hasta 255 caracteres.');
            }
            DataBase::execute("UPDATE caja_sesiones SET id_usuario_cierre = :usuario, fecha_cierre = CURRENT_TIMESTAMP,
                total_ventas = :total_ventas, total_efectivo = :total_efectivo, efectivo_esperado = :efectivo_esperado,
                efectivo_contado = :contado, diferencia = :diferencia, estado = 'CERRADA', observacion_cierre = :observacion
                WHERE id_caja_sesion = :id", array_merge($resumen, [
                'usuario' => $idUsuario, 'contado' => self::decimal($centavosContados),
                'diferencia' => self::decimal($centavosContados - self::centavos($resumen['efectivo_esperado'])),
                'observacion' => $observacion === '' ? null : $observacion, 'id' => $id
            ]));
            DataBase::confirmarTransaccion();
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
