<?php
/**
 * guardar_encuesta.php — Encuesta de satisfacción post-estudio (Fase 4). POST + CSRF.
 * El paciente califica (1-5) una cita propia ya completada. Una encuesta por cita.
 */
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';

api_json();
api_require_roles(['paciente']);
api_require_post();
api_require_csrf();

$cita_id    = api_int('cita_id');
$puntuacion = api_int('puntuacion');
$comentario = api_str('comentario');
$uid        = api_uid();

if ($cita_id <= 0 || $puntuacion < 1 || $puntuacion > 5) {
    api_fail('Puntuación inválida.');
}
if (mb_strlen($comentario) > 1000) {
    $comentario = mb_substr($comentario, 0, 1000);
}

// La cita debe ser del paciente y estar completada.
$st = $conex->prepare("SELECT id FROM citas WHERE id = ? AND paciente_id = ? AND estado = 'completada'");
$st->bind_param('ii', $cita_id, $uid);
$st->execute();
$existe = $st->get_result()->fetch_assoc();
$st->close();
if (!$existe) {
    api_fail('Esta cita no está disponible para calificar.', 200);
}

$coment = $comentario !== '' ? $comentario : null;
$ok = false;
$dup = false;
try {
    $ins = $conex->prepare("INSERT INTO encuestas (cita_id, paciente_id, puntuacion, comentario) VALUES (?, ?, ?, ?)");
    $ins->bind_param('iiis', $cita_id, $uid, $puntuacion, $coment);
    $ok = $ins->execute();
    $ins->close();
} catch (mysqli_sql_exception $e) {
    $dup = ((int)$e->getCode() === 1062); // UNIQUE uq_encuesta_cita -> ya calificada
}
$conex->close();

if ($ok) {
    api_ok(['message' => '¡Gracias por tu opinión!']);
}
api_fail($dup ? 'Ya calificaste esta cita.' : 'No se pudo registrar la encuesta.', 200);
