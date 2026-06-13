<?php
/**
 * cron_recordatorios.php — Fase 4 (B): disparador de recordatorios de cita.
 *
 * Tres formas de ejecutarlo:
 *   1. CLI  (Windows Task Scheduler):  php cli/cron_recordatorios.php [--dry]
 *   2. Token (machine-to-machine):     GET cli/cron_recordatorios.php?key=ECO_CRON_KEY
 *   3. Sesion admin/recepcion + POST + CSRF  (boton "ejecutar ahora")
 *
 * Programar en Windows (cada 30 min p.ej.):
 *   schtasks /create /tn "EcoRecordatorios" /tr "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\Sistema_EcoMadelleineV1\cli\cron_recordatorios.php\"" /sc minute /mo 30
 */

$cli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../config/env_loader.php';
eco_load_env(__DIR__ . '/../.env');

if (!$cli) {
    // En web, bootstrap.php (auto_prepend) ya cargo env + CSRF; arrancamos sesion.
    if (session_status() === PHP_SESSION_NONE) session_start();
}

include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/comunicaciones/recordatorios.php';

/* ── Autorizacion ────────────────────────────────────────────────────── */
$cronKey    = (string) eco_env('ECO_CRON_KEY', '');
$autorizado = false;

if ($cli) {
    $autorizado = true;
} else {
    $key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
    if ($cronKey !== '' && hash_equals($cronKey, $key)) {
        $autorizado = true;                       // token machine-to-machine
    } elseif (isset($_SESSION['usuario_id'])
        && in_array(($_SESSION['rol'] ?? ''), ['administrador', 'recepcionista', 'ecografista'], true)
        && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();                           // boton admin/recep/ecografista
        $autorizado = true;
    }
}

if (!$autorizado) {
    if ($cli) {
        fwrite(STDERR, "No autorizado.\n");
    } else {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    }
    exit();
}

/* ── Ejecucion ───────────────────────────────────────────────────────── */
$argv = $argv ?? [];
$dry      = isset($_GET['dry']) || isset($_POST['dry']) || in_array('--dry', $argv, true);
$ventana  = (int)($_GET['ventana_horas'] ?? $_POST['ventana_horas'] ?? 24);

$opts = ['ventana_horas' => $ventana, 'dry_run' => $dry];
// Un ecografista solo recuerda SUS propias citas; admin/recep/token/CLI = todas.
if (!$cli && ($_SESSION['rol'] ?? '') === 'ecografista') {
    $opts['ecografista_id'] = (int)$_SESSION['usuario_id'];
}
$res = eco_procesar_recordatorios($conex, $opts);
$res['success'] = true;

if ($cli) {
    echo "[" . date('Y-m-d H:i:s') . "] recordatorios ventana={$res['ventana']} dry=" . ($dry ? 'si' : 'no') . "\n";
    echo "  encontradas={$res['encontradas']} email_ok={$res['email_ok']} email_fail={$res['email_fail']}"
        . " in_app={$res['in_app']} sin_correo={$res['sin_correo']}\n";
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($res);
