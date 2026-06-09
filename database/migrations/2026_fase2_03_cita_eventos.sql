-- =============================================================================
-- Fase 2 · C — Normalizacion del ciclo de vida de la cita: `cita_eventos`.
--
-- Hasta ahora el historial de una cita vivia solo en columnas planas de `citas`
-- (fecha_propuesta, reprogramacion_motivo, estado...). Eso pierde el rastro: solo
-- queda el ULTIMO cambio. `cita_eventos` registra cada transicion como una fila
-- append-only -> timeline real (solicitada -> confirmada -> reprogramada -> ...).
--
-- Complementa, no duplica, a `auditoria` (Fase 0): auditoria = rastro de seguridad
-- global; cita_eventos = historia clinica/operativa de UNA cita, visible en su ficha.
--
-- Idempotente (CREATE TABLE IF NOT EXISTS).
--   mysql -u root db_clinica_ecografias < 2026_fase2_03_cita_eventos.sql
-- =============================================================================

USE db_clinica_ecografias;

CREATE TABLE IF NOT EXISTS cita_eventos (
    id              BIGINT(20)  NOT NULL AUTO_INCREMENT,
    cita_id         INT(11)     NOT NULL,
    tipo            VARCHAR(40) NOT NULL,           -- solicitada, confirmada, reprogramada, propuesta, aceptada, rechazada, completada, cancelada, pago_registrado
    estado_anterior VARCHAR(30) NULL,              -- estado de la cita antes del evento
    estado_nuevo    VARCHAR(30) NULL,              -- estado de la cita despues del evento
    actor_id        INT(11)     NULL,              -- usuario que origina el evento (NULL = sistema / paciente sin sesion)
    actor_rol       VARCHAR(20) NULL,
    detalle         TEXT        NULL,              -- JSON con datos extra (fecha nueva, motivo, monto...)
    creado_en       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ce_cita (cita_id, creado_en),
    KEY idx_ce_tipo (tipo),
    CONSTRAINT fk_ce_cita  FOREIGN KEY (cita_id)  REFERENCES citas(id)    ON DELETE CASCADE,
    CONSTRAINT fk_ce_actor FOREIGN KEY (actor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
