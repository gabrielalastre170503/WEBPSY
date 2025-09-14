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

$labels = [];
$data = [];

// Consulta para contar citas (confirmadas o completadas) por cada profesional
$sql = "SELECT u.nombre_completo, COUNT(c.id) as total_citas
        FROM usuarios u
        JOIN citas c ON u.id = c.psicologo_id
        WHERE u.rol IN ('psicologo', 'psiquiatra') AND c.estado IN ('confirmada', 'completada')
        GROUP BY u.id, u.nombre_completo
        ORDER BY total_citas DESC";

$resultado = $conex->query($sql);

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        // Extraemos solo el primer nombre para que no sea tan largo en el gráfico
        $nombre_corto = explode(' ', $fila['nombre_completo'])[0];
        $labels[] = $nombre_corto;
        $data[] = (int)$fila['total_citas'];
    }
}

echo json_encode([
    'labels' => $labels,
    'data' => $data
]);

$conex->close();
?>