<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo psicólogos y roles autorizados
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['paciente_id']) || !is_numeric($_GET['paciente_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de paciente no válido']);
    exit();
}

$paciente_id = (int)$_GET['paciente_id'];
$response = [];

// Obtener nombre del paciente
$stmt_paciente = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$paciente = $stmt_paciente->get_result()->fetch_assoc();
$response['paciente_nombre'] = $paciente['nombre_completo'] ?? 'Paciente no encontrado';

// Obtener informes existentes (ahora incluyendo el ID)
$stmt_informes = $conex->prepare("SELECT id, fecha_evaluacion, motivo_referencia FROM informes_psicologicos WHERE paciente_id = ? ORDER BY fecha_evaluacion DESC");
$stmt_informes->bind_param("i", $paciente_id);
$stmt_informes->execute();
$informes_result = $stmt_informes->get_result();

$informes = [];
while($informe = $informes_result->fetch_assoc()) {
    $informes[] = [
        'id' => $informe['id'], // <-- ID AÑADIDO
        'fecha_formateada' => date('d/m/Y', strtotime($informe['fecha_evaluacion'])),
        'motivo' => htmlspecialchars($informe['motivo_referencia'])
    ];
}
$response['informes'] = $informes;

echo json_encode($response);

$stmt_paciente->close();
$stmt_informes->close();
$conex->close();
?>