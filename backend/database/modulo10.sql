-- ALKIRA - Módulo 10 (HU10): Generador de Pases de Acceso QR y Bitácora de Visitas
-- Compatible 100% con las tablas y ENUM de schema.sql

USE alkira;

-- 1. Vista de pases QR con datos del anfitrión y unidad asignada vigente
CREATE OR REPLACE VIEW view_pases_acceso_qr AS
SELECT
    p.id_pase,
    p.id_usuario_anfitrion,
    CONCAT(u.nombres, ' ', u.apellidos) AS nombre_anfitrion,
    u.correo AS correo_anfitrion,
    (
        SELECT pu.numero_unidad
        FROM usuarios_propiedades AS up
        INNER JOIN propiedades_unidades AS pu ON pu.id_unidad = up.id_unidad
        WHERE up.id_usuario = p.id_usuario_anfitrion
        ORDER BY up.es_principal DESC, up.id_usuario_propiedad DESC
        LIMIT 1
    ) AS numero_unidad,
    p.nombre_visitante,
    p.token_qr,
    p.fecha_inicio_vigencia,
    p.fecha_fin_vigencia,
    CASE
        WHEN p.estado = 'vigente' AND NOW() > p.fecha_fin_vigencia THEN 'vencido'
        ELSE p.estado
    END AS estado,
    p.fecha_creacion
FROM pases_acceso_qr AS p
INNER JOIN usuarios AS u ON u.id_usuario = p.id_usuario_anfitrion;

-- 2. Vista de bitácora de visitas con detalle de pase, anfitrión, unidad y guardia
CREATE OR REPLACE VIEW view_bitacora_visitas AS
SELECT
    b.id_visita,
    b.id_pase,
    p.token_qr,
    b.nombre_visitante,
    p.id_usuario_anfitrion,
    CONCAT(ua.nombres, ' ', ua.apellidos) AS nombre_anfitrion,
    COALESCE(
        (
            SELECT pu.numero_unidad
            FROM usuarios_propiedades AS up
            INNER JOIN propiedades_unidades AS pu ON pu.id_unidad = up.id_unidad
            WHERE up.id_usuario = p.id_usuario_anfitrion
            ORDER BY up.es_principal DESC, up.id_usuario_propiedad DESC
            LIMIT 1
        ),
        'S/N'
    ) AS numero_unidad,
    b.id_guardia,
    COALESCE(CONCAT(ug.nombres, ' ', ug.apellidos), 'Caseta principal') AS nombre_guardia,
    b.fecha_entrada,
    b.fecha_salida,
    b.observaciones,
    CASE
        WHEN b.fecha_entrada IS NULL THEN 'pendiente'
        WHEN b.fecha_salida IS NULL THEN 'en_interior'
        ELSE 'finalizada'
    END AS estado_visita
FROM bitacora_visitas AS b
LEFT JOIN pases_acceso_qr AS p ON p.id_pase = b.id_pase
LEFT JOIN usuarios AS ua ON ua.id_usuario = p.id_usuario_anfitrion
LEFT JOIN usuarios AS ug ON ug.id_usuario = b.id_guardia;

-- 3. Procedimiento: Listar pases QR por anfitrión (HU10.1)
DROP PROCEDURE IF EXISTS sp_pases_por_anfitrion;
DELIMITER $$
CREATE PROCEDURE sp_pases_por_anfitrion(IN idUsuario INT UNSIGNED)
BEGIN
    UPDATE pases_acceso_qr
    SET estado = 'vencido'
    WHERE id_usuario_anfitrion = idUsuario
      AND estado = 'vigente'
      AND NOW() > fecha_fin_vigencia;

    SELECT
        id_pase AS idPase,
        id_usuario_anfitrion AS idUsuarioAnfitrion,
        nombre_anfitrion AS nombreAnfitrion,
        COALESCE(numero_unidad, 'S/N') AS numeroUnidad,
        nombre_visitante AS nombreVisitante,
        token_qr AS tokenQr,
        fecha_inicio_vigencia AS fechaInicioVigencia,
        fecha_fin_vigencia AS fechaFinVigencia,
        estado,
        fecha_creacion AS fechaCreacion
    FROM view_pases_acceso_qr
    WHERE id_usuario_anfitrion = idUsuario
    ORDER BY fecha_creacion DESC, id_pase DESC;
END$$
DELIMITER ;

