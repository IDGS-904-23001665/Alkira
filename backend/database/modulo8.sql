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