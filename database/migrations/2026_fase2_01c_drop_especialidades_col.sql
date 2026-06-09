-- =============================================================================
-- Fase 2 · A1c — Elimina la columna denormalizada usuarios.especialidades.
-- Ejecutar SOLO despues de migrar datos (2026_fase2_01b) y actualizar el codigo
-- de lectura/escritura para usar el modelo normalizado.
-- =============================================================================

USE db_clinica_ecografias;

ALTER TABLE usuarios DROP COLUMN especialidades;
