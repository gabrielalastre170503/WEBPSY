<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo para psicólogos
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$psicologo_id = $_SESSION['usuario_id'];

// Inicializamos un array para los 7 días de la semana (Domingo=0, Lunes=1, etc.)
$dias_semana = [0, 0, 0, 0, 0, 0, 0];

// DAYOFWEEK() en MySQL devuelve 1 para Domingo, 2 para Lunes, etc.
$sql = "SELECT DAYOFWEEK(fecha_cita) as dia_semana, COUNT(id) as total 
        FROM citas 
        WHERE psicologo_id = ? AND estado = 'confirmada'
        GROUP BY dia_semana";

$stmt = $conex->prepare($sql);
$stmt->bind_param("i", $psicologo_id);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    // Restamos 1 para que coincida con el índice del array (Domingo=1 -> índice 0)
    $indice_dia = $fila['dia_semana'] - 1;
    $dias_semana[$indice_dia] = $fila['total'];
}

// Reordenamos para que la semana empiece en Lunes
$datos_ordenados = [
    $dias_semana[1], // Lunes
    $dias_semana[2], // Martes
    $dias_semana[3], // Miércoles
    $dias_semana[4], // Jueves
    $dias_semana[5], // Viernes
    $dias_semana[6], // Sábado
    $dias_semana[0]  // Domingo
];

echo json_encode([
    'labels' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'],
    'data' => $datos_ordenados
]);

$stmt->close();
$conex->close();
?>