CREATE DATABASE IF NOT EXISTS alkira
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE alkira;

-- ============================================================
-- 1. USUARIOS Y ACCESO
-- ============================================================

CREATE TABLE usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(25),
    contrasena_hash VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('administrador', 'finanzas', 'guardia', 'inquilino', 'propietario', 'tecnico')
        NOT NULL DEFAULT 'inquilino',
    estado ENUM('activo', 'inactivo', 'bloqueado') NOT NULL DEFAULT 'activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT(1) NOT NULL DEFAULT 1
);

-- ============================================================
-- 2. INMUEBLES, CONDOMINIOS Y UNIDADES
-- ============================================================

CREATE TABLE inmuebles (
    id_inmueble INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    tipo ENUM('condominio', 'torre', 'bloque', 'local_comercial', 'otro')
        NOT NULL DEFAULT 'condominio',
    direccion VARCHAR(255) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    estado_geografico VARCHAR(100),
    codigo_postal VARCHAR(10),
    descripcion TEXT,
    estado_registro ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_inmueble_padre INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_inmuebles_padre
        FOREIGN KEY (id_inmueble_padre) REFERENCES inmuebles(id_inmueble)
);

CREATE TABLE propiedades_unidades (
    id_unidad INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_inmueble INT UNSIGNED NOT NULL,
    numero_unidad VARCHAR(30) NOT NULL,
    tipo_unidad ENUM('departamento', 'casa', 'local', 'oficina', 'otro')
        NOT NULL DEFAULT 'departamento',
    superficie_m2 DECIMAL(10,2),
    cajones_estacionamiento TINYINT UNSIGNED NOT NULL DEFAULT 0,
    tiene_bodega BOOLEAN NOT NULL DEFAULT FALSE,
    servicios_incluidos TEXT,
    descripcion TEXT,
    estado_unidad ENUM('disponible', 'ocupada', 'mantenimiento', 'inactiva')
        NOT NULL DEFAULT 'disponible',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_unidades_inmueble
        FOREIGN KEY (id_inmueble) REFERENCES inmuebles(id_inmueble)
);

CREATE TABLE usuarios_propiedades (
    id_usuario_propiedad INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    id_unidad INT UNSIGNED NOT NULL,
    relacion ENUM('propietario', 'inquilino', 'residente') NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    es_principal BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_up_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_up_unidad
        FOREIGN KEY (id_unidad) REFERENCES propiedades_unidades(id_unidad)
);

-- ============================================================
-- 3. CONTRATOS, CONCEPTOS DE COBRO Y ESTADOS DE CUENTA
-- ============================================================

CREATE TABLE contratos (
    id_contrato INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_unidad INT UNSIGNED NOT NULL,
    id_inquilino INT UNSIGNED NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE,
    monto_renta DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    deposito_garantia DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ruta_documento VARCHAR(500),
    estado_contrato ENUM('borrador', 'vigente', 'finalizado', 'cancelado')
        NOT NULL DEFAULT 'borrador',
    CONSTRAINT fk_contrato_unidad
        FOREIGN KEY (id_unidad) REFERENCES propiedades_unidades(id_unidad),
    CONSTRAINT fk_contrato_inquilino
        FOREIGN KEY (id_inquilino) REFERENCES usuarios(id_usuario)
);

CREATE TABLE conceptos_cobro (
    id_concepto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255),
    tipo_concepto ENUM('renta', 'mantenimiento', 'servicio', 'recargo', 'otro')
        NOT NULL DEFAULT 'otro',
    monto_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    periodicidad ENUM('unico', 'semanal', 'quincenal', 'mensual', 'anual')
        NOT NULL DEFAULT 'mensual',
    activo TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE estados_cuenta (
    id_estado_cuenta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_contrato INT UNSIGNED NOT NULL,
    id_concepto INT UNSIGNED NOT NULL,
    periodo VARCHAR(7) NOT NULL,
    fecha_emision DATE NOT NULL,
    fecha_limite_pago DATE NOT NULL,
    monto_principal DECIMAL(12,2) NOT NULL,
    recargo_mora DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    monto_pagado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado ENUM('pendiente', 'parcial', 'pagado', 'vencido', 'cancelado')
        NOT NULL DEFAULT 'pendiente',
    CONSTRAINT fk_ec_contrato
        FOREIGN KEY (id_contrato) REFERENCES contratos(id_contrato),
    CONSTRAINT fk_ec_concepto
        FOREIGN KEY (id_concepto) REFERENCES conceptos_cobro(id_concepto)
);

