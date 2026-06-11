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

$rx_total_pacientes = 0;
if ($r = $conex->query("SELECT COUNT(*) AS c FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'")) {
    $row = $r->fetch_assoc();
    $rx_total_pacientes = (int)($row['c'] ?? 0);
}

$page_title    = 'Gestión de pacientes';
$page_subtitle = 'Directorio rápido, citas e informes';
$active_section = 'gestion-pacientes';
$body_class    = 'rx-gestion-pacientes-page';

$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">'
    . '<link rel="stylesheet" href="assets/css/recepcion/recepcion-gestion-pacientes.css">';

$page_header_actions = '
    <button type="button" class="btn-primary" id="btn-open-crear-paciente-eco">
        <i class="fa-solid fa-user-plus"></i> Registrar paciente
    </button>
    <button type="button" class="rx-btn-alta-ext" id="btn-open-crear-paciente-ext">
        <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
        Alta extendida
    </button>';

ob_start();
?>

<!-- Buscador + total (misma línea que Mis Pacientes ecografista) -->
<div class="rx-controls-grid">
    <div class="card">
        <div class="rx-search-wrap">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="buscador-pacientes-rx" class="rx-search-input"
                   placeholder="Buscar por nombre o cédula…" autocomplete="off">
        </div>
    </div>
    <div class="card rx-total-card">
        <div class="rx-total-card__icon" aria-hidden="true">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <div class="rx-total-card__label">Total</div>
            <div class="rx-total-card__value"><span id="rx-pac-count"><?= $rx_total_pacientes ?></span> pacientes</div>
        </div>
    </div>
</div>

<!-- Lista -->
<div class="card" id="rx-pac-list-card" style="padding:0;overflow:hidden;">
    <div id="rx-pac-wrap" class="rx-pac-wrap data-table">
        <p style="padding:20px;color:var(--text-muted);margin:0;">Cargando…</p>
    </div>
</div>

<?php
include __DIR__ . '/layouts/partials/modal_crear_paciente.php';
include __DIR__ . '/layouts/partials/modal_rx_gestion_pacientes.php';
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/panel/eco-table-sort.js"></script>
<script src="assets/js/recepcion/recepcion_rx_pacientes.js"></script>
<script>
window.abrirModalGestionarPaciente = function (id) {
    window.location.href = 'recepcion_ficha_paciente.php?id=' + encodeURIComponent(id);
};
</script>
<script>
(function () {
    var inp = document.getElementById('buscador-pacientes-rx');
    var box = document.getElementById('rx-pac-wrap');
    var countEl = document.getElementById('rx-pac-count');

    function rxActualizarTotal(wrap) {
        if (!countEl || !wrap) return;
        var el = wrap.querySelector('[data-rx-total]');
        if (el) {
            countEl.textContent = el.getAttribute('data-rx-total') || '0';
        }
    }

    window.buscarPacientesRecepcion = function (q) {
        if (!box) return;
        box.innerHTML = '<p style="padding:20px;color:var(--text-muted);margin:0;">Cargando…</p>';
        fetch('buscar_pacientes_secretaria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'query=' + encodeURIComponent(q || '')
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                box.innerHTML = html;
                rxActualizarTotal(box);
                if (typeof window.rxResetTablaOrden === 'function') {
                    window.rxResetTablaOrden();
                }
            })
            .catch(function () {
                box.innerHTML = '<p style="color:#b91c1c;padding:16px;margin:0;">No se pudo cargar el listado.</p>';
            });
    };

    if (inp) {
        buscarPacientesRecepcion('');
        inp.addEventListener('keyup', function () { buscarPacientesRecepcion(this.value); });
    }

    var fpEco = null;
    function initFechaNacimientoEco() {
        var el = document.getElementById('fecha_nacimiento_eco');
        if (!el || typeof flatpickr === 'undefined') return;
        if (fpEco) { fpEco.destroy(); fpEco = null; }
        var loc = (flatpickr.l10ns && flatpickr.l10ns.es) ? flatpickr.l10ns.es : undefined;
        fpEco = flatpickr(el, {
            locale: loc,
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            altInput: true,
            altFormat: 'd/m/Y'
        });
    }

    var btnOpen = document.getElementById('btn-open-crear-paciente-eco');
    if (btnOpen) {
        btnOpen.addEventListener('click', function () {
            var form = document.getElementById('form-crear-paciente-eco');
            var err = document.getElementById('eco-crear-paciente-error');
            if (form) form.reset();
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-crear-paciente');
            setTimeout(initFechaNacimientoEco, 0);
        });
    }

    var btnExt = document.getElementById('btn-open-crear-paciente-ext');
    if (btnExt && typeof window.rxAbrirCrearPacienteExtendido === 'function') {
        btnExt.addEventListener('click', function () {
            rxAbrirCrearPacienteExtendido();
        });
    }

    var formEco = document.getElementById('form-crear-paciente-eco');
    if (formEco) {
        formEco.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('btn-submit-crear-paciente-eco');
            var err = document.getElementById('eco-crear-paciente-error');
            if (err) { err.style.display = 'none'; err.textContent = ''; }
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…'; }
            fetch('guardar_paciente.php', { method: 'POST', body: new FormData(formEco) })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (typeof EcoModal !== 'undefined') EcoModal.close('eco-modal-crear-paciente');
                        document.getElementById('eco-exito-paciente-nombre').textContent = data.nombre || '';
                        document.getElementById('eco-exito-paciente-pass').textContent = data.password || '—';
                        EcoModal.open('eco-modal-exito-paciente');
                        buscarPacientesRecepcion(inp ? inp.value : '');
                    } else if (err) {
                        err.textContent = data.message || 'No se pudo crear el paciente.';
                        err.style.display = 'block';
                    }
                })
                .catch(function () {
                    if (err) {
                        err.textContent = 'Error de red. Intenta de nuevo.';
                        err.style.display = 'block';
                    }
                })
                .finally(function () {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Crear paciente'; }
                });
        });
    }

    var btnExito = document.getElementById('btn-eco-exito-cerrar');
    if (btnExito) {
        btnExito.addEventListener('click', function () {
            if (typeof EcoModal !== 'undefined') EcoModal.close('eco-modal-exito-paciente');
        });
    }
})();
</script>
HTML;

include __DIR__ . '/layouts/shell.php';
