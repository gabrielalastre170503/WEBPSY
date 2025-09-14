<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado']));
}

$sql = "SELECT estado, COUNT(id) as total 
        FROM citas 
        WHERE estado IN ('confirmada', 'reprogramada')
        GROUP BY estado";
$resultado = $conex->query($sql);
$data = ['Confirmadas' => 0, 'Reprogramadas' => 0];

if ($resultado) {
    while($fila = $resultado->fetch_assoc()){
        if($fila['estado'] == 'confirmada') $data['Confirmadas'] = (int)$fila['total'];
        if($fila['estado'] == 'reprogramada') $data['Reprogramadas'] = (int)$fila['total'];
    }
}

echo json_encode([
    'labels' => array_keys($data),
    'data' => array_values($data)
]);
$conex->close();
?>