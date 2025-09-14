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

if (!isset($_GET['paciente_id']) || !is_numeric($_GET['paciente_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de paciente no válido']);
    exit();
}

$paciente_id = (int)$_GET['paciente_id'];
$response = ['tipo' => null, 'datos' => null];

// Primero, buscamos en la tabla de adultos
$stmt_adulto = $conex->prepare("SELECT * FROM historias_adultos WHERE paciente_id = ? LIMIT 1");
$stmt_adulto->bind_param("i", $paciente_id);
$stmt_adulto->execute();
$resultado_adulto = $stmt_adulto->get_result();
if ($resultado_adulto->num_rows > 0) {
    $response['tipo'] = 'adulto';
    $response['datos'] = $resultado_adulto->fetch_assoc();
}
$stmt_adulto->close();

// Si no encontramos nada, entonces buscamos en la tabla infantil
if ($response['tipo'] === null) {
    $stmt_infantil = $conex->prepare("SELECT * FROM historias_infantiles WHERE paciente_id = ? LIMIT 1");
    $stmt_infantil->bind_param("i", $paciente_id);
    $stmt_infantil->execute();
    $resultado_infantil = $stmt_infantil->get_result();
    if ($resultado_infantil->num_rows > 0) {
        $response['tipo'] = 'infantil';
        $response['datos'] = $resultado_infantil->fetch_assoc();
    }
    $stmt_infantil->close();
}

echo json_encode($response);
$conex->close();
?>