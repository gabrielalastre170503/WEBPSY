-- Fase 2 (cierre): citas.estado — añade 'rechazada' y 'no_asistio' (APLICADA).
--
-- El codigo ya usa 'rechazada' en filtros/badges/labels (mi_historial_citas,
-- mis_citas_paciente, estadisticas_ecografista, lib/citas.php) pero el enum no
-- la admitia → en MySQL no estricto un INSERT/UPDATE con ese valor metia ''
-- silenciosamente (estado corrupto). Se anade tambien 'no_asistio' para el
-- KPI de no-show (ausencias).
--
-- Cambio aditivo: ninguna fila existente usa los valores nuevos -> sin perdida.

ALTER TABLE citas MODIFY COLUMN estado
    ENUM('pendiente','confirmada','cancelada','pendiente_paciente','reprogramada','completada','rechazada','no_asistio')
    NOT NULL DEFAULT 'pendiente';
