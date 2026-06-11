<?php
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/facturacion/facturacion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$rol = $_SESSION['rol'] ?? '';
$uid = (int)$_SESSION['usuario_id'];
if (!in_array($rol, ['recepcionista', 'administrador', 'ecografista'], true)) {
    header('Location: dashboard_v2.php');
    exit;
}

$es_eco = ($rol === 'ecografista');

// Recepcion/Admin ven todas las citas facturables; el ecografista solo las suyas.
$sql = "
    SELECT c.id, c.fecha_cita, c.estado, c.monto_total, c.monto_pagado, c.estado_pago, c.metodo_pago,
           c.motivo_principal AS servicios,
           u.nombre_completo AS paciente, u.cedula,
           t.nombre AS estudio, t.precio AS precio_estudio
    FROM citas c
    JOIN usuarios u ON u.id = c.paciente_id
    LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
    WHERE (c.tipo_ecografia_id IS NOT NULL OR c.monto_total IS NOT NULL)
";
if ($es_eco) {
    $sql .= " AND c.ecografista_id = ? ";
}
$sql .= " ORDER BY (c.estado_pago = 'pagado'), c.fecha_cita DESC, c.id DESC";

$citas = [];
if ($es_eco) {
    $stmt = $conex->prepare($sql);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $citas[] = $row;
    }
    $stmt->close();
} elseif ($q = $conex->query($sql)) {
    while ($row = $q->fetch_assoc()) {
        $citas[] = $row;
    }
    $q->free();
}

$tot_facturado = 0.0;
$tot_cobrado   = 0.0;
$tot_porcobrar = 0.0;
foreach ($citas as $c) {
    $mt = (float)($c['monto_total'] ?? 0);
    $mp = (float)$c['monto_pagado'];
    $tot_facturado += $mt;
    $tot_cobrado   += $mp;
    if ($c['estado_pago'] !== 'exonerado') {
        $tot_porcobrar += max($mt - $mp, 0);
    }
}

$page_title     = 'Facturación';
$page_subtitle  = $es_eco ? 'Cobros y estado de pago de tus citas' : 'Cobros y estado de pago de las citas';
$active_section = 'facturacion';

