<?php
session_start();
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';

api_json();

// Seguridad: Solo los administradores pueden ver estos datos
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

// --- NUEVOS RANGOS DE EDAD ---
$rangos = [
    "Menores de 18" => 0,
    "18-29 años" => 0,
    "30-50 años" => 0,
    "Mayores de 50" => 0
];

// Consulta para obtener las edades de todos los pacientes activos
$sql = "SELECT TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado' AND fecha_nacimiento IS NOT NULL";
$resultado = $conex->query($sql);

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $edad = (int)$fila['edad'];
        // --- LÓGICA CORREGIDA PARA LOS NUEVOS RANGOS ---
        if ($edad < 18) {
            $rangos["Menores de 18"]++;
        } elseif ($edad <= 29) {
            $rangos["18-29 años"]++;
        } elseif ($edad <= 50) {
            $rangos["30-50 años"]++;
        } else {
            $rangos["Mayores de 50"]++;
        }
    }
}

// Preparamos los datos para que Chart.js los pueda entender
$labels = array_keys($rangos);
$data = array_values($rangos);

echo json_encode([
    'labels' => $labels,
    'data' => $data
]);

$conex->close();
?>