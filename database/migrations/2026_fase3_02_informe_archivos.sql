-- =============================================================================
-- Fase 3 · 3B — Archivos del informe: imagenes ecograficas y adjuntos.
--
-- Antes no existia storage de imagenes/adjuntos (bloqueador raiz de Fase 3 y de
-- varias funciones premium: visor del paciente, firma PDF, descarga por token).
--
-- Los binarios NO viven en la BD: se guardan en disco bajo /uploads/informes/<id>/
-- (carpeta protegida con .htaccess deny-all; solo se sirven via handler PHP con
-- control de acceso). Esta tabla es el indice + metadatos + hash de integridad.
--
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- =============================================================================

USE db_clinica_ecografias;

CREATE TABLE IF NOT EXISTS informe_archivos (
    id              BIGINT(20)   NOT NULL AUTO_INCREMENT,
    informe_id      INT(11)      NOT NULL,
    categoria       ENUM('imagen','adjunto','pdf_firmado') NOT NULL DEFAULT 'imagen',
    nombre_original VARCHAR(180) NOT NULL,                 -- como lo subio el usuario
    nombre_guardado VARCHAR(120) NOT NULL,                 -- nombre aleatorio en disco
    ruta_rel        VARCHAR(255) NOT NULL,                 -- ruta relativa bajo /uploads
    mime            VARCHAR(100) NULL,
    tamano          INT(11)      NOT NULL DEFAULT 0,        -- bytes
    sha256          CHAR(64)     NULL,                      -- integridad / antimanipulacion
    subido_por      INT(11)      NULL,
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ia_informe (informe_id, categoria),
    KEY idx_ia_subido (subido_por),
    CONSTRAINT fk_ia_informe FOREIGN KEY (informe_id) REFERENCES informes_estudios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ia_subido  FOREIGN KEY (subido_por) REFERENCES usuarios(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
