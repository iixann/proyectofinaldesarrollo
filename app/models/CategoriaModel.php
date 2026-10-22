<?php

namespace app\models;

use \Model;
use \DataBase;

class CategoriaModel extends Model
{
    protected $tabla = 'categorias';
    protected $clavePrimaria = 'id_categoria';

    public static function listarCategorias()
    {
        return DataBase::query('SELECT * FROM categorias ORDER BY id_categoria');
    }

    public static function buscarPorNombre($nombre)
    {
        return DataBase::getRecord('SELECT * FROM categorias WHERE nombre_categoria = :nombre', ['nombre' => $nombre]);
    }

    public static function nombreDuplicado($nombre, $id = null)
    {
        $categoria = self::buscarPorNombre($nombre);
        return $categoria && (string) $categoria->id_categoria !== (string) $id;
    }

    public static function crearCategoria($nombre)
    {
        return DataBase::insert('INSERT INTO categorias (nombre_categoria) VALUES (:nombre)', ['nombre' => $nombre]);
    }

    public static function actualizarCategoria($id, $nombre)
    {
        return DataBase::execute('UPDATE categorias SET nombre_categoria = :nombre WHERE id_categoria = :id', ['nombre' => $nombre, 'id' => $id]);
    }

    public static function bajaCategoria($id)
    {
        return DataBase::execute('UPDATE categorias SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_categoria = :id AND estado = 1', ['id' => $id]);
    }
    public static function reactivar($id)
    {
        if (!self::findId($id)) {
            throw new \DomainException('El registro no existe.');
        }
        return DataBase::execute('UPDATE categorias SET estado = 1, fecha_baja = NULL WHERE id_categoria = :id', ['id' => $id]);
    }

}
