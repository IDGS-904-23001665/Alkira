-- ALKIRA - Módulo 5 (HU05): Reporte de Incidencias con Evidencia Multimedia
-- Compatible 100% con las tablas y ENUM de schema.sql

USE alkira;

-- 1. Vista principal de incidencias con datos de residente, unidad, inmueble y conteo de evidencias
CREATE OR REPLACE VIEW view_incidencias AS
SELECT
    i.id_incidencia,
    i.id_usuario_reporta,
    CONCAT(u.nombres, ' ', u.apellidos) AS nombre_reporta,
    u.correo AS correo_reporta,
    u.telefono AS telefono_reporta,
    i.id_inmueble,
    COALESCE(inm.nombre, 'Sin inmueble') AS nombre_inmueble,
    i.id_unidad,
    COALESCE(pu.numero_unidad, 'Área común') AS numero_unidad,
    i.titulo,
    i.descripcion,
    i.categoria,
    i.urgencia,
    i.estado,
    i.fecha_reporte,
    (
        SELECT COUNT(*)
        FROM evidencias_incidencia AS ev
        WHERE ev.id_incidencia = i.id_incidencia
    ) AS total_evidencias,
    (
        SELECT tm.id_ticket
        FROM tickets_mantenimiento AS tm
        WHERE tm.id_incidencia = i.id_incidencia
        ORDER BY tm.id_ticket DESC
        LIMIT 1
    ) AS id_ticket
FROM incidencias AS i
INNER JOIN usuarios AS u ON u.id_usuario = i.id_usuario_reporta
LEFT JOIN propiedades_unidades AS pu ON pu.id_unidad = i.id_unidad
LEFT JOIN inmuebles AS inm ON inm.id_inmueble = COALESCE(i.id_inmueble, pu.id_inmueble);

-- 2. Vista de evidencias multimedia por incidencia
CREATE OR REPLACE VIEW view_evidencias_incidencia AS
SELECT
    ev.id_evidencia,
    ev.id_incidencia,
    ev.ruta_archivo,
    ev.tipo_archivo,
    ev.descripcion,
    ev.fecha_carga
FROM evidencias_incidencia AS ev;

-- 3. Procedimiento: Listar todas las incidencias para administración (HU05.2)
DROP PROCEDURE IF EXISTS sp_incidencias_admin;
DELIMITER $$
CREATE PROCEDURE sp_incidencias_admin()
BEGIN
    SELECT
        id_incidencia AS idIncidencia,
        id_usuario_reporta AS idUsuarioReporta,
        nombre_reporta AS nombreReporta,
        correo_reporta AS correoReporta,
        telefono_reporta AS telefonoReporta,
        id_inmueble AS idInmueble,
        nombre_inmueble AS nombreInmueble,
        id_unidad AS idUnidad,
        numero_unidad AS numeroUnidad,
        titulo,
        descripcion,
        categoria,
        urgencia,
        estado,
        fecha_reporte AS fechaReporte,
        total_evidencias AS totalEvidencias,
        id_ticket AS idTicket
    FROM view_incidencias
    ORDER BY
        FIELD(urgencia, 'alta', 'media', 'baja'),
        fecha_reporte DESC,
        id_incidencia DESC;
END$$
DELIMITER ;

-- 4. Procedimiento: Listar incidencias de un residente específico (HU05.1)
DROP PROCEDURE IF EXISTS sp_incidencias_por_usuario;
DELIMITER $$
CREATE PROCEDURE sp_incidencias_por_usuario(IN idUsuario INT UNSIGNED)
BEGIN
    SELECT
        id_incidencia AS idIncidencia,
        id_usuario_reporta AS idUsuarioReporta,
        nombre_reporta AS nombreReporta,
        id_inmueble AS idInmueble,
        nombre_inmueble AS nombreInmueble,
        id_unidad AS idUnidad,
        numero_unidad AS numeroUnidad,
        titulo,
        descripcion,
        categoria,
        urgencia,
        estado,
        fecha_reporte AS fechaReporte,
        total_evidencias AS totalEvidencias,
        id_ticket AS idTicket
    FROM view_incidencias
    WHERE id_usuario_reporta = idUsuario
    ORDER BY fecha_reporte DESC, id_incidencia DESC;
END$$
DELIMITER ;

