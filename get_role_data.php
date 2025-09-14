<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo los administradores pueden ver estos datos
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

// Consulta para contar usuarios por rol
$sql = "SELECT rol, COUNT(id) as total FROM usuarios GROUP BY rol";
$resultado = $conex->query($sql);

$labels = [];
$data = [];
$colors = [];

// Paleta de colores para los roles
$colorMap = [
    'paciente'      => 'rgba(2, 177, 244, 0.8)',   // Azul
    'psicologo'     => 'rgba(40, 167, 69, 0.8)',   // Verde
    'psiquiatra'    => 'rgba(23, 162, 184, 0.8)',  // Turquesa
    'secretaria'    => 'rgba(255, 193, 7, 0.8)',   // Amarillo
    'administrador' => 'rgba(108, 117, 125, 0.8)' // Gris
];

while ($fila = $resultado->fetch_assoc()) {
    $labels[] = ucfirst($fila['rol']); // Pone la primera letra en mayúscula
    $data[] = $fila['total'];
    $colors[] = $colorMap[$fila['rol']] ?? '#cccccc'; // Asigna color o gris por defecto
}

echo json_encode([
    'labels' => $labels,
    'data' => $data,
    'colors' => $colors
]);

$conex->close();
?>