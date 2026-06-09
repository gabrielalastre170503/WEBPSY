<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
if (!in_array($_SESSION['rol'] ?? '', ['administrador', 'recepcionista'], true)) {
    header('Location: dashboard_v2.php');
    exit;
}

/* Quick stats */
$citas_hoy = $citas_pendientes = $citas_confirmadas = 0;
if ($r = $conex->query("SELECT COUNT(*) c FROM citas WHERE DATE(fecha_cita)=CURDATE() AND fecha_cita IS NOT NULL")) {
    $citas_hoy = (int)$r->fetch_assoc()['c'];
}
if ($r = $conex->query("SELECT COUNT(*) c FROM citas WHERE estado='pendiente'")) {
    $citas_pendientes = (int)$r->fetch_assoc()['c'];
}
if ($r = $conex->query("SELECT COUNT(*) c FROM citas WHERE estado='confirmada' AND fecha_cita >= NOW()")) {
    $citas_confirmadas = (int)$r->fetch_assoc()['c'];
}

$page_title    = 'Agenda General';
$page_subtitle = 'Vista global de todas las citas programadas en la clínica';
$active_section = 'agenda-general';

$rol_agenda = $_SESSION['rol'] ?? '';

$page_header_actions = '
    <button type="button" class="btn-secondary" id="agenda-btn-recordatorios"><i class="fa-solid fa-bell"></i> Recordatorios</button>
    <button type="button" class="btn-secondary" id="agenda-btn-lista"><i class="fa-solid fa-list"></i> Vista de Lista</button>
    <button type="button" class="btn-primary" id="agenda-btn-nueva"><i class="fa-solid fa-plus"></i> Nueva Cita</button>';

$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">'
    . '<link rel="stylesheet" href="assets/css/agenda-general-modals.css">';

ob_start();
?>

<!-- Stats rápidas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
        <p class="stat-card-label">Citas de Hoy</p>
        <p class="stat-card-value accent"><?= $citas_hoy ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <p class="stat-card-label">Pendientes</p>
        <p class="stat-card-value warning"><?= $citas_pendientes ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <p class="stat-card-label">Confirmadas Próximas</p>
        <p class="stat-card-value success"><?= $citas_confirmadas ?></p>
    </div>
</div>

<!-- Calendario -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-calendar-week" style="margin-right:7px;color:var(--accent);"></i> Calendario de Citas</h3>
        <div style="display:flex;gap:6px;font-size:12px;color:var(--text-secondary);">
            <span style="display:inline-flex;align-items:center;gap:5px;">
                <span style="width:10px;height:10px;border-radius:50%;background:#3b82f6;"></span> Confirmada
            </span>
            <span style="display:inline-flex;align-items:center;gap:5px;margin-left:14px;">
                <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;"></span> Pendiente
            </span>
        </div>
    </div>
    <div id="calendario-general" style="min-height:600px;"></div>
</div>

<!-- FullCalendar (CDN) -->
<link  href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('calendario-general');
    if (!el || typeof FullCalendar === 'undefined') return;

    const calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        locale: 'es',
        height: 'auto',
        firstDay: 1,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
        allDayText: 'Todo el día',
        events: 'get_all_citas.php',
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true },
        slotLabelFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true },
        eventClick: function() {
            window.location.href = <?= json_encode($rol_agenda === 'recepcionista' ? 'recepcion_historial_citas.php' : 'ver_citas_admin.php', JSON_UNESCAPED_SLASHES) ?>;
        }
    });
    calendar.render();
    window.agendaGeneralCalendar = calendar;

    /* Estilos de FullCalendar adaptados al tema */
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        el.style.setProperty('--fc-border-color', 'var(--border)');
        el.style.setProperty('--fc-page-bg-color', 'var(--bg-surface)');
        el.style.setProperty('--fc-neutral-bg-color', 'var(--bg-muted)');
        el.style.setProperty('--fc-list-event-hover-bg-color', 'var(--bg-hover)');
    }
});
</script>

<style>
/* FullCalendar: tema usando variables del shell */
.fc { font-family: var(--font); --fc-border-color: var(--border-soft); --fc-button-bg-color: var(--bg-surface); --fc-button-border-color: var(--border); --fc-button-text-color: var(--text-primary); --fc-button-hover-bg-color: var(--bg-hover); --fc-button-hover-border-color: var(--text-muted); --fc-button-active-bg-color: var(--accent); --fc-button-active-border-color: var(--accent); --fc-today-bg-color: var(--accent-soft); --fc-event-bg-color: var(--accent); --fc-event-border-color: var(--accent); }
.fc .fc-toolbar-title { font-size: 16px !important; color: var(--text-primary); }
.fc .fc-button { font-size: 12px !important; padding: 6px 12px !important; border-radius: 8px !important; font-weight: 600; }
.fc .fc-button-active { color: #fff !important; }
.fc .fc-col-header-cell-cushion { color: var(--text-secondary); font-weight: 600; font-size: 12px; text-transform: uppercase; }
.fc .fc-daygrid-day-number { color: var(--text-primary); font-size: 12.5px; padding: 6px 8px; }
.fc .fc-event { border-radius: 4px; padding: 2px 6px; font-size: 11.5px; cursor: pointer; }
</style>

<?php include __DIR__ . '/layouts/partials/modal_recordatorios.php'; ?>

<?php
$page_content = ob_get_clean();

ob_start();
include __DIR__ . '/layouts/partials/modal_agenda_general.php';
$agenda_modals_html = ob_get_clean();

$page_scripts_extra = $agenda_modals_html
    . '<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>'
    . '<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>'
    . '<script src="assets/js/agenda-general-modals.js"></script>'
    . '<script src="assets/js/recordatorios-ui.js?v=' . (@filemtime(__DIR__ . '/assets/js/recordatorios-ui.js') ?: '1') . '"></script>';

include __DIR__ . '/layouts/shell.php';
