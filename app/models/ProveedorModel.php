<?php

namespace app\models;

use \Model;
use \DataBase;

class ProveedorModel extends Model
{
    protected $tabla = 'proveedores';
    protected $clavePrimaria = 'id_proveedor';

    public static function listarProveedores()
    {
        return DataBase::query('SELECT * FROM proveedores ORDER BY id_proveedor');
    }

    public static function buscarPorCuit($cuit)
    {
        return DataBase::getRecord('SELECT * FROM proveedores WHERE cuit = :cuit', ['cuit' => $cuit]);
    }

    public static function cuitDuplicado($cuit, $id = null)
    {
        if ($cuit === null || $cuit === '') {
            return false;
        }
        $proveedor = self::buscarPorCuit($cuit);
        return $proveedor && (string) $proveedor->id_proveedor !== (string) $id;
    }

    private static function parametros($datosProveedor)
    {
        $parametros = ['razon_social' => $datosProveedor['razon_social']];
        foreach (['cuit', 'nombre_contacto', 'telefono', 'email', 'direccion'] as $campo) {
            // Los opcionales vacíos se guardan como NULL, permitiendo varios CUIT sin informar.
            $parametros[$campo] = $datosProveedor[$campo] === '' ? null : $datosProveedor[$campo];
        }
        return $parametros;
    }

    public static function crearProveedor($datosProveedor)
    {
        return DataBase::insert(
            'INSERT INTO proveedores (razon_social, cuit, nombre_contacto, telefono, email, direccion)
             VALUES (:razon_social, :cuit, :nombre_contacto, :telefono, :email, :direccion)',
            self::parametros($datosProveedor)
        );
    }

    public static function actualizarProveedor($id, $datosProveedor)
    {
        $parametros = self::parametros($datosProveedor);
        $parametros['id'] = $id;
        return DataBase::execute(
            'UPDATE proveedores SET razon_social = :razon_social, cuit = :cuit,
             nombre_contacto = :nombre_contacto, telefono = :telefono, email = :email,
             direccion = :direccion WHERE id_proveedor = :id',
            $parametros
        );
    }

    public static function bajaProveedor($id)
    {
        return DataBase::execute('UPDATE proveedores SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_proveedor = :id AND estado = 1', ['id' => $id]);
    }
    public static function reactivar($id)
    {
        if (!self::findId($id)) {
            throw new \DomainException('El registro no existe.');
        }
        return DataBase::execute('UPDATE proveedores SET estado = 1, fecha_baja = NULL WHERE id_proveedor = :id', ['id' => $id]);
    }

}
