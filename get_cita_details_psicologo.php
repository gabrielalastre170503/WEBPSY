<?php
session_start();
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';

api_json();

// Seguridad: Solo psicólogos y psiquiatras pueden acceder
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista'])) {
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
$ecografista_id = $_SESSION['usuario_id'];

$response = [];

// Consulta para obtener los detalles de la cita
$stmt = $conex->prepare("
    SELECT
        c.id, c.fecha_cita, c.estado, c.motivo_consulta,
        u.id AS paciente_id, u.nombre_completo AS paciente_nombre, u.cedula AS paciente_cedula,
        t.nombre AS tipo_nombre, t.icono AS tipo_icono
    FROM citas c
    JOIN usuarios u ON c.paciente_id = u.id
    LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
    WHERE c.id = ? AND c.ecografista_id = ?
");
$stmt->bind_param("ii", $cita_id, $ecografista_id);
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