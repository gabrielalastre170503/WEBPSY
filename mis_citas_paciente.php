<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if ($_SESSION['rol'] !== 'paciente') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

/* Notificaciones (se muestran una vez y se limpian) */
$notificaciones = [];
if ($stmt_notif = $conex->prepare('SELECT id, notificacion_paciente FROM citas WHERE paciente_id = ? AND notificacion_paciente IS NOT NULL')) {
    $stmt_notif->bind_param('i', $usuario_id);
    $stmt_notif->execute();
    $res = $stmt_notif->get_result();
    while ($row = $res->fetch_assoc()) {
        $notificaciones[] = $row;
        if ($stmt_clear = $conex->prepare('UPDATE citas SET notificacion_paciente = NULL WHERE id = ?')) {
            $stmt_clear->bind_param('i', $row['id']);
            $stmt_clear->execute();
            $stmt_clear->close();
        }
    }
    $stmt_notif->close();
}

/* Citas del paciente */
$citas = [];
$q = "
    SELECT c.id, c.fecha_cita, c.fecha_propuesta, c.estado, c.fecha_solicitud,
           p.nombre_completo AS ecografista_nombre,
           t.nombre AS tipo_estudio, t.icono AS tipo_icono, t.categoria AS tipo_categoria,
           e.puntuacion AS encuesta_punt
    FROM citas c
    LEFT JOIN usuarios p ON c.ecografista_id = p.id
    LEFT JOIN tipos_ecografias t ON c.tipo_ecografia_id = t.id
    LEFT JOIN encuestas e ON e.cita_id = c.id
    WHERE c.paciente_id = ?
    ORDER BY c.fecha_solicitud DESC
