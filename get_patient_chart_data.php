<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo pacientes pueden ver sus datos
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    http_response_code(403);
    exit();
}

$paciente_id = $_SESSION['usuario_id'];

// Array con los nombres de los meses en español
$meses_espanol = [
    1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
];

$labels = [];
$data_map = [];

// --- LÍNEA MODIFICADA ---
// Generar las etiquetas y el mapa de datos para los últimos 8 meses
for ($i = 7; $i >= 0; $i--) { // Cambiado de 5 a 7
    $fecha = new DateTime("first day of -$i month");
    $mes_clave = $fecha->format('Y-m');
    $mes_numero = (int)$fecha->format('n');
    
    $labels[] = $meses_espanol[$mes_numero];
    $data_map[$mes_clave] = 0;
}

// --- LÍNEA MODIFICADA ---
// Consultar las citas confirmadas del paciente en los últimos 8 meses
$ocho_meses_atras = date('Y-m-01', strtotime('-7 months')); // Cambiado de -5 a -7
$sql = "SELECT DATE_FORMAT(fecha_cita, '%Y-%m') as mes, COUNT(id) as total 
        FROM citas 
        WHERE paciente_id = ? 
        AND estado = 'confirmada' 
        AND fecha_cita >= ?
        GROUP BY mes";

$stmt = $conex->prepare($sql);
$stmt->bind_param("is", $paciente_id, $ocho_meses_atras);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    if (isset($data_map[$fila['mes']])) {
        $data_map[$fila['mes']] = (int)$fila['total'];
    }
}

echo json_encode([
    'labels' => $labels,
    'data' => array_values($data_map)
]);

$stmt->close();
$conex->close();
?>