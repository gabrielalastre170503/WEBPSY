-- Fase 3 (b): enlaces de resultado por token (sin login).
--
-- Un token da acceso de SOLO LECTURA a los resultados de un informe
-- (imagenes + adjuntos) durante una ventana de tiempo y un numero limitado de
-- aperturas. Pensado para enviar al paciente por correo/WhatsApp sin obligarle
-- a crear cuenta ni iniciar sesion.
--
-- Seguridad:
--   * Se guarda SOLO el sha256 del token (token_hash). El token en claro vive
--     unicamente en la URL entregada: una fuga de BD no produce enlaces usables.
--   * El token en claro son 64 hex (32 bytes aleatorios): imposible de adivinar.
--   * Caducidad (expira_en) + tope de aperturas (max_usos, NULL = sin tope) +
--     revocacion manual (revocado).

CREATE TABLE IF NOT EXISTS descarga_tokens (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash    CHAR(64)        NOT NULL,
    informe_id    INT             NOT NULL,
    creado_por    INT             NULL,
    max_usos      INT             NULL,                 -- NULL = sin tope (solo expira)
    usos          INT             NOT NULL DEFAULT 0,
    expira_en     DATETIME        NOT NULL,
    revocado      TINYINT(1)      NOT NULL DEFAULT 0,
    creado_en     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_uso_en DATETIME        NULL,
    ultimo_uso_ip VARCHAR(45)     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dt_hash (token_hash),
    KEY idx_dt_informe (informe_id),
    KEY idx_dt_expira (expira_en),
    CONSTRAINT fk_dt_informe FOREIGN KEY (informe_id) REFERENCES informes_estudios(id) ON DELETE CASCADE,
    CONSTRAINT fk_dt_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
