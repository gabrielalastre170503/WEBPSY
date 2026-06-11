<?php
/**
 * verificar_firma.php — Verificacion de integridad/autenticidad de un informe
 * firmado (Fase 3c).
 *
 * Dos modos:
 *   - JSON  (?format=json): usado por el panel del ecografista (requiere sesion;
 *     staff cualquiera, paciente solo el suyo).
 *   - HTML  (navegador): la URL impresa en el PDF firmado. Acceso para staff
 *     logueado, o para cualquiera que aporte el codigo `c` correcto (los
 *     primeros 16 hex del sello), lo que evita enumeracion ciega.
 *
 * NUNCA expone datos clinicos: solo el resultado del sello (numero, profesional,
 * fecha, huella, integro/valido).
 */
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/informes/firma.php';

$informe_id = isset($_GET['informe_id']) ? (int)$_GET['informe_id'] : 0;
$codigo     = isset($_GET['c']) ? (string)$_GET['c'] : '';
$wantJson   = (($_GET['format'] ?? '') === 'json')
    || strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;

$rol  = (string)($_SESSION['rol'] ?? '');
$uid  = (int)($_SESSION['usuario_id'] ?? 0);
$esStaff = in_array($rol, ['ecografista', 'administrador', 'recepcionista'], true);

function eco_verif_responder_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

if ($informe_id <= 0) {
    if ($wantJson) eco_verif_responder_json(['success' => false, 'message' => 'Informe no valido.'], 400);
    http_response_code(400);
    exit('Solicitud invalida.');
}

$sql = "SELECT id, numero_informe, estado, datos_clinicos, esquema_version, fecha_estudio,
               paciente_id, ecografista_id, tipo_ecografia_id, firmado_por, fecha_firma,
               documento_sha256, sello_firma, sello_version
        FROM informes_estudios WHERE id = ?";
$st = $conex->prepare($sql);
$st->bind_param('i', $informe_id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    if ($wantJson) eco_verif_responder_json(['success' => false, 'message' => 'Informe no encontrado.'], 404);
    http_response_code(404);
    exit('Informe no encontrado.');
}

// Nombre del firmante (no se exponen datos del paciente)
$firmante = '';
if (!empty($row['firmado_por'])) {
    $q = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
    $q->bind_param('i', $row['firmado_por']);
    $q->execute();
    $firmante = (string)($q->get_result()->fetch_assoc()['nombre_completo'] ?? '');
    $q->close();
}

$firmado = ($row['estado'] === 'firmado') && !empty($row['sello_firma']);

// ── Control de acceso ────────────────────────────────────────────────
$codigoOk = $firmado && $codigo !== '' && hash_equals(substr((string)$row['sello_firma'], 0, 16), $codigo);
$puedePaciente = ($rol === 'paciente' && $uid === (int)$row['paciente_id']);

if ($wantJson) {
    if (!$esStaff && !$puedePaciente) {
        eco_verif_responder_json(['success' => false, 'message' => 'Acceso no autorizado.'], 403);
    }
} else {
    if (!$esStaff && !$codigoOk) {
        http_response_code(403);
        exit('Para verificar este documento necesitas el enlace completo de verificacion.');
    }
}

// ── Verificacion ─────────────────────────────────────────────────────
$res = $firmado
    ? eco_firma_verificar($row, (string)$row['datos_clinicos'])
    : ['integro' => false, 'sello_valido' => false, 'hash_calculado' => '', 'hash_guardado' => ''];

$valido = $firmado && $res['integro'] && $res['sello_valido'];
$fechaFirmaFmt = !empty($row['fecha_firma']) ? date('d/m/Y H:i', strtotime($row['fecha_firma'])) : '';

if ($wantJson) {
    eco_verif_responder_json([
        'success'      => true,
        'firmado'      => $firmado,
        'valido'       => $valido,
        'integro'      => (bool)$res['integro'],
        'sello_valido' => (bool)$res['sello_valido'],
        'numero'       => (string)$row['numero_informe'],
        'firmante'     => $firmante,
        'fecha_firma'  => $fechaFirmaFmt,
        'hash'         => (string)$row['documento_sha256'],
    ]);
}

