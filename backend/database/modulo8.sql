-- ALKIRA
-- Modulo 8: Analitica e Historiales (KDD)
-- Indicadores financieros

USE alkira;

-- ============================================
-- 1. VISTA DE CARTERA FINANCIERA
-- ============================================

CREATE OR REPLACE VIEW view_kdd_cartera AS
SELECT
    ec.id_estado_cuenta,
    pu.id_inmueble,
    c.id_inquilino,
    ec.periodo,
    ec.fecha_limite_pago,
    ec.estado,

    (ec.monto_principal + ec.recargo_mora)
        AS monto_total,

    ec.monto_pagado,

    GREATEST(
        ec.monto_principal + ec.recargo_mora
        - ec.monto_pagado,
        0
    ) AS monto_pendiente,

    CASE
        WHEN ec.fecha_limite_pago < CURDATE()
        THEN GREATEST(
            ec.monto_principal + ec.recargo_mora
            - ec.monto_pagado,
            0
        )
        ELSE 0
    END AS monto_vencido

FROM estados_cuenta AS ec

INNER JOIN contratos AS c
    ON ec.id_contrato = c.id_contrato

INNER JOIN propiedades_unidades AS pu
    ON c.id_unidad = pu.id_unidad

WHERE ec.estado <> 'cancelado';


-- ============================================
-- 2. PROCEDIMIENTO DE RESUMEN FINANCIERO
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_resumen_financiero;

DELIMITER $$

CREATE PROCEDURE sp_kdd_resumen_financiero()
BEGIN

    SELECT
        COUNT(*) AS totalEstadosCuenta,

        COALESCE(
            SUM(monto_total), 0
        ) AS totalFacturado,

        COALESCE(
            SUM(monto_pagado), 0
        ) AS totalAplicado,

        COALESCE(
            SUM(monto_pendiente), 0
        ) AS totalPendiente,

        COALESCE(
            SUM(monto_vencido), 0
        ) AS totalVencido,

        COUNT(
            CASE
                WHEN monto_vencido > 0 THEN 1
            END
        ) AS cuentasVencidas,

        COALESCE(
            ROUND(
                SUM(monto_vencido) * 100.0
                / NULLIF(SUM(monto_total), 0),
                2
            ),
            0
        ) AS porcentajeMorosidad

    FROM view_kdd_cartera;

END$$

DELIMITER ;



-- ============================================
-- 3. VISTA DE MANTENIMIENTO KDD
-- ============================================

CREATE OR REPLACE VIEW view_kdd_mantenimiento AS
SELECT
    t.id_ticket,
    i.id_incidencia,
    COALESCE(i.id_inmueble, pu.id_inmueble)
        AS id_inmueble,
    i.categoria,
    i.urgencia,
    i.fecha_reporte,
    t.estado AS estado_ticket,
    t.costo_total,
    t.fecha_cierre,

    CASE
        WHEN t.estado = 'cerrado'
         AND t.fecha_cierre IS NOT NULL
         AND t.fecha_cierre >= i.fecha_reporte
        THEN ROUND(
            TIMESTAMPDIFF(
                MINUTE,
                i.fecha_reporte,
                t.fecha_cierre
            ) / 60.0,
            2
        )
        ELSE NULL
    END AS horas_resolucion

FROM tickets_mantenimiento AS t

INNER JOIN incidencias AS i
    ON t.id_incidencia = i.id_incidencia

LEFT JOIN propiedades_unidades AS pu
    ON i.id_unidad = pu.id_unidad;


-- ============================================
-- 4. PROCEDIMIENTO DE RESUMEN OPERATIVO
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_resumen_operativo;

DELIMITER $$

CREATE PROCEDURE sp_kdd_resumen_operativo()
BEGIN

    SELECT
        (SELECT COUNT(*)
         FROM incidencias) AS totalIncidencias,

        (SELECT COUNT(*)
         FROM incidencias
         WHERE urgencia = 'alta') AS incidenciasUrgentes,

        COUNT(*) AS totalTickets,

        COUNT(
            CASE
                WHEN estado_ticket = 'cerrado'
                THEN 1
            END
        ) AS ticketsCerrados,

        COUNT(
            CASE
                WHEN estado_ticket NOT IN ('cerrado', 'cancelado')
                THEN 1
            END
        ) AS ticketsAbiertos,

        ROUND(
            AVG(horas_resolucion),
            2
        ) AS promedioHorasResolucion

    FROM view_kdd_mantenimiento;

END$$

DELIMITER ;


-- ============================================
-- 5. VISTA DE TENDENCIAS FINANCIERAS MENSUALES
-- ============================================

