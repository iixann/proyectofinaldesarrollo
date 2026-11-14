<?php

// Centraliza la conexión PDO y las consultas a la base de datos.
class DataBase
{
    private static $conexion;

    // Impide crear instancias; se usan los métodos estáticos.
    private function __construct() {}

    // Devuelve la conexión compartida o la crea desde el entorno.
    public static function connection()
    {
        // Abre una conexión solo la primera vez y luego la reutiliza.
        if (self::$conexion === null) {
            $servidor = $_ENV['DB_HOST'];
            $nombreBase = $_ENV['DB_NAME'];
            $usuario = $_ENV['DB_USER'];
            $contrasena = $_ENV['DB_PASS'];
            $origen = "mysql:host=" . $servidor . ";dbname=" . $nombreBase . ";charset=utf8mb4";
            $opciones = [
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];
            // Conecta a MySQL mediante PDO con las credenciales del entorno.
            $conexion = new PDO($origen, $usuario, $contrasena, $opciones);
            $conexion->exec('SET time_zone = "-03:00"');
            self::$conexion = $conexion;
        }
        // Devuelve la conexión para que los modelos operen sobre la misma base.
        return self::$conexion;
    }

    // Ejecuta una consulta y devuelve una lista de objetos.
    public static function query($sql, $parametros = [])
    {
        // Prepara el SQL; los valores se envían por separado al ejecutar.
        $sentencia = static::connection()->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia->fetchAll(PDO::FETCH_OBJ);
    }

    // Devuelve un registro como objeto o false si no existe.
    public static function getRecord($sql, $parametros = [])
    {
        // Prepara el SQL; los valores se envían por separado al ejecutar.
        $sentencia = static::connection()->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia->fetch(PDO::FETCH_OBJ);
    }

    // Devuelve los registros junto con el estado de la consulta.
    public static function getRecords($sql, $parametros = [])
    {
        $resultado['status'] = true;
        try {
            $resultado['resultado'] = static::query($sql, $parametros);
        } catch (PDOException $excepcion) {
            error_log($excepcion->getMessage());
            $resultado['status'] = false;
            $resultado['resultado'] = [];
        }
        return $resultado;
    }

    // Ejecuta una escritura y devuelve si pudo realizarse.
    public static function execute($sql, $parametros = [])
    {
        // Prepara el SQL; los valores se envían por separado al ejecutar.
        $sentencia = static::connection()->prepare($sql);
        return $sentencia->execute($parametros);
    }

    // Inserta un registro y devuelve su ID generado.
    public static function insert($sql, $parametros = [])
    {
        static::execute($sql, $parametros);
        return static::connection()->lastInsertId();
    }

    // Inicia un grupo de cambios que deben guardarse juntos.
    public static function iniciarTransaccion()
    {
        return static::connection()->beginTransaction();
    }

    // Guarda todos los cambios de la transacción.
    public static function confirmarTransaccion()
    {
        return static::connection()->commit();
    }

    // Revierte los cambios si hay una transacción abierta.
    public static function cancelarTransaccion()
    {
        if (static::connection()->inTransaction()) {
            static::connection()->rollBack();
        }
    }
}
