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

    // Valida la selección activa y devuelve sus IDs sin duplicados.
    public static function validarCombinacionRoles($seleccion)
    {
        if (!is_array($seleccion) || !$seleccion) throw new \DomainException('Seleccioná al menos un rol.');
        $activos = [];
        foreach (self::obtenerRolesActivos() as $rol) $activos[(string) $rol->id_rol] = $rol->nombre_rol;
        $ids = [];
        foreach ($seleccion as $id) {
            // Rechaza IDs manipulados, desconocidos y roles inactivos.
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id)
                || !isset($activos[(string) $id]) || !in_array($activos[(string) $id], ['ADMINISTRADOR','DUEÑO','VENDEDOR','RECEPCIONISTA'], true)) {
                throw new \DomainException('Seleccioná solamente roles activos válidos.');
            }
            $ids[(int) $id] = $activos[(string) $id];
        }
        // Administrador y dueño siempre se asignan solos.
        if (count($ids) > 1 && array_intersect(array_values($ids), ['ADMINISTRADOR','DUEÑO'])) {
            throw new \DomainException('ADMINISTRADOR y DUEÑO son exclusivos. Solo VENDEDOR y RECEPCIONISTA pueden combinarse.');
        }
        return array_keys($ids);
    }

    // Recupera datos y roles vigentes para autorizar con información real.
    public static function usuarioConRoles($id)
    {
        $usuario = self::findId($id);
        if ($usuario) $usuario->roles = self::obtenerRolesUsuario($id);
        return $usuario;
    }

    // El principal puede gestionar otros administradores; nadie cambia al principal.
    public static function puedeCambiarRoles($actor, $objetivo)
    {
        return $actor && $objetivo && (int) $actor->estado === 1
            && in_array('ADMINISTRADOR', array_column($actor->roles, 'nombre_rol'), true)
            && (int) $objetivo->es_principal !== 1
            && ((int) $actor->es_principal === 1 || !in_array('ADMINISTRADOR', array_column($objetivo->roles, 'nombre_rol'), true));
    }

    // Cambia asignaciones y auditoría juntas, conservando el historial anterior.
    public static function cambiarRoles($idUsuario, $seleccion, $idActor)
    {
        DataBase::iniciarTransaccion();
        try {
            // Usa el mismo bloqueo que las bajas para proteger al último administrador.
            DataBase::query('SELECT id_usuario FROM usuarios ORDER BY id_usuario FOR UPDATE');
            $actor = self::usuarioConRoles($idActor);
            $objetivo = self::usuarioConRoles($idUsuario);
            if (!$actor || (int) $actor->estado !== 1 || !in_array('ADMINISTRADOR', array_column($actor->roles, 'nombre_rol'), true)) {
                throw new \DomainException('Acceso denegado.', 403);
            }
            if (!$objetivo) throw new \DomainException('El usuario no existe.', 404);
            // El formulario nunca altera la identidad protegida del principal.
            if ((int) $objetivo->es_principal === 1) throw new \DomainException('Los roles del administrador principal están protegidos.', 403);
            $roles = self::validarCombinacionRoles($seleccion);
            $nombres = [];
            foreach (self::obtenerRolesActivos() as $rol) if (in_array((int) $rol->id_rol, $roles, true)) $nombres[] = $rol->nombre_rol;
            $anteriores = array_column($objetivo->roles, 'nombre_rol');
            // Nunca deja al sistema sin un administrador activo, incluso en instalaciones antiguas.
            if ((int) $objetivo->estado === 1 && in_array('ADMINISTRADOR', $anteriores, true) && !in_array('ADMINISTRADOR', $nombres, true)) {
                $otros = DataBase::getRecord("SELECT COUNT(DISTINCT u.id_usuario) AS total FROM usuarios u
                    JOIN usuarios_roles ur ON ur.id_usuario=u.id_usuario JOIN roles r ON r.id_rol=ur.id_rol
                    WHERE u.estado=1 AND u.id_usuario<>:id AND ur.estado=1 AND r.estado=1 AND r.nombre_rol='ADMINISTRADOR'
                    AND ur.fecha_desde<=CURRENT_TIMESTAMP AND (ur.fecha_hasta IS NULL OR ur.fecha_hasta>CURRENT_TIMESTAMP)", ['id'=>$idUsuario]);
                if ((int) $otros->total === 0) throw new \DomainException('No se puede quitar el rol al último administrador activo.', 409);
            }
            if (!self::puedeCambiarRoles($actor, $objetivo)) throw new \DomainException('Solo el administrador principal puede cambiar roles de administradores.', 403);
            // Cierra también asignaciones futuras; una cancelación nunca termina antes de comenzar.
            DataBase::execute('UPDATE usuarios_roles SET estado=0, fecha_hasta=GREATEST(CURRENT_TIMESTAMP,fecha_desde)
                WHERE id_usuario=:id AND estado=1', ['id'=>$idUsuario]);
            self::asignarRoles($idUsuario, $roles);
            // Si la auditoría falla, se revierten tanto el cierre como las nuevas asignaciones.
            AuditoriaModel::registrarCambioRoles($idActor, $idUsuario, $anteriores, $nombres);
            DataBase::confirmarTransaccion();
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    // Devuelve los usuarios con sus roles para el listado.
    public static function listarUsuarios()
    {
        $usuarios = DataBase::query("SELECT id_usuario, nombre, apellido, email, estado, es_principal FROM usuarios ORDER BY id_usuario");
        foreach ($usuarios as $usuario) {
            $usuario->roles = self::obtenerRolesUsuario($usuario->id_usuario);
        }
        return $usuarios;
    }

    // Da de baja el usuario sin borrar su historial.
    public static function baja($id)
    {
        DataBase::iniciarTransaccion();
        try {
            // Serializa las bajas para no desactivar simultáneamente a los últimos administradores.
            DataBase::query('SELECT id_usuario FROM usuarios ORDER BY id_usuario FOR UPDATE');
            $usuario = self::findId($id);
            if (!$usuario) throw new \DomainException('El usuario no existe.');
            // Evita dar de baja al principal aunque se invoque el endpoint manualmente.
            if ((int) $usuario->es_principal === 1) throw new \DomainException('El usuario principal no puede darse de baja.', 403);
            $roles = array_column(self::obtenerRolesUsuario($id), 'nombre_rol');
            if ((int) $usuario->estado === 1 && in_array('ADMINISTRADOR', $roles, true)) {
                $otros = DataBase::getRecord("SELECT COUNT(DISTINCT u.id_usuario) AS total FROM usuarios u
                    JOIN usuarios_roles ur ON ur.id_usuario=u.id_usuario JOIN roles r ON r.id_rol=ur.id_rol
                    WHERE u.estado=1 AND u.id_usuario<>:id AND ur.estado=1 AND r.estado=1 AND r.nombre_rol='ADMINISTRADOR'
                    AND ur.fecha_desde<=CURRENT_TIMESTAMP AND (ur.fecha_hasta IS NULL OR ur.fecha_hasta>CURRENT_TIMESTAMP)", ['id'=>$id]);
                if ((int) $otros->total === 0) throw new \DomainException('No se puede dar de baja al último administrador.', 409);
            }
            DataBase::execute('UPDATE usuarios SET estado=0, fecha_baja=CURRENT_TIMESTAMP WHERE id_usuario=:id', ['id'=>$id]);
            DataBase::confirmarTransaccion();
        } catch (\Throwable $excepcion) {
            DataBase::cancelarTransaccion();
            throw $excepcion;
        }
    }

    // Reactiva el usuario en la base de datos.
    public static function reactivar($id)
    {
        // Rechaza la reactivación administrativa del principal antes de escribir.
        $usuario = self::findId($id);
        if ($usuario && (int) $usuario->es_principal === 1) throw new \DomainException('Administrador Principal protegido.', 403);
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
        // El cambio personal de contraseña usa otro método; aquí solo hay administración.
        if ((int) $usuario->es_principal === 1) throw new \DomainException('Administrador Principal protegido.', 403);
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
        $roles = self::validarCombinacionRoles($roles);
        // También protege llamadas internas que intenten sumar roles a uno existente.
        self::validarCombinacionRoles(array_merge(array_column(self::obtenerRolesUsuario($idUsuario), 'id_rol'), $roles));
        foreach ($roles as $idRol) {
            DataBase::execute(
                "INSERT INTO usuarios_roles (id_usuario, id_rol, fecha_desde, fecha_hasta, estado)
                 VALUES (:idUsuario, :idRol, CURRENT_TIMESTAMP, NULL, 1)",
                ['idUsuario' => $idUsuario, 'idRol' => $idRol]
            );
        }
    }
}
