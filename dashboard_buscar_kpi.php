<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    http_response_code(403);
    exit('<p class="admin-kpi-empty">Acceso denegado.</p>');
}

$tipo  = isset($_POST['tipo']) ? trim((string)$_POST['tipo']) : '';
$query = isset($_POST['query']) ? trim((string)$_POST['query']) : '';
$like  = '%' . $query . '%';
$limit = 80;

$tipos_validos = ['usuarios', 'pacientes', 'personal', 'citas'];
if (!in_array($tipo, $tipos_validos, true)) {
    exit('<p class="admin-kpi-empty">Tipo de listado no válido.</p>');
}

function admin_kpi_rol_label(string $rol): string
{
    $map = [
        'administrador'  => 'Administrador',
        'ecografista'    => 'Ecografista',
        'recepcionista'  => 'Recepcionista',
        'paciente'       => 'Paciente',
    ];
    return $map[$rol] ?? ucfirst($rol);
}

function admin_kpi_render_table(array $headers, array $rows): void
{
    if (empty($rows)) {
        echo '<p class="admin-kpi-empty">No hay registros que coincidan con la búsqueda.</p>';
        return;
    }
    echo '<table class="admin-kpi-table"><thead><tr>';
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $cells) {
        echo '<tr>';
        foreach ($cells as $cell) {
            echo '<td>' . $cell . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
}

$rows = [];

switch ($tipo) {
    case 'usuarios':
        $sql = "SELECT nombre_completo, cedula, correo, rol
            FROM usuarios
            WHERE estado = 'aprobado'
            AND (nombre_completo LIKE ? OR cedula LIKE ? OR correo LIKE ?)
            ORDER BY rol, nombre_completo ASC
            LIMIT ?";
        $stmt = $conex->prepare($sql);
        $stmt->bind_param('sssi', $like, $like, $like, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($u = $res->fetch_assoc()) {
            $rows[] = [
                htmlspecialchars($u['nombre_completo']),
                htmlspecialchars($u['cedula'] ?? '—'),
                htmlspecialchars($u['correo'] ?? '—'),
                '<span class="admin-kpi-pill admin-kpi-pill--rol">' . htmlspecialchars(admin_kpi_rol_label($u['rol'])) . '</span>',
            ];
        }
        $stmt->close();
        admin_kpi_render_table(['Nombre', 'Cédula', 'Correo', 'Rol'], $rows);
        break;

    case 'pacientes':
        $sql = "SELECT nombre_completo, cedula, correo, fecha_registro
            FROM usuarios
            WHERE rol = 'paciente' AND estado = 'aprobado'
            AND (nombre_completo LIKE ? OR cedula LIKE ? OR correo LIKE ?)
            ORDER BY nombre_completo ASC
            LIMIT ?";
        $stmt = $conex->prepare($sql);
        $stmt->bind_param('sssi', $like, $like, $like, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($u = $res->fetch_assoc()) {
            $fr = !empty($u['fecha_registro']) ? date('d/m/Y', strtotime($u['fecha_registro'])) : '—';
            $rows[] = [
                htmlspecialchars($u['nombre_completo']),
                htmlspecialchars($u['cedula'] ?? '—'),
                htmlspecialchars($u['correo'] ?? '—'),
                htmlspecialchars($fr),
            ];
        }
        $stmt->close();
        admin_kpi_render_table(['Paciente', 'Cédula', 'Correo', 'Registro'], $rows);
        break;

    case 'personal':
        $sql = "SELECT nombre_completo, cedula, correo, rol
            FROM usuarios
            WHERE rol IN ('ecografista', 'recepcionista') AND estado = 'aprobado'
            AND (nombre_completo LIKE ? OR cedula LIKE ? OR correo LIKE ?)
            ORDER BY rol, nombre_completo ASC
            LIMIT ?";
        $stmt = $conex->prepare($sql);
        $stmt->bind_param('sssi', $like, $like, $like, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($u = $res->fetch_assoc()) {
            $rows[] = [
                htmlspecialchars($u['nombre_completo']),
                htmlspecialchars($u['cedula'] ?? '—'),
                htmlspecialchars($u['correo'] ?? '—'),
                '<span class="admin-kpi-pill admin-kpi-pill--rol">' . htmlspecialchars(admin_kpi_rol_label($u['rol'])) . '</span>',
            ];
        }
        $stmt->close();
        admin_kpi_render_table(['Nombre', 'Cédula', 'Correo', 'Rol'], $rows);
        break;

    case 'citas':
        $sql = "SELECT c.fecha_cita, c.estado,
                paciente.nombre_completo AS paciente_nombre,
                paciente.cedula AS paciente_cedula,
                eco.nombre_completo AS eco_nombre,
                t.nombre AS tipo_nombre
            FROM citas c
            JOIN usuarios paciente ON c.paciente_id = paciente.id
            LEFT JOIN usuarios eco ON c.ecografista_id = eco.id
            LEFT JOIN tipos_ecografias t ON c.tipo_ecografia_id = t.id
            WHERE paciente.nombre_completo LIKE ? OR paciente.cedula LIKE ?
                OR eco.nombre_completo LIKE ? OR t.nombre LIKE ?
            ORDER BY c.fecha_cita DESC
            LIMIT ?";
        $stmt = $conex->prepare($sql);
        $stmt->bind_param('ssssi', $like, $like, $like, $like, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($c = $res->fetch_assoc()) {
            $fecha = $c['fecha_cita'] ? date('d/m/Y h:i A', strtotime($c['fecha_cita'])) : '—';
            $estado = htmlspecialchars($c['estado']);
            $badge = '<span class="admin-kpi-pill admin-kpi-pill--' . $estado . '">' . htmlspecialchars(ucfirst($c['estado'])) . '</span>';
            $rows[] = [
                htmlspecialchars($c['paciente_nombre']),
                htmlspecialchars($c['paciente_cedula'] ?? '—'),
                htmlspecialchars($c['eco_nombre'] ?? 'Sin asignar'),
                htmlspecialchars($c['tipo_nombre'] ?? '—'),
                htmlspecialchars($fecha),
                $badge,
            ];
        }
        $stmt->close();
        admin_kpi_render_table(['Paciente', 'Cédula', 'Ecografista', 'Estudio', 'Fecha', 'Estado'], $rows);
        break;
}

$conex->close();
