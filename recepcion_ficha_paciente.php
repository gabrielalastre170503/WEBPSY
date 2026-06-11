<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'recepcionista') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . eco_url('gestion-pacientes'));
    exit;
}

$paciente_id = (int)$_GET['id'];
$stmt = $conex->prepare("SELECT id, nombre_completo, cedula, correo, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad, fecha_nacimiento, fecha_registro FROM usuarios WHERE id = ? AND rol = 'paciente' AND estado = 'aprobado'");
$stmt->bind_param('i', $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$paciente) {
    header('Location: ' . eco_url('gestion-pacientes'));
    exit;
}

$n_informes = 0;
if ($s = $conex->prepare('SELECT COUNT(*) c FROM informes_estudios WHERE paciente_id = ?')) {
    $s->bind_param('i', $paciente_id);
    $s->execute();
    $n_informes = (int)$s->get_result()->fetch_assoc()['c'];
    $s->close();
}

$n_citas = 0;
if ($s = $conex->prepare("SELECT COUNT(*) c FROM citas WHERE paciente_id = ?")) {
    $s->bind_param('i', $paciente_id);
    $s->execute();
    $n_citas = (int)$s->get_result()->fetch_assoc()['c'];
    $s->close();
}

$page_title    = htmlspecialchars($paciente['nombre_completo']);
$page_subtitle = 'Ficha rápida · Cédula ' . htmlspecialchars($paciente['cedula'] ?? '—');
$active_section = 'gestion-pacientes';

$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';

$page_header_actions = '
    <a href="recepcion_gestion_pacientes.php" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver al listado</a>';

ob_start();
?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:18px;">
    <div class="card" style="padding:14px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Citas</div>
        <div style="font-size:22px;font-weight:800;color:var(--accent-text);"><?= $n_citas ?></div>
    </div>
    <div class="card" style="padding:14px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Estudios (informes)</div>
        <div style="font-size:22px;font-weight:800;color:var(--text-primary);"><?= $n_informes ?></div>
    </div>
    <div class="card" style="padding:14px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:600;">Edad</div>
        <div style="font-size:18px;font-weight:700;"><?= $paciente['edad'] !== null ? (int)$paciente['edad'] . ' años' : '—' ?></div>
    </div>
</div>

<div class="card">
    <h3 style="margin:0 0 12px;font-size:15px;color:var(--text-primary);">Datos de contacto</h3>
    <p style="margin:6px 0;font-size:13.5px;"><i class="fa-solid fa-envelope" style="width:22px;color:var(--accent);"></i> <?= htmlspecialchars($paciente['correo']) ?></p>
    <?php if (!empty($paciente['fecha_nacimiento'])): ?>
        <p style="margin:6px 0;font-size:13.5px;color:var(--text-secondary);">
            <i class="fa-solid fa-cake-candles" style="width:22px;color:var(--accent);"></i>
            Nacimiento: <?= htmlspecialchars(date('d/m/Y', strtotime($paciente['fecha_nacimiento']))) ?>
        </p>
    <?php endif; ?>
    <?php if (!empty($paciente['fecha_registro'])): ?>
        <p style="margin:6px 0;font-size:12.5px;color:var(--text-muted);">
            Registro en sistema: <?= htmlspecialchars(date('d/m/Y', strtotime($paciente['fecha_registro']))) ?>
        </p>
    <?php endif; ?>
</div>

<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:18px;">
    <button type="button" class="btn-primary"
        onclick='rxAbrirProgramarCita(<?= (int)$paciente_id ?>, <?= json_encode($paciente['nombre_completo'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
        <i class="fa-solid fa-calendar-plus"></i> Programar cita directa
    </button>
    <button type="button" class="btn-secondary"
        onclick='rxAbrirInformesPaciente(<?= (int)$paciente_id ?>, <?= json_encode($paciente['nombre_completo'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
        <i class="fa-solid fa-file-waveform"></i> Ver estudios e informes
    </button>
</div>

<?php include __DIR__ . '/layouts/partials/modal_rx_gestion_pacientes.php'; ?>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/recepcion/recepcion_rx_pacientes.js"></script>
HTML;

include __DIR__ . '/layouts/shell.php';
