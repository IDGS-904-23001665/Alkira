-- HU07 | Migracion incremental. Ejecutar en la misma base que schema.sql.
-- No borra tablas ni registros existentes. Requiere MySQL 8 / MariaDB con CTE recursivas.
CREATE TABLE IF NOT EXISTS hu07_opciones (
  id_opcion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_votacion INT UNSIGNED NOT NULL,
  texto VARCHAR(180) NOT NULL,
  posicion TINYINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_hu07_opcion_posicion (id_votacion, posicion),
  CONSTRAINT fk_hu07_opcion_votacion FOREIGN KEY (id_votacion) REFERENCES votaciones(id_votacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nueva tabla con UNIQUE a nivel BD; no altera los votos históricos anteriores.
CREATE TABLE IF NOT EXISTS hu07_votos (
  id_voto BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_votacion INT UNSIGNED NOT NULL,
  id_opcion INT UNSIGNED NOT NULL,
  id_unidad INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  fecha_voto DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hu07_un_voto_por_unidad (id_votacion, id_unidad),
  KEY ix_hu07_voto_opcion (id_opcion),
  CONSTRAINT fk_hu07_voto_votacion FOREIGN KEY (id_votacion) REFERENCES votaciones(id_votacion),
  CONSTRAINT fk_hu07_voto_opcion FOREIGN KEY (id_opcion) REFERENCES hu07_opciones(id_opcion),
  CONSTRAINT fk_hu07_voto_unidad FOREIGN KEY (id_unidad) REFERENCES propiedades_unidades(id_unidad),
  CONSTRAINT fk_hu07_voto_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hu07_destinatarios (
  id_comunicado INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  fecha_lectura DATETIME NULL,
  fecha_recepcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_comunicado, id_usuario),
  KEY ix_hu07_destinatario_usuario (id_usuario, fecha_lectura),
  CONSTRAINT fk_hu07_destinatario_comunicado FOREIGN KEY (id_comunicado) REFERENCES comunicados(id_comunicado),
  CONSTRAINT fk_hu07_destinatario_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hu07_dispositivos (
  id_dispositivo BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT UNSIGNED NOT NULL,
  token_fcm VARCHAR(512) NOT NULL,
  plataforma ENUM('android') NOT NULL DEFAULT 'android',
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hu07_token_fcm (token_fcm),
  KEY ix_hu07_dispositivo_usuario (id_usuario),
  CONSTRAINT fk_hu07_dispositivo_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hu07_envios (
  id_envio BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_comunicado INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  canal ENUM('correo', 'push') NOT NULL,
  estado ENUM('pendiente', 'procesando', 'enviado', 'fallido') NOT NULL DEFAULT 'pendiente',
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_error VARCHAR(350) NULL,
  fecha_envio DATETIME NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hu07_envio (id_comunicado, id_usuario, canal),
  KEY ix_hu07_envio_cola (estado, intentos),
  CONSTRAINT fk_hu07_envio_comunicado FOREIGN KEY (id_comunicado) REFERENCES comunicados(id_comunicado),
  CONSTRAINT fk_hu07_envio_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
