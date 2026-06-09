<?php
/**
 * Marca una notificacion (o todas) como leidas (Fase 4A). POST + CSRF.
 *   - todas=1            -> marca todas las del usuario
 *   - id=<n>             -> marca una
 */
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/notificaciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido.']);
    exit();
}
require_csrf();

$uid = (int)$_SESSION['usuario_id'];

if (!empty($_POST['todas'])) {
    $n = eco_notificaciones_marcar_todas($conex, $uid);
    echo json_encode(['success' => true, 'marcadas' => $n, 'no_leidas' => 0]);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Notificacion no valida.']);
    exit();
}
eco_notificacion_marcar($conex, $id, $uid);
echo json_encode(['success' => true, 'no_leidas' => eco_notificaciones_no_leidas($conex, $uid)]);
