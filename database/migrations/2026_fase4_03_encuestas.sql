-- Fase 4 (cierre): encuestas de satisfaccion post-estudio (APLICADA).
--
-- El paciente califica (1-5) una cita propia ya completada; una encuesta por
-- cita (UNIQUE cita_id). Alimenta el KPI de satisfaccion en Reportes (Fase 6).

CREATE TABLE IF NOT EXISTS encuestas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    cita_id      INT NOT NULL,
    paciente_id  INT NOT NULL,
    puntuacion   TINYINT NOT NULL,                 -- 1..5
    comentario   TEXT NULL,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_encuesta_cita (cita_id),
    KEY idx_enc_pac (paciente_id),
    CONSTRAINT fk_enc_cita FOREIGN KEY (cita_id)     REFERENCES citas(id)    ON DELETE CASCADE,
    CONSTRAINT fk_enc_pac  FOREIGN KEY (paciente_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
