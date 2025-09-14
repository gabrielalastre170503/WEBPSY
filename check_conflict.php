<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['conflict' => false, 'paciente_nombre' => '']; // Respuesta por defecto

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}
if (!isset($_GET['cita_id']) || !is_numeric($_GET['cita_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID no válido']);
    exit();
}

$cita_id_a_confirmar = $_GET['cita_id'];
$psicologo_id = $_SESSION['usuario_id'];

// 1. Obtener la fecha y el nombre del paciente de la cita que se quiere confirmar
$stmt_info = $conex->prepare("
    SELECT c.fecha_cita, u.nombre_completo 
    FROM citas c 
    JOIN usuarios u ON c.paciente_id = u.id
    WHERE c.id = ? AND c.psicologo_id = ?
");
$stmt_info->bind_param("ii", $cita_id_a_confirmar, $psicologo_id);
$stmt_info->execute();
$resultado_info = $stmt_info->get_result();

if ($resultado_info->num_rows > 0) {
    $cita_info = $resultado_info->fetch_assoc();
    $fecha_a_verificar = $cita_info['fecha_cita'];
    $response['paciente_nombre'] = $cita_info['nombre_completo']; // Guardamos el nombre

    // 2. Comprobar si ya existe otra cita confirmada en ese mismo horario
    $stmt_check = $conex->prepare("SELECT id FROM citas WHERE psicologo_id = ? AND fecha_cita = ? AND estado IN ('confirmada', 'reprogramada') AND id != ?");
    $stmt_check->bind_param("isi", $psicologo_id, $fecha_a_verificar, $cita_id_a_confirmar);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $response['conflict'] = true; // ¡Conflicto encontrado!
    }
    $stmt_check->close();
}
$stmt_info->close();
$conex->close();
echo json_encode($response);
?>