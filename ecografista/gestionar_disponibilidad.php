<?php
session_start();
include __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    header('Location: ' . eco_url('login'));
    exit;
}

$ecografista_id = (int)$_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'] ?? $_SESSION['nombre_usuario'] ?? '';

$horario_recurrente = [];
$stmt = $conex->prepare('SELECT dia_semana, hora_inicio, hora_fin FROM horarios_recurrentes WHERE ecografista_id = ?');
$stmt->bind_param('i', $ecografista_id);
$stmt->execute();
$resultado = $stmt->get_result();
while ($fila = $resultado->fetch_assoc()) {
    $horario_recurrente[(int)$fila['dia_semana']] = [
        'inicio' => date('H:i', strtotime($fila['hora_inicio'])),
        'fin'    => date('H:i', strtotime($fila['hora_fin'])),
    ];
}
$stmt->close();

$dias_activos = count($horario_recurrente);
$horas_semanales = 0.0;
foreach ($horario_recurrente as $h) {
    $ini = strtotime($h['inicio']);
    $fin = strtotime($h['fin']);
    if ($fin > $ini) {
        $horas_semanales += ($fin - $ini) / 3600;
    }
}
$horas_semanales = round($horas_semanales, 1);

// Fechas bloqueadas (excepciones) para pintarlas en el calendario
$blocked_dates = [];
if ($s = $conex->prepare("SELECT fecha FROM disponibilidad_excepciones WHERE ecografista_id = ? AND tipo = 'no_disponible'")) {
    $s->bind_param('i', $ecografista_id);
    $s->execute();
    $rs = $s->get_result();
    while ($row = $rs->fetch_assoc()) {
        $blocked_dates[] = date('Y-m-d', strtotime($row['fecha']));
    }
    $s->close();
}
$excepciones_count = count($blocked_dates);

// Días laborables en numeración JS (0=Domingo … 6=Sábado) para marcar los círculos
$working_js_days = [];
foreach (array_keys($horario_recurrente) as $d) {
    $working_js_days[] = ((int)$d === 7) ? 0 : (int)$d;
}

$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$dias = [
    1 => ['nombre' => 'Lunes',     'inicial' => 'L'],
    2 => ['nombre' => 'Martes',    'inicial' => 'M'],
    3 => ['nombre' => 'Miércoles', 'inicial' => 'X'],
    4 => ['nombre' => 'Jueves',    'inicial' => 'J'],
    5 => ['nombre' => 'Viernes',   'inicial' => 'V'],
    6 => ['nombre' => 'Sábado',    'inicial' => 'S'],
    7 => ['nombre' => 'Domingo',   'inicial' => 'D'],
];

$browser_title   = 'Mi Disponibilidad';
$page_title      = '';
$page_subtitle   = '';
$active_section  = 'disponibilidad';
$body_class      = 'disp-page';
// Cache-busting con filemtime: el navegador refresca el CSS al cambiar el archivo.
$disp_css_ver = @filemtime(__DIR__ . '/../assets/css/agenda/gestionar-disponibilidad.css') ?: time();
$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">'
    . '<link rel="stylesheet" href="assets/css/agenda/gestionar-disponibilidad.css?v=' . $disp_css_ver . '">';
$page_header_actions = '';

ob_start();
?>

<?php if ($status === 'ok'): ?>
    <div class="disp-feedback disp-feedback--ok" role="status">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>Disponibilidad actualizada correctamente.</span>
    </div>
<?php endif; ?>

