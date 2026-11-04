-- Solo documentación/referencia de los roles iniciales.
-- El primer administrador fue creado manualmente y no se incluye aquí.

INSERT INTO roles (nombre_rol)
VALUES
('ADMINISTRADOR'),
('DUEÑO'),
('RECEPCIONISTA'),
('VENDEDOR');

-- POS etapa 1: medios de pago iniciales, solo documentación/referencia.
INSERT INTO medios_pago (nombre)
VALUES
('EFECTIVO'),
('TRANSFERENCIA'),
('MERCADO PAGO'),
('DÉBITO'),
('CRÉDITO');

-- POS etapa 3: productos.codigo_barras es VARCHAR(50) NOT NULL UNIQUE.
-- Todo producto requiere Código EAN como texto, conservando ceros iniciales.
-- Este archivo no carga productos ni cambia la estructura de la base.

-- Modelo de stock vigente: productos.stock_actual y productos.stock_minimo.
-- Reposición consulta stock_actual <= stock_minimo; no requiere tablas ni datos iniciales.

-- Contraseñas: usuarios.debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0.
-- Sustituye el nombre previo requiere_cambio_password, conservando sus valores.
-- Las claves nuevas normales usan 0; los restablecimientos web/CLI dejan 1.
-- No se cargan contraseñas ni administradores desde este archivo.

-- Recepción: compras nuevas PENDIENTES, sin modificar stock.
-- recepciones y detalle_recepciones no necesitan datos iniciales.
-- Solo una recepción ACEPTADA genera movimiento RECEPCION y aumenta stock.
-- Para bases anteriores: aplicar manualmente recepcion_manual.sql con la aplicación detenida.
-- El primer ALTER conserva las compras anteriores como RECEPCIONADAS; el segundo fija
-- PENDIENTE para las futuras. Nunca recepcionar ni sumar nuevamente los históricos.
