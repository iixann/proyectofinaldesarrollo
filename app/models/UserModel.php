<?php

namespace app\models;

use \Model;
use \DataBase;

// Consulta y guarda los datos de usuarios y roles en la base.
class UserModel extends Model
{
    protected $tabla = "usuarios";
    protected $clavePrimaria = "id_usuario";

    // Busca el usuario por email para validar el acceso o evitar duplicados.
    public static function buscarPorCorreo($correo)
    {
        return DataBase::getRecord(
            "SELECT id_usuario, nombre, apellido, email, password, estado, debe_cambiar_password FROM usuarios WHERE email = :correo",
            ['correo' => $correo]
        );
    }

    // Devuelve los roles activos asignados al usuario.
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

    // Devuelve los roles habilitados para asignar a un usuario.
    public static function obtenerRolesActivos()
    {
        return DataBase::query("SELECT id_rol, nombre_rol FROM roles WHERE estado = 1 ORDER BY nombre_rol");
    }

    // Devuelve los usuarios con sus roles para el listado.
    public static function listarUsuarios()
    {
        $usuarios = DataBase::query("SELECT id_usuario, nombre, apellido, email, estado FROM usuarios ORDER BY id_usuario");
        foreach ($usuarios as $usuario) {
            $usuario->roles = self::obtenerRolesUsuario($usuario->id_usuario);
        }
        return $usuarios;
    }

    // Da de baja el usuario sin borrar su historial.
    public static function baja($id)
    {
        // Actualiza los datos de usuarios.
        return DataBase::execute('UPDATE usuarios SET estado = 0, fecha_baja = CURRENT_TIMESTAMP WHERE id_usuario = :id', ['id' => $id]);
    }

    // Reactiva el usuario en la base de datos.
    public static function reactivar($id)
    {
        // Actualiza los datos de usuarios.
        return DataBase::execute('UPDATE usuarios SET estado = 1, fecha_baja = NULL WHERE id_usuario = :id', ['id' => $id]);
    }

    // Rechaza contraseñas inválidas o una confirmación distinta.
    public static function validarContrasena($contrasena, $confirmacion = null)
    {
        // Exige una contraseña de al menos nueve caracteres válidos.
        if (!is_string($contrasena) || !mb_check_encoding($contrasena, 'UTF-8') || trim($contrasena) === ''
            || mb_strlen($contrasena, 'UTF-8') < 9) {
            throw new \DomainException('La contraseña debe tener al menos 9 caracteres.');
        }
        // Limita la contraseña a 72 bytes y rechaza caracteres nulos.
        if (strlen($contrasena) > 72 || strpos($contrasena, "\0") !== false) {
            throw new \DomainException('La contraseña debe tener hasta 72 bytes, sin caracteres nulos.');
        }
        // Si llega una confirmación, exige que coincida con la contraseña.
        if ($confirmacion !== null && (!is_string($confirmacion) || $contrasena !== $confirmacion)) {
            throw new \DomainException('Las contraseñas no coinciden.');
        }
    }

    // Guarda una clave temporal y obliga a cambiarla al ingresar.
    public static function restablecerContrasena($id, $contrasena)
    {
        self::validarContrasena($contrasena);
        $usuario = self::findId($id);
        // Si no encuentra el usuario, detiene la solicitud.
        if (!$usuario) {
            throw new \DomainException('El usuario no existe.');
        }
        // Impide reutilizar la contraseña que ya está guardada.
        if (password_verify($contrasena, $usuario->password)) {
            throw new \DomainException('La contraseña temporal debe ser distinta de la actual.');
        }
        // Actualiza los datos de usuarios.
        return DataBase::execute('UPDATE usuarios SET password = :password, debe_cambiar_password = 1 WHERE id_usuario = :id',
            ['password' => password_hash($contrasena, PASSWORD_DEFAULT), 'id' => $id]);
    }

    // Reemplaza la clave si su firma sigue vigente y devuelve la nueva firma.
    public static function cambiarContrasenaObligatoria($id, $contrasena, $firmaActual)
    {
        self::validarContrasena($contrasena);
        // Agrupa los cambios para evitar que se guarden incompletos.
        DataBase::iniciarTransaccion();
        try {
            // Bloquea el registro hasta terminar para evitar cambios simultáneos.
            $usuario = DataBase::getRecord('SELECT password, estado, debe_cambiar_password FROM usuarios WHERE id_usuario = :id FOR UPDATE', ['id' => $id]);
            // Exige un usuario activo y una contraseña temporal que siga vigente.
            if (!$usuario || (int) $usuario->estado !== 1 || (int) $usuario->debe_cambiar_password !== 1
                || !hash_equals(hash('sha256', $usuario->password), $firmaActual)) {
                throw new \DomainException('La sesión ya no permite este cambio. Volvé a iniciar sesión.');
            }
            // Impide reutilizar la contraseña que ya está guardada.
            if (password_verify($contrasena, $usuario->password)) {
                throw new \DomainException('Elegí una contraseña distinta de la temporal.');
            }
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            // Actualiza los datos de usuarios.
            DataBase::execute('UPDATE usuarios SET password = :password, debe_cambiar_password = 0 WHERE id_usuario = :id',
                ['password' => $hash, 'id' => $id]);
            // Confirma todos los cambios realizados en la base.
            DataBase::confirmarTransaccion();
            return hash('sha256', $hash);
        } catch (\Throwable $excepcion) {
            // Revierte los cambios porque la operación falló.
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    // Guarda el usuario y sus roles dentro de una transacción.
    public static function crearUsuario($nombre, $apellido, $correo, $contrasena, $roles)
    {
        self::validarContrasena($contrasena);
        $contrasenaCifrada = password_hash($contrasena, PASSWORD_DEFAULT);
        // Agrupa los cambios para evitar que se guarden incompletos.
        DataBase::iniciarTransaccion();
        try {
            $idUsuario = DataBase::insert(
                "INSERT INTO usuarios (nombre, apellido, email, password, estado, debe_cambiar_password, fecha_alta, fecha_baja)
                 VALUES (:nombre, :apellido, :correo, :contrasena, 1, 0, CURRENT_TIMESTAMP, NULL)",
                ['nombre' => $nombre, 'apellido' => $apellido, 'correo' => $correo, 'contrasena' => $contrasenaCifrada]
            );
            self::asignarRoles($idUsuario, $roles);
            // Confirma todos los cambios realizados en la base.
            DataBase::confirmarTransaccion();
            return $idUsuario;
        } catch (\Throwable $excepcion) {
            // Revierte los cambios porque la operación falló.
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    // Guarda las relaciones entre el usuario y los roles elegidos.
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
