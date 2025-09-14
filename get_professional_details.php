<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo usuarios logueados pueden ver los perfiles
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de profesional no válido']);
    exit();
}

$profesional_id = (int)$_GET['id'];

// Obtener todos los detalles del profesional
$stmt = $conex->prepare("SELECT nombre_completo, cedula, correo, rol, estado, fecha_registro, especialidades FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $profesional_id);
$stmt->execute();
$resultado = $stmt->get_result();
$profesional = $resultado->fetch_assoc();

if (!$profesional) {
    http_response_code(404);
    echo json_encode(['error' => 'Profesional no encontrado']);
    exit();
}

// Formateamos los datos para que se vean bien
$profesional['fecha_registro_formateada'] = date('d/m/Y', strtotime($profesional['fecha_registro']));
$profesional['rol_formateado'] = ucfirst($profesional['rol']);
$profesional['estado_formateado'] = ucfirst($profesional['estado']);

echo json_encode($profesional);

$stmt->close();
$conex->close();
?>
