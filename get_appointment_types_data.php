<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado']));
}

$response = ['labels' => [], 'datasets' => []];
$meses_espanol = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

$primeras_consultas = [];
$seguimientos = [];

for ($i = 5; $i >= 0; $i--) {
    $fecha = new DateTime("first day of -$i month");
    $mes_clave = $fecha->format('Y-m');
    $response['labels'][] = $meses_espanol[(int)$fecha->format('n') - 1];
    $primeras_consultas[$mes_clave] = 0;
    $seguimientos[$mes_clave] = 0;
}

$sql = "SELECT DATE_FORMAT(fecha_cita, '%Y-%m') as mes, tipo_cita, COUNT(id) as total 
        FROM citas 
        WHERE estado IN ('confirmada', 'completada', 'reprogramada') AND fecha_cita >= ?
        GROUP BY mes, tipo_cita";
$seis_meses_atras = date('Y-m-01', strtotime('-5 months'));
$stmt = $conex->prepare($sql);
$stmt->bind_param("s", $seis_meses_atras);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    if ($fila['tipo_cita'] == 'primera_consulta' && isset($primeras_consultas[$fila['mes']])) {
        $primeras_consultas[$fila['mes']] = (int)$fila['total'];
    } elseif ($fila['tipo_cita'] == 'seguimiento' && isset($seguimientos[$fila['mes']])) {
        $seguimientos[$fila['mes']] = (int)$fila['total'];
    }
}
$stmt->close();

$response['datasets'][] = ['label' => 'Primeras Consultas', 'data' => array_values($primeras_consultas), 'backgroundColor' => '#02b1f4'];
$response['datasets'][] = ['label' => 'Seguimiento', 'data' => array_values($seguimientos), 'backgroundColor' => '#6f42c1'];

echo json_encode($response);
$conex->close();
?>