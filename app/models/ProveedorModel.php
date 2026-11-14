<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de proveedores en la base.
class ProveedorModel extends Model
{
    protected $tabla = 'proveedores';
    protected $clavePrimaria = 'id_proveedor';

    // Devuelve los proveedores filtrados por razón social o CUIT.
    public static function listarProveedores($busqueda = '')
    {
        $sql = 'SELECT * FROM proveedores';
        $parametros = [];
        if ($busqueda !== '') {
            $sql .= " WHERE razon_social LIKE :razon ESCAPE '!' OR cuit LIKE :cuit ESCAPE '!' OR nombre_contacto LIKE :contacto ESCAPE '!'";
            $parametros = ['razon' => self::patronBusqueda($busqueda), 'cuit' => self::patronBusqueda($busqueda), 'contacto' => self::patronBusqueda($busqueda)];
        }
        return DataBase::query($sql . ' ORDER BY id_proveedor', $parametros);
    }

    // Busca un proveedor por su CUIT.
    public static function buscarPorCuit($cuit)
    {
        return DataBase::getRecord('SELECT * FROM proveedores WHERE cuit = :cuit', ['cuit' => $cuit]);
    }

    // Indica si otro proveedor ya tiene ese CUIT.
    public static function cuitDuplicado($cuit, $id = null)
    {
        if ($cuit === null || $cuit === '') {
            return false;
        }
        $proveedor = self::buscarPorCuit($cuit);
        return $proveedor && (string) $proveedor->id_proveedor !== (string) $id;
    }

    // Valida los campos recibidos y devuelve los datos junto con los errores.
    public static function validarDatos($entrada, $id = null)
    {
        $datosProveedor = [];
        $errores = [];
        $campos = [
            'razon_social' => ['Razón social', 150],
            'cuit' => ['CUIT', 13],
            'nombre_contacto' => ['Nombre de contacto', 100],
            'telefono' => ['Teléfono', 50],
            'email' => ['Email', 150],
            'direccion' => ['Dirección', 200]
        ];
        foreach ($campos as $campo => [$etiqueta, $limite]) {
            $valor = is_string($entrada[$campo] ?? null) ? trim($entrada[$campo]) : '';
            $datosProveedor[$campo] = $valor;
            if (!mb_check_encoding($valor, 'UTF-8') || (isset($entrada[$campo]) && !is_string($entrada[$campo]))) {
                $errores[] = $etiqueta . ': ingresá un texto válido.';
            }
            if (mb_strlen($valor, 'UTF-8') > $limite) {
                $errores[] = $etiqueta . ': ingresá hasta ' . $limite . ' caracteres.';
            }
        }
        if ($datosProveedor['razon_social'] === '') {
            $errores[] = 'Ingresá una razón social.';
        }
        if ($datosProveedor['email'] !== '' && !filter_var($datosProveedor['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        // Evita guardar un CUIT que ya pertenece a otro proveedor.
        if ($datosProveedor['cuit'] !== '' && mb_check_encoding($datosProveedor['cuit'], 'UTF-8') && self::cuitDuplicado($datosProveedor['cuit'], $id)) {
            $errores[] = 'Ya existe un proveedor con ese CUIT.';
        }
        return [$datosProveedor, $errores];
    }

    // Prepara los valores que se enviarán a la consulta del proveedor.
    private static function parametros($datosProveedor)
    {
        $parametros = ['razon_social' => $datosProveedor['razon_social']];
        foreach (['cuit', 'nombre_contacto', 'telefono', 'email', 'direccion'] as $campo) {
            // Los opcionales vacíos se guardan como NULL, permitiendo varios CUIT sin informar.
            $parametros[$campo] = $datosProveedor[$campo] === '' ? null : $datosProveedor[$campo];
        }
        return $parametros;
    }

    // Inserta el proveedor en la base de datos.
    public static function crearProveedor($datosProveedor)
    {
        return DataBase::insert(
            'INSERT INTO proveedores (razon_social, cuit, nombre_contacto, telefono, email, direccion)
             VALUES (:razon_social, :cuit, :nombre_contacto, :telefono, :email, :direccion)',
            self::parametros($datosProveedor)
        );
    }

    // Actualiza el proveedor en la base de datos.
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

    // Da de baja sin eliminar el proveedor en la base de datos.
    public static function bajaProveedor($id)
    {
        // Actualiza los datos de proveedores.
        return DataBase::execute('UPDATE proveedores SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_proveedor = :id AND estado = 1', ['id' => $id]);
    }
    // Reactiva el proveedor en la base de datos.
    public static function reactivar($id)
    {
        // Si el registro no existe, impide reactivarlo.
        if (!self::findId($id)) {
            throw new \DomainException('El registro no existe.');
        }
        // Actualiza los datos de proveedores.
        return DataBase::execute('UPDATE proveedores SET estado = 1, fecha_baja = NULL WHERE id_proveedor = :id', ['id' => $id]);
    }

}
