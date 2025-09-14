<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Datos inválidos.'];

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cita_id'], $_POST['fecha_propuesta'], $_POST['motivo_reprogramacion'])) {
    $cita_id = $_POST['cita_id'];
    $fecha_propuesta = $_POST['fecha_propuesta'];
    $motivo = $_POST['motivo_reprogramacion'];
    $psicologo_id = $_SESSION['usuario_id'];

    $fecha_formateada = date('d/m/Y \a \l\a\s h:i A', strtotime($fecha_propuesta));
    $notificacion = "El profesional ha propuesto una nueva fecha para tu cita: <strong>{$fecha_formateada}</strong>. Motivo: <em>\"" . htmlspecialchars($motivo) . "\"</em>. Por favor, revisa y confirma en tu panel.";

    $stmt = $conex->prepare("UPDATE citas SET fecha_propuesta = ?, reprogramacion_motivo = ?, notificacion_paciente = ?, estado = 'pendiente_paciente' WHERE id = ? AND psicologo_id = ?");
    $stmt->bind_param("sssii", $fecha_propuesta, $motivo, $notificacion, $cita_id, $psicologo_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Propuesta enviada al paciente.';
    } else {
        $response['message'] = 'Error al guardar la propuesta en la base de datos.';
    }
    $stmt->close();
}

// --- LÓGICA AÑADIDA ---
    // Se establece la fecha de respuesta al momento de proponer una nueva fecha
    $stmt = $conex->prepare("UPDATE citas SET fecha_propuesta = ?, reprogramacion_motivo = ?, notificacion_paciente = ?, estado = 'pendiente_paciente', fecha_respuesta = NOW() WHERE id = ? AND psicologo_id = ?");
    $stmt->bind_param("sssii", $fecha_propuesta, $motivo, $notificacion, $cita_id, $psicologo_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Propuesta enviada al paciente.';
    } else {
        $response['message'] = 'Error al guardar la propuesta.';
    }

$conex->close();
echo json_encode($response);
?>