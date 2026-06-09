-- Fase 3 (c): firma digital trazable de informes.
--
-- Anade a informes_estudios el sello criptografico de la firma:
--   * documento_sha256: huella SHA-256 del contenido canonico (integridad).
--   * sello_firma:      HMAC-SHA256 que ata huella + firmante + fecha (autenticidad).
--   * sello_version:    version del esquema de sellado (para futura rotacion).
-- La identidad y el momento ya viven en firmado_por / fecha_firma.
-- El PDF firmado se guarda en informe_archivos con categoria 'pdf_firmado'.

ALTER TABLE informes_estudios
    ADD COLUMN IF NOT EXISTS documento_sha256 CHAR(64)    NULL AFTER fecha_firma,
    ADD COLUMN IF NOT EXISTS sello_firma      VARCHAR(128) NULL AFTER documento_sha256,
    ADD COLUMN IF NOT EXISTS sello_version    VARCHAR(40)  NULL AFTER sello_firma;
