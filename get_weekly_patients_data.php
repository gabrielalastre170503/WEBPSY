<?php
session_start();
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';

api_json();

// Seguridad: Solo para psicólogos
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$ecografista_id = $_SESSION['usuario_id'];

// --- CÓDIGO CORREGIDO PARA MOSTRAR DÍAS EN ESPAÑOL ---

// 1. Array con los nombres de los días en español
$dias_espanol = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

// 2. Inicializar arrays para los últimos 7 días
$labels = [];
$data = [];
for ($i = 6; $i >= 0; $i--) {
    $timestamp = strtotime("-$i days");
    $date = date('Y-m-d', $timestamp);
    
    // Obtenemos el número del día de la semana (0 para Domingo, 1 para Lunes...)
    $dia_semana_num = date('w', $timestamp);
    
    // Creamos la etiqueta en español, ej: "Lun 21"
    $labels[] = $dias_espanol[$dia_semana_num] . ' ' . date('d', $timestamp);
    
    $data[$date] = 0; // Inicializar en 0
}
// --- FIN DE LA CORRECCIÓN ---


// Consultar pacientes creados por el psicólogo en los últimos 7 días
$una_semana_atras = date('Y-m-d 00:00:00', strtotime('-6 days'));
$sql = "SELECT DATE(fecha_registro) as dia, COUNT(id) as total 
        FROM usuarios 
        WHERE creado_por_id = ? 
        AND fecha_registro >= ?
        GROUP BY dia";

$stmt = $conex->prepare($sql);
$stmt->bind_param("is", $ecografista_id, $una_semana_atras);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    if (isset($data[$fila['dia']])) {
        $data[$fila['dia']] = $fila['total'];
    }
}

// Devolver los datos en formato JSON
echo json_encode([
    'labels' => $labels,
    'data' => array_values($data)
]);

$stmt->close();
$conex->close();
?>