CREATE OR REPLACE VIEW view_kdd_tendencia_mensual AS
SELECT
    periodo,

    COUNT(*) AS totalEstadosCuenta,

    COALESCE(
        SUM(monto_total), 0
    ) AS totalFacturado,

    COALESCE(
        SUM(monto_pagado), 0
    ) AS totalAplicado,

    COALESCE(
        SUM(monto_pendiente), 0
    ) AS totalPendiente,

    COALESCE(
        SUM(monto_vencido), 0
    ) AS totalVencido,

    COUNT(
        CASE
            WHEN monto_vencido > 0 THEN 1
        END
    ) AS cuentasVencidas,

    COALESCE(
        ROUND(
            SUM(monto_vencido) * 100.0
            / NULLIF(SUM(monto_total), 0),
            2
        ),
        0
    ) AS porcentajeMorosidad

FROM view_kdd_cartera

GROUP BY periodo;


-- ============================================
-- 6. PROCEDIMIENTO DE TENDENCIA MENSUAL
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_tendencia_mensual;

DELIMITER $$

CREATE PROCEDURE sp_kdd_tendencia_mensual()
BEGIN

    SELECT
        periodo,
        totalEstadosCuenta,
        totalFacturado,
        totalAplicado,
        totalPendiente,
        totalVencido,
        cuentasVencidas,
        porcentajeMorosidad

    FROM view_kdd_tendencia_mensual

    ORDER BY periodo ASC;

END$$

DELIMITER ;



-- ============================================
-- 7. VISTA DE INMUEBLES PARA FILTROS KDD
-- ============================================

CREATE OR REPLACE VIEW view_kdd_inmuebles AS
SELECT
    id_inmueble AS idInmueble,
    nombre AS nombreInmueble,
    tipo,
    id_inmueble_padre AS idInmueblePadre
FROM inmuebles
WHERE activo = 1;


-- ============================================
-- 8. PROCEDIMIENTO FINANCIERO CON FILTROS
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_resumen_financiero_filtrado;

DELIMITER $$

CREATE PROCEDURE sp_kdd_resumen_financiero_filtrado(
    IN pIdInmueble INT UNSIGNED,
    IN pPeriodo VARCHAR(7)
)
BEGIN

    WITH RECURSIVE inmueblesRelacionados AS (
        SELECT id_inmueble
        FROM inmuebles
        WHERE id_inmueble = pIdInmueble

        UNION ALL

        SELECT i.id_inmueble
        FROM inmuebles AS i
        INNER JOIN inmueblesRelacionados AS r
            ON i.id_inmueble_padre = r.id_inmueble
    )

    SELECT
        COUNT(*) AS totalEstadosCuenta,

        COALESCE(SUM(monto_total), 0)
            AS totalFacturado,

        COALESCE(SUM(monto_pagado), 0)
            AS totalAplicado,

        COALESCE(SUM(monto_pendiente), 0)
            AS totalPendiente,

        COALESCE(SUM(monto_vencido), 0)
            AS totalVencido,

        COUNT(
            CASE WHEN monto_vencido > 0 THEN 1 END
        ) AS cuentasVencidas,

        COALESCE(
            ROUND(
                SUM(monto_vencido) * 100.0 /
                NULLIF(SUM(monto_total), 0),
                2
            ),
            0
        ) AS porcentajeMorosidad

    FROM view_kdd_cartera

    WHERE
        (
            pIdInmueble IS NULL
            OR id_inmueble IN (
                SELECT id_inmueble
                FROM inmueblesRelacionados
            )
        )

        AND (
            pPeriodo IS NULL
            OR periodo = pPeriodo
        );

END$$

DELIMITER ;



-- ============================================
-- 9. PROCEDIMIENTO DE TENDENCIA MENSUAL FILTRADA
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_tendencia_mensual_filtrada;

DELIMITER $$

CREATE PROCEDURE sp_kdd_tendencia_mensual_filtrada(
    IN pIdInmueble INT UNSIGNED,
    IN pPeriodo VARCHAR(7)
)
BEGIN

    WITH RECURSIVE inmueblesRelacionados AS (
        SELECT id_inmueble
        FROM inmuebles
        WHERE id_inmueble = pIdInmueble

        UNION ALL

        SELECT i.id_inmueble
        FROM inmuebles AS i

        INNER JOIN inmueblesRelacionados AS r
            ON i.id_inmueble_padre = r.id_inmueble
    )

    SELECT
        periodo,

        COUNT(*) AS totalEstadosCuenta,

        COALESCE(SUM(monto_total), 0)
            AS totalFacturado,

        COALESCE(SUM(monto_pagado), 0)
            AS totalAplicado,

        COALESCE(SUM(monto_pendiente), 0)
            AS totalPendiente,

        COALESCE(SUM(monto_vencido), 0)
            AS totalVencido,

        COUNT(
            CASE
                WHEN monto_vencido > 0 THEN 1
            END
        ) AS cuentasVencidas,

        COALESCE(
            ROUND(
                SUM(monto_vencido) * 100.0
                / NULLIF(SUM(monto_total), 0),
                2
            ),
            0
        ) AS porcentajeMorosidad

    FROM view_kdd_cartera

    WHERE
        (
            pIdInmueble IS NULL
            OR id_inmueble IN (
                SELECT id_inmueble
                FROM inmueblesRelacionados
            )
        )

        AND (
            pPeriodo IS NULL
            OR periodo = pPeriodo
        )

    GROUP BY periodo

    ORDER BY periodo ASC;

