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

/* Solo se muestran al paciente los informes finalizados o firmados (no borradores). */
$informes = [];
if ($stmt = $conex->prepare("
    SELECT ie.id, ie.numero_informe, ie.fecha_estudio, ie.estado, ie.creado_en,
           t.nombre AS tipo_nombre, t.icono AS tipo_icono, t.categoria AS tipo_categoria,
           u.nombre_completo AS ecografista_nombre
    FROM informes_estudios ie
    LEFT JOIN tipos_ecografias t ON t.id = ie.tipo_ecografia_id
    LEFT JOIN usuarios u         ON u.id = ie.ecografista_id
    WHERE ie.paciente_id = ? AND ie.estado IN ('finalizado','firmado')
    ORDER BY ie.creado_en DESC
")) {
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $informes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$meses_abbr = [1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'];

$estados_meta = [
    'finalizado' => ['Finalizado', 'badge-success', '#22c55e'],
    'firmado'    => ['Firmado',    'badge-info',    '#0ea5e9'],
];

/* Estadísticas */
$total       = count($informes);
$num_firmado = 0;
$num_anio    = 0;
$anio_actual = (int)date('Y');
$ultimo_label = '—';

foreach ($informes as $i => $inf) {
    if ($inf['estado'] === 'firmado') $num_firmado++;
    $raw = $inf['fecha_estudio'] ?: substr($inf['creado_en'], 0, 10);
    $ts  = $raw ? strtotime($raw) : null;
    if ($ts && (int)date('Y', $ts) === $anio_actual) $num_anio++;
    if ($i === 0 && $ts) $ultimo_label = date('d/m/Y', $ts);
}

$page_title     = 'Mis Informes';
$page_subtitle  = 'Resultados de tus estudios ecográficos';
$active_section = 'mis-informes';

ob_start();
?>

<style>
.inf-toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; margin-bottom:16px; }
.inf-search { position:relative; flex:1; min-width:220px; max-width:360px; }
.inf-search i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:13px; pointer-events:none; }
.inf-search input {
    width:100%; padding:10px 12px 10px 36px; border:1.5px solid var(--border); border-radius:10px;
    font-family:inherit; font-size:13.5px; background:var(--bg-surface); color:var(--text-primary);
    box-sizing:border-box; transition:border-color .18s ease, box-shadow .18s ease;
}
.inf-search input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(2,177,244,.12); }