-- 4. Procedimiento: Generar nuevo pase QR único (HU10.1)
DROP PROCEDURE IF EXISTS sp_generar_pase_qr;
DELIMITER $$
CREATE PROCEDURE sp_generar_pase_qr(
    IN p_id_anfitrion INT UNSIGNED,
    IN p_nombre_visitante VARCHAR(150),
    IN p_fecha_inicio DATETIME,
    IN p_fecha_fin DATETIME
)
BEGIN
    DECLARE v_token VARCHAR(255);
    DECLARE v_id_pase INT UNSIGNED;

    SET v_token = CONCAT(
        'ALK-QR-',
        DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'),
        '-',
        UPPER(SUBSTRING(MD5(CONCAT(UUID(), RAND(), p_id_anfitrion)), 1, 6))
    );

    INSERT INTO pases_acceso_qr (
        id_usuario_anfitrion,
        nombre_visitante,
        token_qr,
        fecha_inicio_vigencia,
        fecha_fin_vigencia,
        estado
    ) VALUES (
        p_id_anfitrion,
        p_nombre_visitante,
        v_token,
        p_fecha_inicio,
        p_fecha_fin,
        'vigente'
    );

    SET v_id_pase = LAST_INSERT_ID();

    INSERT INTO historial_actividades_kdd (
        id_usuario,
        tipo_actividad,
        descripcion,
        datos_contexto
    ) VALUES (
        p_id_anfitrion,
        'pase_qr_generado',
        CONCAT('Pase QR generado para visitante: ', p_nombre_visitante),
        JSON_OBJECT(
            'modulo', 'accesos_qr',
            'idPase', v_id_pase,
            'tokenQr', v_token
        )
    );

    SELECT
        id_pase AS idPase,
        id_usuario_anfitrion AS idUsuarioAnfitrion,
        nombre_anfitrion AS nombreAnfitrion,
        COALESCE(numero_unidad, 'S/N') AS numeroUnidad,
        nombre_visitante AS nombreVisitante,
        token_qr AS tokenQr,
        fecha_inicio_vigencia AS fechaInicioVigencia,
        fecha_fin_vigencia AS fechaFinVigencia,
        estado,
        fecha_creacion AS fechaCreacion
    FROM view_pases_acceso_qr
    WHERE id_pase = v_id_pase;
END$$
DELIMITER ;

-- 5. Procedimiento: Revocar pase QR vigente (HU10.1 / HU10.2)
DROP PROCEDURE IF EXISTS sp_revocar_pase_qr;
DELIMITER $$
CREATE PROCEDURE sp_revocar_pase_qr(
    IN p_id_pase INT UNSIGNED,
    IN p_id_usuario INT UNSIGNED
)
BEGIN
    UPDATE pases_acceso_qr
    SET estado = 'revocado'
    WHERE id_pase = p_id_pase
      AND estado = 'vigente';

    INSERT INTO historial_actividades_kdd (
        id_usuario,
        tipo_actividad,
        descripcion,
        datos_contexto
    ) VALUES (
        p_id_usuario,
        'pase_qr_revocado',
        CONCAT('Pase QR #', p_id_pase, ' revocado'),
        JSON_OBJECT('modulo', 'accesos_qr', 'idPase', p_id_pase)
    );

    SELECT
        id_pase AS idPase,
        estado
    FROM pases_acceso_qr
    WHERE id_pase = p_id_pase;
END$$
DELIMITER ;

-- 6. Trigger: Registrar en KDD cuando un pase pasa a 'utilizado'
DROP TRIGGER IF EXISTS trg_pase_usado_kdd;
DELIMITER $$
CREATE TRIGGER trg_pase_usado_kdd
AFTER UPDATE ON pases_acceso_qr
FOR EACH ROW
BEGIN
    IF NEW.estado = 'utilizado' AND OLD.estado <> 'utilizado' THEN
        INSERT INTO historial_actividades_kdd (
            id_usuario,
            tipo_actividad,
            descripcion,
            datos_contexto
        ) VALUES (
            NEW.id_usuario_anfitrion,
            'pase_qr_utilizado',
            CONCAT('Ciclo de visita completado para ', NEW.nombre_visitante),
            JSON_OBJECT(
                'modulo', 'accesos_qr',
                'idPase', NEW.id_pase,
                'tokenQr', NEW.token_qr
            )
        );
    END IF;
END$$
DELIMITER ;

