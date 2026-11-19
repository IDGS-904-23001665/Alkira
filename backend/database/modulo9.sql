
USE alkira;

-- HU09.2: Base de conocimiento y preguntas frecuentes
CREATE TABLE IF NOT EXISTS preguntas_frecuentes_ia (
    id_faq INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pregunta VARCHAR(255) NOT NULL,
    respuesta TEXT NOT NULL,
    categoria VARCHAR(80) NOT NULL DEFAULT 'General',
    palabras_clave VARCHAR(500) NOT NULL DEFAULT '',
    prioridad TINYINT UNSIGNED NOT NULL DEFAULT 5,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    id_administrador INT UNSIGNED NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_faq_activa_prioridad (activa, prioridad),

    CONSTRAINT fk_faq_administrador
        FOREIGN KEY (id_administrador)
        REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Historial de altas, ediciones y eliminaciones de FAQs
CREATE TABLE IF NOT EXISTS auditoria_faq_ia (
    id_auditoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_faq INT UNSIGNED NULL,
    id_administrador INT UNSIGNED NOT NULL,
    accion ENUM('crear', 'actualizar', 'eliminar') NOT NULL,
    antes_json TEXT NULL,
    despues_json TEXT NULL,
    fecha_accion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_auditoria_fecha (fecha_accion),

    CONSTRAINT fk_auditoria_faq
        FOREIGN KEY (id_faq)
        REFERENCES preguntas_frecuentes_ia(id_faq)
        ON DELETE SET NULL,

    CONSTRAINT fk_auditoria_admin
        FOREIGN KEY (id_administrador)
        REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
