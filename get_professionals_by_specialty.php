<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente' || !isset($_GET['rol'])) {
    http_response_code(403);
    exit();
}

$rol = $_GET['rol'];

// Consulta que selecciona profesionales (psicólogos o psiquiatras)
// que tienen al menos un día de trabajo definido en la tabla horarios_recurrentes.
$sql = "SELECT DISTINCT u.id, u.nombre_completo 
        FROM usuarios u
        JOIN horarios_recurrentes hr ON u.id = hr.psicologo_id
        WHERE u.rol = ? AND u.estado = 'aprobado'";

$stmt = $conex->prepare($sql);
$stmt->bind_param("s", $rol);
$stmt->execute();
$resultado = $stmt->get_result();

$profesionales = [];
while ($fila = $resultado->fetch_assoc()) {
    $profesionales[] = $fila;
}

echo json_encode($profesionales);

$stmt->close();
$conex->close();
?>