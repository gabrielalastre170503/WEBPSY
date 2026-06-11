<?php
session_start();
include __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'recepcionista') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$profesionales = [];
$q = $conex->query("
    SELECT u.id, u.nombre_completo, u.correo, u.cedula, u.fecha_registro,
           (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', ')
              FROM usuario_especialidades ue
              JOIN especialidades e ON e.id = ue.especialidad_id
             WHERE ue.usuario_id = u.id) AS especialidades,
           (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c
            WHERE c.ecografista_id = u.id AND c.estado IN ('confirmada','completada')) AS pacientes_atendidos
    FROM usuarios u
    WHERE u.rol = 'ecografista' AND u.estado = 'aprobado'
    ORDER BY u.nombre_completo ASC
");
if ($q) {
    $profesionales = $q->fetch_all(MYSQLI_ASSOC);
    $q->free();
}

$page_title    = 'Directorio clínico';
$page_subtitle = 'Ecografistas y contacto del equipo';
$active_section = 'directorio';

ob_start();
?>

<div class="card" style="margin-bottom:14px;">
    <p style="margin:0;font-size:13.5px;color:var(--text-secondary);line-height:1.5;">
        Listado de ecografistas activos. Para agendar en nombre de un paciente, usa <strong>Gestión de pacientes</strong> o <strong>Programar cita directa</strong>.
    </p>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <?php if (empty($profesionales)): ?>
        <p style="padding:28px;text-align:center;color:var(--text-muted);">No hay ecografistas registrados.</p>
    <?php else: ?>
    <div class="data-table" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Especialidades</th>
                    <th>Correo</th>
                    <th>Identificación</th>
                    <th style="text-align:center;">Pacientes atendidos</th>
                    <th>Desde</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profesionales as $p):
                    $esp = $p['especialidades'] ? $p['especialidades'] : '—';
                    $fd = !empty($p['fecha_registro']) ? date('d/m/Y', strtotime($p['fecha_registro'])) : '—';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['nombre_completo']) ?></strong></td>
                    <td><?= htmlspecialchars($esp) ?></td>
                    <td style="font-size:12.5px;"><?= htmlspecialchars($p['correo']) ?></td>
                    <td><?= htmlspecialchars($p['cedula'] ?? '—') ?></td>
                    <td style="text-align:center;"><?= (int)$p['pacientes_atendidos'] ?></td>
                    <td style="font-size:12.5px;color:var(--text-secondary);"><?= htmlspecialchars($fd) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();

include __DIR__ . '/../layouts/shell.php';
