<?php
session_start();
include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../lib/personal/admin_data.php';
require_once __DIR__ . '/../lib/personal/especialidades.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'administrador') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_especialidad'])) {
    require_csrf();
    $uid = (int)($_POST['usuario_id'] ?? 0);
    $esp = trim((string)($_POST['especialidades'] ?? ''));

    // Solo se permite editar especialidades de ecografistas.
    $esEco = false;
    if ($uid > 0 && ($chk = $conex->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'ecografista'"))) {
        $chk->bind_param('i', $uid);
        $chk->execute();
        $esEco = $chk->get_result()->num_rows > 0;
        $chk->close();
    }

    if (!$esEco) {
        $msg_err = 'Usuario no válido.';
    } elseif (eco_sync_especialidades_usuario($conex, $uid, $esp)) {
        $msg_ok = 'Especialidades actualizadas correctamente.';
    } else {
        $msg_err = 'No se pudo guardar los cambios.';
    }
}

$data = eco_admin_build_especialidades_panel($conex);
$catalogo_global = eco_catalogo_especialidades($conex);

$page_title    = 'Especialidades';
$page_subtitle = 'Áreas de experiencia del personal ecográfico';
$active_section = 'admin-especialidades';

ob_start();
?>

<?php if ($msg_ok): ?>
    <div class="card" style="border-left:4px solid var(--success);background:rgba(34,197,94,.06);margin-bottom:14px;padding:12px 16px;">
        <strong style="color:#15803d;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_ok) ?></strong>
    </div>
<?php endif; ?>
<?php if ($msg_err): ?>
    <div class="card" style="border-left:4px solid var(--danger);background:rgba(239,68,68,.06);margin-bottom:14px;padding:12px 16px;">
        <strong style="color:#b91c1c;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($msg_err) ?></strong>
    </div>
<?php endif; ?>

<?php $totalProf = count($data['profesionales']); ?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);"><i class="fa-solid fa-layer-group"></i></div>
        <p class="stat-card-label">Especialidades únicas</p>
        <p class="stat-card-value accent"><?= (int)$data['unique_total'] ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;"><i class="fa-solid fa-user-check"></i></div>
        <p class="stat-card-label">Con especialidad</p>
        <p class="stat-card-value success"><?= (int)$data['with_specialty'] ?></p>
        <p class="stat-card-sub">de <?= $totalProf ?> ecografistas</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;"><i class="fa-solid fa-user-clock"></i></div>
        <p class="stat-card-label">Por asignar</p>
        <p class="stat-card-value warning"><?= (int)$data['without_specialty'] ?></p>
    </div>
</div>

<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-map-location-dot" style="margin-right:7px;color:var(--accent);"></i> Mapa de especialidades</h3>
    </div>
    <?php if (empty($data['resumen'])): ?>
        <p style="color:var(--text-muted);padding:20px 0;text-align:center;">No hay especialidades registradas aún.</p>
    <?php else: ?>
        <div class="data-table" style="border:none;">
            <table>
                <thead><tr><th>Especialidad</th><th>Profesionales</th><th>Equipo de referencia</th></tr></thead>
                <tbody>
                    <?php foreach ($data['resumen'] as $r):
                        $preview = array_slice($r['profesionales'], 0, 3);
                        $restantes = max($r['total'] - count($preview), 0);
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['nombre']) ?></strong></td>
                            <td><?= (int)$r['total'] ?></td>
                            <td>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                    <?php foreach ($preview as $n): ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($n) ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($restantes > 0): ?>
                                        <span class="badge badge-warning">+<?= $restantes ?> más</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-pen-to-square" style="margin-right:7px;color:var(--accent);"></i> Asignar o editar</h3>
    </div>
    <div style="margin-bottom:14px;">
        <input type="search" id="specialty-search-input" placeholder="Buscar por nombre, rol o especialidad..."
               style="width:100%;max-width:420px;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13.5px;box-sizing:border-box;">
    </div>

    <div class="data-table" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Profesional</th>
                    <th>Rol</th>
                    <th>Especialidades actuales</th>
                    <th>Estado</th>
                    <th>Actualizar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['profesionales'])): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">No hay ecografistas registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($data['profesionales'] as $prof):
                        $estado = strtolower((string)($prof['estado'] ?? ''));
                    ?>
                        <tr class="specialty-row" data-search="<?= htmlspecialchars($prof['search_text']) ?>">
                            <td>
                                <strong><?= htmlspecialchars($prof['nombre_completo']) ?></strong><br>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($prof['correo']) ?></small>
                            </td>
                            <td><span class="badge badge-accent"><?= htmlspecialchars($prof['rol']) ?></span></td>
                            <td>
                                <?php if (!empty($prof['especialidades_lista'])): ?>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        <?php foreach ($prof['especialidades_lista'] as $e): ?>
                                            <span class="badge badge-info"><?= htmlspecialchars($e) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:12.5px;">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $estado === 'aprobado' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars(ucfirst($prof['estado'] ?? '—')) ?></span>
                            </td>
                            <td>
                                <form method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="actualizar_especialidad" value="1">
                                    <input type="hidden" name="usuario_id" value="<?= (int)$prof['id'] ?>">
                                    <input type="text" name="especialidades" value="<?= htmlspecialchars($prof['especialidades_texto']) ?>"
                                           placeholder="Ej. Abdominal, Obstétrica" list="catalogo-especialidades"
                                           style="min-width:180px;flex:1;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;box-sizing:border-box;">
                                    <button type="submit" class="btn-primary" style="padding:8px 12px;font-size:12px;"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <datalist id="catalogo-especialidades">
        <?php foreach ($catalogo_global as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"></option>
        <?php endforeach; ?>
    </datalist>
</div>

<script>
document.getElementById('specialty-search-input')?.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('.specialty-row').forEach(function (row) {
        const s = row.getAttribute('data-search') || '';
        row.style.display = !q || s.includes(q) ? '' : 'none';
    });
});
</script>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
