<?php
session_start();
include 'conexion.php';

// --- ESTA ES LA LÍNEA CLAVE QUE LO ARREGLA ---
// Forzamos la zona horaria a la de Venezuela
date_default_timezone_set('America/Caracas');

header('Content-Type: application/json');

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado']));
}

$response = ['labels' => [], 'data' => []];
$dias_map = [];

// 1. Inicializar los últimos 7 días con 0 citas
for ($i = 6; $i >= 0; $i--) {
    $fecha = new DateTime("-$i days");
    $fecha_clave = $fecha->format('Y-m-d');
    $response['labels'][] = $fecha->format('d/m'); // Formato "dd/mm"
    $dias_map[$fecha_clave] = 0;
}

// 2. Consulta para contar las citas en los últimos 7 días
$siete_dias_atras = date('Y-m-d', strtotime('-6 days'));
$hoy = date('Y-m-d'); // Usamos la fecha de hoy como límite superior

$sql = "SELECT DATE(fecha_cita) as dia, COUNT(id) as total 
        FROM citas 
        WHERE estado IN ('confirmada', 'completada', 'reprogramada') 
        AND DATE(fecha_cita) BETWEEN ? AND ?
        GROUP BY dia";

$stmt = $conex->prepare($sql);
$stmt->bind_param("ss", $siete_dias_atras, $hoy);
$stmt->execute();
$resultado = $stmt->get_result();

// 3. Rellenar el mapa de datos con los resultados
if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        if (isset($dias_map[$fila['dia']])) {
            $dias_map[$fila['dia']] = (int)$fila['total'];
        }
    }
    $stmt->close();
}

$response['data'] = array_values($dias_map);

echo json_encode($response);
$conex->close();
?>