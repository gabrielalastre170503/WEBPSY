<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json'); // ¡Muy importante!
$response = ['success' => false, 'message' => 'Ocurrió un error inesperado.'];

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paciente_id'], $_POST['fecha_sesion'], $_POST['nota'])) {
    $paciente_id = $_POST['paciente_id'];
    $fecha_sesion = $_POST['fecha_sesion'];
    $nota = $_POST['nota'];
    $psicologo_id = $_SESSION['usuario_id'];

    if (empty($nota)) {
        $response['message'] = 'El campo de la nota no puede estar vacío.';
        echo json_encode($response);
        exit();
    }

    $stmt = $conex->prepare("INSERT INTO notas_sesion (paciente_id, psicologo_id, fecha_sesion, nota) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $paciente_id, $psicologo_id, $fecha_sesion, $nota);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = '¡Nota guardada con éxito!';
    } else {
        $response['message'] = 'Error al guardar la nota en la base de datos.';
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
?>