";
if ($stmt = $conex->prepare($q)) {
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$msg_ok = '';
if (isset($_GET['status']) && $_GET['status'] === 'cita_creada') {
    $msg_ok = 'Tu solicitud de cita se registró correctamente. Te notificaremos cuando el ecografista la confirme.';
}

/* ── Metadatos de estado (etiqueta, badge, color, grupo de filtro) ── */
$meses_abbr = [1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'];

$estado_meta = [
    'confirmada'         => ['Confirmada',  'badge-success', '#22c55e', 'proxima'],
    'completada'         => ['Completada',  'badge-info',    '#0ea5e9', 'historial'],
    'pendiente'          => ['Pendiente',   'badge-warning', '#f59e0b', 'pendiente'],
    'pendiente_paciente' => ['Pospuesta',   'badge-warning', '#f59e0b', 'pendiente'],
    'reprogramada'       => ['Reprogramada','badge-purple',  '#8b5cf6', 'proxima'],
    'cancelada'          => ['Cancelada',   'badge-danger',  '#ef4444', 'historial'],
    'rechazada'          => ['Rechazada',   'badge-danger',  '#ef4444', 'historial'],
];
$meta_default = ['Solicitada', 'badge-accent', '#02b1f4', 'pendiente'];

/* ── Estadísticas + próxima cita ── */
$total       = count($citas);
$num_prox    = 0;
$num_pend    = 0;
$num_comp    = 0;
$proxima_ts  = null;
$ahora       = time();

foreach ($citas as $c) {
    $grupo = ($estado_meta[$c['estado']] ?? $meta_default)[3];
    if ($grupo === 'pendiente') $num_pend++;
    if ($c['estado'] === 'completada') $num_comp++;

    $efectiva = ($c['estado'] === 'pendiente_paciente' && !empty($c['fecha_propuesta']))
        ? $c['fecha_propuesta'] : $c['fecha_cita'];
    if (in_array($c['estado'], ['confirmada', 'reprogramada'], true) && !empty($efectiva)) {
        $ts = strtotime($efectiva);
        if ($ts && $ts >= $ahora) {
            $num_prox++;
            if ($proxima_ts === null || $ts < $proxima_ts) $proxima_ts = $ts;
        }
    }
}

$proxima_label = '—';
$proxima_sub   = 'sin citas agendadas';
if ($proxima_ts !== null) {
    $proxima_label = date('d/m/Y', $proxima_ts);
    $proxima_sub   = date('h:i A', $proxima_ts);
}

$page_title       = 'Mis Citas';
$page_subtitle    = 'Consulta el estado de tus citas y los detalles de cada solicitud';
$active_section   = 'miscitas';

ob_start();
?>

<style>
.cita-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
.cita-tab {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 16px; border-radius:999px; font-size:13px; font-weight:600;
    color:var(--text-secondary); background:var(--bg-surface);
    border:1px solid var(--border); cursor:pointer;
    transition:all .18s ease; white-space:nowrap;
}
.cita-tab:hover { color:var(--text-primary); border-color:rgba(2,177,244,.35); }
.cita-tab.is-active { background:var(--accent); color:#fff; border-color:var(--accent); box-shadow:0 4px 12px rgba(2,177,244,.28); }
.cita-tab .cita-tab-count {
    font-size:11.5px; font-weight:700; padding:1px 7px; border-radius:999px;
    background:var(--bg-muted); color:var(--text-secondary); line-height:1.6;
}
.cita-tab.is-active .cita-tab-count { background:rgba(255,255,255,.22); color:#fff; }

.cita-list { display:flex; flex-direction:column; gap:12px; }
.cita-card {
    display:flex; align-items:center; gap:18px;
    padding:16px 20px; background:var(--bg-surface);
    border:1px solid var(--border); border-left:3px solid var(--cita-color,#02b1f4);
    border-radius:var(--radius-lg);
    transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease;
}
.cita-card:hover { box-shadow:var(--shadow); transform:translateY(-2px); }

.cita-date {
    width:62px; flex-shrink:0; text-align:center;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:8px 4px; border-radius:12px;
    background:color-mix(in srgb, var(--cita-color) 12%, transparent);
    color:var(--cita-color);
}
.cita-date-day   { font-size:22px; font-weight:800; line-height:1; }
.cita-date-month { font-size:11px; font-weight:700; letter-spacing:.06em; margin-top:2px; }
.cita-date-time  { font-size:11px; font-weight:600; margin-top:4px; opacity:.85; }
.cita-date--tbd  { background:var(--bg-muted); color:var(--text-muted); }
.cita-date--tbd i { font-size:18px; }
.cita-date--tbd span { font-size:9.5px; font-weight:700; letter-spacing:.04em; margin-top:5px; text-transform:uppercase; }

.cita-main { flex:1; min-width:0; }
.cita-title { font-size:14.5px; font-weight:700; color:var(--text-primary); margin:0 0 4px; }
.cita-meta { font-size:12.5px; color:var(--text-secondary); display:flex; flex-wrap:wrap; gap:4px 16px; }
.cita-meta span { display:inline-flex; align-items:center; gap:6px; }
.cita-meta i { color:var(--text-muted); width:13px; text-align:center; }

.cita-side { display:flex; align-items:center; gap:14px; flex-shrink:0; }
.cita-btn {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 16px; border-radius:9px; font-size:13px; font-weight:600;
    background:var(--accent-soft); color:var(--accent-text); border:1px solid rgba(2,177,244,.25);
    cursor:pointer; transition:all .2s ease; white-space:nowrap;
}
.cita-btn:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
.cita-btn--cancel { background:rgba(239,68,68,.1); color:#dc2626; border-color:rgba(239,68,68,.25); }
.cita-btn--cancel:hover { background:#ef4444; color:#fff; border-color:#ef4444; }

.cita-empty { text-align:center; padding:48px 24px; color:var(--text-muted); }
.cita-empty > i { font-size:42px; color:var(--accent); opacity:.5; margin-bottom:14px; display:block; }

@media (max-width:680px){
    .cita-card { flex-wrap:wrap; }
    .cita-side { width:100%; justify-content:space-between; }
}

/* ── Modal: detalle de la cita ── */
.cd-head { display:flex; align-items:center; gap:13px; margin-bottom:18px; padding-right:30px; }
.cd-head__icon { width:44px; height:44px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; background:linear-gradient(135deg,var(--accent),#38bdf8); }
.cd-head__title { margin:0; font-size:1.05rem; font-weight:700; color:var(--text-primary); }
.cd-head__sub { margin:2px 0 0; font-size:12.5px; color:var(--text-muted); }

.cd-hero { display:flex; align-items:center; gap:14px; padding:14px 16px; border-radius:14px; border:1px solid var(--border); margin-bottom:18px; background:var(--bg-muted); }
.cd-hero__date { width:56px; flex-shrink:0; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:9px 0; border-radius:11px; background:color-mix(in srgb, var(--cd-color,#02b1f4) 14%, transparent); color:var(--cd-color,#02b1f4); }
.cd-hero__day { font-size:22px; font-weight:800; line-height:1; }
.cd-hero__month { font-size:10.5px; font-weight:700; letter-spacing:.06em; margin-top:3px; }
.cd-hero__info { flex:1; min-width:0; }
.cd-hero__label { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); font-weight:600; }
.cd-hero__value { font-size:14.5px; font-weight:700; color:var(--text-primary); margin-top:2px; }
.cd-badge { display:inline-flex; align-items:center; gap:6px; margin-top:8px; padding:3px 11px; border-radius:999px; font-size:11.5px; font-weight:700; color:#fff; background:var(--cd-color,#02b1f4); }

.cd-section { margin-bottom:16px; }
.cd-section:last-child { margin-bottom:0; }
.cd-section__title { font-size:11px; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); font-weight:700; margin:0 0 8px; display:flex; align-items:center; gap:7px; }
.cd-rows { background:var(--bg-surface); border:1px solid var(--border); border-radius:12px; padding:2px 14px; }
.cd-row { display:flex; gap:12px; padding:11px 0; border-bottom:1px dashed var(--border); align-items:flex-start; }
.cd-row:last-child { border-bottom:none; }
.cd-row__icon { width:28px; height:28px; border-radius:8px; background:var(--accent-soft); color:var(--accent-text); display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; margin-top:1px; }
.cd-row__text { min-width:0; flex:1; }
.cd-row__label { font-size:10.5px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; font-weight:600; }
.cd-row__value { font-size:13.5px; color:var(--text-primary); margin-top:2px; line-height:1.45; word-break:break-word; }

.cd-banner { padding:14px 16px; border-radius:12px; background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.35); margin-bottom:18px; }
.cd-banner__title { font-weight:700; color:#b45309; display:flex; align-items:center; gap:8px; font-size:13.5px; }
.cd-banner__text { font-size:13px; color:var(--text-primary); margin:8px 0 0; line-height:1.5; }
.cd-banner__actions { margin-top:13px; display:flex; gap:10px; flex-wrap:wrap; }
.cd-banner__actions a { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:9px; font-size:13px; font-weight:600; text-decoration:none; }

.cd-loading { text-align:center; color:var(--text-muted); padding:28px 12px; font-size:13.5px; }

/* Modal de solicitud creada */
.cc-icon { width:74px; height:74px; margin:0 auto 16px; border-radius:50%; background:rgba(34,197,94,.14); color:#16a34a; display:flex; align-items:center; justify-content:center; font-size:35px; animation:ccPop .35s cubic-bezier(.34,1.56,.64,1); }
@keyframes ccPop { 0% { transform:scale(.5); opacity:0; } 100% { transform:scale(1); opacity:1; } }
.cc-title { margin:0 0 9px; font-size:19px; font-weight:800; color:var(--text-primary); }
.cc-text { margin:0 auto 22px; max-width:330px; font-size:13.5px; color:var(--text-secondary); line-height:1.55; }
.cc-btn { width:100%; justify-content:center; }
.cc-icon--danger { background:rgba(239,68,68,.14); color:#dc2626; }
.cc-foot { display:flex; gap:10px; justify-content:center; }
</style>

<?php foreach ($notificaciones as $n): ?>
    <div class="card" style="border-left:4px solid var(--accent);background:var(--accent-soft);margin-bottom:12px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
        <span style="font-size:13.5px;color:var(--text-primary);"><strong><i class="fa-solid fa-bell"></i> Notificación:</strong> <?= htmlspecialchars($n['notificacion_paciente']) ?></span>
    </div>
<?php endforeach; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <p class="stat-card-label">Total de citas</p>
        <p class="stat-card-value accent"><?= $total ?></p>
        <p class="stat-card-sub">solicitudes registradas</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-calendar-day"></i></div>
        <p class="stat-card-label">Próxima cita</p>
        <p class="stat-card-value" style="font-size:20px;"><?= htmlspecialchars($proxima_label) ?></p>
        <p class="stat-card-sub"><?= htmlspecialchars($proxima_sub) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;"><i class="fa-solid fa-hourglass-half"></i></div>
        <p class="stat-card-label">Pendientes</p>
        <p class="stat-card-value warning"><?= $num_pend ?></p>
        <p class="stat-card-sub">en espera de confirmación</p>
    </div>
    <a href="<?= eco_url('solicitar-cita') ?>" class="stat-card" style="text-decoration:none;">
        <div class="stat-card-icon"><i class="fa-solid fa-file-circle-plus"></i></div>
        <p class="stat-card-label">Acción rápida</p>
        <p class="stat-card-value accent" style="font-size:18px;">Solicitar cita</p>
        <p class="stat-card-sub">agenda un nuevo estudio</p>
    </a>
</div>

<?php if ($total === 0): ?>
    <div class="card">
        <div class="cita-empty">
            <i class="fa-solid fa-calendar-xmark"></i>
            <p style="margin:0 0 4px;font-weight:600;color:var(--text-secondary);">No tienes ninguna cita solicitada o programada</p>
            <p style="margin:0 0 16px;font-size:13px;">Cuando solicites un estudio, aparecerá aquí con su estado en tiempo real.</p>
            <a href="<?= eco_url('solicitar-cita') ?>" class="btn-primary"><i class="fa-solid fa-file-circle-plus"></i> Solicitar nueva cita</a>
        </div>
    </div>
<?php else: ?>

    <div class="cita-tabs">
        <button type="button" class="cita-tab is-active" data-filter="todas">
            Todas <span class="cita-tab-count"><?= $total ?></span>
        </button>
        <button type="button" class="cita-tab" data-filter="proxima">
            Próximas <span class="cita-tab-count"><?= $num_prox ?></span>
        </button>
        <button type="button" class="cita-tab" data-filter="pendiente">
            Pendientes <span class="cita-tab-count"><?= $num_pend ?></span>
        </button>
        <button type="button" class="cita-tab" data-filter="historial">
            Historial <span class="cita-tab-count"><?= ($total - $num_prox - $num_pend) ?></span>
        </button>
    </div>

    <div class="cita-list">
        <?php foreach ($citas as $cita):
            $meta   = $estado_meta[$cita['estado']] ?? $meta_default;
            [$etiqueta, $badge, $color, $grupo] = $meta;

            $efectiva = ($cita['estado'] === 'pendiente_paciente' && !empty($cita['fecha_propuesta']))
                ? $cita['fecha_propuesta'] : $cita['fecha_cita'];
            $ts = $efectiva ? strtotime($efectiva) : null;

            /* Una cita próxima cuya fecha ya pasó pertenece al historial */
            if ($grupo === 'proxima' && $ts !== null && $ts < $ahora) {
                $grupo = 'historial';
            }

            $icono = $cita['tipo_icono'] ?: 'fa-solid fa-wave-square';
            $titulo = $cita['tipo_estudio'] ?: 'Ecografía';
        ?>
            <div class="cita-card" data-grupo="<?= htmlspecialchars($grupo) ?>" style="--cita-color:<?= htmlspecialchars($color) ?>;">
                <?php if ($ts): ?>
                    <div class="cita-date">
                        <span class="cita-date-day"><?= date('d', $ts) ?></span>
                        <span class="cita-date-month"><?= $meses_abbr[(int)date('n', $ts)] ?></span>
                        <span class="cita-date-time"><?= date('H:i', $ts) ?></span>
                    </div>
                <?php else: ?>
                    <div class="cita-date cita-date--tbd">
                        <i class="fa-solid fa-clock"></i>
                        <span>Por<br>confirmar</span>
                    </div>
                <?php endif; ?>

                <div class="cita-main">
                    <p class="cita-title"><i class="<?= htmlspecialchars($icono, ENT_QUOTES) ?>" style="color:<?= htmlspecialchars($color) ?>;margin-right:7px;"></i><?= htmlspecialchars($titulo) ?></p>
                    <div class="cita-meta">
                        <span><i class="fa-solid fa-user-doctor"></i><?= htmlspecialchars($cita['ecografista_nombre'] ?? 'Sin asignar') ?></span>
                        <?php if (!empty($cita['tipo_categoria'])): ?>
                            <span><i class="fa-solid fa-layer-group"></i><?= htmlspecialchars($cita['tipo_categoria']) ?></span>
                        <?php endif; ?>
                        <?php if ($cita['estado'] === 'pendiente_paciente' && $ts): ?>
                            <span style="color:#b45309;"><i class="fa-solid fa-calendar-day"></i>Nueva fecha propuesta</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cita-side">
                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($etiqueta) ?></span>
                    <button type="button" class="cita-btn" onclick="abrirDetalleCitaPaciente(<?= (int)$cita['id'] ?>)">
                        <i class="fa-solid fa-eye"></i> Ver detalles
                    </button>
                    <?php if ($grupo === 'proxima'): ?>
                        <button type="button" class="cita-btn cita-btn--cancel" onclick="cancelarCitaPaciente(<?= (int)$cita['id'] ?>)">
                            <i class="fa-solid fa-calendar-xmark"></i> Cancelar
                        </button>
                    <?php endif; ?>
                    <?php if ($cita['estado'] === 'completada'): ?>
                        <?php if (!empty($cita['encuesta_punt'])): ?>
                            <span class="cita-enc-rated" title="Tu valoración">
                                <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= (int)$cita['encuesta_punt'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                            </span>
                        <?php else: ?>
                            <button type="button" class="cita-btn cita-btn--rate" onclick="abrirEncuesta(<?= (int)$cita['id'] ?>)">
                                <i class="fa-solid fa-star"></i> Calificar
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div id="cita-empty-filter" class="card cita-empty" style="display:none;">
            <i class="fa-solid fa-calendar-xmark"></i>
            <p style="margin:0;font-weight:600;color:var(--text-secondary);">No hay citas en esta categoría</p>
        </div>
    </div>

<?php endif; ?>

<div id="eco-modal-detalle-cita-paciente" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:540px;">
        <div class="eco-modal__main" style="padding-top:24px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cd-head">
                <div class="cd-head__icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <h2 class="cd-head__title">Detalles de la cita</h2>
                    <p class="cd-head__sub" id="modal-cita-num">…</p>
                </div>
            </div>
            <div id="modal-cita-body"><p class="cd-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p></div>
        </div>
    </div>
</div>

<div id="eco-modal-cancelar-cita" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:420px;">
        <div class="eco-modal__main" style="padding:32px 26px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cc-icon cc-icon--danger"><i class="fa-solid fa-calendar-xmark"></i></div>
            <h2 class="cc-title">¿Cancelar esta cita?</h2>
            <p class="cc-text">Esta acción no se puede deshacer. Si necesitas otra fecha, podrás solicitar una nueva cita.</p>
            <div class="cc-foot">
                <button type="button" class="btn-secondary" data-eco-modal-close><i class="fa-solid fa-arrow-left"></i> Volver</button>
                <a id="cancelar-cita-confirm" href="#" class="btn-primary" style="background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 12px rgba(239,68,68,.3);"><i class="fa-solid fa-xmark"></i> Sí, cancelar</a>
            </div>
        </div>
    </div>
</div>

<?php if ($msg_ok): ?>
<div id="eco-modal-cita-creada" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:430px;">
        <div class="eco-modal__main" style="padding:32px 26px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cc-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h2 class="cc-title">¡Solicitud enviada!</h2>
            <p class="cc-text"><?= htmlspecialchars($msg_ok) ?></p>
            <button type="button" class="btn-primary cc-btn" data-eco-modal-close><i class="fa-solid fa-check"></i> Entendido</button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.cita-btn--rate { color:#b45309; }
.cita-btn--rate:hover { background:#fef3c7; border-color:#fcd34d; }
.cita-enc-rated { display:inline-flex; gap:2px; color:#fbbf24; font-size:13px; align-items:center; }
#eco-modal-encuesta .enc-stars i { cursor:pointer; transition:transform .1s; }
#eco-modal-encuesta .enc-stars i:hover { transform:scale(1.15); }
</style>
<div id="eco-modal-encuesta" class="eco-modal" aria-hidden="true" role="dialog">
    <div class="eco-modal__dialog" style="max-width:430px;">
        <div class="eco-modal__main" style="padding:30px 26px;text-align:center;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="cc-icon" style="background:rgba(251,191,36,.15);color:#d97706;"><i class="fa-solid fa-star"></i></div>
            <h2 class="cc-title">¿Cómo fue tu experiencia?</h2>
            <p class="cc-text">Tu opinión nos ayuda a mejorar la atención.</p>
            <div class="enc-stars" style="font-size:30px;color:#fbbf24;margin:6px 0 14px;">
                <i class="fa-regular fa-star" data-v="1"></i>
                <i class="fa-regular fa-star" data-v="2"></i>
                <i class="fa-regular fa-star" data-v="3"></i>
                <i class="fa-regular fa-star" data-v="4"></i>
                <i class="fa-regular fa-star" data-v="5"></i>
            </div>
            <textarea id="enc-comentario" rows="3" maxlength="1000" placeholder="Comentario (opcional)" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-primary);font-family:inherit;resize:vertical;"></textarea>
            <p id="enc-error" style="color:#dc2626;font-size:12.5px;min-height:16px;margin:8px 0 0;"></p>
            <div class="cc-foot" style="margin-top:10px;">
                <button type="button" class="btn-secondary" data-eco-modal-close><i class="fa-solid fa-arrow-left"></i> Cancelar</button>
                <button type="button" id="enc-submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    /* Filtro por pestañas */
    var tabs  = document.querySelectorAll('.cita-tab');
    var cards = document.querySelectorAll('.cita-card');
    var empty = document.getElementById('cita-empty-filter');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            var f = tab.getAttribute('data-filter');
            var visibles = 0;
            cards.forEach(function (c) {
                var show = (f === 'todas' || c.getAttribute('data-grupo') === f);
                c.style.display = show ? '' : 'none';
                if (show) visibles++;
            });
            if (empty) empty.style.display = (visibles === 0) ? '' : 'none';
        });
    });

    /* Modal de detalles */
    var modalId = 'eco-modal-detalle-cita-paciente';
    var bodyEl  = document.getElementById('modal-cita-body');
    var subEl   = document.getElementById('modal-cita-num');

    var mesesAbbr = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
    var estadoMeta = {
        confirmada:         ['Confirmada',   '#22c55e', 'fa-circle-check'],
        completada:         ['Completada',   '#0ea5e9', 'fa-clipboard-check'],
        pendiente:          ['Pendiente',    '#f59e0b', 'fa-hourglass-half'],
        pendiente_paciente: ['Pospuesta',    '#f59e0b', 'fa-clock-rotate-left'],
        reprogramada:       ['Reprogramada', '#8b5cf6', 'fa-calendar-day'],
        cancelada:          ['Cancelada',    '#ef4444', 'fa-ban'],
        rechazada:          ['Rechazada',    '#ef4444', 'fa-xmark']
    };

    function esc(v) {
        if (v == null) return '';
        return String(v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    var na = '<span style="color:var(--text-muted);">No especificado</span>';
    function val(v) { return v ? esc(v) : na; }
    function multiline(v) { return v ? esc(v).replace(/\n/g, '<br>') : na; }
    function parseFecha(s) { if (!s) return null; var d = new Date(String(s).replace(' ', 'T')); return isNaN(d.getTime()) ? null : d; }
    function row(icon, label, value) {
        return '<div class="cd-row"><div class="cd-row__icon"><i class="fa-solid ' + icon + '"></i></div>'
            + '<div class="cd-row__text"><div class="cd-row__label">' + label + '</div>'
            + '<div class="cd-row__value">' + value + '</div></div></div>';
    }

    function renderDetalle(data) {
        var meta  = estadoMeta[data.estado] || ['Solicitada', '#02b1f4', 'fa-circle-info'];
        var color = meta[1];
        if (subEl) subEl.textContent = 'Solicitud #' + data.id;

        var d = parseFecha(data.fecha_cita);
        var heroDate = d
            ? '<div class="cd-hero__date"><span class="cd-hero__day">' + d.getDate() + '</span><span class="cd-hero__month">' + mesesAbbr[d.getMonth()] + '</span></div>'
            : '<div class="cd-hero__date"><span class="cd-hero__day" style="font-size:17px;"><i class="fa-solid fa-clock"></i></span></div>';

        var html = '';
        html += '<div class="cd-hero" style="--cd-color:' + color + ';">' + heroDate
            + '<div class="cd-hero__info"><div class="cd-hero__label">Fecha de la cita</div>'
            + '<div class="cd-hero__value">' + esc(data.fecha_cita_formateada || 'Por confirmar') + '</div>'
            + '<span class="cd-badge"><i class="fa-solid ' + meta[2] + '"></i> ' + meta[0] + '</span></div></div>';

        if (data.estado === 'pendiente_paciente' || data.estado === 'reprogramada') {
            html += '<div class="cd-banner">';
            html += '<div class="cd-banner__title"><i class="fa-solid fa-calendar-day"></i> El profesional propuso una nueva fecha</div>';
            html += '<p class="cd-banner__text"><strong>Nueva fecha sugerida:</strong> ' + val(data.fecha_propuesta_formateada) + '</p>';
            if (data.reprogramacion_motivo) {
                html += '<p class="cd-banner__text"><strong>Motivo:</strong> <em>' + multiline(data.reprogramacion_motivo) + '</em></p>';
            }
            html += '<div class="cd-banner__actions">';
            html += '<a href="gestionar_propuesta.php?cita_id=' + encodeURIComponent(data.id) + '&accion=rechazar" class="btn-secondary"><i class="fa-solid fa-xmark"></i> Rechazar</a>';
            html += '<a href="gestionar_propuesta.php?cita_id=' + encodeURIComponent(data.id) + '&accion=aceptar" class="btn-primary"><i class="fa-solid fa-check"></i> Aceptar nueva fecha</a>';
            html += '</div></div>';
        }

        html += '<div class="cd-section"><p class="cd-section__title"><i class="fa-solid fa-clipboard-list"></i> Detalle de la solicitud</p><div class="cd-rows">';
        html += row('fa-wave-square', 'Estudio', val(data.motivo_principal));
        html += row('fa-star', 'Tipo de cita', val(data.tipo_cita));
        html += row('fa-hospital', 'Modalidad', val(data.modalidad));
        html += row('fa-notes-medical', 'Antecedentes médicos y detalles', multiline(data.motivo_consulta));
        html += '</div></div>';

        html += '<div class="cd-section"><p class="cd-section__title"><i class="fa-solid fa-user-doctor"></i> Profesional asignado</p><div class="cd-rows">';
        html += row('fa-user-doctor', 'Nombre', val(data.profesional_nombre));
        html += row('fa-id-badge', 'Rol', val(data.profesional_rol));
        html += '</div></div>';

        html += '<div class="cd-section"><p class="cd-section__title"><i class="fa-solid fa-comment-dots"></i> Notas adicionales</p><div class="cd-rows">';
        html += row('fa-comment', 'Tus notas', multiline(data.notas_paciente));
        html += '</div></div>';

        bodyEl.innerHTML = html;
    }

    window.abrirDetalleCitaPaciente = function (citaId) {
        if (!bodyEl) return;
        bodyEl.innerHTML = '<p class="cd-loading"><i class="fa-solid fa-spinner fa-spin"></i> Cargando detalles…</p>';
        if (subEl) subEl.textContent = '…';
        if (typeof EcoModal !== 'undefined') EcoModal.open(modalId);
        fetch('get_cita_details_paciente.php?id=' + encodeURIComponent(citaId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    bodyEl.innerHTML = '<p style="color:#b91c1c;padding:12px;">' + esc(data.error) + '</p>';
                    return;
                }
                renderDetalle(data);
            })
            .catch(function () {
                bodyEl.innerHTML = '<p style="color:#b91c1c;padding:12px;">No se pudieron cargar los detalles.</p>';
            });
    };

    window.cerrarDetalleCitaPaciente = function () {
        if (typeof EcoModal !== 'undefined') EcoModal.close(modalId);
    };

    /* Cancelar una próxima cita (con confirmación en modal) */
    window.cancelarCitaPaciente = function (id) {
        var a = document.getElementById('cancelar-cita-confirm');
        if (a) a.href = 'cancelar_cita_paciente.php?cita_id=' + encodeURIComponent(id);
        if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-cancelar-cita');
    };

    /* Modal de confirmación al registrar la solicitud */
    var okModal = document.getElementById('eco-modal-cita-creada');
    if (okModal && typeof EcoModal !== 'undefined') {
        EcoModal.open('eco-modal-cita-creada');
        if (window.history && history.replaceState) {
            history.replaceState(null, '', window.location.pathname);
        }
    }

    /* Encuesta de satisfacción post-estudio */
    var encCitaId = 0, encPunt = 0;
    var encStars = document.querySelectorAll('#eco-modal-encuesta .enc-stars i');
    function encRender(n) {
        encStars.forEach(function (s) {
            var v = +s.getAttribute('data-v');
            s.className = (v <= n ? 'fa-solid' : 'fa-regular') + ' fa-star';
        });
    }
    encStars.forEach(function (s) {
        s.addEventListener('click', function () { encPunt = +s.getAttribute('data-v'); encRender(encPunt); });
    });
    window.abrirEncuesta = function (id) {
        encCitaId = id; encPunt = 0; encRender(0);
        var c = document.getElementById('enc-comentario'); if (c) c.value = '';
        var e = document.getElementById('enc-error'); if (e) e.textContent = '';
        if (typeof EcoModal !== 'undefined') EcoModal.open('eco-modal-encuesta');
    };
    var encBtn = document.getElementById('enc-submit');
    if (encBtn) encBtn.addEventListener('click', function () {
        var err = document.getElementById('enc-error');
        if (encPunt < 1) { if (err) err.textContent = 'Selecciona una puntuación.'; return; }
        encBtn.disabled = true;
        var fd = new FormData();
        fd.append('cita_id', encCitaId);
        fd.append('puntuacion', encPunt);
        fd.append('comentario', document.getElementById('enc-comentario').value);
        fetch('guardar_encuesta.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                encBtn.disabled = false;
                if (d.success) { location.reload(); }
                else if (err) { err.textContent = d.message || 'No se pudo enviar.'; }
            })
            .catch(function () { encBtn.disabled = false; if (err) err.textContent = 'Error de red.'; });
    });
})();
</script>
HTML;

include __DIR__ . '/layouts/shell.php';
