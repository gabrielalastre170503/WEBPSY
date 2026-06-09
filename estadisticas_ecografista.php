<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['rol'] !== 'ecografista') {
    header('Location: dashboard_v2.php');
    exit;
}

$uid = (int)$_SESSION['usuario_id'];

/* ── KPIs ── */
function kpi(mysqli $conex, int $uid, string $where): int
{
    $sql = "SELECT COUNT(*) c FROM citas WHERE ecografista_id = ? $where";
    if ($s = $conex->prepare($sql)) {
        $s->bind_param('i', $uid); $s->execute();
        $n = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
        return $n;
    }
    return 0;
}
$total_citas  = kpi($conex, $uid, "");
$completadas  = kpi($conex, $uid, "AND estado = 'completada'");
$este_mes     = kpi($conex, $uid, "AND estado IN ('confirmada','completada','reprogramada') AND YEAR(fecha_cita)=YEAR(CURDATE()) AND MONTH(fecha_cita)=MONTH(CURDATE())");

$pacientes = 0;
if ($s = $conex->prepare("SELECT COUNT(DISTINCT paciente_id) c FROM citas WHERE ecografista_id = ?")) {
    $s->bind_param('i', $uid); $s->execute();
    $pacientes = (int)$s->get_result()->fetch_assoc()['c']; $s->close();
}

/* ── Rango compartido (meses) ── */
$meses_es = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
$desde = date('Y-m-01', strtotime('-5 months'));

