<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de categorías en la base.
class CategoriaModel extends Model
{
    protected $tabla = 'categorias';
    protected $clavePrimaria = 'id_categoria';

    // Devuelve el listado de categorías desde la base de datos.
    public static function listarCategorias($busqueda = '')
    {
        $sql = 'SELECT * FROM categorias';
        $parametros = [];
        if ($busqueda !== '') {
            $sql .= " WHERE nombre_categoria LIKE :busqueda ESCAPE '!'";
            $parametros = ['busqueda' => self::patronBusqueda($busqueda)];
        }
        return DataBase::query($sql . ' ORDER BY id_categoria', $parametros);
    }

    // Busca el registro que coincide con el nombre.
    public static function buscarPorNombre($nombre)
    {
        return DataBase::getRecord('SELECT * FROM categorias WHERE nombre_categoria = :nombre', ['nombre' => $nombre]);
    }

    // Comprueba si el nombre ya está registrado según los datos recibidos.
    public static function nombreDuplicado($nombre, $id = null)
    {
        $categoria = self::buscarPorNombre($nombre);
        return $categoria && (string) $categoria->id_categoria !== (string) $id;
    }

    // Inserta la categoría en la base de datos.
    public static function crearCategoria($nombre)
    {
        // Guarda un registro en categorias.
        return DataBase::insert('INSERT INTO categorias (nombre_categoria) VALUES (:nombre)', ['nombre' => $nombre]);
    }

    // Actualiza la categoría en la base de datos.
    public static function actualizarCategoria($id, $nombre)
    {
        // Actualiza los datos de categorias.
        return DataBase::execute('UPDATE categorias SET nombre_categoria = :nombre WHERE id_categoria = :id', ['nombre' => $nombre, 'id' => $id]);
    }

    // Da de baja sin eliminar la categoría en la base de datos.
    public static function bajaCategoria($id)
    {
        // Actualiza los datos de categorias.
        return DataBase::execute('UPDATE categorias SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_categoria = :id AND estado = 1', ['id' => $id]);
    }
    // Reactiva la categoría en la base de datos.
    public static function reactivar($id)
    {
        // Si el registro no existe, impide reactivarlo.
        if (!self::findId($id)) {
            throw new \DomainException('El registro no existe.');
        }
        // Actualiza los datos de categorias.
        return DataBase::execute('UPDATE categorias SET estado = 1, fecha_baja = NULL WHERE id_categoria = :id', ['id' => $id]);
    }

}
