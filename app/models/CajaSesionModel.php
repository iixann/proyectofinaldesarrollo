<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de sesiones de caja en la base.
class CajaSesionModel extends Model
{
    protected $tabla = 'caja_sesiones';
    protected $clavePrimaria = 'id_caja_sesion';

    // Devuelve las sesiones con la caja y el usuario de apertura.
    public static function listarSesiones()
    {
        return DataBase::query('SELECT s.*, c.nombre AS caja, u.nombre, u.apellido FROM caja_sesiones s
            INNER JOIN cajas c ON c.id_caja = s.id_caja
            INNER JOIN usuarios u ON u.id_usuario = s.id_usuario_apertura
            ORDER BY s.fecha_apertura DESC, s.id_caja_sesion DESC');
    }

    // Al vendedor solo le informa disponibilidad y el ID de su propia sesión.
    public static function disponibilidadOperativa($idUsuario)
    {
        return DataBase::query("SELECT c.id_caja, c.nombre,
            CASE WHEN s.id_caja_sesion IS NULL THEN 'DISPONIBLE'
                WHEN s.id_usuario_apertura=:usuario THEN 'PROPIA' ELSE 'OCUPADA' END AS disponibilidad,
            CASE WHEN s.id_usuario_apertura=:propietario THEN s.id_caja_sesion ELSE NULL END AS mi_sesion
            FROM cajas c LEFT JOIN caja_sesiones s ON s.id_caja=c.id_caja AND s.estado='ABIERTA'
            WHERE c.estado=1 ORDER BY c.nombre", ['usuario'=>$idUsuario, 'propietario'=>$idUsuario]);
    }

    // Ofrece únicamente cajas libres; el bloqueo al abrir vuelve a comprobarlo.
    public static function cajasDisponibles()
    {
        return DataBase::query("SELECT c.id_caja,c.nombre FROM cajas c WHERE c.estado=1
            AND NOT EXISTS (SELECT 1 FROM caja_sesiones s WHERE s.id_caja=c.id_caja AND s.estado='ABIERTA') ORDER BY c.nombre");
    }

    // Busca la sesión con los datos de caja, apertura y cierre.
    public static function buscarSesion($id, $idUsuario = null)
    {
        return DataBase::getRecord('SELECT s.*, c.nombre AS caja, u.nombre, u.apellido,
            uc.nombre AS nombre_cierre, uc.apellido AS apellido_cierre FROM caja_sesiones s
            INNER JOIN cajas c ON c.id_caja = s.id_caja
            INNER JOIN usuarios u ON u.id_usuario = s.id_usuario_apertura
            LEFT JOIN usuarios uc ON uc.id_usuario = s.id_usuario_cierre
            WHERE s.id_caja_sesion = :id' . ($idUsuario !== null ? ' AND s.id_usuario_apertura=:usuario' : ''),
            $idUsuario !== null ? ['id'=>$id,'usuario'=>$idUsuario] : ['id'=>$id]);
    }

    // Devuelve las cajas habilitadas para una apertura.
    public static function cajasActivas()
    {
        return DataBase::query('SELECT id_caja, nombre FROM cajas WHERE estado = 1 ORDER BY nombre');
    }

    // Devuelve las sesiones abiertas del vendedor en cajas activas.
    public static function sesionesDisponiblesPos($idUsuario)
    {
        return DataBase::query("SELECT s.id_caja_sesion, s.id_caja, c.nombre AS caja, u.nombre, u.apellido
            FROM caja_sesiones s INNER JOIN cajas c ON c.id_caja = s.id_caja
            INNER JOIN usuarios u ON u.id_usuario = s.id_usuario_apertura
            WHERE s.estado = 'ABIERTA' AND c.estado = 1 AND s.id_usuario_apertura = :usuario
            ORDER BY c.nombre, s.id_caja_sesion", ['usuario' => $idUsuario]);
    }

    // Busca una sesión propia abierta en una caja activa.
    public static function sesionDisponiblePos($id, $idUsuario)
    {
        return DataBase::getRecord("SELECT s.id_caja_sesion, s.id_caja, c.nombre AS caja
            FROM caja_sesiones s INNER JOIN cajas c ON c.id_caja = s.id_caja
            WHERE s.id_caja_sesion = :id AND s.estado = 'ABIERTA' AND c.estado = 1
            AND s.id_usuario_apertura = :usuario", ['id' => $id, 'usuario' => $idUsuario]);
    }

    // Valida el importe y lo convierte a centavos enteros.
    public static function centavos($importe)
    {
        if (!is_string($importe) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $importe)) {
            throw new \DomainException('Ingresá un importe mayor o igual a cero, con hasta dos decimales y diez dígitos enteros.');
        }
        $partes = explode('.', $importe);
        return (int) $partes[0] * 100 + (int) str_pad($partes[1] ?? '', 2, '0');
    }

    // Convierte centavos a un importe con dos decimales.
    public static function decimal($centavos)
    {
        $signo = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);
        return $signo . intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    // Registra la apertura si la caja está activa y no tiene sesión abierta.
    public static function abrir($idCaja, $idUsuario, $monto)
    {
        $monto = self::decimal(self::centavos($monto));
        // Agrupa los cambios para evitar que se guarden incompletos.
        DataBase::iniciarTransaccion();
        try {
            // Bloquea el registro hasta terminar para evitar cambios simultáneos.
            $caja = DataBase::getRecord('SELECT id_caja, estado FROM cajas WHERE id_caja = :id FOR UPDATE', ['id' => $idCaja]);
            // Solo permite operar con una caja existente y activa.
            if (!$caja || (int) $caja->estado !== 1) {
                throw new \DomainException('La caja no existe o está inactiva.');
            }
            // El bloqueo de la caja serializa aperturas incluso cuando no hay sesiones.
            if (DataBase::getRecord("SELECT id_caja_sesion FROM caja_sesiones WHERE id_caja = :id AND estado = 'ABIERTA' FOR UPDATE", ['id' => $idCaja])) {
                throw new \DomainException('La caja ya tiene una sesión abierta.');
            }
            // Guarda un registro en caja_sesiones.
            $id = DataBase::insert("INSERT INTO caja_sesiones (id_caja, id_usuario_apertura, monto_inicial, estado)
                VALUES (:caja, :usuario, :monto, 'ABIERTA')", ['caja' => $idCaja, 'usuario' => $idUsuario, 'monto' => $monto]);
            // Confirma todos los cambios realizados en la base.
            DataBase::confirmarTransaccion();
            return $id;
        } catch (\Throwable $excepcion) {
            // Revierte los cambios porque la operación falló.
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    // Calcula ventas, efectivo cobrado y efectivo esperado en caja.
    public static function resumen($sesion)
    {
        $ventas = DataBase::getRecord('SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE id_caja_sesion = :id AND estado = 1', ['id' => $sesion->id_caja_sesion]);
        $efectivo = DataBase::getRecord("SELECT COALESCE(SUM(p.monto), 0) AS total FROM pagos_ventas p
            INNER JOIN ventas v ON v.id_venta = p.id_venta INNER JOIN medios_pago m ON m.id_medio_pago = p.id_medio_pago
            WHERE v.id_caja_sesion = :id AND v.estado = 1 AND m.nombre = 'EFECTIVO'", ['id' => $sesion->id_caja_sesion]);
        $totalVentas = self::centavos((string) $ventas->total);
        $totalEfectivo = self::centavos((string) $efectivo->total);
        $esperado = self::centavos((string) $sesion->monto_inicial) + $totalEfectivo;
        // Impide guardar un efectivo esperado fuera del rango permitido.
        if ($esperado > 999999999999) {
            throw new \DomainException('El efectivo esperado supera el máximo permitido.');
        }
        return ['total_ventas' => self::decimal($totalVentas), 'total_efectivo' => self::decimal($totalEfectivo), 'efectivo_esperado' => self::decimal($esperado)];
    }

    // Devuelve lo cobrado en la sesión agrupado por medio de pago.
    public static function totalesPorMedio($id)
    {
        return DataBase::query('SELECT m.nombre, SUM(p.monto) AS total FROM pagos_ventas p
            INNER JOIN ventas v ON v.id_venta = p.id_venta
            INNER JOIN medios_pago m ON m.id_medio_pago = p.id_medio_pago
            WHERE v.id_caja_sesion = :id AND v.estado = 1 GROUP BY m.id_medio_pago, m.nombre ORDER BY m.nombre', ['id' => $id]);
    }

    // Indica si los roles y el usuario permiten cerrar esa sesión.
    public static function puedeCerrar($idApertura, $idUsuario, array $roles)
    {
        return in_array('ADMINISTRADOR', $roles, true) || in_array('DUEÑO', $roles, true)
            || (in_array('VENDEDOR', $roles, true) && (string) $idApertura === (string) $idUsuario);
    }

    // Guarda el cierre y la diferencia entre efectivo contado y esperado.
    public static function cerrar($id, $idUsuario, $contado, $observacion, array $roles)
    {
        // Agrupa los cambios para evitar que se guarden incompletos.
        DataBase::iniciarTransaccion();
        try {
            // Bloquea el registro hasta terminar para evitar cambios simultáneos.
            $sesion = DataBase::getRecord('SELECT * FROM caja_sesiones WHERE id_caja_sesion = :id FOR UPDATE', ['id' => $id]);
            // Solo permite cerrar la sesión al usuario autorizado.
            if ($sesion && !self::puedeCerrar($sesion->id_usuario_apertura, $idUsuario, $roles)) {
                throw new \DomainException('No tenés permiso para cerrar esta sesión de caja.', 403);
            }
            // Si la sesión no está abierta, impide continuar la operación.
            if (!$sesion || $sesion->estado !== 'ABIERTA') {
                throw new \DomainException('La sesión no existe o ya está cerrada.');
            }
            // Las ventas futuras también deberán bloquear esta sesión antes de confirmarse.
            $resumen = self::resumen($sesion);
            $centavosContados = self::centavos($contado);
            if (mb_strlen($observacion, 'UTF-8') > 255) {
                throw new \DomainException('La observación debe tener hasta 255 caracteres.');
            }
            // Actualiza los datos de caja_sesiones.
            DataBase::execute("UPDATE caja_sesiones SET id_usuario_cierre = :usuario, fecha_cierre = CURRENT_TIMESTAMP,
                total_ventas = :total_ventas, total_efectivo = :total_efectivo, efectivo_esperado = :efectivo_esperado,
                efectivo_contado = :contado, diferencia = :diferencia, estado = 'CERRADA', observacion_cierre = :observacion
                WHERE id_caja_sesion = :id", array_merge($resumen, [
                'usuario' => $idUsuario, 'contado' => self::decimal($centavosContados),
                'diferencia' => self::decimal($centavosContados - self::centavos($resumen['efectivo_esperado'])),
                'observacion' => $observacion === '' ? null : $observacion, 'id' => $id
            ]));
            // Confirma todos los cambios realizados en la base.
            DataBase::confirmarTransaccion();
        } catch (\Throwable $excepcion) {
            // Revierte los cambios porque la operación falló.
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }
}
