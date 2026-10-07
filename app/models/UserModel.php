<?php

namespace app\models;

use \Model;
use \DataBase;

class UserModel extends Model
{
    protected $tabla = "usuarios";
    protected $clavePrimaria = "id_usuario";

    public static function buscarPorCorreo($correo)
    {
        return DataBase::getRecord(
            "SELECT id_usuario, nombre, apellido, email, password, estado FROM usuarios WHERE email = :correo",
            ['correo' => $correo]
        );
    }

    public static function obtenerRolesUsuario($idUsuario)
    {
        return DataBase::query(
            "SELECT DISTINCT r.id_rol, r.nombre_rol FROM roles r
             INNER JOIN usuarios_roles ur ON ur.id_rol = r.id_rol
             WHERE ur.id_usuario = :idUsuario AND ur.estado = 1 AND r.estado = 1
             AND ur.fecha_desde <= CURRENT_TIMESTAMP
             AND (ur.fecha_hasta IS NULL OR ur.fecha_hasta > CURRENT_TIMESTAMP)
             ORDER BY r.nombre_rol",
            ['idUsuario' => $idUsuario]
        );
    }

    public static function obtenerRolesActivos()
    {
        return DataBase::query("SELECT id_rol, nombre_rol FROM roles WHERE estado = 1 ORDER BY nombre_rol");
    }

    public static function listarUsuarios()
    {
        $usuarios = DataBase::query("SELECT id_usuario, nombre, apellido, email, estado FROM usuarios ORDER BY id_usuario");
        foreach ($usuarios as $usuario) {
            $usuario->roles = self::obtenerRolesUsuario($usuario->id_usuario);
        }
        return $usuarios;
    }

    public static function crearUsuario($nombre, $apellido, $correo, $contrasena, $roles)
    {
        $contrasenaCifrada = password_hash($contrasena, PASSWORD_DEFAULT);
        DataBase::iniciarTransaccion();
        try {
            $idUsuario = DataBase::insert(
                "INSERT INTO usuarios (nombre, apellido, email, password, estado, requiere_cambio_password, fecha_alta, fecha_baja)
                 VALUES (:nombre, :apellido, :correo, :contrasena, 1, 0, CURRENT_TIMESTAMP, NULL)",
                ['nombre' => $nombre, 'apellido' => $apellido, 'correo' => $correo, 'contrasena' => $contrasenaCifrada]
            );
            self::asignarRoles($idUsuario, $roles);
            DataBase::confirmarTransaccion();
            return $idUsuario;
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    public static function asignarRoles($idUsuario, $roles)
    {
        foreach ($roles as $idRol) {
            DataBase::execute(
                "INSERT INTO usuarios_roles (id_usuario, id_rol, fecha_desde, fecha_hasta, estado)
                 VALUES (:idUsuario, :idRol, CURRENT_TIMESTAMP, NULL, 1)",
                ['idUsuario' => $idUsuario, 'idRol' => $idRol]
            );
        }
    }
}