-- ============================================================
-- 4. COMPROBANTES, TRANSACCIONES Y CONCILIACIÓN
-- ============================================================

CREATE TABLE pagos_transacciones (
    id_pago INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_estado_cuenta INT UNSIGNED NOT NULL,
    id_usuario_registro INT UNSIGNED NOT NULL,
    fecha_pago DATETIME,
    monto DECIMAL(12,2) NOT NULL,
    metodo_pago ENUM('transferencia', 'deposito', 'efectivo', 'otro')
        NOT NULL DEFAULT 'transferencia',
    referencia_bancaria VARCHAR(100),
    estado ENUM('preregistrado', 'en_revision', 'aprobado', 'rechazado')
        NOT NULL DEFAULT 'preregistrado',
    observaciones VARCHAR(500),
    CONSTRAINT fk_pago_estado_cuenta
        FOREIGN KEY (id_estado_cuenta) REFERENCES estados_cuenta(id_estado_cuenta),
    CONSTRAINT fk_pago_usuario
        FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id_usuario)
);

CREATE TABLE comprobantes_pago (
    id_comprobante INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pago INT UNSIGNED NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo_archivo ENUM('JPG', 'PNG', 'PDF') NOT NULL,
    fecha_carga DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado_revision ENUM('pendiente', 'aprobado', 'rechazado')
        NOT NULL DEFAULT 'pendiente',
    motivo_revision VARCHAR(500),
    CONSTRAINT fk_comprobante_pago
        FOREIGN KEY (id_pago) REFERENCES pagos_transacciones(id_pago)
);

CREATE TABLE conciliaciones_pago (
    id_conciliacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pago INT UNSIGNED NOT NULL,
    id_usuario_revisor INT UNSIGNED NOT NULL,
    fecha_revision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resultado ENUM('aprobado', 'rechazado') NOT NULL,
    motivo VARCHAR(500),
    referencia_recibo VARCHAR(100),
    ruta_recibo VARCHAR(500),
    CONSTRAINT fk_conciliacion_pago
        FOREIGN KEY (id_pago) REFERENCES pagos_transacciones(id_pago),
    CONSTRAINT fk_conciliacion_revisor
        FOREIGN KEY (id_usuario_revisor) REFERENCES usuarios(id_usuario)
);

