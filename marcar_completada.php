<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false];

// Seguridad: Solo psicólogos y psiquiatras pueden marcar citas como completadas
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

if (isset($_GET['cita_id'])) {
    $cita_id = $_GET['cita_id'];
    $psicologo_id = $_SESSION['usuario_id'];

    // Preparamos la consulta para actualizar el estado de la cita a 'completada'
    // Nos aseguramos de que el psicólogo solo pueda modificar sus propias citas
    $stmt = $conex->prepare("UPDATE citas SET estado = 'completada' WHERE id = ? AND psicologo_id = ?");
    $stmt->bind_param("ii", $cita_id, $psicologo_id);

    if ($stmt->execute()) {
        // Verificamos si alguna fila fue realmente afectada
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
        } else {
            $response['message'] = 'No se encontró la cita o no tienes permiso para modificarla.';
        }
    } else {
        $response['message'] = 'Error al ejecutar la consulta.';
    }

    $stmt->close();
} else {
    $response['message'] = 'No se proporcionó el ID de la cita.';
}

$conex->close();
echo json_encode($response);
?>