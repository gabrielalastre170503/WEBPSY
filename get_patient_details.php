<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo roles autorizados
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de paciente no válido']);
    exit();
}

$paciente_id = $_GET['id'];
$response = [];

// 1. Obtener datos básicos del paciente (incluyendo la edad)
$stmt = $conex->prepare("SELECT nombre_completo, cedula, correo, edad FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$paciente_data = $stmt->get_result()->fetch_assoc();


if (!$paciente_data) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit();
}
$response['paciente'] = $paciente_data;

// 2. Verificar si tiene historia clínica
$stmt_historia = $conex->prepare("SELECT 1 FROM historias_adultos WHERE paciente_id = ? UNION SELECT 1 FROM historias_infantiles WHERE paciente_id = ?");
$stmt_historia->bind_param("ii", $paciente_id, $paciente_id);
$stmt_historia->execute();
$stmt_historia->store_result();
$response['tiene_historia'] = $stmt_historia->num_rows > 0;
$stmt_historia->close();

// 3. Contar el número de informes
$stmt_informes = $conex->prepare("SELECT COUNT(id) as total FROM informes_psicologicos WHERE paciente_id = ?");
$stmt_informes->bind_param("i", $paciente_id);
$stmt_informes->execute();
$response['total_informes'] = $stmt_informes->get_result()->fetch_assoc()['total'];
$stmt_informes->close();

// 4. --- NUEVA LÓGICA AÑADIDA ---
// Contar el número de notas de sesión
$stmt_notas = $conex->prepare("SELECT COUNT(id) as total FROM notas_sesion WHERE paciente_id = ?");
$stmt_notas->bind_param("i", $paciente_id);
$stmt_notas->execute();
$response['total_notas'] = $stmt_notas->get_result()->fetch_assoc()['total'];
$stmt_notas->close();

echo json_encode($response);
$conex->close();
?>