/* ── Por día de la semana ── */
$dow_raw = array_fill(1, 7, 0); // DAYOFWEEK: 1=Dom .. 7=Sáb
if ($s = $conex->prepare("SELECT DAYOFWEEK(fecha_cita) d, COUNT(*) t FROM citas
        WHERE ecografista_id=? AND estado IN ('confirmada','completada','reprogramada') GROUP BY d")) {
    $s->bind_param('i', $uid); $s->execute();
    $r = $s->get_result();
    while ($f = $r->fetch_assoc()) { $dow_raw[(int)$f['d']] = (int)$f['t']; }
    $s->close();
}
$dow_data = [$dow_raw[2],$dow_raw[3],$dow_raw[4],$dow_raw[5],$dow_raw[6],$dow_raw[7],$dow_raw[1]];

/* ── 3) Distribución por estado ── */
$estado_lbl = ['confirmada'=>'Confirmadas','completada'=>'Completadas','pendiente'=>'Pendientes','pendiente_paciente'=>'Pospuestas','reprogramada'=>'Reprogramadas','cancelada'=>'Canceladas','rechazada'=>'Rechazadas'];
$estado_col = ['confirmada'=>'#22c55e','completada'=>'#0ea5e9','pendiente'=>'#f59e0b','pendiente_paciente'=>'#f59e0b','reprogramada'=>'#8b5cf6','cancelada'=>'#ef4444','rechazada'=>'#ef4444'];
$estL = []; $estD = []; $estC = [];
if ($s = $conex->prepare("SELECT estado, COUNT(*) t FROM citas WHERE ecografista_id=? GROUP BY estado ORDER BY t DESC")) {
    $s->bind_param('i', $uid); $s->execute();
    $r = $s->get_result();
    while ($f = $r->fetch_assoc()) {
        $e = $f['estado'];
        $estL[] = $estado_lbl[$e] ?? ucfirst((string)$e);
        $estD[] = (int)$f['t'];
        $estC[] = $estado_col[$e] ?? '#94a3b8';
    }
    $s->close();
}

/* ── 4) Top tipos de estudio ── */
$tipL = []; $tipD = [];
if ($s = $conex->prepare("SELECT t.nombre, COUNT(*) c FROM citas ci
        JOIN tipos_ecografias t ON t.id = ci.tipo_ecografia_id
        WHERE ci.ecografista_id=? GROUP BY t.id, t.nombre ORDER BY c DESC LIMIT 8")) {
    $s->bind_param('i', $uid); $s->execute();
    $r = $s->get_result();
    while ($f = $r->fetch_assoc()) { $tipL[] = $f['nombre']; $tipD[] = (int)$f['c']; }
    $s->close();
}

/* ── Citas por hora (7:00–19:00) ── */
$hmin = 7; $hmax = 19; $hmap = [];
for ($h = $hmin; $h <= $hmax; $h++) $hmap[$h] = 0;
if ($s = $conex->prepare("SELECT HOUR(fecha_cita) h, COUNT(*) t FROM citas
        WHERE ecografista_id=? AND estado IN ('confirmada','completada','reprogramada') GROUP BY h")) {
    $s->bind_param('i', $uid); $s->execute();
    $r = $s->get_result();
    while ($f = $r->fetch_assoc()) { $h = (int)$f['h']; if (isset($hmap[$h])) $hmap[$h] = (int)$f['t']; }
    $s->close();
}
$horaLabels = array_map(fn($h) => sprintf('%02d:00', $h), array_keys($hmap));
$horaData = array_values($hmap);

/* ── Pacientes nuevos por mes (6 meses) ── */
$labelsPM = []; $mapPM = [];
for ($i = 5; $i >= 0; $i--) {
    $d = new DateTime("first day of -$i month");
    $labelsPM[] = $meses_es[(int)$d->format('n')] . ' ' . $d->format('y');
    $mapPM[$d->format('Y-m')] = 0;
}
if ($s = $conex->prepare("SELECT DATE_FORMAT(fecha_registro,'%Y-%m') m, COUNT(*) t FROM usuarios
        WHERE creado_por_id=? AND fecha_registro >= ? GROUP BY m")) {
    $s->bind_param('is', $uid, $desde); $s->execute();
    $r = $s->get_result();
    while ($f = $r->fetch_assoc()) { if (isset($mapPM[$f['m']])) $mapPM[$f['m']] = (int)$f['t']; }
    $s->close();
}
$dataPM = array_values($mapPM);

/* ── Pacientes por edad ── */
$edad_buckets = ['0-17'=>0,'18-29'=>0,'30-44'=>0,'45-59'=>0,'60+'=>0,'Sin dato'=>0];
if ($s = $conex->prepare("SELECT TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) age
        FROM usuarios u WHERE u.id IN (SELECT DISTINCT paciente_id FROM citas WHERE ecografista_id=?)")) {
    $s->bind_param('i', $uid); $s->execute();
    $r = $s->get_result();
    while ($f = $r->fetch_assoc()) {
        $a = $f['age'];
        if ($a === null || $a === '') { $edad_buckets['Sin dato']++; continue; }
        $a = (int)$a;
        if ($a < 18)      $edad_buckets['0-17']++;
        elseif ($a < 30)  $edad_buckets['18-29']++;
        elseif ($a < 45)  $edad_buckets['30-44']++;
        elseif ($a < 60)  $edad_buckets['45-59']++;
        else              $edad_buckets['60+']++;
    }
    $s->close();
}
$edadLabels = array_keys($edad_buckets);
$edadData   = array_values($edad_buckets);

$page_title     = 'Gráficos y Estadísticas';
$page_subtitle  = 'Resumen visual de tu actividad clínica';
$active_section = 'estadisticas';

ob_start();
?>

<style>
.est-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); gap:18px; }
.est-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; }
.est-card h3 { margin:0 0 4px; font-size:14.5px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
.est-card h3 i { color:var(--accent); }
.est-card p.sub { margin:0 0 14px; font-size:12px; color:var(--text-muted); }
.est-canvas-wrap { position:relative; height:260px; }
.est-empty { text-align:center; color:var(--text-muted); padding:40px 12px; font-size:13px; }
.est-empty i { font-size:34px; opacity:.4; display:block; margin-bottom:10px; color:var(--accent); }
</style>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <p class="stat-card-label">Citas totales</p>
        <p class="stat-card-value accent"><?= $total_citas ?></p>
        <p class="stat-card-sub">históricas</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(14,165,233,.12);color:#0369a1;"><i class="fa-solid fa-clipboard-check"></i></div>
        <p class="stat-card-label">Completadas</p>
        <p class="stat-card-value" style="color:#0369a1;"><?= $completadas ?></p>
        <p class="stat-card-sub">estudios realizados</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(139,92,246,.12);color:#7c3aed;"><i class="fa-solid fa-calendar-week"></i></div>
        <p class="stat-card-label">Este mes</p>
        <p class="stat-card-value" style="color:#7c3aed;"><?= $este_mes ?></p>
        <p class="stat-card-sub">citas programadas</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-user-injured"></i></div>
        <p class="stat-card-label">Pacientes</p>
        <p class="stat-card-value success"><?= $pacientes ?></p>
        <p class="stat-card-sub">atendidos distintos</p>
    </div>
</div>

<div class="est-grid">
    <div class="est-card">
        <h3><i class="fa-solid fa-user-plus"></i> Pacientes nuevos</h3>
        <p class="sub">Registrados por ti · últimos 6 meses</p>
        <div class="est-canvas-wrap"><canvas id="chPac"></canvas></div>
    </div>

    <div class="est-card">
        <h3><i class="fa-solid fa-chart-simple"></i> Actividad por día</h3>
        <p class="sub">Distribución por día de la semana</p>
        <div class="est-canvas-wrap"><canvas id="chDias"></canvas></div>
    </div>

    <div class="est-card">
        <h3><i class="fa-solid fa-chart-pie"></i> Estado de las citas</h3>
        <p class="sub">Proporción por estado</p>
        <?php if (array_sum($estD) > 0): ?>
            <div class="est-canvas-wrap"><canvas id="chEstado"></canvas></div>
        <?php else: ?>
            <div class="est-empty"><i class="fa-solid fa-chart-pie"></i> Aún no hay citas para graficar.</div>
        <?php endif; ?>
    </div>

    <div class="est-card">
        <h3><i class="fa-solid fa-wave-square"></i> Estudios más frecuentes</h3>
        <p class="sub">Top tipos de ecografía</p>
        <?php if (array_sum($tipD) > 0): ?>
            <div class="est-canvas-wrap"><canvas id="chTipos"></canvas></div>
        <?php else: ?>
            <div class="est-empty"><i class="fa-solid fa-wave-square"></i> Aún no hay estudios registrados.</div>
        <?php endif; ?>
    </div>

    <div class="est-card">
        <h3><i class="fa-solid fa-clock"></i> Citas por hora</h3>
        <p class="sub">Franjas horarias más activas</p>
        <div class="est-canvas-wrap"><canvas id="chHora"></canvas></div>
    </div>

    <div class="est-card">
        <h3><i class="fa-solid fa-cake-candles"></i> Pacientes por edad</h3>
        <p class="sub">Distribución por grupo de edad</p>
        <div class="est-canvas-wrap"><canvas id="chEdad"></canvas></div>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$JS = [
    'diasData' => $dow_data,
    'estL' => $estL, 'estD' => $estD, 'estC' => $estC,
    'tipL' => $tipL, 'tipD' => $tipD,
    'horaLabels' => $horaLabels, 'horaData' => $horaData,
    'labelsPM' => $labelsPM, 'dataPM' => $dataPM,
    'edadLabels' => $edadLabels, 'edadData' => $edadData,
];
$json = json_encode($JS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$page_scripts_extra = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    var D = {$json};
    if (typeof Chart === 'undefined') return;

    var css = getComputedStyle(document.documentElement);
    var txt = (css.getPropertyValue('--text-secondary') || '#64748b').trim();
    var grid = 'rgba(148,163,184,.18)';
    Chart.defaults.color = txt;
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";

    var accent = (css.getPropertyValue('--accent') || '#02b1f4').trim();

    function mk(id, cfg) { var el = document.getElementById(id); if (el) new Chart(el, cfg); }

    mk('chDias', {
        type: 'bar',
        data: { labels: ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'], datasets: [{ label: 'Citas', data: D.diasData, backgroundColor: '#8b5cf6', borderRadius: 6, maxBarThickness: 34 }] },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } }, x: { grid: { display: false } } } }
    });

    mk('chEstado', {
        type: 'doughnut',
        data: { labels: D.estL, datasets: [{ data: D.estD, backgroundColor: D.estC, borderWidth: 2, borderColor: (css.getPropertyValue('--bg-surface') || '#fff').trim() }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
            plugins: { legend: { position: 'bottom', labels: { padding: 14, usePointStyle: true, boxWidth: 8 } } } }
    });

    mk('chTipos', {
        type: 'bar',
        data: { labels: D.tipL, datasets: [{ label: 'Estudios', data: D.tipD, backgroundColor: '#0ea5e9', borderRadius: 6 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } }, y: { grid: { display: false } } } }
    });

    function hexA(h, a) {
        h = h.replace('#', '');
        var r = parseInt(h.substr(0,2),16), g = parseInt(h.substr(2,2),16), b = parseInt(h.substr(4,2),16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
    }

    mk('chHora', {
        type: 'bar',
        data: { labels: D.horaLabels, datasets: [{ label: 'Citas', data: D.horaData, backgroundColor: '#14b8a6', borderRadius: 5, maxBarThickness: 26 }] },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 13 } } } }
    });

    mk('chPac', {
        type: 'line',
        data: { labels: D.labelsPM, datasets: [{ label: 'Pacientes', data: D.dataPM, borderColor: '#22c55e', backgroundColor: hexA('#22c55e', .12), fill: true, tension: .35, pointRadius: 3, borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } }, x: { grid: { display: false } } } }
    });

    mk('chEdad', {
        type: 'bar',
        data: { labels: D.edadLabels, datasets: [{ label: 'Pacientes', data: D.edadData, backgroundColor: '#6366f1', borderRadius: 6, maxBarThickness: 40 }] },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } }, x: { grid: { display: false } } } }
    });
})();
</script>
HTML;

include __DIR__ . '/layouts/shell.php';
