-- ALKIRA - Módulo 2: estados de cuenta y contratos

USE alkira;

-- Contratos relacionados con los inquilinos y las unidades.
CREATE OR REPLACE VIEW view_contratos AS
SELECT
    c.id_contrato,
    c.id_inquilino,
    c.id_unidad,
    pu.numero_unidad,
    u.nombres,
    u.apellidos,
    c.fecha_inicio,
    c.fecha_fin,
    c.monto_renta,
    c.deposito_garantia,
    c.ruta_documento,
    c.estado_contrato
FROM contratos AS c
INNER JOIN usuarios AS u ON u.id_usuario = c.id_inquilino
INNER JOIN propiedades_unidades AS pu ON pu.id_unidad = c.id_unidad;

-- Desglose general de cobros, sin filtrar aún por usuario.
CREATE OR REPLACE VIEW view_adeudos AS
SELECT
    ec.id_estado_cuenta,
    vc.id_contrato,
    vc.id_inquilino,
    vc.id_unidad,
    vc.numero_unidad,
    cc.id_concepto,
    cc.nombre AS concepto,
    cc.tipo_concepto,
    ec.periodo,
    ec.fecha_emision,
    ec.fecha_limite_pago,
    ec.monto_principal,
    ec.recargo_mora,
    ec.monto_pagado,
    GREATEST(ec.monto_principal + ec.recargo_mora - ec.monto_pagado, 0.00)
        AS monto_pendiente,
    ec.estado
FROM estados_cuenta AS ec
INNER JOIN view_contratos AS vc ON vc.id_contrato = ec.id_contrato
INNER JOIN conceptos_cobro AS cc ON cc.id_concepto = ec.id_concepto;

-- Procedimiento de solo lectura: no recibe el usuario directamente de la app.
DROP PROCEDURE IF EXISTS sp_adeudos;
DELIMITER $$
CREATE PROCEDURE sp_adeudos(IN idInquilino INT UNSIGNED)
BEGIN
    SELECT
        id_estado_cuenta AS idEstadoCuenta,
        id_contrato AS idContrato,
        id_unidad AS idUnidad,
        numero_unidad AS numeroUnidad,
        id_concepto AS idConcepto,
        concepto,
        tipo_concepto AS tipoConcepto,
        periodo,
        fecha_emision AS fechaEmision,
        fecha_limite_pago AS fechaLimitePago,
        monto_principal AS montoPrincipal,
        recargo_mora AS recargoMora,
        monto_pagado AS montoPagado,
        monto_pendiente AS montoPendiente,
        estado
    FROM view_adeudos
    WHERE id_inquilino = idInquilino
      AND estado IN ('pendiente', 'parcial', 'vencido')
      AND monto_pendiente > 0
    ORDER BY fecha_limite_pago ASC, id_estado_cuenta ASC;
END$$
DELIMITER ;

-- Procedimiento de lectura para HU02.2/HU02.4: solo información de contratos.
DROP PROCEDURE IF EXISTS sp_contratos;
DELIMITER $$
CREATE PROCEDURE sp_contratos(IN idInquilino INT UNSIGNED)
BEGIN
    SELECT
        id_contrato AS idContrato,
        id_unidad AS idUnidad,
        numero_unidad AS numeroUnidad,
        fecha_inicio AS fechaInicio,
        fecha_fin AS fechaFin,
        monto_renta AS montoRenta,
        deposito_garantia AS depositoGarantia,
        ruta_documento AS rutaDocumento,
        estado_contrato AS estadoContrato
    FROM view_contratos
    WHERE id_inquilino = idInquilino
    ORDER BY fecha_inicio DESC, id_contrato DESC;
END$$
DELIMITER ;

