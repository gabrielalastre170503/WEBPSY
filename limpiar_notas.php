<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Ocurrió un error.'];

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paciente_id'])) {
    $paciente_id = $_POST['paciente_id'];
    $psicologo_id = $_SESSION['usuario_id'];

    // Preparamos la consulta para borrar las notas de este paciente Y este psicólogo
    $stmt = $conex->prepare("DELETE FROM notas_sesion WHERE paciente_id = ? AND psicologo_id = ?");
    $stmt->bind_param("ii", $paciente_id, $psicologo_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Todas las notas del paciente han sido eliminadas.';
    } else {
        $response['message'] = 'Error al eliminar las notas de la base de datos.';
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
?>