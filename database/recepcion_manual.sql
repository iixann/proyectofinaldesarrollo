-- SOLO PARA EJECUCIÓN MANUAL EN phpMyAdmin, UNA VEZ.
-- Detener el uso de la aplicación durante toda la operación y hacer respaldo.
-- Las compras anteriores YA sumaron stock: se conservan como RECEPCIONADAS.
-- No se crean recepciones retroactivas ni se alteran existencias/movimientos.
ALTER TABLE compras
    ADD COLUMN estado_recepcion ENUM('PENDIENTE', 'RECEPCIONADA', 'RECHAZADA') NOT NULL DEFAULT 'RECEPCIONADA',
    ADD INDEX idx_compras_recepcion (estado_recepcion, estado);

-- Solo cambia el valor por defecto futuro; conserva RECEPCIONADA en los históricos.
ALTER TABLE compras
    ALTER COLUMN estado_recepcion SET DEFAULT 'PENDIENTE';

CREATE TABLE recepciones (
    id_recepcion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_compra INT UNSIGNED NULL,
    id_proveedor INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    fecha_recepcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('ACEPTADA', 'RECHAZADA') NOT NULL,
    observacion VARCHAR(255) NULL,
    motivo_rechazo VARCHAR(255) NULL,
    UNIQUE KEY uq_recepciones_compra (id_compra),
    FOREIGN KEY (id_compra) REFERENCES compras(id_compra),
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    INDEX idx_recepciones_proveedor (id_proveedor),
    INDEX idx_recepciones_usuario (id_usuario),
    INDEX idx_recepciones_fecha (fecha_recepcion, id_recepcion)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalle_recepciones (
    id_detalle_recepcion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_recepcion INT UNSIGNED NOT NULL,
    id_producto INT UNSIGNED NOT NULL,
    cantidad_esperada INT UNSIGNED NULL,
    cantidad_recibida INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_detalle_recepciones_producto (id_recepcion, id_producto),
    FOREIGN KEY (id_recepcion) REFERENCES recepciones(id_recepcion),
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto),
    INDEX idx_detalle_recepciones_producto (id_producto)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
