<?php

class Model
{
    protected $tabla;
    protected $clavePrimaria = "id";

    public static function findId($id)
    {
        $modelo = new static();
        $sql = "SELECT * FROM " . $modelo->tabla . " WHERE " . $modelo->clavePrimaria . " = :id";
        return DataBase::getRecord($sql, ["id" => $id]);
    }

    public static function getAll($configuracion = null)
    {
        $modelo = new static();
        $sql = "SELECT * FROM " . $modelo->tabla;
        return DataBase::getRecords($sql);
    }
}
