<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo psicólogos y psiquiatras pueden acceder
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['cita_id']) || !is_numeric($_GET['cita_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cita no válido']);
    exit();
}

$cita_id = $_GET['cita_id'];
$psicologo_id = $_SESSION['usuario_id'];

$response = [];

// Consulta para obtener los detalles de la cita
$stmt = $conex->prepare("
    SELECT 
        c.id, c.fecha_cita, c.estado, c.motivo_consulta,
        u.nombre_completo as paciente_nombre, u.cedula as paciente_cedula
    FROM citas c
    JOIN usuarios u ON c.paciente_id = u.id
    WHERE c.id = ? AND c.psicologo_id = ?
");
$stmt->bind_param("ii", $cita_id, $psicologo_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $response['success'] = true;
    $response['data'] = $resultado->fetch_assoc();
} else {
    $response['success'] = false;
    $response['message'] = 'Cita no encontrada o no pertenece a este profesional.';
}

echo json_encode($response);

$stmt->close();
$conex->close();
?>