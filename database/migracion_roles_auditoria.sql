-- Aplicar una sola vez sobre la base seleccionada, con backup previo.
-- No cambia datos existentes ni necesita alterar movimientos_stock: tipo_movimiento es VARCHAR(30).
-- Auditoría administrativa de productos; el decomiso se consulta en movimientos_stock.
CREATE TABLE auditoria_operaciones (
    id_auditoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    accion VARCHAR(30) NOT NULL,
    entidad VARCHAR(30) NOT NULL,
    id_entidad INT UNSIGNED NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    detalle VARCHAR(255) NOT NULL,
    INDEX idx_auditoria_entidad (entidad, id_entidad, fecha, id_auditoria),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
