<?php
session_start();
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';

api_json();

// Seguridad: Solo psicólogos y roles autorizados
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cita no válido']);
    exit();
}

$cita_id = (int)$_GET['id'];
$ecografista_id = $_SESSION['usuario_id'];

// Trae toda la información del paciente y los datos de la solicitud
$stmt = $conex->prepare("
    SELECT c.id, c.fecha_solicitud, c.fecha_cita, c.fecha_propuesta, c.estado,
           c.motivo_consulta, c.motivo_principal, c.notas_paciente,
           c.modalidad, c.tipo_cita, c.tipo_ecografia_id,
           u.nombre_completo AS paciente_nombre, u.cedula AS paciente_cedula,
           TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS paciente_edad, u.correo AS paciente_correo,
           u.fecha_nacimiento AS paciente_fnac, u.fecha_registro AS paciente_registro,
           t.nombre AS tipo_nombre, t.icono AS tipo_icono
    FROM citas c
    JOIN usuarios u ON c.paciente_id = u.id
    LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
    WHERE c.id = ? AND c.ecografista_id = ?
");
$stmt->bind_param("ii", $cita_id, $ecografista_id);
$stmt->execute();
$resultado = $stmt->get_result();
$cita = $resultado->fetch_assoc();

if (!$cita) {
    http_response_code(404);
    echo json_encode(['error' => 'Solicitud no encontrada o no asignada a usted.']);
    exit();
}

// Formatear los datos para que se vean bien
$cita['fecha_propuesta_formateada'] = $cita['fecha_cita'] ? date('d/m/Y h:i A', strtotime($cita['fecha_cita'])) : 'Sin fecha propuesta';
$cita['fecha_solicitud_formateada'] = $cita['fecha_solicitud'] ? date('d/m/Y h:i A', strtotime($cita['fecha_solicitud'])) : '—';
$cita['paciente_fnac_formateada']   = ($cita['paciente_fnac'] && $cita['paciente_fnac'] !== '0000-00-00') ? date('d/m/Y', strtotime($cita['paciente_fnac'])) : '—';
$cita['paciente_registro_formateada'] = $cita['paciente_registro'] ? date('d/m/Y', strtotime($cita['paciente_registro'])) : '—';
$cita['tipo_cita_formateado'] = $cita['tipo_cita'] ? ucwords(str_replace('_', ' ', $cita['tipo_cita'])) : '—';
$cita['modalidad_formateada'] = $cita['modalidad'] ? ucfirst($cita['modalidad']) : '—';

echo json_encode($cita, JSON_UNESCAPED_UNICODE);

$stmt->close();
$conex->close();
?>