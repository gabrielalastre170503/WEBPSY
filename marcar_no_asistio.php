<?php
/**
 * marcar_no_asistio.php — Marca una cita como inasistencia (no-show). POST + CSRF.
 * Solo aplica a citas confirmadas/reprogramadas cuya fecha ya paso.
 * Acceso: ecografista (solo sus citas), recepcionista y administrador (cualquiera).
 */
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/seguridad/seguridad.php';
require_once __DIR__ . '/lib/citas/citas.php';

api_json();
$rol = api_rol();
if (!in_array($rol, ['ecografista', 'recepcionista', 'administrador'], true)) {
    api_fail('Acceso denegado.', 200);
}

api_require_csrf();

$cita_id = api_int('cita_id');
if ($cita_id <= 0) {
    api_fail('No se proporcionó el ID de la cita.', 200);
}

// Un ecografista solo puede tocar sus propias citas.
if ($rol === 'ecografista') {
    $st = $conex->prepare("UPDATE citas SET estado = 'no_asistio'
        WHERE id = ? AND ecografista_id = ?
          AND estado IN ('confirmada','reprogramada') AND fecha_cita < NOW()");
    $st->bind_param('ii', $cita_id, $_SESSION['usuario_id']);
} else {
    $st = $conex->prepare("UPDATE citas SET estado = 'no_asistio'
        WHERE id = ?
          AND estado IN ('confirmada','reprogramada') AND fecha_cita < NOW()");
    $st->bind_param('i', $cita_id);
}
$st->execute();
$ok = $st->affected_rows > 0;
$st->close();

if ($ok) {
    eco_auditar($conex, 'cita_no_asistio', ['entidad' => 'cita', 'entidad_id' => $cita_id]);
    eco_cita_evento($conex, $cita_id, 'no_asistio', ['estado_nuevo' => 'no_asistio']);
    $conex->close();
    api_ok(['message' => 'Cita marcada como inasistencia.']);
}

$conex->close();
api_fail('No se pudo marcar. La cita debe estar confirmada/reprogramada y su fecha ya haber pasado.', 200);
