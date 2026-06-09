-- Fase 4 (B): control de recordatorios de cita (idempotencia del cron).
--
-- Una fila por (cita, ventana) marca que el recordatorio de esa ventana ya se
-- proceso, para que el cron no lo repita. `detalle` guarda el resultado por canal.

CREATE TABLE IF NOT EXISTS cita_recordatorios (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cita_id   INT             NOT NULL,
    ventana   VARCHAR(20)     NOT NULL,          -- '24h' (extensible: '2h', '1h')
    canal     VARCHAR(20)     NOT NULL DEFAULT 'multi',
    estado    VARCHAR(20)     NOT NULL DEFAULT 'enviado',
    detalle   VARCHAR(255)    NULL,
    creado_en DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cita_ventana (cita_id, ventana),
    CONSTRAINT fk_recd_cita FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
