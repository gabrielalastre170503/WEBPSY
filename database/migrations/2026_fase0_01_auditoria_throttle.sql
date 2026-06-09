-- =============================================================================
-- Fase 0 · cierre — Auditoria + throttling de login persistente.
--   * auditoria       : bitacora de acciones sensibles (quien / que / cuando / IP).
--   * intentos_login  : registro de intentos de acceso para frenar fuerza bruta.
--
-- Idempotente (CREATE TABLE IF NOT EXISTS). No borra ni modifica datos previos.
--   mysql -u root db_clinica_ecografias < 2026_fase0_01_auditoria_throttle.sql
-- =============================================================================

USE db_clinica_ecografias;

-- Bitacora de acciones sensibles. usuario_id NULL = accion anonima (login fallido).
CREATE TABLE IF NOT EXISTS auditoria (
    id          BIGINT(20)   NOT NULL AUTO_INCREMENT,
    usuario_id  INT(11)      NULL,
    accion      VARCHAR(60)  NOT NULL,
    entidad     VARCHAR(40)  NULL,
    entidad_id  INT(11)      NULL,
    detalle     TEXT         NULL,
    ip          VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aud_usuario (usuario_id),
    KEY idx_aud_accion (accion),
    KEY idx_aud_creado (creado_en),
    CONSTRAINT fk_aud_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos de login (exitosos y fallidos). El throttle cuenta los fallidos por
-- correo o IP dentro de una ventana de tiempo. Sin FK: el correo puede no existir.
CREATE TABLE IF NOT EXISTS intentos_login (
    id         BIGINT(20)   NOT NULL AUTO_INCREMENT,
    correo     VARCHAR(100) NOT NULL,
    ip         VARCHAR(45)  NULL,
    exito      TINYINT(1)   NOT NULL DEFAULT 0,
    creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intentos_correo (correo, creado_en),
    KEY idx_intentos_ip (ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
