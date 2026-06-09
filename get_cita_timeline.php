<?php
/**
 * get_cita_timeline.php — Fase 4 (C): linea de tiempo de una cita.
 *
 * Devuelve los eventos de cita_eventos en orden cronologico + HTML listo para
 * inyectar en cualquier modal de detalle de cita (ecografista/admin/recep/paciente).
 * Acceso segun rol: admin/recep cualquiera; ecografista y paciente solo las suyas.
 */
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/citas.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit();
}

$cita_id = isset($_GET['cita_id']) ? (int)$_GET['cita_id'] : 0;
if ($cita_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Cita no valida.']);
    exit();
}

$rol = (string)$_SESSION['rol'];
$uid = (int)$_SESSION['usuario_id'];

$st = $conex->prepare("SELECT paciente_id, ecografista_id FROM citas WHERE id = ?");
$st->bind_param('i', $cita_id);
$st->execute();
$cita = $st->get_result()->fetch_assoc();
$st->close();

if (!$cita) {
    echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
    exit();
}

$puede = in_array($rol, ['administrador', 'recepcionista'], true)
    || ($rol === 'ecografista' && $uid === (int)$cita['ecografista_id'])
    || ($rol === 'paciente'    && $uid === (int)$cita['paciente_id']);

if (!$puede) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso a esta cita.']);
    exit();
}

$eventos = eco_cita_eventos($conex, $cita_id);
echo json_encode([
    'success' => true,
    'total'   => count($eventos),
    'html'    => eco_cita_timeline_html($eventos),
]);