END$$

DELIMITER ;


-- ============================================
-- 10. RESUMEN OPERATIVO CON FILTROS KDD
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_resumen_operativo_filtrado;

DELIMITER $$

CREATE PROCEDURE sp_kdd_resumen_operativo_filtrado(
    IN pIdInmueble INT UNSIGNED,
    IN pPeriodo VARCHAR(7)
)
BEGIN

    WITH RECURSIVE inmueblesRelacionados AS (
        SELECT id_inmueble
        FROM inmuebles
        WHERE id_inmueble = pIdInmueble

        UNION ALL

        SELECT i.id_inmueble
        FROM inmuebles AS i
        INNER JOIN inmueblesRelacionados AS r
            ON i.id_inmueble_padre = r.id_inmueble
    ),

    incidenciasFiltradas AS (
        SELECT
            i.id_incidencia,
            i.urgencia,
            i.fecha_reporte

        FROM incidencias AS i

        LEFT JOIN propiedades_unidades AS pu
            ON i.id_unidad = pu.id_unidad

        WHERE (
            pIdInmueble IS NULL
            OR COALESCE(i.id_inmueble, pu.id_inmueble)
                IN (
                    SELECT id_inmueble
                    FROM inmueblesRelacionados
                )
        )

        AND (
            pPeriodo IS NULL
            OR DATE_FORMAT(i.fecha_reporte, '%Y-%m')
                = pPeriodo
        )
    ),

    ticketsFiltrados AS (
        SELECT
            vm.id_ticket,
            vm.estado_ticket,
            vm.horas_resolucion

        FROM view_kdd_mantenimiento AS vm

        INNER JOIN incidenciasFiltradas AS i
            ON vm.id_incidencia = i.id_incidencia
    )

    SELECT
        (
            SELECT COUNT(*)
            FROM incidenciasFiltradas
        ) AS totalIncidencias,

        (
            SELECT COUNT(*)
            FROM incidenciasFiltradas
            WHERE urgencia = 'alta'
        ) AS incidenciasUrgentes,

        (
            SELECT COUNT(*)
            FROM ticketsFiltrados
        ) AS totalTickets,

        (
            SELECT COUNT(*)
            FROM ticketsFiltrados
            WHERE estado_ticket = 'cerrado'
        ) AS ticketsCerrados,

        (
            SELECT COUNT(*)
            FROM ticketsFiltrados
            WHERE estado_ticket NOT IN (
                'cerrado', 'cancelado'
            )
        ) AS ticketsAbiertos,

        (
            SELECT ROUND(AVG(horas_resolucion), 2)
            FROM ticketsFiltrados
        ) AS promedioHorasResolucion;

END$$

DELIMITER ;


-- ============================================
-- 11. HISTORIAL DE PAGOS REGISTRADOS
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_historial_pagos_filtrado;

DELIMITER $$

CREATE PROCEDURE sp_kdd_historial_pagos_filtrado(
    IN pIdInmueble INT UNSIGNED,
    IN pPeriodo VARCHAR(7)
)
BEGIN
    WITH RECURSIVE inmueblesRelacionados AS (
        SELECT id_inmueble
        FROM inmuebles
        WHERE id_inmueble = pIdInmueble

        UNION ALL

        SELECT i.id_inmueble
        FROM inmuebles i
        INNER JOIN inmueblesRelacionados r
            ON i.id_inmueble_padre = r.id_inmueble
    )

    SELECT
        p.id_pago AS idPago,
        pu.numero_unidad AS unidad,
        ec.periodo,
        p.fecha_pago AS fechaPago,
        p.monto,
        p.metodo_pago AS metodoPago,
        p.estado AS estadoPago

    FROM pagos_transacciones p

    INNER JOIN estados_cuenta ec
        ON p.id_estado_cuenta = ec.id_estado_cuenta

    INNER JOIN contratos c
        ON ec.id_contrato = c.id_contrato

    INNER JOIN propiedades_unidades pu
        ON c.id_unidad = pu.id_unidad

    WHERE (
        pIdInmueble IS NULL
        OR pu.id_inmueble IN (
            SELECT id_inmueble FROM inmueblesRelacionados
        )
    )

    AND (pPeriodo IS NULL OR ec.periodo = pPeriodo)

    ORDER BY p.fecha_pago DESC, p.id_pago DESC
    LIMIT 200;
