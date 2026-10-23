<?php

namespace app\models;

use \Model;
use \DataBase;

class CajaModel extends Model
{
    protected $tabla = 'cajas';
    protected $clavePrimaria = 'id_caja';

    public static function listarCajas()
    {
        return DataBase::query('SELECT * FROM cajas ORDER BY id_caja');
    }

    public static function buscarPorNombre($nombre)
    {
        return DataBase::getRecord('SELECT * FROM cajas WHERE nombre = :nombre', ['nombre' => $nombre]);
    }

    public static function nombreDuplicado($nombre, $id = null)
    {
        $caja = self::buscarPorNombre($nombre);
        return $caja && (string) $caja->id_caja !== (string) $id;
    }

    public static function crearCaja($datos)
    {
        return DataBase::insert('INSERT INTO cajas (nombre, descripcion) VALUES (:nombre, :descripcion)', $datos);
    }

    public static function actualizarCaja($id, $datos)
    {
        $datos['id'] = $id;
        return DataBase::execute('UPDATE cajas SET nombre = :nombre, descripcion = :descripcion WHERE id_caja = :id', $datos);
    }

    public static function bajaCaja($id)
    {
        return DataBase::execute('UPDATE cajas SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_caja = :id AND estado = 1', ['id' => $id]);
    }
    public static function reactivar($id)
    {
        if (!self::findId($id)) {
            throw new \DomainException('El registro no existe.');
        }
        return DataBase::execute('UPDATE cajas SET estado = 1, fecha_baja = NULL WHERE id_caja = :id', ['id' => $id]);
    }

}
