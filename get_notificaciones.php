<?php
/**
 * Lista las notificaciones del usuario en sesion + contador sin leer (Fase 4A).
 * La campana del topbar lo consulta periodicamente.
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

$uid = (int)$_SESSION['usuario_id'];
$items = [];
foreach (eco_notificaciones_listar($conex, $uid, 15) as $n) {
    $items[] = [
        'id'      => (int)$n['id'],
        'tipo'    => $n['tipo'],
        'titulo'  => $n['titulo'],
        'mensaje' => $n['mensaje'],
        'url'     => $n['url'],
        'icono'   => $n['icono'] ?: 'fa-solid fa-bell',
        'leida'   => (int)$n['leida'],
        'hace'    => eco_hace_seg((int)$n['hace_seg']),
    ];
}

echo json_encode([
    'success'   => true,
    'no_leidas' => eco_notificaciones_no_leidas($conex, $uid),
    'items'     => $items,
]);
