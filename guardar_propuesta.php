<?php
session_start();
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/citas.php';

api_json();
$response = ['success' => false, 'message' => 'Datos inválidos.'];

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista'], true)) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cita_id'], $_POST['fecha_propuesta'], $_POST['motivo_reprogramacion'])) {
    echo json_encode($response);
    $conex->close();
    exit();
}

$cita_id         = (int)$_POST['cita_id'];
$fecha_propuesta = trim((string)$_POST['fecha_propuesta']);
$motivo          = trim((string)$_POST['motivo_reprogramacion']);
$ecografista_id  = (int)$_SESSION['usuario_id'];

if ($cita_id <= 0 || $fecha_propuesta === '' || $motivo === '') {
    echo json_encode($response);
    $conex->close();
    exit();
}

$fecha_formateada = date('d/m/Y \a \l\a\s h:i A', strtotime($fecha_propuesta));
$notificacion = "El profesional ha propuesto una nueva fecha para tu cita: <strong>{$fecha_formateada}</strong>. Motivo: <em>\"" . htmlspecialchars($motivo) . "\"</em>. Por favor, revisa y confirma en tu panel.";

$stmt = $conex->prepare(
    "UPDATE citas SET fecha_propuesta = ?, reprogramacion_motivo = ?, notificacion_paciente = ?, estado = 'pendiente_paciente', fecha_respuesta = NOW()
     WHERE id = ? AND ecografista_id = ?"
);
$stmt->bind_param('sssii', $fecha_propuesta, $motivo, $notificacion, $cita_id, $ecografista_id);

if ($stmt->execute()) {
    eco_cita_evento($conex, $cita_id, 'propuesta', ['estado_nuevo' => 'pendiente_paciente', 'detalle' => ['fecha_propuesta' => $fecha_propuesta, 'motivo' => $motivo]]);
    $response['success'] = true;
    $response['message'] = 'Propuesta enviada al paciente.';
} else {
    $response['message'] = 'Error al guardar la propuesta en la base de datos.';
}
$stmt->close();

$conex->close();
echo json_encode($response);
