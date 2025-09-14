<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo psicólogos y roles autorizados
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
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
$psicologo_id = $_SESSION['usuario_id'];

// --- CONSULTA ACTUALIZADA ---
// Ahora también seleccionamos la cédula y la edad del paciente
$stmt = $conex->prepare("
    SELECT c.*, u.nombre_completo as paciente_nombre, u.cedula as paciente_cedula, u.edad as paciente_edad 
    FROM citas c 
    JOIN usuarios u ON c.paciente_id = u.id
    WHERE c.id = ? AND c.psicologo_id = ?
");
$stmt->bind_param("ii", $cita_id, $psicologo_id);
$stmt->execute();
$resultado = $stmt->get_result();
$cita = $resultado->fetch_assoc();

if (!$cita) {
    http_response_code(404);
    echo json_encode(['error' => 'Solicitud no encontrada o no asignada a usted.']);
    exit();
}

// Formatear los datos para que se vean bien
$cita['fecha_solicitada_formateada'] = date('d/m/Y h:i A', strtotime($cita['fecha_cita']));
$cita['tipo_cita_formateado'] = ucwords(str_replace('_', ' ', $cita['tipo_cita']));
$cita['modalidad_formateada'] = ucfirst($cita['modalidad']);

echo json_encode($cita);

$stmt->close();
$conex->close();
?>