
-- ============================================
-- ALKIRA - MODULO 02
-- HU02.3 - Reglas de cobro y mantenimiento
-- ============================================
--
-- Configuracion de cuotas generales
-- o especificas por tipo de unidad.
--
-- No modifica los estados de cuenta
-- existentes ni genera nuevos adeudos.

USE alkira;

CREATE TABLE IF NOT EXISTS reglas_cobro (
    id_regla_cobro INT UNSIGNED
        NOT NULL AUTO_INCREMENT,

    id_concepto INT UNSIGNED NOT NULL,

    alcance ENUM(
        'general',
        'departamento',
        'casa',
        'local',
        'oficina',
        'otro'
    ) NOT NULL DEFAULT 'general',

    monto_cuota DECIMAL(12,2) NOT NULL,

    tipo_recargo ENUM(
        'fijo',
        'porcentaje'
    ) NOT NULL DEFAULT 'fijo',

    valor_recargo DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    dia_corte TINYINT UNSIGNED NOT NULL,

    dia_limite_pago TINYINT UNSIGNED NOT NULL,

    activo TINYINT(1)
        NOT NULL DEFAULT 1,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    fecha_actualizacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id_regla_cobro),

    UNIQUE KEY uk_regla_concepto_alcance (
        id_concepto,
        alcance
    ),

    KEY idx_regla_activo (activo),

    CONSTRAINT fk_regla_concepto
        FOREIGN KEY (id_concepto)
        REFERENCES conceptos_cobro(id_concepto),

    CONSTRAINT chk_regla_monto
        CHECK (monto_cuota >= 0),

    CONSTRAINT chk_regla_recargo
        CHECK (valor_recargo >= 0),

    CONSTRAINT chk_regla_porcentaje
        CHECK (
            tipo_recargo <> 'porcentaje'
            OR valor_recargo <= 100
        ),

    CONSTRAINT chk_regla_dia_corte
        CHECK (dia_corte BETWEEN 1 AND 31),

    CONSTRAINT chk_regla_dia_limite
        CHECK (dia_limite_pago BETWEEN 1 AND 31)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