ob_start();
?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px;">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <p class="stat-card-label">Total facturado</p>
        <p class="stat-card-value accent"><?= eco_money($tot_facturado) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <p class="stat-card-label">Cobrado</p>
        <p class="stat-card-value success"><?= eco_money($tot_cobrado) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;"><i class="fa-solid fa-clock"></i></div>
        <p class="stat-card-label">Por cobrar</p>
        <p class="stat-card-value warning"><?= eco_money($tot_porcobrar) ?></p>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div class="card-header" style="padding:16px 20px;margin:0;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;"><i class="fa-solid fa-cash-register" style="margin-right:8px;color:var(--accent);"></i> Citas (<?= count($citas) ?>)</h3>
        <div class="fact-filtros" style="display:flex;gap:6px;flex-wrap:wrap;">
            <button type="button" class="btn-secondary fact-filtro is-active" data-filtro="todos" style="font-size:12px;">Todas</button>
            <button type="button" class="btn-secondary fact-filtro" data-filtro="pendiente" style="font-size:12px;">Pendientes</button>
            <button type="button" class="btn-secondary fact-filtro" data-filtro="parcial" style="font-size:12px;">Parciales</button>
            <button type="button" class="btn-secondary fact-filtro" data-filtro="pagado" style="font-size:12px;">Pagadas</button>
        </div>
    </div>

    <?php if (empty($citas)): ?>
        <p style="padding:30px 20px;margin:0;color:var(--text-muted);text-align:center;">Aún no hay citas con estudio asignado para facturar.</p>
    <?php else: ?>
        <div class="data-table fact-table" style="border:none;border-radius:0;">
            <table class="rx-pac-table">
                <thead>
                    <tr>
                        <th class="rx-sort-th" data-sort-col="0" data-sort-type="text" tabindex="0" role="button">Paciente</th>
                        <th class="rx-sort-th" data-sort-col="1" data-sort-type="text" tabindex="0" role="button">Estudio / Servicios</th>
                        <th class="rx-sort-th" data-sort-col="2" data-sort-type="date" tabindex="0" role="button">Fecha</th>
                        <th class="rx-sort-th" data-sort-col="3" data-sort-type="number" tabindex="0" role="button" style="text-align:right;">Total</th>
                        <th class="rx-sort-th" data-sort-col="4" data-sort-type="number" tabindex="0" role="button" style="text-align:right;">Pagado</th>
                        <th class="rx-sort-th" data-sort-col="5" data-sort-type="number" tabindex="0" role="button" style="text-align:right;">Saldo</th>
                        <th class="rx-sort-th" data-sort-col="6" data-sort-type="text" tabindex="0" role="button">Estado</th>
                        <th style="text-align:right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citas as $c):
                        $mt = (float)($c['monto_total'] ?? 0);
                        $mp = (float)$c['monto_pagado'];
                        $saldo = max($mt - $mp, 0);
                        [$txt, $bg] = eco_estado_pago_color($c['estado_pago']);
                        $fecha = $c['fecha_cita'] ? date('d/m/Y', strtotime($c['fecha_cita'])) : '—';
                        $fecha_iso = $c['fecha_cita'] ? date('Y-m-d', strtotime($c['fecha_cita'])) : '';
                        $servicios = trim((string)($c['servicios'] ?? ''));
                        $estudios_list = eco_estudios_desde_texto($servicios);
                        $estudio_lead  = $estudios_list ? implode(', ', $estudios_list) : ($c['estudio'] ?: 'Sin estudio');
                        $metodo  = trim((string)($c['metodo_pago'] ?? ''));
                        $settled = in_array($c['estado_pago'], ['pagado', 'exonerado'], true);
                    ?>
                        <tr class="fact-row" data-estado="<?= htmlspecialchars($c['estado_pago']) ?>">
                            <td>
                                <strong style="display:block;font-size:13.5px;"><?= htmlspecialchars($c['paciente']) ?></strong>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($c['cedula'] ?: '—') ?></small>
                            </td>
                            <td style="max-width:320px;">
                                <span><?= htmlspecialchars($estudio_lead) ?></span>
                                <?php if ($servicios !== '' && $servicios !== $estudio_lead): ?>
                                    <small style="display:block;color:var(--text-muted);font-size:11.5px;line-height:1.4;margin-top:2px;"><?= htmlspecialchars($servicios) ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-sort-value="<?= htmlspecialchars($fecha_iso) ?>"><?= htmlspecialchars($fecha) ?></td>
                            <td style="text-align:right;font-weight:600;" data-sort-value="<?= number_format($mt, 2, '.', '') ?>"><?= eco_money($mt) ?></td>
                            <td style="text-align:right;color:#15803d;" data-sort-value="<?= number_format($mp, 2, '.', '') ?>"><?= eco_money($mp) ?></td>
                            <td style="text-align:right;font-weight:700;<?= $saldo > 0 && $c['estado_pago'] !== 'exonerado' ? 'color:#b45309;' : 'color:var(--text-muted);' ?>" data-sort-value="<?= number_format($saldo, 2, '.', '') ?>"><?= eco_money($saldo) ?></td>
                            <td data-sort-value="<?= htmlspecialchars($c['estado_pago']) ?>">
                                <span class="badge" style="background:<?= $bg ?>;color:<?= $txt ?>;"><?= htmlspecialchars(eco_estado_pago_label($c['estado_pago'])) ?></span>
                                <?php if (in_array($c['estado_pago'], ['pagado', 'parcial'], true) && $metodo !== ''): ?>
                                    <small style="display:block;color:var(--text-muted);font-size:11px;margin-top:3px;"><i class="fa-solid fa-credit-card" style="margin-right:4px;opacity:.7;"></i><?= htmlspecialchars($metodo) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <button type="button" class="btn-primary fact-cobrar" style="font-size:11.5px;padding:7px 11px;"
                                        data-id="<?= (int)$c['id'] ?>"
                                        data-paciente="<?= htmlspecialchars($c['paciente'], ENT_QUOTES) ?>"
                                        data-estudio="<?= htmlspecialchars($servicios !== '' ? $servicios : ($c['estudio'] ?: 'Sin estudio'), ENT_QUOTES) ?>"
                                        data-total="<?= number_format($mt, 2, '.', '') ?>"
                                        data-pagado="<?= number_format($mp, 2, '.', '') ?>"
                                        data-estado="<?= htmlspecialchars($c['estado_pago']) ?>"
                                        data-metodo="<?= htmlspecialchars($metodo, ENT_QUOTES) ?>">
                                    <i class="fa-solid fa-<?= $settled ? 'eye' : 'cash-register' ?>"></i> <?= $settled ? 'Ver' : 'Cobrar' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de cobro -->