-- 7. Procedimiento: Validar token QR en caseta con doble escaneo (1° ENTRADA, 2° SALIDA)
DROP PROCEDURE IF EXISTS sp_validar_qr;
DELIMITER $$
CREATE PROCEDURE sp_validar_qr(
    IN p_token VARCHAR(255),
    IN p_id_guardia INT UNSIGNED,
    IN p_observaciones VARCHAR(500)
)
BEGIN
    DECLARE v_id_pase INT UNSIGNED DEFAULT NULL;
    DECLARE v_estado VARCHAR(20) DEFAULT NULL;
    DECLARE v_inicio DATETIME DEFAULT NULL;
    DECLARE v_fin DATETIME DEFAULT NULL;
    DECLARE v_visitante VARCHAR(150) DEFAULT NULL;
    DECLARE v_id_visita_pendiente INT UNSIGNED DEFAULT NULL;
    DECLARE v_id_visita_abierta INT UNSIGNED DEFAULT NULL;

    SELECT
        id_pase,
        estado,
        fecha_inicio_vigencia,
        fecha_fin_vigencia,
        nombre_visitante
    INTO
        v_id_pase,
        v_estado,
        v_inicio,
        v_fin,
        v_visitante
    FROM pases_acceso_qr
    WHERE token_qr = TRIM(p_token)
    LIMIT 1;

    IF v_id_pase IS NULL THEN
        SELECT
            'RECHAZADO' AS resultado,
            'El código QR no existe en el padrón.' AS mensaje,
            NULL AS idPase,
            NULL AS nombreVisitante;
    ELSEIF v_estado = 'revocado' THEN
        SELECT
            'RECHAZADO' AS resultado,
            'Este pase QR fue revocado por el anfitrión o administración.' AS mensaje,
            v_id_pase AS idPase,
            v_visitante AS nombreVisitante;
    ELSEIF v_estado = 'utilizado' THEN
        SELECT
            'RECHAZADO' AS resultado,
            'Este pase QR ya completó su ciclo de entrada y salida.' AS mensaje,
            v_id_pase AS idPase,
            v_visitante AS nombreVisitante;
    ELSE
        -- Verificar si ya tiene una entrada activa en el interior (sin fecha_salida)
        SELECT id_visita
        INTO v_id_visita_abierta
        FROM bitacora_visitas
        WHERE id_pase = v_id_pase
          AND fecha_entrada IS NOT NULL
          AND fecha_salida IS NULL
        ORDER BY id_visita DESC
        LIMIT 1;

        IF v_id_visita_abierta IS NOT NULL THEN
            -- 2do escaneo: registrar SALIDA y marcar pase como 'utilizado'
            UPDATE bitacora_visitas
            SET
                fecha_salida = NOW(),
                id_guardia = COALESCE(p_id_guardia, id_guardia),
                observaciones = COALESCE(NULLIF(TRIM(p_observaciones), ''), observaciones, 'Salida registrada en caseta')
            WHERE id_visita = v_id_visita_abierta;

            UPDATE pases_acceso_qr
            SET estado = 'utilizado'
            WHERE id_pase = v_id_pase;

            SELECT
                'SALIDA' AS resultado,
                CONCAT('Salida registrada correctamente para ', v_visitante, '.') AS mensaje,
                v_id_pase AS idPase,
                v_visitante AS nombreVisitante;
        ELSEIF v_estado = 'vencido' OR NOW() > v_fin THEN
            UPDATE pases_acceso_qr
            SET estado = 'vencido'
            WHERE id_pase = v_id_pase;

            SELECT
                'RECHAZADO' AS resultado,
                'El pase QR ha expirado su ventana de vigencia.' AS mensaje,
                v_id_pase AS idPase,
                v_visitante AS nombreVisitante;
        ELSEIF NOW() < v_inicio THEN
            SELECT
                'RECHAZADO' AS resultado,
                CONCAT('El pase aún no inicia su vigencia (inicia ', DATE_FORMAT(v_inicio, '%d/%m/%Y %H:%i'), ').') AS mensaje,
                v_id_pase AS idPase,
                v_visitante AS nombreVisitante;
        ELSE
            -- 1er escaneo: verificar si existe fila precreada con fecha_entrada IS NULL (como en schema.sql)
            SELECT id_visita
            INTO v_id_visita_pendiente
            FROM bitacora_visitas
            WHERE id_pase = v_id_pase
              AND fecha_entrada IS NULL
            ORDER BY id_visita DESC
            LIMIT 1;

            IF v_id_visita_pendiente IS NOT NULL THEN
                UPDATE bitacora_visitas
                SET
                    fecha_entrada = NOW(),
                    id_guardia = COALESCE(p_id_guardia, id_guardia),
                    observaciones = COALESCE(NULLIF(TRIM(p_observaciones), ''), 'Entrada autorizada por QR')
                WHERE id_visita = v_id_visita_pendiente;
            ELSE
                INSERT INTO bitacora_visitas (
                    id_pase,
                    id_guardia,
                    nombre_visitante,
                    fecha_entrada,
                    observaciones
                ) VALUES (
                    v_id_pase,
                    p_id_guardia,
                    v_visitante,
                    NOW(),
                    COALESCE(NULLIF(TRIM(p_observaciones), ''), 'Entrada autorizada por QR')
                );
            END IF;

            SELECT
                'ENTRADA' AS resultado,
                CONCAT('Acceso concedido. Entrada registrada para ', v_visitante, '.') AS mensaje,
                v_id_pase AS idPase,
                v_visitante AS nombreVisitante;
        END IF;
    END IF;
END$$
DELIMITER ;
