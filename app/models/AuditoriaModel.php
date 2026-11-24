<?php

namespace app\models;

use \Model;
use \DataBase;

// Conserva quién realizó los cambios de catálogo sin sustituir movimientos de stock.
class AuditoriaModel extends Model
{
    // Guarda la operación dentro de la transacción del producto.
    public static function registrarProducto($idUsuario, $accion, $idProducto, $detalle)
    {
        DataBase::insert("INSERT INTO auditoria_operaciones (id_usuario,accion,entidad,id_entidad,detalle)
            VALUES (:usuario,:accion,'PRODUCTO',:producto,:detalle)",
            ['usuario'=>$idUsuario, 'accion'=>$accion, 'producto'=>$idProducto, 'detalle'=>$detalle]);
    }

    // Devuelve las últimas operaciones del producto con su responsable.
    public static function listarProducto($idProducto)
    {
        return DataBase::query("SELECT a.accion,a.fecha,a.detalle,u.nombre,u.apellido FROM auditoria_operaciones a
            INNER JOIN usuarios u ON u.id_usuario=a.id_usuario WHERE a.entidad='PRODUCTO' AND a.id_entidad=:producto
            ORDER BY a.fecha DESC,a.id_auditoria DESC LIMIT 100", ['producto'=>$idProducto]);
    }
}
