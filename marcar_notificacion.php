<?php
/**
 * Marca una notificacion (o todas) como leidas (Fase 4A). POST + CSRF.
 *   - todas=1            -> marca todas las del usuario
 *   - id=<n>             -> marca una
 */
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/notificaciones.php';

api_json();
api_require_login();
api_require_post();
api_require_csrf();

$uid = api_uid();

if (api_param('todas')) {
    $n = eco_notificaciones_marcar_todas($conex, $uid);
    api_ok(['marcadas' => $n, 'no_leidas' => 0]);
}

$id = api_int('id');
if ($id <= 0) {
    api_fail('Notificacion no valida.');
}
eco_notificacion_marcar($conex, $id, $uid);
api_ok(['no_leidas' => eco_notificaciones_no_leidas($conex, $uid)]);