/* ── Vista HTML ───────────────────────────────────────────────────── */
if (!$firmado) {
    $estadoTit = 'Documento no firmado';
    $estadoTxt = 'Este informe todavía no ha sido firmado electrónicamente.';
    $color = '#92400e'; $bg = '#fef3c7'; $icon = 'fa-circle-exclamation';
} elseif ($valido) {
    $estadoTit = 'Documento íntegro y firma válida';
    $estadoTxt = 'El contenido no ha sido alterado desde la firma y el sello del servidor es auténtico.';
    $color = '#166534'; $bg = '#dcfce7'; $icon = 'fa-circle-check';
} else {
    $estadoTit = 'Verificación fallida';
    $estadoTxt = $res['integro']
        ? 'El sello de firma no es auténtico. No confíes en este documento.'
        : 'El contenido fue alterado tras la firma. No confíes en este documento.';
    $color = '#991b1b'; $bg = '#fee2e2'; $icon = 'fa-triangle-exclamation';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación de firma — EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body{margin:0;font-family:system-ui,"Segoe UI",sans-serif;background:#eef2f7;color:#1a2332;
             min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
        .box{max-width:520px;width:100%;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.1);overflow:hidden;}
        .top{padding:30px 30px 22px;text-align:center;background:<?= $bg ?>;color:<?= $color ?>;}
        .top i{font-size:42px;}
        .top h1{font-size:20px;margin:14px 0 6px;}
        .top p{margin:0;font-size:14px;line-height:1.55;opacity:.9;}
        .det{padding:22px 30px 28px;}
        .row{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px solid #eef2f7;font-size:13.5px;}
        .row:last-child{border-bottom:none;}
        .row .k{color:#5a6878;font-weight:600;}
        .row .v{text-align:right;font-weight:600;}
        .hash{font-family:ui-monospace,Consolas,monospace;font-size:11px;word-break:break-all;color:#5a6878;
              background:#f8fbff;border:1px solid #e1e8f0;border-radius:8px;padding:10px 12px;margin-top:6px;}
        .foot{font-size:11.5px;color:#94a3b8;text-align:center;padding:0 30px 22px;}
    </style>
</head>
<body>
    <div class="box">
        <div class="top">
            <i class="fa-solid <?= $icon ?>"></i>
            <h1><?= htmlspecialchars($estadoTit) ?></h1>
            <p><?= htmlspecialchars($estadoTxt) ?></p>
        </div>
        <div class="det">
            <div class="row"><span class="k">Informe N.º</span><span class="v"><?= htmlspecialchars($row['numero_informe'] ?: '—') ?></span></div>
            <?php if ($firmado): ?>
            <div class="row"><span class="k">Firmado por</span><span class="v"><?= htmlspecialchars($firmante ?: '—') ?></span></div>
            <div class="row"><span class="k">Fecha de firma</span><span class="v"><?= htmlspecialchars($fechaFirmaFmt) ?></span></div>
            <div class="row"><span class="k">Integridad del contenido</span><span class="v" style="color:<?= $res['integro'] ? '#166534' : '#991b1b' ?>"><?= $res['integro'] ? 'Correcta' : 'Alterada' ?></span></div>
            <div class="row"><span class="k">Sello del servidor</span><span class="v" style="color:<?= $res['sello_valido'] ? '#166534' : '#991b1b' ?>"><?= $res['sello_valido'] ? 'Auténtico' : 'Inválido' ?></span></div>
            <div style="margin-top:10px;">
                <span class="k" style="font-size:12px;color:#5a6878;font-weight:600;">Huella SHA-256</span>
                <div class="hash"><?= htmlspecialchars($row['documento_sha256'] ?: '—') ?></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="foot"><i class="fa-solid fa-shield-halved"></i> EcoMadelleine · Verificación de firma electrónica</div>
    </div>
</body>
</html>