<div class="stats-grid disp-stats">
    <div class="stat-card disp-stat-enhanced">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <p class="stat-card-label">Días activos</p>
        <p class="stat-card-value accent" id="stat-dias-activos"><?= $dias_activos ?></p>
        <p class="stat-card-sub">de 7 días de la semana</p>
        <div class="disp-stat-bar"><span class="disp-stat-bar__fill disp-stat-bar__fill--accent" id="stat-bar-dias" style="width:<?= ($dias_activos / 7) * 100 ?>%"></span></div>
    </div>
    <div class="stat-card disp-stat-enhanced">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;">
            <i class="fa-solid fa-business-time"></i>
        </div>
        <p class="stat-card-label">Horas semanales</p>
        <p class="stat-card-value success"><span id="stat-horas-semanales"><?= number_format($horas_semanales, 1) ?></span> h</p>
        <p class="stat-card-sub">según horario configurado</p>
        <div class="disp-stat-bar"><span class="disp-stat-bar__fill disp-stat-bar__fill--success" id="stat-bar-horas" style="width:<?= min(100, ($horas_semanales / 56) * 100) ?>%"></span></div>
    </div>
    <div class="stat-card disp-stat-enhanced">
        <div class="stat-card-icon" style="background:rgba(239,68,68,.1);color:#dc2626;">
            <i class="fa-solid fa-calendar-xmark"></i>
        </div>
        <p class="stat-card-label">Días bloqueados</p>
        <p class="stat-card-value danger"><?= $excepciones_count ?></p>
        <p class="stat-card-sub">excepciones en calendario</p>
        <div class="disp-stat-bar"><span class="disp-stat-bar__fill disp-stat-bar__fill--danger" style="width:<?= min(100, ($excepciones_count / 30) * 100) ?>%"></span></div>
    </div>
</div>

<div class="disp-layout">
    <section class="disp-panel disp-schedule-card" aria-labelledby="disp-schedule-title">
        <header class="disp-panel__head">
            <div class="disp-panel__title">
                <span class="disp-panel__icon" aria-hidden="true"><i class="fa-solid fa-repeat"></i></span>
                <div>
                    <h3 id="disp-schedule-title">Horario semanal fijo</h3>
                    <p class="disp-panel__subtitle">Activa los días que trabajas y define tu jornada.</p>
                </div>
            </div>
            <span class="disp-badge">Recurrente</span>
        </header>

        <div class="disp-panel__body">

            <div class="disp-quick-bar" role="group" aria-label="Plantillas rápidas">
                <span class="disp-quick-bar__label"><i class="fa-solid fa-wand-magic-sparkles"></i> Plantillas:</span>
                <button type="button" class="disp-quick-btn" data-template="lv-9-17"><i class="fa-solid fa-briefcase"></i> L-V 9 a 17</button>
                <button type="button" class="disp-quick-btn" data-template="ls-8-14"><i class="fa-solid fa-sun"></i> L-S 8 a 14</button>
                <button type="button" class="disp-quick-btn" data-template="todos-9-13"><i class="fa-solid fa-calendar-week"></i> Todos 9 a 13</button>
                <button type="button" class="disp-quick-btn disp-quick-btn--ghost" data-template="limpiar"><i class="fa-solid fa-eraser"></i> Limpiar</button>
            </div>

            <form action="guardar_disponibilidad.php" method="POST" class="disp-schedule-form" id="form-horario-recurrente">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="guardar_recurrente">

                <div class="disp-days">
                    <?php foreach ($dias as $num => $info):
                        $nombre = $info['nombre'];
                        $inicial = $info['inicial'];
                        $activo = isset($horario_recurrente[$num]);
                        $inicio = $horario_recurrente[$num]['inicio'] ?? '09:00';
                        $fin    = $horario_recurrente[$num]['fin'] ?? '17:00';
                        $horas_dia = 0;
                        if ($activo) {
                            $ini = strtotime($inicio);
                            $f   = strtotime($fin);
                            if ($f > $ini) $horas_dia = round(($f - $ini) / 3600, 1);
                        }
                    ?>
                    <div class="disp-day-row<?= $activo ? ' is-active' : '' ?>" data-day="<?= (int)$num ?>">
                        <div class="disp-day-row__left">
                            <label class="disp-switch" title="Activar <?= htmlspecialchars($nombre) ?>">
                                <input type="checkbox"
                                       name="dias[<?= (int)$num ?>][activo]"
                                       id="dia_<?= (int)$num ?>"
                                       class="disp-day-toggle"
                                       <?= $activo ? 'checked' : '' ?>>
                                <span class="disp-switch-slider" aria-hidden="true"></span>
                            </label>
                            <span class="disp-day-circle" aria-hidden="true"><?= $inicial ?></span>
                            <label for="dia_<?= (int)$num ?>" class="disp-day-name">
                                <?= htmlspecialchars($nombre) ?>
                                <span class="disp-day-hours" data-hours-label><?= $activo ? number_format($horas_dia, 1) . ' h' : '—' ?></span>
                            </label>
                        </div>

                        <div class="contenedor-rango-horas" aria-label="Horario de <?= htmlspecialchars($nombre) ?>">
                            <span class="disp-hora-connector disp-hora-connector--de">De</span>
                            <span class="disp-time-slot">
                                <input type="time"
                                       name="dias[<?= (int)$num ?>][inicio]"
                                       value="<?= htmlspecialchars($inicio) ?>"
                                       class="input-hora-premium"
                                       <?= $activo ? '' : 'disabled' ?>>
                            </span>
                            <span class="disp-hora-connector disp-hora-connector--a">a</span>
                            <span class="disp-time-slot">
                                <input type="time"
                                       name="dias[<?= (int)$num ?>][fin]"
                                       value="<?= htmlspecialchars($fin) ?>"
                                       class="input-hora-premium"
                                       <?= $activo ? '' : 'disabled' ?>>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="disp-panel__footer">
                    <button type="submit" class="btn-primary disp-btn-save">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                        Guardar horario semanal
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="disp-panel disp-calendar-card" aria-labelledby="disp-calendar-title">
        <header class="disp-panel__head">
            <div class="disp-panel__title">
                <span class="disp-panel__icon disp-panel__icon--calendar" aria-hidden="true"><i class="fa-solid fa-calendar-day"></i></span>
                <div>
                    <h3 id="disp-calendar-title">Excepciones y días libres</h3>
                    <p class="disp-panel__subtitle">Click en un día para bloquearlo. Click sobre uno bloqueado para reactivarlo.</p>
                </div>
            </div>
            <span class="disp-badge disp-badge--muted">Calendario</span>
        </header>

        <div class="disp-panel__body">
            <div class="disp-calendar-frame">
                <div id="disp-calendar" class="disp-calendar-wrap"
                     data-working-days="<?= htmlspecialchars(implode(',', $working_js_days), ENT_QUOTES) ?>"
                     data-blocked-dates="<?= htmlspecialchars(implode(',', $blocked_dates), ENT_QUOTES) ?>"></div>
            </div>

            <div class="disp-legend" role="list" aria-label="Leyenda del calendario">
                <span class="disp-legend__item" role="listitem">
                    <span class="disp-legend__dot disp-legend__dot--work"><i class="fa-solid fa-check"></i></span>
                    Día disponible
                </span>
                <span class="disp-legend__item" role="listitem">
                    <span class="disp-legend__dot disp-legend__dot--off"><i class="fa-solid fa-xmark"></i></span>
                    Día no disponible
                </span>
                <span class="disp-legend__item" role="listitem">
                    <span class="disp-legend__dot disp-legend__dot--today"><i class="fa-solid fa-circle"></i></span>
                    Hoy
                </span>
            </div>
        </div>
    </section>
