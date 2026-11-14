<?php

// Comparte búsquedas básicas entre los modelos.
class Model
{
    protected $tabla;
    protected $clavePrimaria = "id";

    // Prepara una búsqueda parcial tratando % y _ como texto.
    protected static function patronBusqueda($texto)
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto) . '%';
    }

    // Busca por la clave primaria y devuelve el registro o false.
    public static function findId($id)
    {
        $modelo = new static();
        $sql = "SELECT * FROM " . $modelo->tabla . " WHERE " . $modelo->clavePrimaria . " = :id";
        return DataBase::getRecord($sql, ["id" => $id]);
    }

    // Devuelve los registros de la tabla del modelo y su estado.
    public static function getAll($configuracion = null)
    {
        $modelo = new static();
        $sql = "SELECT * FROM " . $modelo->tabla;
        return DataBase::getRecords($sql);
    }
}
