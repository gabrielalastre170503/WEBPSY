<?php
/**
 * Lista las notificaciones del usuario en sesion + contador sin leer (Fase 4A).
 * La campana del topbar lo consulta periodicamente.
 */
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/comunicaciones/notificaciones.php';

api_json();
api_require_login();

$uid = api_uid();
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

api_ok([
    'no_leidas' => eco_notificaciones_no_leidas($conex, $uid),
    'items'     => $items,
]);
