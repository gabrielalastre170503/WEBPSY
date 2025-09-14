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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cita_id'], $_POST['nueva_fecha_cita'], $_POST['motivo_reprogramacion'])) {
    $cita_id = $_POST['cita_id'];
    $nueva_fecha = $_POST['nueva_fecha_cita'];
    $motivo = $_POST['motivo_reprogramacion'];
    $psicologo_id = $_SESSION['usuario_id'];

    $fecha_formateada = date('d/m/Y \a \l\a\s h:i A', strtotime($nueva_fecha));
    $notificacion = "Su cita ha sido reprogramada para el <strong>{$fecha_formateada}</strong>. Motivo: <em>\"" . htmlspecialchars($motivo) . "\"</em>. Si no está de acuerdo, por favor contáctenos a través de la sección de Ayuda.";

    // --- LÓGICA CORREGIDA ---
    // La nueva fecha se guarda en 'fecha_propuesta' y el estado cambia a 'pendiente_paciente'
    $stmt = $conex->prepare("UPDATE citas SET fecha_propuesta = ?, reprogramacion_motivo = ?, notificacion_paciente = ?, estado = 'pendiente_paciente' WHERE id = ? AND psicologo_id = ?");
    $stmt->bind_param("sssii", $nueva_fecha, $motivo, $notificacion, $cita_id, $psicologo_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Cita reprogramada y paciente notificado.';
    } else {
        $response['message'] = 'Error al actualizar la base de datos.';
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
?>