.inf-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.inf-tab {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 15px; border-radius:999px; font-size:12.5px; font-weight:600;
    color:var(--text-secondary); background:var(--bg-surface);
    border:1px solid var(--border); cursor:pointer; transition:all .18s ease; white-space:nowrap;
}
.inf-tab:hover { color:var(--text-primary); border-color:rgba(2,177,244,.35); }
.inf-tab.is-active { background:var(--accent); color:#fff; border-color:var(--accent); box-shadow:0 4px 12px rgba(2,177,244,.28); }
.inf-tab-count { font-size:11px; font-weight:700; padding:1px 7px; border-radius:999px; background:var(--bg-muted); color:var(--text-secondary); }
.inf-tab.is-active .inf-tab-count { background:rgba(255,255,255,.22); color:#fff; }

.inf-list { display:flex; flex-direction:column; gap:12px; }
.inf-card {
    display:flex; align-items:center; gap:18px;
    padding:16px 20px; background:var(--bg-surface);
    border:1px solid var(--border); border-left:3px solid var(--inf-color,#02b1f4);
    border-radius:var(--radius-lg);
    transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease;
}
.inf-card:hover { box-shadow:var(--shadow); transform:translateY(-2px); }

.inf-date {
    width:62px; flex-shrink:0; text-align:center;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:8px 4px; border-radius:12px;
    background:color-mix(in srgb, var(--inf-color) 12%, transparent); color:var(--inf-color);
}
.inf-date-day   { font-size:21px; font-weight:800; line-height:1; }
.inf-date-month { font-size:10.5px; font-weight:700; letter-spacing:.06em; margin-top:2px; }
.inf-date-year  { font-size:10.5px; font-weight:600; margin-top:3px; opacity:.85; }

.inf-main { flex:1; min-width:0; }
.inf-title { font-size:14.5px; font-weight:700; color:var(--text-primary); margin:0 0 4px; display:flex; align-items:center; gap:8px; }
.inf-meta { font-size:12.5px; color:var(--text-secondary); display:flex; flex-wrap:wrap; gap:4px 16px; }
.inf-meta span { display:inline-flex; align-items:center; gap:6px; }
.inf-meta i { color:var(--text-muted); width:13px; text-align:center; }

.inf-side { display:flex; align-items:center; gap:14px; flex-shrink:0; }
.inf-btn {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 16px; border-radius:9px; font-size:13px; font-weight:600;
    background:var(--accent-soft); color:var(--accent-text); border:1px solid rgba(2,177,244,.25);
    text-decoration:none; transition:all .2s ease; white-space:nowrap;
    cursor:pointer; font-family:inherit;
}
.inf-btn:hover { background:var(--accent); color:#fff; border-color:var(--accent); }

.inf-empty { text-align:center; padding:48px 24px; color:var(--text-muted); }
.inf-empty > i { font-size:42px; color:var(--accent); opacity:.5; margin-bottom:14px; display:block; }

@media (max-width:680px){
    .inf-card { flex-wrap:wrap; }
    .inf-side { width:100%; justify-content:space-between; }
}
</style>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon"><i class="fa-solid fa-file-medical"></i></div>
        <p class="stat-card-label">Informes disponibles</p>
        <p class="stat-card-value accent"><?= $total ?></p>
        <p class="stat-card-sub">estudios disponibles</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(14,165,233,.12);color:#0369a1;"><i class="fa-solid fa-file-signature"></i></div>
        <p class="stat-card-label">Firmados</p>
        <p class="stat-card-value" style="color:#0369a1;"><?= $num_firmado ?></p>
        <p class="stat-card-sub">validados por el ecografista</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(139,92,246,.12);color:#7c3aed;"><i class="fa-solid fa-calendar-day"></i></div>
        <p class="stat-card-label">Este año</p>
        <p class="stat-card-value" style="color:#7c3aed;"><?= $num_anio ?></p>
        <p class="stat-card-sub">estudios en <?= $anio_actual ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-calendar-check"></i></div>
        <p class="stat-card-label">Último estudio</p>
        <p class="stat-card-value" style="font-size:20px;"><?= htmlspecialchars($ultimo_label) ?></p>
        <p class="stat-card-sub">fecha más reciente</p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-folder-open" style="margin-right:8px;color:var(--accent);"></i> Historial de informes</h3>
    </div>

    <?php if ($total === 0): ?>
        <div class="inf-empty">
            <i class="fa-solid fa-file-circle-xmark"></i>
            <p style="margin:0 0 4px;font-weight:600;color:var(--text-secondary);">Aún no tienes informes disponibles</p>
            <p style="margin:0;font-size:13px;">Tus resultados aparecerán aquí cuando el ecografista finalice tu estudio.</p>
        </div>
    <?php else: ?>

        <div class="inf-toolbar">
            <div class="inf-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="inf-search-input" placeholder="Buscar por estudio, número o ecografista…" autocomplete="off">
            </div>
            <div class="inf-tabs">
                <button type="button" class="inf-tab is-active" data-filter="todos">Todos <span class="inf-tab-count"><?= $total ?></span></button>
                <button type="button" class="inf-tab" data-filter="firmado">Firmados <span class="inf-tab-count"><?= $num_firmado ?></span></button>
                <button type="button" class="inf-tab" data-filter="finalizado">Finalizados <span class="inf-tab-count"><?= ($total - $num_firmado) ?></span></button>
            </div>
        </div>

        <div class="inf-list" id="inf-list">
            <?php foreach ($informes as $inf):
                $meta  = $estados_meta[$inf['estado']] ?? ['Disponible', 'badge-accent', '#02b1f4'];
                [$etiqueta, $badge, $color] = $meta;
                $raw   = $inf['fecha_estudio'] ?: substr($inf['creado_en'], 0, 10);
                $ts    = $raw ? strtotime($raw) : null;
                $icono = $inf['tipo_icono'] ?: 'fa-solid fa-wave-square';
                $titulo = $inf['tipo_nombre'] ?: 'Ecografía';
                $busca = mb_strtolower(trim($titulo . ' ' . ($inf['numero_informe'] ?? '') . ' ' . ($inf['ecografista_nombre'] ?? '')));
            ?>
                <div class="inf-card" data-estado="<?= htmlspecialchars($inf['estado']) ?>" data-search="<?= htmlspecialchars($busca, ENT_QUOTES) ?>" style="--inf-color:<?= htmlspecialchars($color) ?>;">
                    <?php if ($ts): ?>
                        <div class="inf-date">
                            <span class="inf-date-day"><?= date('d', $ts) ?></span>
                            <span class="inf-date-month"><?= $meses_abbr[(int)date('n', $ts)] ?></span>
                            <span class="inf-date-year"><?= date('Y', $ts) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="inf-date"><span class="inf-date-day"><i class="fa-solid fa-file"></i></span></div>
                    <?php endif; ?>

                    <div class="inf-main">
                        <p class="inf-title"><i class="<?= htmlspecialchars($icono, ENT_QUOTES) ?>" style="color:<?= htmlspecialchars($color) ?>;"></i><?= htmlspecialchars($titulo) ?></p>
                        <div class="inf-meta">
                            <span><i class="fa-solid fa-hashtag"></i><?= htmlspecialchars($inf['numero_informe'] ?: '—') ?></span>
                            <?php if (!empty($inf['tipo_categoria'])): ?>
                                <span><i class="fa-solid fa-layer-group"></i><?= htmlspecialchars($inf['tipo_categoria']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($inf['ecografista_nombre'])): ?>
                                <span><i class="fa-solid fa-user-doctor"></i><?= htmlspecialchars($inf['ecografista_nombre']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="inf-side">
                        <span class="badge <?= $badge ?>"><?= htmlspecialchars($etiqueta) ?></span>
                        <button type="button" class="inf-btn" data-informe-id="<?= (int)$inf['id'] ?>" data-informe-titulo="<?= htmlspecialchars($titulo, ENT_QUOTES) ?>">
                            <i class="fa-solid fa-eye"></i> Ver informe
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <div id="inf-empty-filter" class="inf-empty" style="display:none;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <p style="margin:0;font-weight:600;color:var(--text-secondary);">No se encontraron informes</p>
                <p style="margin:0;font-size:13px;">Prueba con otro término de búsqueda o filtro.</p>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Misma modal "Ver informe" del rol ecografista (lectura): reutiliza id/clases → mismo CSS de shell-modals.css -->
<div id="eco-modal-informe-detalle-eco" class="eco-modal eco-modal-panel-ecografista" aria-hidden="true" role="dialog" aria-labelledby="eco-inf-det-titulo">
    <div class="modal-content-form-eco">
        <div class="modal-form-eco-header">
            <div class="eco-modal-tipo-icon" id="eco-inf-det-icon">
                <i class="fa-solid fa-file-waveform"></i>
            </div>
            <div class="eco-header-tipo-info">
                <h2 id="eco-inf-det-titulo">Informe de Estudio</h2>
                <p id="eco-inf-det-paciente">—</p>
            </div>
            <div class="eco-modal-informe-detalle-actions">
                <button type="button" class="eco-btn-cancel" id="eco-inf-det-print" title="Imprimir informe">
                    <i class="fa-solid fa-print"></i> Imprimir
                </button>
                <button type="button" class="modal-close-btn" data-eco-modal-close aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="modal-form-eco-body" id="eco-informe-detalle-body">
            <div class="modal-form-eco-loader">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Cargando informe…</p>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    var tabs   = document.querySelectorAll('.inf-tab');
    var cards  = Array.prototype.slice.call(document.querySelectorAll('.inf-card'));
    var search = document.getElementById('inf-search-input');
    var empty  = document.getElementById('inf-empty-filter');
    if (!cards.length) return;

    var filtroEstado = 'todos';

    function aplicar() {
        var q = (search && search.value || '').trim().toLowerCase();
        var visibles = 0;
        cards.forEach(function (c) {
            var okEstado = (filtroEstado === 'todos' || c.getAttribute('data-estado') === filtroEstado);
            var okBusca  = (!q || (c.getAttribute('data-search') || '').indexOf(q) !== -1);
            var show = okEstado && okBusca;
            c.style.display = show ? '' : 'none';
            if (show) visibles++;
        });
        if (empty) empty.style.display = (visibles === 0) ? '' : 'none';
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            filtroEstado = tab.getAttribute('data-filter');
            aplicar();
        });
    });

    if (search) search.addEventListener('input', aplicar);
})();
</script>
<script>
(function () {
    var modal = document.getElementById('eco-modal-informe-detalle-eco');
    if (!modal || !window.EcoModal) return;
    var iconEl   = document.getElementById('eco-inf-det-icon');
    var tituloEl = document.getElementById('eco-inf-det-titulo');
    var pacEl    = document.getElementById('eco-inf-det-paciente');
    var bodyEl   = document.getElementById('eco-informe-detalle-body');
    var printBtn = document.getElementById('eco-inf-det-print');
    var currentId = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(data) {
        if (data.error) {
            bodyEl.innerHTML = '<p style="color:#c0392b;padding:20px;">' + esc(data.error) + '</p>';
            iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            return;
        }
        var inf = data.informe || {}, tipo = data.tipo || {}, pac = data.paciente || {};
        iconEl.innerHTML = '<i class="' + esc(tipo.icono || 'fa-solid fa-wave-square') + '"></i>';
        tituloEl.textContent = tipo.nombre || 'Informe de estudio';
        var edad = pac.edad ? (String(pac.edad).trim() + ' años') : '';
        pacEl.textContent = 'Paciente: ' + (pac.nombre || '—') + '  ·  CI: ' + (pac.cedula || '—') + '  ·  ' + (edad || '—');

        var estado = inf.estado || '';
        var colors = { finalizado: ['#166534', '#dcfce7'], firmado: ['#075985', '#e0f2fe'] };
        var ec = colors[estado] || ['#374151', '#f3f4f6'];
        var badge = '<span style="background:' + ec[1] + ';color:' + ec[0] + ';padding:2px 10px;border-radius:12px;font-weight:600;font-size:11px;">' +
            esc(inf.estado_label || estado) + '</span>';

        var meta = '<div class="inf-det-meta">' +
            '<span><i class="fa-solid fa-hashtag"></i> <strong>' + esc(inf.numero_informe || '-') + '</strong></span>' +
            '<span><i class="fa-regular fa-calendar"></i> <strong>' + esc(inf.fecha_formateada || '-') + '</strong></span>' +
            '<span><i class="fa-solid fa-user-doctor"></i> <strong>' + esc(data.ecografista || '-') + '</strong></span>' +
            '<span>' + badge + '</span></div>';

        var firma = '';
        if (inf.firma) {
            firma = '<div style="margin:8px 0 4px;padding:8px 12px;border-radius:8px;background:#e0f2fe;color:#075985;font-size:12.5px;">' +
                '<i class="fa-solid fa-signature"></i> Firmado por <strong>' + esc(inf.firma.por) + '</strong>' +
                (inf.firma.fecha ? ' · ' + esc(inf.firma.fecha) : '') + '</div>';
        }
        bodyEl.innerHTML = meta + firma + (data.html || '');
    }

    // Imprimir sin salir del modal: carga la versión imprimible en un iframe oculto que auto-llama window.print().
    if (printBtn) printBtn.addEventListener('click', function () {
        if (!currentId) return;
        var prev = document.getElementById('inf-print-frame');
        if (prev) prev.remove();
        var iframe = document.createElement('iframe');
        iframe.id = 'inf-print-frame';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:8.5in;height:11in;border:0;visibility:hidden;';
        iframe.src = 'ver_informe_estudio.php?informe_id=' + encodeURIComponent(currentId) + '&print=1';
        document.body.appendChild(iframe);
        setTimeout(function () { try { iframe.remove(); } catch (e) {} }, 60000);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.inf-btn[data-informe-id]');
        if (!btn) return;
        e.preventDefault();
        var id = btn.getAttribute('data-informe-id');
        currentId = id;
        iconEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        tituloEl.textContent = 'Cargando…';
        pacEl.textContent = '';
        bodyEl.innerHTML = '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando informe…</p></div>';
        EcoModal.open('eco-modal-informe-detalle-eco');
        fetch('get_informe_detalle.php?informe_id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(render)
            .catch(function (err) {
                bodyEl.innerHTML = '<p style="color:#c0392b;padding:20px;">Error al cargar: ' +
                    esc(err && err.message ? err.message : 'Error de red.') + '</p>';
                iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            });
    });
})();
</script>
HTML;

include __DIR__ . '/layouts/shell.php';
