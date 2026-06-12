<?php
/**
 * mantenimiento_retencion.php — Ejecuta la purga de datos efímeros (retención).
 * SOLO CLI (no accesible por web).
 *
 * Uso:
 *   php mantenimiento_retencion.php            # dry-run: muestra cuántos se purgarían
 *   php mantenimiento_retencion.php --apply    # ejecuta el borrado
 *
 * Cron sugerido (semanal, domingo 03:00):
 *   0 3 * * 0  php /ruta/al/proyecto/mantenimiento_retencion.php --apply
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por línea de comandos.');
}

require __DIR__ . '/../conexion.php';
require __DIR__ . '/../lib/seguridad/retencion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

$apply = in_array('--apply', $argv, true);
$res   = eco_retencion_purgar($conex, !$apply);

echo ($apply ? '[APLICADO] ' : '[DRY-RUN]  ') . 'Purga de retención (' . date('Y-m-d H:i') . ')' . PHP_EOL;
echo '  intentos_login   : ' . $res['intentos_login'] . PHP_EOL;
echo '  descarga_tokens  : ' . $res['descarga_tokens'] . PHP_EOL;

if ($apply) {
    eco_auditar($conex, 'retencion_purga', ['detalle' => [
        'intentos_login'  => $res['intentos_login'],
        'descarga_tokens' => $res['descarga_tokens'],
    ]]);
    echo 'Registrado en auditoría.' . PHP_EOL;
} else {
    echo 'Nada borrado (dry-run). Usa --apply para ejecutar.' . PHP_EOL;
}

$conex->close();
