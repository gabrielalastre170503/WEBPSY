<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador', 'secretaria']) || !isset($_GET['paciente_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['rol'];
$paciente_id = (int)$_GET['paciente_id'];

$response = [];

// Obtener nombre del paciente
$stmt_paciente = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$paciente = $stmt_paciente->get_result()->fetch_assoc();
$response['paciente_nombre'] = $paciente['nombre_completo'] ?? 'Paciente no encontrado';

// Obtener notas existentes
if ($rol_usuario === 'secretaria' || $rol_usuario === 'administrador') {
    $stmt_notas = $conex->prepare("SELECT id, fecha_sesion, nota FROM notas_sesion WHERE paciente_id = ? ORDER BY fecha_sesion DESC");
    $stmt_notas->bind_param("i", $paciente_id);
} else {
    $stmt_notas = $conex->prepare("SELECT id, fecha_sesion, nota FROM notas_sesion WHERE paciente_id = ? AND psicologo_id = ? ORDER BY fecha_sesion DESC");
    $stmt_notas->bind_param("ii", $paciente_id, $usuario_id);
}
$stmt_notas->execute();
$notas_result = $stmt_notas->get_result();

$notas = [];
while($nota = $notas_result->fetch_assoc()) {
    $notas[] = [
        'id' => $nota['id'],
        'fecha_formateada' => date('d/m/Y h:i A', strtotime($nota['fecha_sesion'])),
        'nota' => htmlspecialchars($nota['nota'])
    ];
}
$response['notas'] = $notas;

echo json_encode($response);

$stmt_paciente->close();
$stmt_notas->close();
$conex->close();
?>