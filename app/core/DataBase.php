<?php

class DataBase
{
    private static $conexion;

    private function __construct() {}

    public static function connection()
    {
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
            $conexion = new PDO($origen, $usuario, $contrasena, $opciones);
            $conexion->exec('SET time_zone = "-03:00"');
            self::$conexion = $conexion;
        }
        return self::$conexion;
    }

    public static function query($sql, $parametros = [])
    {
        $sentencia = static::connection()->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia->fetchAll(PDO::FETCH_OBJ);
    }

    public static function getRecord($sql, $parametros = [])
    {
        $sentencia = static::connection()->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia->fetch(PDO::FETCH_OBJ);
    }

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

    public static function execute($sql, $parametros = [])
    {
        $sentencia = static::connection()->prepare($sql);
        return $sentencia->execute($parametros);
    }

    public static function insert($sql, $parametros = [])
    {
        static::execute($sql, $parametros);
        return static::connection()->lastInsertId();
    }

    public static function iniciarTransaccion()
    {
        return static::connection()->beginTransaction();
    }

    public static function confirmarTransaccion()
    {
        return static::connection()->commit();
    }

    public static function cancelarTransaccion()
    {
        if (static::connection()->inTransaction()) {
            static::connection()->rollBack();
        }
    }
}
