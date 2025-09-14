<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Datos inválidos.'];

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paciente_id'], $_POST['fecha_cita'], $_POST['motivo_consulta'])) {
    $paciente_id = $_POST['paciente_id'];
    $fecha_cita = $_POST['fecha_cita'];
    $motivo = $_POST['motivo_consulta'];
    $psicologo_id = $_SESSION['usuario_id'];

    $fecha_formateada = date('d/m/Y \a \l\a\s h:i A', strtotime($fecha_cita));
    $notificacion = "Tu psicólogo te ha programado una nueva cita para el <strong>{$fecha_formateada}</strong>. Motivo: <em>\"" . htmlspecialchars($motivo) . "\"</em>.";

    $stmt = $conex->prepare("INSERT INTO citas (paciente_id, psicologo_id, fecha_cita, motivo_consulta, estado, notificacion_paciente) VALUES (?, ?, ?, ?, 'confirmada', ?)");
    $stmt->bind_param("iisss", $paciente_id, $psicologo_id, $fecha_cita, $motivo, $notificacion);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Cita creada y notificada al paciente.';
    } else {
        $response['message'] = 'Error al guardar la cita en la base de datos.';
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
?>