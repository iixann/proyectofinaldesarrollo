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
            "SELECT id_usuario, nombre, apellido, email, password, estado, debe_cambiar_password FROM usuarios WHERE email = :correo",
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

    public static function baja($id)
    {
        return DataBase::execute('UPDATE usuarios SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_usuario = :id', ['id' => $id]);
    }

    public static function reactivar($id)
    {
        return DataBase::execute('UPDATE usuarios SET estado = 1, fecha_baja = NULL WHERE id_usuario = :id', ['id' => $id]);
    }

    public static function validarContrasena($contrasena, $confirmacion = null)
    {
        if (!is_string($contrasena) || !mb_check_encoding($contrasena, 'UTF-8') || trim($contrasena) === ''
            || mb_strlen($contrasena, 'UTF-8') < 9) {
            throw new \DomainException('La contraseña debe tener al menos 9 caracteres.');
        }
        if (strlen($contrasena) > 72 || strpos($contrasena, "\0") !== false) {
            throw new \DomainException('La contraseña debe tener hasta 72 bytes, sin caracteres nulos.');
        }
        if ($confirmacion !== null && (!is_string($confirmacion) || $contrasena !== $confirmacion)) {
            throw new \DomainException('Las contraseñas no coinciden.');
        }
    }

    public static function restablecerContrasena($id, $contrasena)
    {
        self::validarContrasena($contrasena);
        $usuario = self::findId($id);
        if (!$usuario) {
            throw new \DomainException('El usuario no existe.');
        }
        if (password_verify($contrasena, $usuario->password)) {
            throw new \DomainException('La contraseña temporal debe ser distinta de la actual.');
        }
        return DataBase::execute('UPDATE usuarios SET password = :password, debe_cambiar_password = 1 WHERE id_usuario = :id',
            ['password' => password_hash($contrasena, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function cambiarContrasenaObligatoria($id, $contrasena, $firmaActual)
    {
        self::validarContrasena($contrasena);
        DataBase::iniciarTransaccion();
        try {
            $usuario = DataBase::getRecord('SELECT password, estado, debe_cambiar_password FROM usuarios WHERE id_usuario = :id FOR UPDATE', ['id' => $id]);
            if (!$usuario || (int) $usuario->estado !== 1 || (int) $usuario->debe_cambiar_password !== 1
                || !hash_equals(hash('sha256', $usuario->password), $firmaActual)) {
                throw new \DomainException('La sesión ya no permite este cambio. Volvé a iniciar sesión.');
            }
            if (password_verify($contrasena, $usuario->password)) {
                throw new \DomainException('Elegí una contraseña distinta de la temporal.');
            }
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            DataBase::execute('UPDATE usuarios SET password = :password, debe_cambiar_password = 0 WHERE id_usuario = :id',
                ['password' => $hash, 'id' => $id]);
            DataBase::confirmarTransaccion();
            return hash('sha256', $hash);
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    public static function crearUsuario($nombre, $apellido, $correo, $contrasena, $roles)
    {
        self::validarContrasena($contrasena);
        $contrasenaCifrada = password_hash($contrasena, PASSWORD_DEFAULT);
        DataBase::iniciarTransaccion();
        try {
            $idUsuario = DataBase::insert(
                "INSERT INTO usuarios (nombre, apellido, email, password, estado, debe_cambiar_password, fecha_alta, fecha_baja)
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
