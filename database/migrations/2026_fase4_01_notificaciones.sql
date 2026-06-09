-- Fase 4 (A): notificaciones in-app.
--
-- Bandeja por usuario. Las genera el sistema en eventos relevantes (cita
-- confirmada/reprogramada/cancelada, propuesta de fecha, recordatorio, informe
-- firmado...). La campana del topbar las consulta y marca como leidas.

CREATE TABLE IF NOT EXISTS notificaciones (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT             NOT NULL,             -- destinatario
    tipo        VARCHAR(40)     NOT NULL,             -- cita_confirmada, recordatorio, ...
    titulo      VARCHAR(140)    NOT NULL,
    mensaje     VARCHAR(400)    NULL,
    url         VARCHAR(255)    NULL,                 -- destino al hacer click
    icono       VARCHAR(40)     NULL,                 -- clase Font Awesome
    leida       TINYINT(1)      NOT NULL DEFAULT 0,
    leida_en    DATETIME        NULL,
    creado_en   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_dest (usuario_id, leida, creado_en),
    CONSTRAINT fk_notif_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
