<?php
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/core/table_sort_helpers.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['recepcionista', 'administrador'], true)) {
    exit('Acceso denegado');
}

/**
 * @return string Iniciales (máx. 2) para avatar
 */
function rx_paciente_iniciales(string $nombre): string
{
    $iniciales = '';
    foreach (explode(' ', trim($nombre)) as $part) {
        if ($part !== '' && strlen($iniciales) < 2) {
            $iniciales .= strtoupper($part[0]);
        }
    }
    return $iniciales !== '' ? $iniciales : '?';
}

$termino_busqueda = isset($_POST['query']) ? (string)$_POST['query'] : '';
$busqueda = '%' . $termino_busqueda . '%';

$sqlCount = "SELECT COUNT(*) AS total FROM usuarios
    WHERE rol = 'paciente' AND estado = 'aprobado'
    AND (nombre_completo LIKE ? OR cedula LIKE ? OR direccion LIKE ?)";
$stmtCount = $conex->prepare($sqlCount);
$stmtCount->bind_param('sss', $busqueda, $busqueda, $busqueda);
$stmtCount->execute();
$totalFiltrado = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
$stmtCount->close();

$sql = "SELECT id, nombre_completo, correo, cedula, direccion, fecha_registro
    FROM usuarios
    WHERE rol = 'paciente' AND estado = 'aprobado'
    AND (nombre_completo LIKE ? OR cedula LIKE ? OR direccion LIKE ?)
    ORDER BY nombre_completo ASC";

$stmt = $conex->prepare($sql);
$stmt->bind_param('sss', $busqueda, $busqueda, $busqueda);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    echo '<div class="table-responsive" data-rx-total="' . $totalFiltrado . '">';
    echo '<table class="rx-pac-table">';
    echo '<colgroup>';
    echo '<col class="col-paciente"><col class="col-cedula"><col class="col-correo"><col class="col-direccion"><col class="col-ingreso"><col class="col-acciones">';
    echo '</colgroup>';
    echo '<thead><tr>';
    echo eco_sort_th('Paciente', 0, 'text');
    echo eco_sort_th('Cédula', 1, 'number');
    echo eco_sort_th('Correo', 2, 'text');
    echo eco_sort_th('Dirección', 3, 'text');
    echo eco_sort_th('Ingreso', 4, 'date');
    echo '<th class="rx-th-acciones">Acciones</th>';
    echo '</tr></thead><tbody>';
    while ($paciente = $resultado->fetch_assoc()) {
        $id = (int)$paciente['id'];
        $nomAttr = htmlspecialchars((string)$paciente['nombre_completo'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $iniciales = htmlspecialchars(rx_paciente_iniciales((string)$paciente['nombre_completo']));
        $fechaRegistro = $paciente['fecha_registro'] ? date('d/m/Y', strtotime($paciente['fecha_registro'])) : '—';
        $sortNombre = htmlspecialchars(mb_strtolower(trim((string)$paciente['nombre_completo']), 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $cedulaDigits = preg_replace('/\D/', '', (string)($paciente['cedula'] ?? ''));
        $sortCedula = htmlspecialchars($cedulaDigits !== '' ? $cedulaDigits : '0', ENT_QUOTES, 'UTF-8');
        $sortCorreo = htmlspecialchars(mb_strtolower(trim((string)($paciente['correo'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $sortDireccion = htmlspecialchars(mb_strtolower(trim((string)($paciente['direccion'] ?? '')), 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $sortIngreso = $paciente['fecha_registro']
            ? htmlspecialchars(date('Y-m-d', strtotime($paciente['fecha_registro'])), ENT_QUOTES, 'UTF-8')
            : '';
        echo '<tr>';
        echo '<td class="rx-pac-td-nombre" data-sort-value="' . $sortNombre . '">';
        echo '<div class="rx-pac-cell-nombre">';
        echo '<span class="rx-pac-avatar" aria-hidden="true">' . $iniciales . '</span>';
        echo '<strong>' . htmlspecialchars($paciente['nombre_completo']) . '</strong>';
        echo '</div></td>';
        echo '<td class="rx-pac-td-cedula" data-sort-value="' . $sortCedula . '">' . htmlspecialchars($paciente['cedula'] ?: '—') . '</td>';
        echo '<td class="rx-pac-td-email" data-sort-value="' . $sortCorreo . '">' . htmlspecialchars($paciente['correo'] ?: '—') . '</td>';
        echo '<td class="rx-pac-td-direccion" data-sort-value="' . $sortDireccion . '">' . htmlspecialchars($paciente['direccion'] ?: '—') . '</td>';
        echo '<td class="rx-pac-td-ingreso" data-sort-value="' . $sortIngreso . '">' . htmlspecialchars($fechaRegistro) . '</td>';
        echo '<td class="rx-td-acciones">';
        echo '<div class="acciones-wrapper">';
        echo '<button type="button" class="rx-btn rx-btn--prim rx-js-ficha" data-rx-pid="' . $id . '"><i class="fa-solid fa-id-card"></i> Ficha</button>';
        echo '<button type="button" class="rx-btn rx-btn--sec rx-js-prog" data-rx-pid="' . $id . '" data-rx-nom="' . $nomAttr . '"><i class="fa-solid fa-calendar-plus"></i> Programar</button>';
        echo '<button type="button" class="rx-btn rx-btn--muted rx-js-inf" data-rx-pid="' . $id . '" data-rx-nom="' . $nomAttr . '"><i class="fa-solid fa-file-waveform"></i> Informes</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
} else {
    echo '<p class="rx-pac-empty" data-rx-total="0">No se encontraron pacientes que coincidan con tu búsqueda.</p>';
}

$stmt->close();
$conex->close();
