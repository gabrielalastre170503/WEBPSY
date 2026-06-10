-- Fase cumplimiento médico — Consentimiento informado del paciente.
-- Registra la aceptación del consentimiento (por versión de texto). Un paciente
-- vuelve a aceptar si la versión vigente (ECO_CONSENT_VERSION) cambia.

CREATE TABLE IF NOT EXISTS consentimientos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id  INT NOT NULL,
    version      VARCHAR(20) NOT NULL,
    aceptado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip           VARCHAR(45) NULL,
    user_agent   VARCHAR(255) NULL,
    UNIQUE KEY uq_consentimiento (paciente_id, version),
    CONSTRAINT fk_consent_paciente FOREIGN KEY (paciente_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
