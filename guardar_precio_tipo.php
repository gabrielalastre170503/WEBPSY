<?php
/**
 * Actualiza el precio de un tipo de estudio (solo administrador).
 */
session_start();
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';

api_json();
$response = ['success' => false, 'message' => 'Ocurrio un error.'];

api_require_roles(['administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

api_require_csrf();

$tipo_id = isset($_POST['tipo_id']) ? (int)$_POST['tipo_id'] : 0;
$precio  = isset($_POST['precio']) ? (float)$_POST['precio'] : -1;

if ($tipo_id <= 0 || $precio < 0) {
    $response['message'] = 'Datos no válidos.';
    echo json_encode($response);
    exit();
}

$up = $conex->prepare("UPDATE tipos_ecografias SET precio = ? WHERE id = ?");
$up->bind_param('di', $precio, $tipo_id);

if ($up->execute()) {
    $response['success'] = true;
    $response['message'] = 'Precio actualizado.';
    $response['precio']  = $precio;
} else {
    error_log('guardar_precio_tipo: ' . $conex->error);
    $response['message'] = 'No se pudo guardar el precio.';
}
$up->close();
$conex->close();
echo json_encode($response);
