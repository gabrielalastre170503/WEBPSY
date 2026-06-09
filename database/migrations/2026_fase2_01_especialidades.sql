-- =============================================================================
-- Fase 2 · A1 — Normalizacion de especialidades (1NF)
-- Reemplaza usuarios.especialidades (CSV en una celda) por:
--   especialidades          : catalogo maestro de especialidades
--   usuario_especialidades   : tabla puente N:M ecografista <-> especialidad
-- La columna usuarios.especialidades se elimina en 2026_fase2_01c (despues de
-- migrar los datos con 2026_fase2_01b_migrar_especialidades.php).
-- =============================================================================

USE db_clinica_ecografias;

-- Catalogo maestro de especialidades
CREATE TABLE IF NOT EXISTS especialidades (
    id        INT(11)     NOT NULL AUTO_INCREMENT,
    nombre    VARCHAR(80) NOT NULL,
    activa    TINYINT(1)  NOT NULL DEFAULT 1,
    creado_en TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_especialidad_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Puente N:M: que especialidades tiene cada ecografista
CREATE TABLE IF NOT EXISTS usuario_especialidades (
    usuario_id      INT(11) NOT NULL,
    especialidad_id INT(11) NOT NULL,
    PRIMARY KEY (usuario_id, especialidad_id),
    KEY idx_ue_esp (especialidad_id),
    CONSTRAINT fk_ue_usuario FOREIGN KEY (usuario_id)      REFERENCES usuarios(id)        ON DELETE CASCADE,
    CONSTRAINT fk_ue_esp     FOREIGN KEY (especialidad_id) REFERENCES especialidades(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
