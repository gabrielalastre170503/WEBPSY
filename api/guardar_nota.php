<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../conexion.php';
api_json();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']); exit;
}

api_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método inválido']); exit;
}

$paciente_id  = (int)($_POST['paciente_id'] ?? 0);
$fecha_sesion = trim($_POST['fecha_sesion'] ?? '');
$contenido    = trim($_POST['contenido'] ?? '');
$ecografista_id = (int)$_SESSION['usuario_id'];

if ($paciente_id <= 0 || $contenido === '') {
    echo json_encode(['ok' => false, 'error' => 'Faltan datos requeridos']); exit;
}

if ($fecha_sesion === '') {
    $fecha_sesion = date('Y-m-d H:i:s');
} else {
    $fecha_sesion = str_replace('T', ' ', $fecha_sesion);
    if (strlen($fecha_sesion) === 16) $fecha_sesion .= ':00';
}

if ($s = $conex->prepare("INSERT INTO notas_clinicas (paciente_id, ecografista_id, fecha_sesion, contenido) VALUES (?,?,?,?)")) {
    $s->bind_param('iiss', $paciente_id, $ecografista_id, $fecha_sesion, $contenido);
    if ($s->execute()) {
        echo json_encode(['ok' => true, 'id' => $s->insert_id]);
    } else {
        error_log('guardar_nota: ' . $s->error);
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la nota.']);
    }
    $s->close();
} else {
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
}
