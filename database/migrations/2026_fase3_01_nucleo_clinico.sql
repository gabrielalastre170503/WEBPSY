-- =============================================================================
-- Fase 3 · 3A — Integridad del nucleo clinico (informes_estudios)
--   * contadores         : secuencia atomica para numeracion correlativa
--   * numero_informe      : ahora UNIQUE (los borradores quedan NULL = permitido)
--   * firma               : firmado_por + fecha_firma (finalizado -> firmado)
--   * anulacion           : anulado_por + fecha_anulacion + motivo_anulacion
--   * finalizado_en        : marca de cuando paso a finalizado (auditoria)
-- =============================================================================

USE db_clinica_ecografias;

-- Contador correlativo. Incremento atomico por conexion via LAST_INSERT_ID().
CREATE TABLE IF NOT EXISTS contadores (
    clave VARCHAR(40) NOT NULL,
    valor INT(11)     NOT NULL DEFAULT 0,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE informes_estudios
    ADD COLUMN finalizado_en    DATETIME     NULL AFTER estado,
    ADD COLUMN firmado_por      INT(11)      NULL AFTER finalizado_en,
    ADD COLUMN fecha_firma      DATETIME     NULL AFTER firmado_por,
    ADD COLUMN anulado_por      INT(11)      NULL AFTER fecha_firma,
    ADD COLUMN fecha_anulacion  DATETIME     NULL AFTER anulado_por,
    ADD COLUMN motivo_anulacion VARCHAR(255) NULL AFTER fecha_anulacion,
    ADD UNIQUE KEY uk_inf_numero (numero_informe),
    ADD KEY idx_inf_firmado (firmado_por),
    ADD KEY idx_inf_anulado (anulado_por),
    ADD CONSTRAINT fk_inf_firmado FOREIGN KEY (firmado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_inf_anulado FOREIGN KEY (anulado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Backfill: los informes ya finalizados toman su fecha de creacion como finalizado_en.
UPDATE informes_estudios
   SET finalizado_en = creado_en
 WHERE estado IN ('finalizado','firmado') AND finalizado_en IS NULL;

-- Semilla del contador del anio actual a partir del maximo correlativo ya existente
-- (los numeros viejos con formato aleatorio no cuentan; arrancan en 0 para el anio).
INSERT IGNORE INTO contadores (clave, valor) VALUES (CONCAT('informe_', YEAR(CURDATE())), 0);
