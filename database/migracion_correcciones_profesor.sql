-- COPIA DE SEGURIDAD Y APLICACIÓN DETENIDA. Seleccionar primero la base correcta.
-- En la MISMA conexión: SET @principal_email = 'correo-del-administrador-principal';
-- Si no se define un administrador válido, se cancela ANTES de modificar tablas.
DROP PROCEDURE IF EXISTS verificar_principal_migracion;
DELIMITER $$
CREATE PROCEDURE verificar_principal_migracion()
BEGIN
    IF @principal_email IS NULL OR (SELECT COUNT(*) FROM usuarios u WHERE u.email=CONVERT(@principal_email USING utf8mb4) COLLATE utf8mb4_unicode_ci AND u.estado=1
        AND EXISTS (SELECT 1 FROM usuarios_roles ur JOIN roles r ON r.id_rol=ur.id_rol
        WHERE ur.id_usuario=u.id_usuario AND ur.estado=1 AND r.nombre_rol='ADMINISTRADOR' AND r.estado=1
        AND ur.fecha_desde<=CURRENT_TIMESTAMP AND ur.fecha_hasta IS NULL)) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Defina @principal_email con un ADMINISTRADOR activo sin vencimiento';
    END IF;
END$$
DELIMITER ;
CALL verificar_principal_migracion();
DROP PROCEDURE verificar_principal_migracion;
ALTER TABLE usuarios
    ADD es_principal TINYINT(1) NOT NULL DEFAULT 0,
    ADD principal_unico TINYINT GENERATED ALWAYS AS (CASE WHEN es_principal=1 THEN 1 ELSE NULL END) STORED,
    ADD UNIQUE KEY uq_usuario_principal (principal_unico),
    ADD CONSTRAINT chk_principal_activo CHECK (es_principal IN (0,1) AND (es_principal=0 OR estado=1));
ALTER TABLE ventas
    ADD estado_rectificacion ENUM('VIGENTE','RECTIFICADA') NOT NULL DEFAULT 'VIGENTE',
    ADD id_venta_original INT UNSIGNED NULL,
    ADD id_usuario_rectifica INT UNSIGNED NULL,
    ADD fecha_rectificacion DATETIME NULL,
    ADD motivo_rectificacion VARCHAR(255) NULL,
    ADD UNIQUE KEY uq_venta_rectificada (id_venta_original),
    ADD FOREIGN KEY (id_venta_original) REFERENCES ventas(id_venta),
    ADD FOREIGN KEY (id_usuario_rectifica) REFERENCES usuarios(id_usuario);

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

UPDATE usuarios SET es_principal=1 WHERE email=CONVERT(@principal_email USING utf8mb4) COLLATE utf8mb4_unicode_ci;
