<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de cajas en la base.
class CajaModel extends Model
{
    protected $tabla = 'cajas';
    protected $clavePrimaria = 'id_caja';

    // Devuelve el listado de cajas desde la base de datos.
    public static function listarCajas()
    {
        return DataBase::query('SELECT * FROM cajas ORDER BY id_caja');
    }

    // Busca el registro que coincide con el nombre.
    public static function buscarPorNombre($nombre)
    {
        return DataBase::getRecord('SELECT * FROM cajas WHERE nombre = :nombre', ['nombre' => $nombre]);
    }

    // Comprueba si el nombre ya está registrado según los datos recibidos.
    public static function nombreDuplicado($nombre, $id = null)
    {
        $caja = self::buscarPorNombre($nombre);
        return $caja && (string) $caja->id_caja !== (string) $id;
    }

    // Inserta la caja en la base de datos.
    public static function crearCaja($datos)
    {
        // Guarda un registro en cajas.
        return DataBase::insert('INSERT INTO cajas (nombre, descripcion) VALUES (:nombre, :descripcion)', $datos);
    }

    // Actualiza la caja en la base de datos.
    public static function actualizarCaja($id, $datos)
    {
        $datos['id'] = $id;
        // Actualiza los datos de cajas.
        return DataBase::execute('UPDATE cajas SET nombre = :nombre, descripcion = :descripcion WHERE id_caja = :id', $datos);
    }

    // Da de baja sin eliminar la caja en la base de datos.
    public static function bajaCaja($id)
    {
        // Actualiza los datos de cajas.
        return DataBase::execute('UPDATE cajas SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_caja = :id AND estado = 1', ['id' => $id]);
    }
    // Reactiva la caja en la base de datos.
    public static function reactivar($id)
    {
        // Si el registro no existe, impide reactivarlo.
        if (!self::findId($id)) {
            throw new \DomainException('El registro no existe.');
        }
        // Actualiza los datos de cajas.
        return DataBase::execute('UPDATE cajas SET estado = 1, fecha_baja = NULL WHERE id_caja = :id', ['id' => $id]);
    }

}
