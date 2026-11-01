USE alkira;

CREATE TABLE IF NOT EXISTS historial_estados_tickets (
    id_movimiento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ticket INT UNSIGNED NOT NULL,
    estado_anterior VARCHAR(30) NULL,
    estado_nuevo VARCHAR(30) NOT NULL,
    fecha_cambio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historial_ticket
        FOREIGN KEY (id_ticket) REFERENCES tickets_mantenimiento(id_ticket),
    INDEX idx_historial_ticket_fecha (id_ticket, fecha_cambio, id_movimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registra el primer estado de tickets CREADOS a partir de ahora.
DROP TRIGGER IF EXISTS trg_ticket_historial_al_crear;
DELIMITER $$
CREATE TRIGGER trg_ticket_historial_al_crear
AFTER INSERT ON tickets_mantenimiento
FOR EACH ROW
BEGIN
    INSERT INTO historial_estados_tickets (id_ticket, estado_anterior, estado_nuevo)
    VALUES (NEW.id_ticket, NULL, NEW.estado);
END$$
DELIMITER ;

-- Registra cada transicion de estado, independientemente del modulo que la realice.
DROP TRIGGER IF EXISTS trg_ticket_historial_al_actualizar;
DELIMITER $$
CREATE TRIGGER trg_ticket_historial_al_actualizar
AFTER UPDATE ON tickets_mantenimiento
FOR EACH ROW
BEGIN
    IF OLD.estado <> NEW.estado THEN
        INSERT INTO historial_estados_tickets (id_ticket, estado_anterior, estado_nuevo)
        VALUES (NEW.id_ticket, OLD.estado, NEW.estado);
    END IF;
END$$
DELIMITER ;

-- No se crean entradas ficticias para tickets anteriores a esta migracion.


CREATE TABLE IF NOT EXISTS materiales_utilizados_ticket (
    id_material INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_ticket INT UNSIGNED NOT NULL,
    descripcion VARCHAR(180) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    costo_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_material),
    KEY idx_materiales_ticket (id_ticket),
    CONSTRAINT fk_materiales_ticket
        FOREIGN KEY (id_ticket) REFERENCES tickets_mantenimiento(id_ticket),
    CONSTRAINT chk_materiales_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_materiales_costo CHECK (costo_unitario >= 0),
    CONSTRAINT chk_materiales_subtotal CHECK (subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
