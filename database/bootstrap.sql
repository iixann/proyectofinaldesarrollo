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
