<?php
/**
 * consentimiento.php — Pantalla de consentimiento informado (gate del paciente).
 * Standalone (no usa shell.php para evitar el propio guard). Bloquea hasta aceptar.
 */
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/seguridad/consentimiento.php';
require_once __DIR__ . '/lib/seguridad/seguridad.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'paciente') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}
$uid = (int)$_SESSION['usuario_id'];

// Ya aceptó la versión vigente: no mostrar de nuevo.
if (eco_consentimiento_vigente($conex, $uid)) {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (empty($_POST['acepto'])) {
        $error = 'Debes marcar la casilla para continuar.';
    } else {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (eco_consentimiento_registrar($conex, $uid, $ip, $ua)) {
            eco_auditar($conex, 'consentimiento_aceptado', ['detalle' => ['version' => ECO_CONSENT_VERSION]]);
            header('Location: ' . eco_url('dashboard') . '?status=consentimiento_ok');
            exit;
        }
        $error = 'No se pudo registrar tu consentimiento. Inténtalo de nuevo.';
    }
}

$nombre = $_SESSION['nombre_completo'] ?? 'Paciente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consentimiento informado · EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --azul:#02b1f4; --azul-dark:#014a82; --ink:#0c1a2e; --gris:#4a5870; --err:#b91c1c; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Inter',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:linear-gradient(180deg,#eaf3ff 0%,#f5f9ff 100%); color:var(--ink); padding:24px; }
        .card { background:#fff; border:1px solid rgba(2,177,244,.18); border-radius:22px; padding:36px 38px;
                max-width:680px; width:100%; box-shadow:0 30px 80px rgba(12,26,46,.12); }
        .head { display:flex; align-items:center; gap:14px; margin-bottom:18px; }
        .ic { width:56px; height:56px; border-radius:16px; background:rgba(2,177,244,.12); color:var(--azul-dark);
              display:flex; align-items:center; justify-content:center; font-size:24px; flex-shrink:0; }
        h1 { font-size:20px; font-weight:700; letter-spacing:-.02em; }
        .head p { font-size:13.5px; color:var(--gris); margin-top:2px; }
        .doc { max-height:42vh; overflow-y:auto; padding:18px 20px; border:1px solid #e6edf5; border-radius:14px;
               background:#fbfdff; font-size:13.5px; line-height:1.6; color:#243043; margin-bottom:18px; }
        .doc h4 { font-size:14px; color:var(--azul-dark); margin:14px 0 6px; }
        .doc ul { margin:0 0 8px 18px; }
        .doc a { color:var(--azul-dark); font-weight:600; }
        .acepto { display:flex; gap:10px; align-items:flex-start; font-size:13.5px; color:var(--ink); margin-bottom:18px; cursor:pointer; }
        .acepto input { width:18px; height:18px; margin-top:1px; accent-color:var(--azul); flex-shrink:0; }
        .row { display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
        .btn { padding:14px 22px; border:none; border-radius:13px; cursor:pointer;
               background:linear-gradient(135deg,var(--azul),var(--azul-dark)); color:#fff; font-weight:600; font-size:14.5px;
               font-family:inherit; box-shadow:0 14px 30px rgba(2,177,244,.32); }
        .logout { color:var(--gris); font-weight:600; text-decoration:none; font-size:13px; }
        .logout:hover { color:var(--azul-dark); }
        .msg-err { padding:12px 16px; border-radius:12px; font-size:13.5px; margin-bottom:16px;
                   background:rgba(239,68,68,.08); color:var(--err); border:1px solid rgba(239,68,68,.25);
                   display:flex; gap:10px; align-items:flex-start; }
        .ver { font-size:11.5px; color:#94a3b8; margin-top:14px; text-align:right; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <div class="ic"><i class="fa-solid fa-file-shield"></i></div>
            <div>
                <h1>Consentimiento informado</h1>
                <p>Hola <?= htmlspecialchars($nombre) ?>, antes de continuar necesitamos tu aceptación.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="msg-err"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>

        <div class="doc"><?= eco_consentimiento_texto() ?></div>

        <form method="POST" action="consentimiento.php">
            <?= csrf_field() ?>
            <label class="acepto">
                <input type="checkbox" name="acepto" value="1">
                <span>He leído y comprendo la información anterior y otorgo mi consentimiento informado para el tratamiento de mis datos personales y de salud descrito.</span>
            </label>
            <div class="row">
                <a href="<?= eco_url('logout') ?>" class="logout"><i class="fa-solid fa-arrow-left"></i> Salir</a>
                <button type="submit" class="btn"><i class="fa-solid fa-check"></i> Acepto y continúo</button>
            </div>
        </form>
        <div class="ver">Versión <?= htmlspecialchars(ECO_CONSENT_VERSION) ?></div>
    </div>
</body>
</html>
