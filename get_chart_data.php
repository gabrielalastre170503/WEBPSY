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

// Preparar los últimos 6 meses para las etiquetas del gráfico
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish');
$labels = [];
$data = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i month"));
    $labels[] = ucfirst(strftime('%B %Y', strtotime($mes . '-01')));
    $data[$mes] = 0; // Inicializar en 0
}

// Consultar las citas confirmadas de los últimos 6 meses
$seis_meses_atras = date('Y-m-01 00:00:00', strtotime('-5 months'));
$sql = "SELECT DATE_FORMAT(fecha_cita, '%Y-%m') as mes, COUNT(id) as total 
        FROM citas 
        WHERE psicologo_id = ? 
        AND estado = 'confirmada' 
        AND fecha_cita >= ?
        GROUP BY mes
        ORDER BY mes ASC";

$stmt = $conex->prepare($sql);
$stmt->bind_param("is", $psicologo_id, $seis_meses_atras);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $data[$fila['mes']] = $fila['total'];
}

// Devolver los datos en formato JSON
echo json_encode([
    'labels' => $labels,
    'data' => array_values($data) // Asegurarse de que los datos sean un array simple
]);

$stmt->close();
$conex->close();
?>