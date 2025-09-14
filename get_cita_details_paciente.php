<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo pacientes logueados pueden ver sus citas
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cita no válido']);
    exit();
}

$cita_id = $_GET['id'];
$paciente_id = $_SESSION['usuario_id'];

// Obtener todos los detalles de la cita y del profesional
$sql = "SELECT c.*, p.nombre_completo as profesional_nombre, p.rol as profesional_rol
        FROM citas c
        LEFT JOIN usuarios p ON c.psicologo_id = p.id
        WHERE c.id = ? AND c.paciente_id = ?";
$stmt = $conex->prepare($sql);
$stmt->bind_param("ii", $cita_id, $paciente_id);
$stmt->execute();
$resultado = $stmt->get_result();
$cita = $resultado->fetch_assoc();

if (!$cita) {
    http_response_code(404);
    echo json_encode(['error' => 'Cita no encontrada']);
    exit();
}

// Formatear los datos para que se vean bien
$cita['fecha_cita_formateada'] = $cita['fecha_cita'] ? date('d/m/Y h:i A', strtotime($cita['fecha_cita'])) : 'Por confirmar';
$cita['fecha_propuesta_formateada'] = $cita['fecha_propuesta'] ? date('d/m/Y h:i A', strtotime($cita['fecha_propuesta'])) : '';
$cita['tipo_cita'] = ucwords(str_replace('_', ' ', $cita['tipo_cita']));
$cita['modalidad'] = ucfirst($cita['modalidad']);
$cita['profesional_rol'] = ucfirst($cita['profesional_rol']);

echo json_encode($cita);

$stmt->close();
$conex->close();
?>