END$$

DELIMITER ;


-- ============================================
-- 12. HISTORIAL DE INCIDENCIAS Y TICKETS
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_historial_operativo_filtrado;

DELIMITER $$

CREATE PROCEDURE sp_kdd_historial_operativo_filtrado(
    IN pIdInmueble INT UNSIGNED,
    IN pPeriodo VARCHAR(7)
)
BEGIN
    WITH RECURSIVE inmueblesRelacionados AS (
        SELECT id_inmueble
        FROM inmuebles
        WHERE id_inmueble = pIdInmueble

        UNION ALL

        SELECT i.id_inmueble
        FROM inmuebles i
        INNER JOIN inmueblesRelacionados r
            ON i.id_inmueble_padre = r.id_inmueble
    )

    SELECT
        i.id_incidencia AS idIncidencia,
        t.id_ticket AS idTicket,
        i.titulo,
        i.categoria,
        i.urgencia,
        i.fecha_reporte AS fechaReporte,
        t.estado AS estadoTicket,
        t.fecha_cierre AS fechaCierre,
        t.costo_total AS costoTotal,

        CASE
            WHEN t.estado = 'cerrado'
                 AND t.fecha_cierre IS NOT NULL
                 AND t.fecha_cierre >= i.fecha_reporte

            THEN ROUND(
                TIMESTAMPDIFF(
                    MINUTE,
                    i.fecha_reporte,
                    t.fecha_cierre
                ) / 60,
                2
            )

            ELSE NULL
        END AS horasResolucion

    FROM incidencias i

    LEFT JOIN propiedades_unidades pu
        ON i.id_unidad = pu.id_unidad

    LEFT JOIN tickets_mantenimiento t
        ON i.id_incidencia = t.id_incidencia

    WHERE (
        pIdInmueble IS NULL
        OR COALESCE(i.id_inmueble, pu.id_inmueble)
            IN (
                SELECT id_inmueble
                FROM inmueblesRelacionados
            )
    )

    AND (
        pPeriodo IS NULL
        OR DATE_FORMAT(i.fecha_reporte, '%Y-%m') = pPeriodo
    )

    ORDER BY
        i.fecha_reporte DESC,
        i.id_incidencia DESC,
        t.id_ticket DESC

    LIMIT 200;
END$$

DELIMITER ;


-- ============================================
-- 13. ALERTAS DESCRIPTIVAS DE MOROSIDAD
-- ============================================

DROP PROCEDURE IF EXISTS sp_kdd_alertas_morosidad_filtradas;

DELIMITER $$

CREATE PROCEDURE sp_kdd_alertas_morosidad_filtradas(
    IN pIdInmueble INT UNSIGNED,
    IN pPeriodo VARCHAR(7)
)
BEGIN
    WITH RECURSIVE inmueblesRelacionados AS (
        SELECT id_inmueble
        FROM inmuebles
        WHERE id_inmueble = pIdInmueble

        UNION ALL

        SELECT i.id_inmueble
        FROM inmuebles i
        INNER JOIN inmueblesRelacionados r
            ON i.id_inmueble_padre = r.id_inmueble
    )

    SELECT
        pu.numero_unidad AS unidad,

        COUNT(*) AS cuentasRegistradas,

        SUM(
            CASE
                WHEN vc.monto_vencido > 0 THEN 1
                ELSE 0
            END
        ) AS cuentasVencidas,

        ROUND(
            SUM(vc.monto_vencido),
            2
        ) AS saldoVencido,

        CASE
            WHEN SUM(
                CASE
                    WHEN vc.monto_vencido > 0 THEN 1
                    ELSE 0
                END
            ) >= 2

            OR SUM(vc.monto_vencido) >= 10000

            THEN 'alta'
            ELSE 'media'
        END AS nivelAlerta

    FROM view_kdd_cartera vc

    INNER JOIN estados_cuenta ec
        ON vc.id_estado_cuenta = ec.id_estado_cuenta

    INNER JOIN contratos c
        ON ec.id_contrato = c.id_contrato

    INNER JOIN propiedades_unidades pu
        ON c.id_unidad = pu.id_unidad

    WHERE (
        pIdInmueble IS NULL
        OR vc.id_inmueble IN (
            SELECT id_inmueble
            FROM inmueblesRelacionados
        )
    )

    AND (
        pPeriodo IS NULL
        OR vc.periodo = pPeriodo
    )

    GROUP BY pu.id_unidad, pu.numero_unidad

    HAVING SUM(vc.monto_vencido) > 0

    ORDER BY
        saldoVencido DESC,
        cuentasVencidas DESC

    LIMIT 200;
END$$

DELIMITER ;