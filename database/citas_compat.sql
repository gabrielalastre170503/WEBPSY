-- Columnas de compatibilidad para soportar el flujo de citas existente.
-- Mantienen el modelo conceptual nuevo (ecografista_id, tipo_ecografia_id)
-- pero anaden columnas auxiliares que algunos formularios/endpoints usaban.

USE db_clinica_ecografias;

ALTER TABLE citas
    ADD COLUMN motivo_consulta        TEXT          NULL AFTER motivo,
    ADD COLUMN modalidad              ENUM('presencial','virtual') NOT NULL DEFAULT 'presencial' AFTER motivo_consulta,
    ADD COLUMN tipo_cita              ENUM('primera_consulta','seguimiento') NOT NULL DEFAULT 'primera_consulta' AFTER modalidad,
    ADD COLUMN fecha_respuesta        DATETIME      NULL AFTER fecha_cita,
    ADD COLUMN fecha_propuesta        DATETIME      NULL AFTER fecha_respuesta,
    ADD COLUMN motivo_principal       VARCHAR(255)  NULL AFTER fecha_propuesta,
    ADD COLUMN notas_paciente         TEXT          NULL AFTER motivo_principal,
    ADD COLUMN reprogramacion_motivo  TEXT          NULL AFTER notas_paciente,
    ADD COLUMN notificacion_paciente  TEXT          NULL AFTER reprogramacion_motivo;

ALTER TABLE citas
    MODIFY COLUMN estado ENUM('pendiente','confirmada','cancelada','pendiente_paciente','reprogramada','completada') NOT NULL DEFAULT 'pendiente';

-- Migracion de datos viejos (si la BD anterior existe)
INSERT INTO db_clinica_ecografias.citas
    (id, paciente_id, ecografista_id, fecha_solicitud, fecha_cita,
     motivo, motivo_consulta, modalidad, tipo_cita,
     fecha_respuesta, fecha_propuesta, motivo_principal, notas_paciente,
     reprogramacion_motivo, notificacion_paciente, estado)
SELECT
    id, paciente_id, psicologo_id, fecha_solicitud, fecha_cita,
    motivo_consulta, motivo_consulta, modalidad, tipo_cita,
    fecha_respuesta, fecha_propuesta, motivo_principal, notas_paciente,
    reprogramacion_motivo, notificacion_paciente, estado
FROM formulario.citas;