-- 5. Procedimiento: Crear incidencia con evidencia opcional y bitácora KDD (HU05.1)
DROP PROCEDURE IF EXISTS sp_crear_incidencia;
DELIMITER $$
CREATE PROCEDURE sp_crear_incidencia(
    IN p_id_usuario INT UNSIGNED,
    IN p_id_inmueble INT UNSIGNED,
    IN p_id_unidad INT UNSIGNED,
    IN p_titulo VARCHAR(150),
    IN p_descripcion TEXT,
    IN p_categoria VARCHAR(30),
    IN p_urgencia VARCHAR(20),
    IN p_ruta_archivo VARCHAR(500),
    IN p_tipo_archivo VARCHAR(20),
    IN p_descripcion_evidencia VARCHAR(255)
)
BEGIN
    DECLARE v_id_incidencia INT UNSIGNED;

    INSERT INTO incidencias (
        id_usuario_reporta,
        id_unidad,
        id_inmueble,
        titulo,
        descripcion,
        categoria,
        urgencia,
        estado
    ) VALUES (
        p_id_usuario,
        p_id_unidad,
        p_id_inmueble,
        p_titulo,
        p_descripcion,
        p_categoria,
        p_urgencia,
        'reportada'
    );

    SET v_id_incidencia = LAST_INSERT_ID();

    IF p_ruta_archivo IS NOT NULL AND TRIM(p_ruta_archivo) <> '' THEN
        INSERT INTO evidencias_incidencia (
            id_incidencia,
            ruta_archivo,
            tipo_archivo,
            descripcion
        ) VALUES (
            v_id_incidencia,
            TRIM(p_ruta_archivo),
            COALESCE(NULLIF(TRIM(p_tipo_archivo), ''), 'JPG'),
            COALESCE(NULLIF(TRIM(p_descripcion_evidencia), ''), 'Evidencia adjunta al reporte')
        );
    END IF;

    INSERT INTO historial_actividades_kdd (
        id_usuario,
        tipo_actividad,
        descripcion,
        datos_contexto
    ) VALUES (
        p_id_usuario,
        'incidencia_reportada',
        CONCAT('Incidencia #', v_id_incidencia, ' registrada: ', p_titulo),
        JSON_OBJECT(
            'modulo', 'incidencias',
            'idIncidencia', v_id_incidencia,
            'categoria', p_categoria,
            'urgencia', p_urgencia
        )
    );

    SELECT v_id_incidencia AS idIncidencia;
END$$
DELIMITER ;

-- 6. Trigger: Al pasar una incidencia a 'asignada', crear ticket en tickets_mantenimiento si no existe
DROP TRIGGER IF EXISTS trg_incidencia_asignada_ticket;
DELIMITER $$
CREATE TRIGGER trg_incidencia_asignada_ticket
AFTER UPDATE ON incidencias
FOR EACH ROW
BEGIN
    IF NEW.estado = 'asignada' AND OLD.estado <> 'asignada' THEN
        IF NOT EXISTS (
            SELECT 1
            FROM tickets_mantenimiento
            WHERE id_incidencia = NEW.id_incidencia
        ) THEN
            INSERT INTO tickets_mantenimiento (
                id_incidencia,
                fecha_programada,
                descripcion_trabajo,
                estado,
                costo_total
            ) VALUES (
                NEW.id_incidencia,
                DATE_ADD(NOW(), INTERVAL 1 DAY),
                CONCAT('Atención derivada de incidencia #', NEW.id_incidencia, ': ', NEW.titulo),
                'abierto',
                0.00
            );
        END IF;
    END IF;
END$$
DELIMITER ;

-- 7. Procedimiento: Categorizar y actualizar estado de incidencia (HU05.2)
DROP PROCEDURE IF EXISTS sp_actualizar_estado_incidencia;
DELIMITER $$
CREATE PROCEDURE sp_actualizar_estado_incidencia(
    IN p_id_incidencia INT UNSIGNED,
    IN p_categoria VARCHAR(30),
    IN p_urgencia VARCHAR(20),
    IN p_estado VARCHAR(20),
    IN p_id_admin INT UNSIGNED
)
BEGIN
    UPDATE incidencias
    SET
        categoria = p_categoria,
        urgencia = p_urgencia,
        estado = p_estado
    WHERE id_incidencia = p_id_incidencia;

    INSERT INTO historial_actividades_kdd (
        id_usuario,
        tipo_actividad,
        descripcion,
        datos_contexto
    ) VALUES (
        p_id_admin,
        'incidencia_actualizada',
        CONCAT('Incidencia #', p_id_incidencia, ' actualizada a estado ', p_estado),
        JSON_OBJECT(
            'modulo', 'incidencias',
            'idIncidencia', p_id_incidencia,
            'categoria', p_categoria,
            'urgencia', p_urgencia,
            'estado', p_estado
        )
    );

    SELECT
        id_incidencia AS idIncidencia,
        categoria,
        urgencia,
        estado,
        id_ticket AS idTicket
    FROM view_incidencias
    WHERE id_incidencia = p_id_incidencia;
END$$
DELIMITER ;