CREATE TABLE historial_estatus_pago (
    id_historial_pago INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pago INT UNSIGNED NOT NULL,
    estado_anterior VARCHAR(30),
    estado_nuevo VARCHAR(30) NOT NULL,
    fecha_cambio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario_responsable INT UNSIGNED,
    comentario VARCHAR(500),
    CONSTRAINT fk_hist_pago
        FOREIGN KEY (id_pago) REFERENCES pagos_transacciones(id_pago),
    CONSTRAINT fk_hist_pago_usuario
        FOREIGN KEY (id_usuario_responsable) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 5. INCIDENCIAS, EVIDENCIAS Y MANTENIMIENTO
-- ============================================================

CREATE TABLE incidencias (
    id_incidencia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario_reporta INT UNSIGNED NOT NULL,
    id_unidad INT UNSIGNED,
    id_inmueble INT UNSIGNED,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    categoria ENUM('plomeria', 'electricidad', 'estructura', 'limpieza', 'seguridad', 'otro')
        NOT NULL DEFAULT 'otro',
    urgencia ENUM('baja', 'media', 'alta') NOT NULL DEFAULT 'media',
    estado ENUM('reportada', 'en_revision', 'asignada', 'en_proceso', 'resuelta', 'cerrada')
        NOT NULL DEFAULT 'reportada',
    fecha_reporte DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_incidencia_usuario
        FOREIGN KEY (id_usuario_reporta) REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_incidencia_unidad
        FOREIGN KEY (id_unidad) REFERENCES propiedades_unidades(id_unidad),
    CONSTRAINT fk_incidencia_inmueble
        FOREIGN KEY (id_inmueble) REFERENCES inmuebles(id_inmueble)
);

CREATE TABLE evidencias_incidencia (
    id_evidencia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_incidencia INT UNSIGNED NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo_archivo ENUM('JPG', 'PNG', 'MP4', 'PDF', 'otro') NOT NULL DEFAULT 'JPG',
    descripcion VARCHAR(255),
    fecha_carga DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evidencia_incidencia
        FOREIGN KEY (id_incidencia) REFERENCES incidencias(id_incidencia)
);

CREATE TABLE tickets_mantenimiento (
    id_ticket INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_incidencia INT UNSIGNED NOT NULL,
    fecha_programada DATETIME,
    descripcion_trabajo TEXT,
    estado ENUM('abierto', 'asignado', 'en_proceso', 'pendiente_revision', 'cerrado', 'cancelado')
        NOT NULL DEFAULT 'abierto',
    costo_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    fecha_cierre DATETIME,
    CONSTRAINT fk_ticket_incidencia
        FOREIGN KEY (id_incidencia) REFERENCES incidencias(id_incidencia)
);

CREATE TABLE asignacion_ordenes_trabajo (
    id_asignacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ticket INT UNSIGNED NOT NULL,
    id_tecnico INT UNSIGNED NOT NULL,
    fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descripcion_orden TEXT,
    estado ENUM('asignada', 'aceptada', 'en_proceso', 'completada', 'cancelada')
        NOT NULL DEFAULT 'asignada',
    costo_refacciones DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_asignacion_ticket
        FOREIGN KEY (id_ticket) REFERENCES tickets_mantenimiento(id_ticket),
    CONSTRAINT fk_asignacion_tecnico
        FOREIGN KEY (id_tecnico) REFERENCES usuarios(id_usuario)
);

CREATE TABLE firmas_conformidad (
    id_firma INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ticket INT UNSIGNED NOT NULL,
    id_usuario_residente INT UNSIGNED NOT NULL,
    ruta_firma VARCHAR(500) NOT NULL,
    fecha_firma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    comentario VARCHAR(500),
    CONSTRAINT fk_firma_ticket
        FOREIGN KEY (id_ticket) REFERENCES tickets_mantenimiento(id_ticket),
    CONSTRAINT fk_firma_residente
        FOREIGN KEY (id_usuario_residente) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 6. COMUNICADOS, VOTACIONES Y NOTIFICACIONES
-- ============================================================

CREATE TABLE comunicados (
    id_comunicado INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_inmueble INT UNSIGNED NOT NULL,
    id_usuario_autor INT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    contenido TEXT NOT NULL,
    fecha_publicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_programada DATETIME,
    estado ENUM('borrador', 'programado', 'publicado', 'cancelado')
        NOT NULL DEFAULT 'borrador',
    CONSTRAINT fk_comunicado_inmueble
        FOREIGN KEY (id_inmueble) REFERENCES inmuebles(id_inmueble),
    CONSTRAINT fk_comunicado_autor
        FOREIGN KEY (id_usuario_autor) REFERENCES usuarios(id_usuario)
);

CREATE TABLE votaciones (
    id_votacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_inmueble INT UNSIGNED NOT NULL,
    id_usuario_creador INT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATETIME NOT NULL,
    fecha_cierre DATETIME NOT NULL,
    estado ENUM('borrador', 'activa', 'cerrada', 'cancelada') NOT NULL DEFAULT 'borrador',
    CONSTRAINT fk_votacion_inmueble
        FOREIGN KEY (id_inmueble) REFERENCES inmuebles(id_inmueble),
    CONSTRAINT fk_votacion_creador
        FOREIGN KEY (id_usuario_creador) REFERENCES usuarios(id_usuario)
);

CREATE TABLE votos_registrados (
    id_voto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_votacion INT UNSIGNED NOT NULL,
    id_unidad INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    opcion_voto VARCHAR(180) NOT NULL,
    fecha_voto DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_voto_votacion
        FOREIGN KEY (id_votacion) REFERENCES votaciones(id_votacion),
    CONSTRAINT fk_voto_unidad
        FOREIGN KEY (id_unidad) REFERENCES propiedades_unidades(id_unidad),
    CONSTRAINT fk_voto_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE notificaciones_push_log (
    id_notificacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    mensaje VARCHAR(500) NOT NULL,
    fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado_envio ENUM('pendiente', 'enviada', 'fallida', 'leida') NOT NULL DEFAULT 'pendiente',
    CONSTRAINT fk_notificacion_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 7. ANALÍTICA, HISTORIAL Y KDD
-- ============================================================

CREATE TABLE historial_actividades_kdd (
    id_actividad INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED,
    tipo_actividad VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha_actividad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    datos_contexto JSON,
    CONSTRAINT fk_kdd_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE metricas_analiticas (
    id_metrica INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_inmueble INT UNSIGNED,
    nombre_metrica VARCHAR(120) NOT NULL,
    valor DECIMAL(16,4) NOT NULL,
    unidad_medida VARCHAR(40),
    periodo_inicio DATE,
    periodo_fin DATE,
    fecha_calculo DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_metrica_inmueble
        FOREIGN KEY (id_inmueble) REFERENCES inmuebles(id_inmueble)
);

CREATE TABLE registros_kdd_movil (
    id_registro_kdd INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    tipo_registro VARCHAR(100) NOT NULL,
    valor_registro DECIMAL(16,4),
    detalle TEXT,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_kdd_movil_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 8. CHATBOT ALKIRA
-- ============================================================

CREATE TABLE conversaciones_ia (
    id_conversacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME,
    estado ENUM('activa', 'finalizada', 'archivada') NOT NULL DEFAULT 'activa',
    CONSTRAINT fk_conversacion_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE mensajes_ia (
    id_mensaje INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_conversacion INT UNSIGNED NOT NULL,
    emisor ENUM('usuario', 'asistente') NOT NULL,
    contenido TEXT NOT NULL,
    fecha_mensaje DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensaje_conversacion
        FOREIGN KEY (id_conversacion) REFERENCES conversaciones_ia(id_conversacion)
);

-- ============================================================
-- 9. PASES QR Y BITÁCORA DE VISITAS
-- ============================================================

CREATE TABLE pases_acceso_qr (
    id_pase INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario_anfitrion INT UNSIGNED NOT NULL,
    nombre_visitante VARCHAR(150) NOT NULL,
    token_qr VARCHAR(255) NOT NULL UNIQUE,
    fecha_inicio_vigencia DATETIME NOT NULL,
    fecha_fin_vigencia DATETIME NOT NULL,
    estado ENUM('vigente', 'utilizado', 'vencido', 'revocado') NOT NULL DEFAULT 'vigente',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pase_anfitrion
        FOREIGN KEY (id_usuario_anfitrion) REFERENCES usuarios(id_usuario)
);

CREATE TABLE bitacora_visitas (
    id_visita INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pase INT UNSIGNED,
    id_guardia INT UNSIGNED,
    nombre_visitante VARCHAR(150) NOT NULL,
    fecha_entrada DATETIME,
    fecha_salida DATETIME,
    observaciones VARCHAR(500),
    CONSTRAINT fk_visita_pase
        FOREIGN KEY (id_pase) REFERENCES pases_acceso_qr(id_pase),
    CONSTRAINT fk_visita_guardia
        FOREIGN KEY (id_guardia) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 10. DATOS DE PRUEBA
-- ============================================================

INSERT INTO usuarios
(nombres, apellidos, correo, telefono, contrasena_hash, tipo_usuario)
VALUES
('Ana', 'Ramírez López', 'admin@alkira.test', '4770000001', '$2b$12$HASH_DEMO_NO_VALIDO_000000000000000000000000000000000000000000000000', 'administrador'),
('Luis', 'Hernández Soto', 'finanzas@alkira.test', '4770000002', '$2b$12$HASH_DEMO_NO_VALIDO_000000000000000000000000000000000000000000000000', 'finanzas'),
('María', 'García Torres', 'residente@alkira.test', '4770000003', '$2b$12$HASH_DEMO_NO_VALIDO_000000000000000000000000000000000000000000000000', 'inquilino'),
('Pedro', 'Martínez Ruiz', 'propietario@alkira.test', '4770000004', '$2b$12$HASH_DEMO_NO_VALIDO_000000000000000000000000000000000000000000000000', 'propietario'),
('Carlos', 'Pérez Díaz', 'tecnico@alkira.test', '4770000005', '$2b$12$HASH_DEMO_NO_VALIDO_000000000000000000000000000000000000000000000000', 'tecnico'),
('Sofía', 'López Cruz', 'guardia@alkira.test', '4770000006', '$2b$12$HASH_DEMO_NO_VALIDO_000000000000000000000000000000000000000000000000', 'guardia');

INSERT INTO inmuebles
(nombre, tipo, direccion, ciudad, estado_geografico, codigo_postal, descripcion)
VALUES
('Condominio Los Olivos', 'condominio', 'Av. del Parque 120', 'León', 'Guanajuato', '37150', 'Condominio de prueba'),
('Torre A', 'torre', 'Av. del Parque 120', 'León', 'Guanajuato', '37150', 'Torre residencial A');

UPDATE inmuebles SET id_inmueble_padre = 1 WHERE id_inmueble = 2;

INSERT INTO propiedades_unidades
(id_inmueble, numero_unidad, tipo_unidad, superficie_m2, cajones_estacionamiento, tiene_bodega, servicios_incluidos, estado_unidad)
VALUES
(2, 'A-101', 'departamento', 78.50, 1, TRUE, 'Agua, mantenimiento de áreas comunes', 'ocupada'),
(2, 'A-102', 'departamento', 65.00, 1, FALSE, 'Agua, mantenimiento de áreas comunes', 'disponible');

INSERT INTO usuarios_propiedades
(id_usuario, id_unidad, relacion, fecha_inicio, es_principal)
VALUES
(3, 1, 'inquilino', '2026-01-01', TRUE),
(4, 2, 'propietario', '2025-06-01', TRUE);

INSERT INTO contratos
(id_unidad, id_inquilino, fecha_inicio, fecha_fin, monto_renta, deposito_garantia, ruta_documento, estado_contrato)
VALUES
(1, 3, '2026-01-01', '2026-12-31', 8500.00, 8500.00, 'documentos/contratos/contrato_demo.pdf', 'vigente');

INSERT INTO conceptos_cobro (nombre, descripcion, tipo_concepto, monto_base, periodicidad)
VALUES
('Renta mensual', 'Renta mensual de la unidad', 'renta', 8500.00, 'mensual'),
('Mantenimiento', 'Cuota de mantenimiento mensual', 'mantenimiento', 750.00, 'mensual'),
('Recargo por mora', 'Recargo aplicado por retraso', 'recargo', 150.00, 'mensual');

INSERT INTO estados_cuenta
(id_contrato, id_concepto, periodo, fecha_emision, fecha_limite_pago, monto_principal, recargo_mora, monto_pagado, estado)
VALUES
(1, 1, '2026-10', '2026-10-01', '2026-10-10', 8500.00, 0.00, 0.00, 'vencido'),
(1, 2, '2026-10', '2026-10-01', '2026-10-10', 750.00, 0.00, 0.00, 'vencido');

INSERT INTO pagos_transacciones
(id_estado_cuenta, id_usuario_registro, fecha_pago, monto, metodo_pago, referencia_bancaria, estado, observaciones)
VALUES
(1, 3, '2026-10-15 10:30:00', 8500.00, 'transferencia', 'REF-DEMO-001', 'en_revision', 'Pago de prueba; pendiente de validación');

INSERT INTO comprobantes_pago
(id_pago, ruta_archivo, tipo_archivo, estado_revision)
VALUES
(1, 'documentos/comprobantes/comprobante_demo.pdf', 'PDF', 'pendiente');

INSERT INTO incidencias
(id_usuario_reporta, id_unidad, id_inmueble, titulo, descripcion, categoria, urgencia, estado)
VALUES
(3, 1, 2, 'Fuga debajo del fregadero', 'Se detecta una fuga de agua en la cocina.', 'plomeria', 'alta', 'reportada');

INSERT INTO evidencias_incidencia
(id_incidencia, ruta_archivo, tipo_archivo, descripcion)
VALUES
(1, 'documentos/incidencias/fuga_demo.jpg', 'JPG', 'Fotografía de prueba de la fuga');

INSERT INTO tickets_mantenimiento
(id_incidencia, fecha_programada, descripcion_trabajo, estado, costo_total)
VALUES
(1, '2026-10-20 09:00:00', 'Revisar conexión y reemplazar sello si es necesario.', 'asignado', 0.00);

INSERT INTO asignacion_ordenes_trabajo
(id_ticket, id_tecnico, descripcion_orden, estado)
VALUES
(1, 5, 'Atender fuga de agua en unidad A-101.', 'asignada');

INSERT INTO comunicados
(id_inmueble, id_usuario_autor, titulo, contenido, estado)
VALUES
(1, 1, 'Mantenimiento de áreas comunes', 'El sábado se realizará mantenimiento preventivo en áreas comunes.', 'publicado');

INSERT INTO votaciones
(id_inmueble, id_usuario_creador, titulo, descripcion, fecha_inicio, fecha_cierre, estado)
VALUES
(1, 1, 'Mejora de iluminación', 'Elegir propuesta para mejorar la iluminación del condominio.',
 '2026-10-19 08:00:00', '2026-10-25 20:00:00', 'activa');

INSERT INTO votos_registrados
(id_votacion, id_unidad, id_usuario, opcion_voto)
VALUES
(1, 1, 3, 'Luces LED');

INSERT INTO notificaciones_push_log
(id_usuario, titulo, mensaje, estado_envio)
VALUES
(3, 'Pago en revisión', 'Tu comprobante de pago fue recibido y está pendiente de validación.', 'enviada');

INSERT INTO historial_actividades_kdd
(id_usuario, tipo_actividad, descripcion, datos_contexto)
VALUES
(3, 'pago_reportado', 'El residente registró un pago para revisión.',
 JSON_OBJECT('modulo', 'pagos', 'referencia', 'REF-DEMO-001'));

INSERT INTO metricas_analiticas
(id_inmueble, nombre_metrica, valor, unidad_medida, periodo_inicio, periodo_fin)
VALUES
(1, 'porcentaje_ocupacion', 50.0000, 'porcentaje', '2026-10-01', '2026-10-31');

INSERT INTO registros_kdd_movil
(id_usuario, tipo_registro, valor_registro, detalle)
VALUES
(3, 'pagos_reportados', 1, 'Cantidad de pagos reportados en datos de prueba');

INSERT INTO conversaciones_ia (id_usuario, estado)
VALUES (3, 'activa');

INSERT INTO mensajes_ia (id_conversacion, emisor, contenido)
VALUES
(1, 'usuario', '¿Tengo pagos pendientes?'),
(1, 'asistente', 'Hay cargos de octubre pendientes de validación en este entorno de prueba.');

INSERT INTO pases_acceso_qr
(id_usuario_anfitrion, nombre_visitante, token_qr, fecha_inicio_vigencia, fecha_fin_vigencia, estado)
VALUES
(3, 'Roberto Sánchez', 'TOKEN-QR-DEMO-001', '2026-10-19 13:00:00', '2026-10-19 18:00:00', 'vigente');

INSERT INTO bitacora_visitas
(id_pase, id_guardia, nombre_visitante, observaciones)
VALUES
(1, 6, 'Roberto Sánchez', 'Pase creado para prueba; entrada aún no registrada');