<?php
session_start();
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';

api_json();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cita no valido']);
    exit();
}

$cita_id     = (int)$_GET['id'];
$paciente_id = (int)$_SESSION['usuario_id'];

$sql = "SELECT c.*,
               eco.nombre_completo AS ecografista_nombre,
               eco.rol             AS ecografista_rol,
               t.id   AS tipo_id,
               t.nombre AS tipo_nombre,
               t.categoria AS tipo_categoria,
               t.descripcion AS tipo_descripcion,
               t.icono AS tipo_icono
        FROM citas c
        LEFT JOIN usuarios eco         ON c.ecografista_id = eco.id
        LEFT JOIN tipos_ecografias t   ON c.tipo_ecografia_id = t.id
        WHERE c.id = ? AND c.paciente_id = ?";
$stmt = $conex->prepare($sql);
$stmt->bind_param("ii", $cita_id, $paciente_id);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cita) {
    http_response_code(404);
    echo json_encode(['error' => 'Cita no encontrada']);
    exit();
}

$respuesta = [
    'fecha_cita'           => $cita['fecha_cita'] ? date('d/m/Y h:i A', strtotime($cita['fecha_cita'])) : 'Por confirmar',
    'estado'               => ucfirst($cita['estado']),
    'ecografista_nombre'   => $cita['ecografista_nombre'] ?? 'No asignado',
    'tipo_estudio'         => $cita['tipo_nombre'] ?? 'No especificado',
    'tipo_categoria'       => $cita['tipo_categoria'] ?? '',
    'tipo_descripcion'     => $cita['tipo_descripcion'] ?? '',
    'tipo_icono'           => $cita['tipo_icono'] ?? 'fa-solid fa-wave-square',
    'motivo_consulta'      => $cita['motivo_consulta'] ?? $cita['motivo'] ?? '',
    'reprogramacion_motivo'=> $cita['reprogramacion_motivo'] ?? null,
];

echo json_encode($respuesta);
$conex->close();
