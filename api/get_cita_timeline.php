<?php
/**
 * get_cita_timeline.php — Fase 4 (C): linea de tiempo de una cita.
 *
 * Devuelve los eventos de cita_eventos en orden cronologico + HTML listo para
 * inyectar en cualquier modal de detalle de cita (ecografista/admin/recep/paciente).
 * Acceso segun rol: admin/recep cualquiera; ecografista y paciente solo las suyas.
 */
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/citas/citas.php';

api_json();
if (api_uid() <= 0) {
    api_fail('No autenticado.', 403);
}

$cita_id = api_get_int('cita_id');
if ($cita_id <= 0) {
    api_fail('Cita no valida.', 200);
}

$rol = api_rol();
$uid = api_uid();

$st = $conex->prepare("SELECT paciente_id, ecografista_id FROM citas WHERE id = ?");
$st->bind_param('i', $cita_id);
$st->execute();
$cita = $st->get_result()->fetch_assoc();
$st->close();

if (!$cita) {
    api_fail('Cita no encontrada.', 200);
}

$puede = in_array($rol, ['administrador', 'recepcionista'], true)
    || ($rol === 'ecografista' && $uid === (int)$cita['ecografista_id'])
    || ($rol === 'paciente'    && $uid === (int)$cita['paciente_id']);

if (!$puede) {
    api_fail('Sin acceso a esta cita.', 403);
}

$eventos = eco_cita_eventos($conex, $cita_id);
api_ok([
    'total' => count($eventos),
    'html'  => eco_cita_timeline_html($eventos),
]);
