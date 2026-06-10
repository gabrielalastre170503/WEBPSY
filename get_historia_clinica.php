<?php
/**
 * Historia clinica consolidada de un paciente: linea de tiempo unificada de
 * informes de estudio, notas de sesion y citas. Solo lectura (JSON).
 */
session_start();
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/facturacion.php';
require_once __DIR__ . '/lib/seguridad.php';

api_json();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$paciente_id = isset($_GET['paciente_id']) && is_numeric($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
if ($paciente_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Paciente no válido']);
    exit();
}

// Paciente (datos completos)
$stmt = $conex->prepare("SELECT nombre_completo, cedula, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad, correo, telefono, direccion, fecha_nacimiento, fecha_registro FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param('i', $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$paciente) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado']);
    exit();
}

// Bitácora de acceso a datos clínicos (cumplimiento): quién consultó esta historia.
eco_auditar($conex, 'acceso_historia_clinica', [
    'entidad'    => 'paciente',
    'entidad_id' => $paciente_id,
    'detalle'    => ['paciente' => $paciente['nombre_completo'] ?? ''],
]);

// Extrae el ultimo importe "$X" de un texto libre (los costos viven en el texto
// de motivo_principal, p. ej. "... Total $40"). Devuelve float o null.
$extraer_costo = static function (?string $texto): ?float {
    if (!$texto) {
        return null;
    }
    if (preg_match_all('/\$\s*([0-9]+(?:[.,][0-9]{1,2})?)/', $texto, $m) && !empty($m[1])) {
        $ultimo = end($m[1]);
        return (float)str_replace(',', '.', $ultimo);
    }
    return null;
};

$eventos = [];
$costo_total = 0.0;

// --- Informes de estudio ---
$st = $conex->prepare("
    SELECT ie.id, ie.numero_informe, ie.estado,
           COALESCE(ie.fecha_estudio, DATE(ie.creado_en)) AS fecha,
           ie.creado_en, t.nombre AS tipo_nombre, t.categoria AS tipo_categoria,
           u.nombre_completo AS ecografista
      FROM informes_estudios ie
      LEFT JOIN tipos_ecografias t ON t.id = ie.tipo_ecografia_id
      LEFT JOIN usuarios u ON u.id = ie.ecografista_id
     WHERE ie.paciente_id = ?
");
$st->bind_param('i', $paciente_id);
$st->execute();
$rs = $st->get_result();
while ($r = $rs->fetch_assoc()) {
    $eventos[] = [
        'tipo'        => 'informe',
        'id'          => (int)$r['id'],
        'fecha'       => $r['fecha'] ?: substr((string)$r['creado_en'], 0, 10),
        'fecha_orden' => $r['fecha'] ? $r['fecha'] . ' 00:00:00' : $r['creado_en'],
        'titulo'      => $r['tipo_nombre'] ?: 'Informe de estudio',
        'categoria'   => $r['tipo_categoria'] ?: '',
        'estado'      => $r['estado'],
        'numero'      => $r['numero_informe'] ?: '',
        'profesional' => $r['ecografista'] ?: '',
        'detalle'     => '',
    ];
}
$st->close();

// --- Notas de sesion ---
$st = $conex->prepare("
    SELECT nc.id, nc.fecha_sesion, nc.contenido, u.nombre_completo AS ecografista
      FROM notas_clinicas nc
      LEFT JOIN usuarios u ON u.id = nc.ecografista_id
     WHERE nc.paciente_id = ?
");
$st->bind_param('i', $paciente_id);
$st->execute();
$rs = $st->get_result();
while ($r = $rs->fetch_assoc()) {
    $eventos[] = [
        'tipo'        => 'nota',
        'id'          => (int)$r['id'],
        'fecha'       => substr((string)$r['fecha_sesion'], 0, 10),
        'fecha_orden' => $r['fecha_sesion'],
        'titulo'      => 'Nota de sesión',
        'categoria'   => '',
        'estado'      => '',
        'numero'      => '',
        'profesional' => $r['ecografista'] ?: '',
        'detalle'     => mb_substr((string)$r['contenido'], 0, 240),
    ];
}
$st->close();

// --- Citas ---
$st = $conex->prepare("
    SELECT c.id, c.fecha_cita, c.fecha_solicitud, c.estado, c.motivo_consulta,
           c.motivo_principal, c.modalidad, c.tipo_cita,
           c.monto_total, c.monto_pagado, c.estado_pago,
           t.nombre AS tipo_nombre, u.nombre_completo AS ecografista
      FROM citas c
      LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
      LEFT JOIN usuarios u ON u.id = c.ecografista_id
     WHERE c.paciente_id = ?
");
$st->bind_param('i', $paciente_id);
$st->execute();
$rs = $st->get_result();
while ($r = $rs->fetch_assoc()) {
    $fecha_ref = $r['fecha_cita'] ?: $r['fecha_solicitud'];

    // Facturacion real si existe; si no, intenta el importe historico del texto.
    $mt = $r['monto_total'] !== null ? (float)$r['monto_total'] : null;
    $mp = (float)$r['monto_pagado'];
    $costo = $mt !== null ? $mt : $extraer_costo($r['motivo_principal']);
    if ($costo !== null) {
        $costo_total += $costo;
    }

    $estudios_cita = eco_estudios_desde_texto($r['motivo_principal'] ?? '');
    $titulo_cita   = $estudios_cita ? implode(', ', $estudios_cita) : ($r['tipo_nombre'] ?: 'Cita');

    $eventos[] = [
        'tipo'        => 'cita',
        'id'          => (int)$r['id'],
        'fecha'       => substr((string)$fecha_ref, 0, 10),
        'fecha_orden' => $fecha_ref,
        'titulo'      => $titulo_cita,
        'categoria'   => '',
        'estado'      => $r['estado'],
        'numero'      => '',
        'profesional' => $r['ecografista'] ?: '',
        'detalle'     => mb_substr((string)($r['motivo_consulta'] ?? ''), 0, 240),
        'servicios'   => trim((string)($r['motivo_principal'] ?? '')),
        'modalidad'   => $r['modalidad'] ? ucfirst($r['modalidad']) : '',
        'tipo_cita'   => $r['tipo_cita'] ? ucwords(str_replace('_', ' ', $r['tipo_cita'])) : '',
        'costo'       => $costo,
        'costo_fmt'   => $costo !== null ? eco_money($costo) : '',
        'pagado_fmt'  => $mt !== null ? eco_money($mp) : '',
        'saldo_fmt'   => $mt !== null ? eco_money(max($mt - $mp, 0)) : '',
        'pago_estado' => $mt !== null ? $r['estado_pago'] : '',
        'pago_label'  => $mt !== null ? eco_estado_pago_label($r['estado_pago']) : '',
    ];
}
$st->close();

// Orden cronologico descendente (mas reciente primero)
usort($eventos, static function ($a, $b) {
    return strcmp((string)$b['fecha_orden'], (string)$a['fecha_orden']);
});

// Formatear fecha visible
foreach ($eventos as &$ev) {
    $ev['fecha_fmt'] = $ev['fecha'] ? date('d/m/Y', strtotime($ev['fecha'])) : '—';
    unset($ev['fecha_orden']);
}
unset($ev);

$resumen = [
    'informes' => 0,
    'notas'    => 0,
    'citas'    => 0,
];
foreach ($eventos as $ev) {
    $resumen[$ev['tipo'] . 's'] = ($resumen[$ev['tipo'] . 's'] ?? 0) + 1;
}

$fmt_fecha = static function ($v) {
    return ($v && $v !== '0000-00-00') ? date('d/m/Y', strtotime($v)) : '—';
};

echo json_encode([
    'paciente' => [
        'nombre'     => $paciente['nombre_completo'],
        'cedula'     => $paciente['cedula'] ?? '',
        'edad'       => $paciente['edad'] ?? '',
        'correo'     => $paciente['correo'] ?? '',
        'telefono'   => $paciente['telefono'] ?? '',
        'direccion'  => $paciente['direccion'] ?? '',
        'nacimiento' => $fmt_fecha($paciente['fecha_nacimiento'] ?? null),
        'registro'   => $fmt_fecha($paciente['fecha_registro'] ?? null),
    ],
    'resumen'        => $resumen,
    'total'          => count($eventos),
    'costo_total'    => $costo_total,
    'costo_total_fmt'=> $costo_total > 0 ? eco_money($costo_total) : '',
    'eventos'        => $eventos,
], JSON_UNESCAPED_UNICODE);

$conex->close();
