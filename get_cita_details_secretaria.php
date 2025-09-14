<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['secretaria', 'administrador'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['cita_id']) || !is_numeric($_GET['cita_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cita no válido']);
    exit();
}

$cita_id = (int)$_GET['cita_id'];

// Consulta actualizada para obtener también el profesional solicitado
$stmt = $conex->prepare("
    SELECT 
        c.id, 
        c.motivo_consulta, 
        u.nombre_completo as paciente_nombre,
        c.psicologo_id as profesional_solicitado_id, -- ID del profesional
        p.nombre_completo as profesional_solicitado_nombre -- Nombre del profesional
    FROM citas c 
    JOIN usuarios u ON c.paciente_id = u.id
    LEFT JOIN usuarios p ON c.psicologo_id = p.id
    WHERE c.id = ?
");
$stmt->bind_param("i", $cita_id);
$stmt->execute();
$resultado = $stmt->get_result();
$cita = $resultado->fetch_assoc();

if (!$cita) {
    http_response_code(404);
    echo json_encode(['error' => 'Cita no encontrada']);
    exit();
}

echo json_encode($cita);

$stmt->close();
$conex->close();
?>