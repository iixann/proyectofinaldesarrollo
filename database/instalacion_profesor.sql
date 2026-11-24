-- Instalación desde cero en una base NUEVA; no ejecutar sobre una base existente.
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS proyectofinaldesarrollo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE proyectofinaldesarrollo;
-- Solo documentación/referencia de la base creada manualmente desde phpMyAdmin.
-- Relación: usuarios 1 -> N usuarios_roles N -> 1 roles.
-- Un usuario puede tener uno o varios roles.

CREATE TABLE roles (
    id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    principal_unico TINYINT GENERATED ALWAYS AS (CASE WHEN es_principal = 1 THEN 1 ELSE NULL END) STORED,
    UNIQUE KEY uq_usuario_principal (principal_unico),
    CONSTRAINT chk_principal_activo CHECK (es_principal IN (0,1) AND (es_principal = 0 OR estado = 1)),
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios_roles (
    id_usuario_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    id_rol INT UNSIGNED NOT NULL,
    fecha_desde DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_hasta DATETIME NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,

    CONSTRAINT fk_usuarios_roles_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_usuarios_roles_rol
        FOREIGN KEY (id_rol)
        REFERENCES roles(id_rol)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_usuarios_roles_usuario (id_usuario),
    INDEX idx_usuarios_roles_rol (id_rol)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Categorías: tabla creada manualmente en phpMyAdmin. No ejecutar desde la aplicación.
CREATE TABLE categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL UNIQUE,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Proveedores: maestro independiente creado manualmente en phpMyAdmin.
CREATE TABLE proveedores (
    id_proveedor INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(150) NOT NULL,
    cuit VARCHAR(13) NULL UNIQUE,
    nombre_contacto VARCHAR(100) NULL,
    telefono VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    direccion VARCHAR(200) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Productos y su relación muchos-a-muchos con proveedores, creados manualmente.
-- Solo documentación: no ejecutar desde la aplicación.
CREATE TABLE productos (
    id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT UNSIGNED NOT NULL,
    nombre_producto VARCHAR(150) NOT NULL,
    codigo_barras VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NULL,
    precio_venta DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 0,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL,

    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria),
    UNIQUE (nombre_producto, id_categoria),
    UNIQUE KEY uq_productos_codigo_barras (codigo_barras)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE productos_proveedores (
    id_producto_proveedor INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_producto INT UNSIGNED NOT NULL,
    id_proveedor INT UNSIGNED NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL,

    FOREIGN KEY (id_producto) REFERENCES productos(id_producto),
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor),
    UNIQUE (id_producto, id_proveedor)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Solo documentación de la tabla creada manualmente en phpMyAdmin.
CREATE TABLE movimientos_stock (
    id_movimiento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_producto INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    tipo_movimiento VARCHAR(30) NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    stock_anterior INT NOT NULL,
    stock_nuevo INT NOT NULL,
    observacion VARCHAR(255) NULL,
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_producto) REFERENCES productos(id_producto),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Compras y detalle histórico: creados manualmente, solo documentación.
CREATE TABLE compras (
    id_compra INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_proveedor INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    numero_comprobante VARCHAR(50) NULL,
    fecha_compra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    observacion VARCHAR(255) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    estado_recepcion ENUM('PENDIENTE', 'RECEPCIONADA', 'RECHAZADA') NOT NULL DEFAULT 'PENDIENTE',

    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    UNIQUE (id_proveedor, numero_comprobante),
    INDEX idx_compras_recepcion (estado_recepcion, estado)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalle_compras (
    id_detalle_compra INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_compra INT UNSIGNED NOT NULL,
    id_producto INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,

    FOREIGN KEY (id_compra) REFERENCES compras(id_compra),
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- POS etapa 1: estructura aprobada y creada manualmente en phpMyAdmin.
-- Solo documentación; la aplicación no ejecuta este SQL.
CREATE TABLE cajas (
    id_caja INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL,
    UNIQUE KEY uq_cajas_nombre (nombre)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caja_sesiones (
    id_caja_sesion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_caja INT UNSIGNED NOT NULL,
    id_usuario_apertura INT UNSIGNED NOT NULL,
    fecha_apertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
    id_usuario_cierre INT UNSIGNED NULL,
    fecha_cierre DATETIME NULL,
    total_ventas DECIMAL(12,2) NULL,
    total_efectivo DECIMAL(12,2) NULL,
    efectivo_esperado DECIMAL(12,2) NULL,
    efectivo_contado DECIMAL(12,2) NULL,
    diferencia DECIMAL(12,2) NULL,
    estado ENUM('ABIERTA', 'CERRADA') NOT NULL DEFAULT 'ABIERTA',
    observacion_cierre VARCHAR(255) NULL,

    CONSTRAINT fk_caja_sesiones_caja
        FOREIGN KEY (id_caja) REFERENCES cajas(id_caja)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_caja_sesiones_usuario_apertura
        FOREIGN KEY (id_usuario_apertura) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_caja_sesiones_usuario_cierre
        FOREIGN KEY (id_usuario_cierre) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_caja_sesiones_caja_estado (id_caja, estado),

    INDEX idx_caja_sesiones_usuario_apertura (id_usuario_apertura),

    INDEX idx_caja_sesiones_usuario_cierre (id_usuario_cierre),

    INDEX idx_caja_sesiones_fecha_apertura (fecha_apertura)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medios_pago (
    id_medio_pago INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL,
    UNIQUE KEY uq_medios_pago_nombre (nombre)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ventas (
    id_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_caja_sesion INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    estado_rectificacion ENUM('VIGENTE','RECTIFICADA') NOT NULL DEFAULT 'VIGENTE',
    id_venta_original INT UNSIGNED NULL,
    id_usuario_rectifica INT UNSIGNED NULL,
    fecha_rectificacion DATETIME NULL,
    motivo_rectificacion VARCHAR(255) NULL,
    UNIQUE KEY uq_venta_rectificada (id_venta_original),
    FOREIGN KEY (id_venta_original) REFERENCES ventas(id_venta),
    FOREIGN KEY (id_usuario_rectifica) REFERENCES usuarios(id_usuario),

    CONSTRAINT fk_ventas_caja_sesion
        FOREIGN KEY (id_caja_sesion) REFERENCES caja_sesiones(id_caja_sesion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_ventas_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_ventas_sesion_estado (id_caja_sesion, estado),

    INDEX idx_ventas_usuario (id_usuario),

    INDEX idx_ventas_fecha (fecha_venta, id_venta)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalle_ventas (
    id_detalle_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta INT UNSIGNED NOT NULL,
    id_producto INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,

    CONSTRAINT fk_detalle_ventas_venta
        FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_detalle_ventas_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    UNIQUE KEY uq_detalle_ventas_producto (id_venta, id_producto),

    INDEX idx_detalle_ventas_producto (id_producto)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagos_ventas (
    id_pago_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta INT UNSIGNED NOT NULL,
    id_medio_pago INT UNSIGNED NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    monto_entregado DECIMAL(12,2) NULL,
    vuelto DECIMAL(12,2) NULL,
    referencia VARCHAR(150) NULL,

    CONSTRAINT fk_pagos_ventas_venta
        FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_pagos_ventas_medio_pago
        FOREIGN KEY (id_medio_pago) REFERENCES medios_pago(id_medio_pago)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    UNIQUE KEY uq_pagos_ventas_medio (id_venta, id_medio_pago),

    INDEX idx_pagos_ventas_medio (id_medio_pago)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Compra no modifica stock. Solo la recepción ACEPTADA registra entrada física.
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

-- El principal y su rol permanecen protegidos incluso ante SQL directo.
DELIMITER $$
CREATE TRIGGER proteger_principal_actualizar BEFORE UPDATE ON usuarios FOR EACH ROW
BEGIN
    IF OLD.es_principal = 1 AND (NEW.es_principal <> 1 OR NEW.estado <> 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario principal no puede desactivarse ni perder su marca';
    END IF;
    IF NEW.es_principal = 1 AND OLD.es_principal = 0 AND NOT EXISTS (
        SELECT 1 FROM usuarios_roles ur JOIN roles r ON r.id_rol = ur.id_rol
        WHERE ur.id_usuario = OLD.id_usuario AND ur.estado = 1 AND r.estado = 1
        AND r.nombre_rol = 'ADMINISTRADOR' AND ur.fecha_desde <= CURRENT_TIMESTAMP AND ur.fecha_hasta IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El principal debe tener rol ADMINISTRADOR activo sin vencimiento';
    END IF;
END$$
CREATE TRIGGER proteger_principal_insertar BEFORE INSERT ON usuarios FOR EACH ROW
BEGIN
    IF NEW.es_principal = 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Primero cree el administrador y su rol; luego marque al principal';
    END IF;
END$$
CREATE TRIGGER proteger_principal_eliminar BEFORE DELETE ON usuarios FOR EACH ROW
BEGIN
    IF OLD.es_principal = 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario principal no puede eliminarse';
    END IF;
END$$
CREATE TRIGGER proteger_rol_principal_actualizar BEFORE UPDATE ON usuarios_roles FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM usuarios u JOIN roles r ON r.id_rol = OLD.id_rol
        WHERE u.id_usuario = OLD.id_usuario AND u.es_principal = 1 AND r.nombre_rol = 'ADMINISTRADOR')
        AND (NEW.id_usuario <> OLD.id_usuario OR NEW.id_rol <> OLD.id_rol OR NEW.estado <> 1
        OR NEW.fecha_desde > CURRENT_TIMESTAMP OR NEW.fecha_hasta IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede quitar ni vencer el rol del administrador principal';
    END IF;
END$$
CREATE TRIGGER proteger_rol_principal_eliminar BEFORE DELETE ON usuarios_roles FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM usuarios u JOIN roles r ON r.id_rol = OLD.id_rol
        WHERE u.id_usuario = OLD.id_usuario AND u.es_principal = 1 AND r.nombre_rol = 'ADMINISTRADOR') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede eliminar el rol del administrador principal';
    END IF;
END$$
CREATE TRIGGER proteger_tipo_rol_principal BEFORE UPDATE ON roles FOR EACH ROW
BEGIN
    IF OLD.nombre_rol = 'ADMINISTRADOR' AND (NEW.nombre_rol <> 'ADMINISTRADOR' OR NEW.estado <> 1)
        AND EXISTS (SELECT 1 FROM usuarios_roles ur JOIN usuarios u ON u.id_usuario = ur.id_usuario
        WHERE ur.id_rol = OLD.id_rol AND u.es_principal = 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El rol ADMINISTRADOR principal debe permanecer activo';
    END IF;
END$$
DELIMITER ;

INSERT INTO roles (nombre_rol) VALUES ('ADMINISTRADOR'),('DUEÑO'),('RECEPCIONISTA'),('VENDEDOR');
INSERT INTO medios_pago (nombre) VALUES ('EFECTIVO'),('TRANSFERENCIA'),('MERCADO PAGO'),('DÉBITO'),('CRÉDITO');
INSERT INTO usuarios (nombre,apellido,email,password,estado,debe_cambiar_password)
VALUES ('Administrador','Demo','admin@admin.com','$2y$12$HYllsDolBu44qEvFdQ7Z/OwMQxiBur83XgvpK3ZBBhcmnE1Zjb.P2',1,0);
INSERT INTO usuarios_roles (id_usuario,id_rol,estado)
SELECT u.id_usuario,r.id_rol,1 FROM usuarios u CROSS JOIN roles r
WHERE u.email='admin@admin.com' AND r.nombre_rol='ADMINISTRADOR';
-- La identidad del principal se selecciona por correo, no por un ID fijo.
UPDATE usuarios SET es_principal=1 WHERE email='admin@admin.com';

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
