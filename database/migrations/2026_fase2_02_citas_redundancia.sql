-- =============================================================================
-- Fase 2 · B — Elimina columnas redundantes/muertas de `citas`.
--
-- Analisis de datos (45 filas):
--   * `motivo`           : duplicado exacto de `motivo_consulta` (0 filas difieren,
--                          0 filas con dato que no este en motivo_consulta). Solo lo
--                          escribia guardar_cita_directa.php (mismo valor en ambas).
--                          Ningun SELECT lo lee. -> redundante.
--   * `notas`            : 0 filas, sin escritor ni lector. -> columna muerta.
--
-- Se conservan (semantica distinta, NO son duplicados):
--   motivo_consulta (motivo canonico), motivo_principal (categoria/resumen estudios),
--   notas_paciente, reprogramacion_motivo, notificacion_paciente.
--
-- Requiere: guardar_cita_directa.php ya actualizado para no escribir `motivo`.
-- =============================================================================

USE db_clinica_ecografias;

ALTER TABLE citas
    DROP COLUMN motivo,
    DROP COLUMN notas;
