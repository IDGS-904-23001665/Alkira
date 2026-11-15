
-- ============================================
-- ALKIRA - MODULO 02
-- HU02.3 - Recargo unico por mora
-- ============================================

USE alkira;

-- Conservar la configuracion del recargo
-- correspondiente a cada estado de cuenta.
--
-- Un adeudo solo puede tener un registro.
-- No se modifican adeudos historicos.

CREATE TABLE IF NOT EXISTS recargos_mora_adeudos (

    id_estado_cuenta INT UNSIGNED NOT NULL,

    id_regla_cobro INT UNSIGNED NULL,

    tipo_recargo ENUM(
        'fijo',
        'porcentaje'
    ) NOT NULL,

    valor_recargo DECIMAL(12,2) NOT NULL,

    fecha_configuracion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    fecha_aplicacion DATETIME NULL,

    monto_aplicado DECIMAL(12,2) NULL,

    PRIMARY KEY (id_estado_cuenta),

    CONSTRAINT fk_recargo_estado_cuenta
        FOREIGN KEY (id_estado_cuenta)
        REFERENCES estados_cuenta(id_estado_cuenta),

    CONSTRAINT fk_recargo_regla_cobro
        FOREIGN KEY (id_regla_cobro)
        REFERENCES reglas_cobro(id_regla_cobro)
        ON DELETE SET NULL,

    CONSTRAINT chk_recargo_valor
        CHECK (valor_recargo >= 0),

    CONSTRAINT chk_recargo_porcentaje
        CHECK (
            tipo_recargo <> 'porcentaje'
            OR valor_recargo <= 100
        ),

    CONSTRAINT chk_recargo_monto
        CHECK (
            monto_aplicado IS NULL
            OR monto_aplicado >= 0
        ),

    CONSTRAINT chk_recargo_aplicacion
        CHECK (
            (
                fecha_aplicacion IS NULL
                AND monto_aplicado IS NULL
            )
            OR
            (
                fecha_aplicacion IS NOT NULL
                AND monto_aplicado IS NOT NULL
            )
        )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Verificar estructura.
SHOW COLUMNS FROM recargos_mora_adeudos;

-- Verificar que los adeudos existentes
-- permanezcan intactos.
SELECT COUNT(*) AS total_estados_cuenta
FROM estados_cuenta;

-- La tabla nueva comienza vacia.
SELECT COUNT(*) AS configuraciones_recargo
FROM recargos_mora_adeudos;
