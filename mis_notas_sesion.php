<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['rol'] !== 'ecografista') { header('Location: dashboard_v2.php'); exit; }

$ecografista_id = (int)$_SESSION['usuario_id'];

/* Lista de pacientes con conteo de notas */
$pacientes = [];
$sql = "SELECT DISTINCT u.id, u.nombre_completo, u.correo, u.cedula, u.fecha_registro,
               (SELECT COUNT(*) FROM notas_clinicas n WHERE n.paciente_id=u.id) AS total_notas,
               (SELECT MAX(n.fecha_sesion) FROM notas_clinicas n WHERE n.paciente_id=u.id) AS ultima_nota
        FROM usuarios u
        LEFT JOIN citas c ON u.id=c.paciente_id
        WHERE u.rol='paciente' AND u.estado='aprobado'
              AND (u.creado_por_id=? OR c.ecografista_id=?)
        ORDER BY ultima_nota DESC, u.nombre_completo ASC";
if ($s = $conex->prepare($sql)) {
    $s->bind_param('ii', $ecografista_id, $ecografista_id);
    $s->execute();
    $pacientes = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

$page_title    = 'Notas de Sesión';
$page_subtitle = 'Cuaderno clínico privado por paciente';
$active_section = 'notas-sesion';

$page_head_extra = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';

ob_start();
?>

<?php if (empty($pacientes)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <i class="fa-solid fa-notes-medical" style="font-size:3rem;color:var(--text-muted);opacity:.4;margin-bottom:14px;"></i>
        <h3 style="margin:0 0 6px;color:var(--text-primary);">Aún no tienes pacientes</h3>
        <p style="color:var(--text-secondary);margin:0;font-size:13.5px;">Cuando tengas pacientes asignados podrás añadirles notas clínicas privadas.</p>
    </div>
<?php else: ?>

<div class="card" style="padding:14px 18px;margin-bottom:14px;">
    <div style="position:relative;">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
        <input type="search" id="notas-search" placeholder="Buscar paciente por nombre o cédula..."
               style="width:100%;padding:10px 14px 10px 40px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;background:var(--bg-surface);color:var(--text-primary);box-sizing:border-box;">
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div class="data-table" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Cédula</th>
                    <th>Correo</th>
                    <th>Notas</th>
                    <th>Última nota</th>
                    <th style="text-align:right;">Acción</th>
                </tr>
            </thead>
            <tbody id="tbody-notas">
            <?php foreach ($pacientes as $p):
                $iniciales = '';
                foreach (explode(' ', trim($p['nombre_completo'])) as $part) if (strlen($iniciales) < 2 && $part !== '') $iniciales .= strtoupper($part[0]);
                $ultima = $p['ultima_nota'] ? date('d/m/Y', strtotime($p['ultima_nota'])) : '—';
                $busqueda = strtolower(($p['nombre_completo']??'') . ' ' . ($p['cedula']??''));
            ?>
                <tr class="nota-row" data-search="<?= htmlspecialchars($busqueda) ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;background:linear-gradient(135deg,var(--accent),#38bdf8);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11.5px;flex-shrink:0;"><?= htmlspecialchars($iniciales ?: '?') ?></div>
                            <strong style="color:var(--text-primary);"><?= htmlspecialchars($p['nombre_completo']) ?></strong>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($p['cedula'] ?: '—') ?></td>
                    <td style="color:var(--text-secondary);font-size:12.5px;"><?= htmlspecialchars($p['correo'] ?: '—') ?></td>
                    <td><span class="badge <?= $p['total_notas'] > 0 ? 'badge-accent' : 'badge-info' ?>"><?= (int)$p['total_notas'] ?></span></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($ultima) ?></td>
                    <td style="text-align:right;">
                        <button type="button" onclick='abrirNotasPacienteEco(<?= (int)$p['id'] ?>, <?= json_encode($p['nombre_completo']) ?>)' class="btn-primary" style="padding:6px 12px;font-size:12px;">
                            <i class="fa-solid fa-notes-medical"></i> Ver / Añadir
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/layouts/partials/modal_gestionar_paciente_ecografista.php'; ?>

<script>
/* Buscador */
(function(){
    const s = document.getElementById('notas-search');
    if (!s) return;
    const rows = document.querySelectorAll('.nota-row');
    s.addEventListener('input', () => {
        const q = s.value.trim().toLowerCase();
        rows.forEach(r => { r.style.display = r.dataset.search.includes(q) ? '' : 'none'; });
    });
})();

document.addEventListener('eco:notas-changed', function (event) {
    const detail = event.detail || {};
    const fila = document.querySelector(`#tbody-notas .nota-row button[onclick*="abrirNotasPacienteEco(${detail.pacienteId},"]`);
    if (!fila) return;
    const badge = fila.closest('tr').querySelector('.badge');
    if (!badge) return;
    if (detail.action === 'clear') badge.textContent = '0';
    if (detail.action === 'add') badge.textContent = String(parseInt(badge.textContent || '0', 10) + 1);
    badge.classList.remove('badge-info', 'badge-accent');
    badge.classList.add(parseInt(badge.textContent, 10) > 0 ? 'badge-accent' : 'badge-info');
});
</script>

<?php
$page_content = ob_get_clean();
$page_scripts_extra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/panel/ecografista-modals.js?v=25"></script>
HTML;
include __DIR__ . '/layouts/shell.php';
