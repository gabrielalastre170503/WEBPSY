<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado']));
}

$response = ['labels' => [], 'data' => []];
$meses_espanol = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$data_map = [];

// 1. Generar las etiquetas y el mapa de datos para los últimos 6 meses, inicializados en 0
for ($i = 5; $i >= 0; $i--) {
    $fecha = new DateTime("first day of -$i month");
    $mes_clave = $fecha->format('Y-m');
    $response['labels'][] = $meses_espanol[(int)$fecha->format('n') - 1];
    $data_map[$mes_clave] = 0; // Inicializar con 0
}

// --- CONSULTA CORREGIDA ---
// Ahora usa AVG() para calcular el promedio de horas
$sql = "SELECT 
            DATE_FORMAT(fecha_solicitud, '%Y-%m') as mes, 
            AVG(TIMESTAMPDIFF(HOUR, fecha_solicitud, fecha_respuesta)) as tiempo_promedio_horas 
        FROM citas 
        WHERE fecha_respuesta IS NOT NULL AND fecha_solicitud >= ?
        GROUP BY mes";

$seis_meses_atras = date('Y-m-01', strtotime('-5 months'));
$stmt = $conex->prepare($sql);
$stmt->bind_param("s", $seis_meses_atras);
$stmt->execute();
$resultado = $stmt->get_result();

// 3. Rellenar el mapa de datos con los resultados de la consulta
if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        if (isset($data_map[$fila['mes']])) {
            // Redondeamos el promedio a 2 decimales
            $data_map[$fila['mes']] = round((float)($fila['tiempo_promedio_horas'] ?? 0), 2);
        }
    }
    $stmt->close();
}

// 4. Preparamos la respuesta final
$response['data'] = array_values($data_map);

echo json_encode($response);
$conex->close();
?>