<div id="fact-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:16px;">
    <div style="background:var(--bg-surface);border-radius:16px;max-width:440px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.3);overflow:hidden;">
        <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="margin:0;font-size:16px;"><i class="fa-solid fa-cash-register" style="color:var(--accent);margin-right:8px;"></i> Registrar cobro</h3>
            <button type="button" id="fact-close" style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--text-muted);"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:20px 22px;">
            <p style="margin:0 0 14px;font-size:13px;color:var(--text-secondary);"><strong id="fact-paciente">—</strong><br><span id="fact-estudio" style="color:var(--text-muted);font-size:12px;"></span></p>
            <div id="fact-error" style="display:none;margin-bottom:12px;padding:9px 12px;border-radius:8px;font-size:12.5px;background:rgba(239,68,68,.1);color:#b91c1c;"></div>
            <input type="hidden" id="fact-cita-id">

            <div id="fact-pagada" style="display:none;margin-bottom:4px;padding:16px;border-radius:10px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);text-align:center;">
                <i class="fa-solid fa-circle-check" style="font-size:26px;color:#15803d;"></i>
                <p class="fact-pagada-text" style="margin:8px 0 0;font-size:13.5px;font-weight:600;color:#15803d;"></p>
            </div>

            <div id="fact-form">
            <div style="display:flex;gap:12px;margin-bottom:12px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Monto total ($)</label>
                    <input type="number" id="fact-total" min="0" step="0.01" style="width:100%;padding:9px 11px;border:1.5px solid var(--border);border-radius:8px;box-sizing:border-box;">
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Ya pagado ($)</label>
                    <input type="number" id="fact-pagado" readonly style="width:100%;padding:9px 11px;border:1.5px solid var(--border);border-radius:8px;box-sizing:border-box;background:var(--bg-muted);">
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Abono ahora ($)</label>
                <input type="number" id="fact-abono" min="0" step="0.01" placeholder="0.00" style="width:100%;padding:9px 11px;border:1.5px solid var(--border);border-radius:8px;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:6px;">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Método de pago</label>
                <select id="fact-metodo" style="width:100%;padding:9px 11px;border:1.5px solid var(--border);border-radius:8px;box-sizing:border-box;">
                    <option value="">Seleccionar…</option>
                    <?php foreach (eco_metodos_pago() as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            </div><!-- /#fact-form -->
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:space-between;">
            <button type="button" id="fact-exonerar" class="btn-secondary" style="font-size:12.5px;color:#475569;"><i class="fa-solid fa-gift"></i> Exonerar</button>
            <div style="display:flex;gap:8px;">
                <button type="button" id="fact-cancel" class="btn-secondary">Cancelar</button>
                <button type="button" id="fact-guardar" class="btn-primary"><i class="fa-solid fa-check"></i> Registrar</button>
            </div>
        </div>
    </div>
</div>

<style>
.fact-table th.rx-sort-th { cursor:pointer; user-select:none; white-space:nowrap; }
.fact-table th.rx-sort-th:hover { color:var(--accent); }
.fact-table th.rx-sort-th::after { content:'⇅'; opacity:.3; margin-left:6px; font-size:11px; font-weight:400; }
.fact-table th.rx-sort-th--asc::after { content:'▲'; opacity:.85; font-size:9px; }
.fact-table th.rx-sort-th--desc::after { content:'▼'; opacity:.85; font-size:9px; }
</style>
<script src="assets/js/panel/eco-table-sort.js"></script>
<script>
(function () {
    var modal = document.getElementById('fact-modal');
    function openModal() { modal.style.display = 'flex'; }
    function closeModal() { modal.style.display = 'none'; setError(''); }
    function setError(m) { var e = document.getElementById('fact-error'); e.textContent = m || ''; e.style.display = m ? 'block' : 'none'; }

    document.querySelectorAll('.fact-cobrar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('fact-cita-id').value = btn.getAttribute('data-id');
            document.getElementById('fact-paciente').textContent = btn.getAttribute('data-paciente');
            document.getElementById('fact-estudio').textContent = btn.getAttribute('data-estudio');
            document.getElementById('fact-total').value = btn.getAttribute('data-total');
            document.getElementById('fact-pagado').value = btn.getAttribute('data-pagado');
            document.getElementById('fact-abono').value = '';
            document.getElementById('fact-metodo').value = '';
            setError('');

            // Cita ya saldada (pagada o exonerada): mostrar mensaje en vez del formulario.
            var estadoPago = btn.getAttribute('data-estado') || '';
            var metodo = btn.getAttribute('data-metodo') || '';
            var pagada = estadoPago === 'pagado';
            var settled = pagada || estadoPago === 'exonerado';
            var pagadaBox = document.getElementById('fact-pagada');
            if (settled) {
                var msg = pagada ? 'Ya se abonó el total de esta cita.' : 'Esta cita fue exonerada de pago.';
                if (pagada && metodo) msg += ' Método: ' + metodo + '.';
                pagadaBox.querySelector('.fact-pagada-text').textContent = msg;
                pagadaBox.style.display = 'block';
                document.getElementById('fact-form').style.display = 'none';
                document.getElementById('fact-exonerar').style.display = 'none';
                document.getElementById('fact-guardar').style.display = 'none';
                document.getElementById('fact-cancel').textContent = 'Cerrar';
            } else {
                pagadaBox.style.display = 'none';
                document.getElementById('fact-form').style.display = '';
                document.getElementById('fact-exonerar').style.display = '';
                document.getElementById('fact-guardar').style.display = '';
                document.getElementById('fact-cancel').textContent = 'Cancelar';
            }
            openModal();
        });
    });

    document.getElementById('fact-close').addEventListener('click', closeModal);
    document.getElementById('fact-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

    function enviar(fd, btn) {
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        fetch('registrar_pago.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                btn.disabled = false; btn.innerHTML = orig;
                if (d && d.success) { location.reload(); }
                else { setError((d && d.message) || 'No se pudo registrar.'); }
            })
            .catch(function () { btn.disabled = false; btn.innerHTML = orig; setError('Error de red.'); });
    }

    document.getElementById('fact-guardar').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('accion', 'cobrar');
        fd.append('cita_id', document.getElementById('fact-cita-id').value);
        fd.append('monto_total', document.getElementById('fact-total').value || '0');
        fd.append('abono', document.getElementById('fact-abono').value || '0');
        fd.append('metodo_pago', document.getElementById('fact-metodo').value);
        enviar(fd, this);
    });

    document.getElementById('fact-exonerar').addEventListener('click', function () {
        if (!confirm('¿Exonerar de pago esta cita?')) return;
        var fd = new FormData();
        fd.append('accion', 'exonerar');
        fd.append('cita_id', document.getElementById('fact-cita-id').value);
        enviar(fd, this);
    });

    document.querySelectorAll('.fact-filtro').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.fact-filtro').forEach(function (x) { x.classList.remove('is-active'); });
            b.classList.add('is-active');
            var f = b.getAttribute('data-filtro');
            document.querySelectorAll('.fact-row').forEach(function (row) {
                row.style.display = (f === 'todos' || row.getAttribute('data-estado') === f) ? '' : 'none';
            });
        });
    });

    // Ordenamiento por columnas (reusa el sorter de las tablas de recepción).
    if (window.EcoTableSort) { window.EcoTableSort.init(document.querySelector('.fact-table')); }
})();
</script>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/layouts/shell.php';
