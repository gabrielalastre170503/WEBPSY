<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo los administradores pueden ver estos datos
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

// --- LÓGICA CORREGIDA Y ROBUSTA PARA OBTENER LOS ÚLTIMOS 6 MESES ---

// Array con los nombres de los meses en español
$meses_espanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$labels = [];
$data_map = []; // Usaremos un mapa para asociar 'YYYY-MM' con su conteo

// Generar las etiquetas y el mapa de datos para los últimos 6 meses
for ($i = 5; $i >= 0; $i--) {
    $fecha = new DateTime("first day of -$i month");
    $mes_clave = $fecha->format('Y-m'); // Formato '2025-07'
    $mes_numero = (int)$fecha->format('n');
    
    $labels[] = $meses_espanol[$mes_numero]; // Añade el nombre del mes en español
    $data_map[$mes_clave] = 0; // Inicializa el conteo para este mes en 0
}

// --- FIN DE LA LÓGICA CORREGIDA ---

// Consultar los usuarios registrados en los últimos 6 meses
$seis_meses_atras = date('Y-m-01', strtotime('-5 months'));
$sql = "SELECT DATE_FORMAT(fecha_registro, '%Y-%m') as mes, COUNT(id) as total 
        FROM usuarios 
        WHERE fecha_registro >= ?
        GROUP BY mes";

$stmt = $conex->prepare($sql);
$stmt->bind_param("s", $seis_meses_atras);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    if (isset($data_map[$fila['mes']])) {
        $data_map[$fila['mes']] = (int)$fila['total'];
    }
}

// Devolver los datos en formato JSON
echo json_encode([
    'labels' => $labels,
    'data' => array_values($data_map) // Convertimos el mapa a un array simple
]);

$stmt->close();
$conex->close();
?>