</div>

<section class="disp-tips" aria-label="Consejos rápidos">
    <div class="disp-tips__head">
        <span class="disp-tips__icon"><i class="fa-solid fa-lightbulb"></i></span>
        <h4>Consejos para gestionar tu disponibilidad</h4>
    </div>
    <div class="disp-tips__grid">
        <div class="disp-tip">
            <span class="disp-tip__icon"><i class="fa-solid fa-bolt"></i></span>
            <p><strong>Plantillas rápidas:</strong> aplica un horario típico con un solo click y ajusta lo que necesites.</p>
        </div>
        <div class="disp-tip">
            <span class="disp-tip__icon"><i class="fa-solid fa-toggle-on"></i></span>
            <p><strong>Activa/desactiva días:</strong> usa el switch para incluir o excluir un día sin perder la hora configurada.</p>
        </div>
        <div class="disp-tip">
            <span class="disp-tip__icon"><i class="fa-solid fa-calendar-xmark"></i></span>
            <p><strong>Días puntuales:</strong> haz click en una fecha del calendario para marcarla como no disponible (vacaciones, eventos).</p>
        </div>
        <div class="disp-tip">
            <span class="disp-tip__icon"><i class="fa-solid fa-floppy-disk"></i></span>
            <p><strong>Guarda los cambios:</strong> recuerda pulsar "Guardar horario semanal" tras cualquier modificación.</p>
        </div>
    </div>
</section>

<?php
$page_content = ob_get_clean();
$disp_js_ver = @filemtime(__DIR__ . '/../assets/js/agenda/gestionar-disponibilidad.js') ?: time();
$page_scripts_extra = '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>'
    . '<script src="assets/js/agenda/gestionar-disponibilidad.js?v=' . $disp_js_ver . '"></script>';
include __DIR__ . '/../layouts/shell.php';
