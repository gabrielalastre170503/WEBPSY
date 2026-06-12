<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'recepcionista') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$page_title    = 'Historial de citas';
$page_subtitle = 'Busca citas por paciente, cédula o ecografista';
$active_section = 'historial-citas-general';

ob_start();
?>

<style>
#rx-hist-wrap .approvals-table { width:100%; border-collapse:collapse; font-size:13.5px; }
#rx-hist-wrap .approvals-table th, #rx-hist-wrap .approvals-table td {
    padding:10px 12px; border-bottom:1px solid var(--border-soft); text-align:left;
}
#rx-hist-wrap .approvals-table thead { background:var(--bg-muted); }
#rx-hist-wrap .status-badge { display:inline-block; padding:4px 10px; border-radius:6px; font-size:11.5px; font-weight:600; }
#rx-hist-wrap .status-badge.status-confirmada, #rx-hist-wrap .status-badge.status-reprogramada { background:rgba(34,197,94,.12); color:#15803d; }
#rx-hist-wrap .status-badge.status-pendiente { background:rgba(245,158,11,.12); color:#b45309; }
#rx-hist-wrap .status-badge.status-completada { background:rgba(59,130,246,.12); color:#1d4ed8; }
#rx-hist-wrap .status-badge.status-cancelada { background:rgba(239,68,68,.12); color:#b91c1c; }
</style>

<div class="card" style="padding:14px 18px;margin-bottom:14px;">
    <div style="position:relative;">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
        <input type="search" id="buscador-historial-rx" placeholder="Buscar por paciente, cédula o ecografista…"
               style="width:100%;padding:10px 14px 10px 40px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;background:var(--bg-surface);color:var(--text-primary);box-sizing:border-box;">
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div id="rx-hist-wrap" class="data-table" style="border:none;padding:0 4px 12px;">
        <p style="padding:20px;color:var(--text-muted);">Cargando…</p>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    var inp = document.getElementById('buscador-historial-rx');
    var box = document.getElementById('rx-hist-wrap');
    function buscar(q) {
        if (!box) return;
        box.innerHTML = '<p style="padding:20px;color:var(--text-muted);">Buscando…</p>';
        fetch((window.ECO_BASE || '') + 'api/buscar_citas_secretaria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'query=' + encodeURIComponent(q)
        })
            .then(function (r) { return r.text(); })
            .then(function (html) { box.innerHTML = html; })
            .catch(function () {
                box.innerHTML = '<p style="color:#b91c1c;padding:16px;">No se pudo cargar el historial.</p>';
            });
    }
    if (inp) {
        buscar('');
        inp.addEventListener('keyup', function () { buscar(this.value); });
    }
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
