<?php
/**
 * Registra el cobro/abono de una cita (recepcion o administrador).
 * Acciones: cobrar (fija monto_total y suma un abono), exonerar.
 */
session_start();
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/facturacion.php';
require_once __DIR__ . '/lib/citas.php';

api_json();
$response = ['success' => false, 'message' => 'Ocurrio un error.'];

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'] ?? '', ['recepcionista', 'administrador', 'ecografista'], true)) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit();
}
$rol_sesion = (string)$_SESSION['rol'];
$uid_sesion = (int)$_SESSION['usuario_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

api_require_csrf();

$cita_id  = isset($_POST['cita_id']) ? (int)$_POST['cita_id'] : 0;
$accion   = ($_POST['accion'] ?? 'cobrar') === 'exonerar' ? 'exonerar' : 'cobrar';

if ($cita_id <= 0) {
    $response['message'] = 'Cita no válida.';
    echo json_encode($response);
    exit();
}

$sel = $conex->prepare("SELECT ecografista_id, monto_total, monto_pagado, estado_pago FROM citas WHERE id = ?");
$sel->bind_param('i', $cita_id);
$sel->execute();
$cita = $sel->get_result()->fetch_assoc();
$sel->close();

if (!$cita) {
    $response['message'] = 'Cita no encontrada.';
    echo json_encode($response);
    exit();
}

// Un ecografista solo puede facturar sus propias citas.
if ($rol_sesion === 'ecografista' && (int)$cita['ecografista_id'] !== $uid_sesion) {
    http_response_code(403);
    $response['message'] = 'Solo puedes facturar tus propias citas.';
    echo json_encode($response);
    exit();
}

if ($accion === 'exonerar') {
    $up = $conex->prepare("UPDATE citas SET estado_pago = 'exonerado', fecha_pago = NOW() WHERE id = ?");
    $up->bind_param('i', $cita_id);
    $ok = $up->execute();
    $up->close();
    if ($ok) {
        eco_cita_evento($conex, $cita_id, 'pago_registrado', [
            'estado_anterior' => $cita['estado_pago'],
            'estado_nuevo'    => 'exonerado',
            'detalle'         => ['accion' => 'exonerar'],
        ]);
    }
    $response['success']     = (bool)$ok;
    $response['message']     = $ok ? 'Cita exonerada de pago.' : 'No se pudo exonerar.';
    $response['estado_pago'] = 'exonerado';
    echo json_encode($response);
    $conex->close();
    exit();
}

// accion cobrar
$monto_total = isset($_POST['monto_total']) ? (float)$_POST['monto_total'] : (float)($cita['monto_total'] ?? 0);
$abono       = isset($_POST['abono']) ? (float)$_POST['abono'] : 0.0;
$metodo      = trim((string)($_POST['metodo_pago'] ?? ''));

if ($monto_total < 0 || $abono < 0) {
    $response['message'] = 'Los importes no pueden ser negativos.';
    echo json_encode($response);
    exit();
}

$nuevo_pagado = round((float)$cita['monto_pagado'] + $abono, 2);
$estado       = eco_estado_pago($monto_total, $nuevo_pagado);

if ($abono > 0) {
    $up = $conex->prepare("UPDATE citas SET monto_total = ?, monto_pagado = ?, estado_pago = ?, metodo_pago = ?, fecha_pago = NOW() WHERE id = ?");
    $metodo_val = $metodo !== '' ? $metodo : null;
    $up->bind_param('ddssi', $monto_total, $nuevo_pagado, $estado, $metodo_val, $cita_id);
} else {
    // Solo se ajusta el monto a cobrar, sin abono.
    $up = $conex->prepare("UPDATE citas SET monto_total = ?, estado_pago = ? WHERE id = ?");
    $up->bind_param('dsi', $monto_total, $estado, $cita_id);
}
$ok = $up->execute();
$up->close();

if ($ok) {
    if ($abono > 0) {
        eco_cita_evento($conex, $cita_id, 'pago_registrado', [
            'estado_anterior' => $cita['estado_pago'],
            'estado_nuevo'    => $estado,
            'detalle'         => ['abono' => $abono, 'monto_total' => $monto_total, 'metodo' => $metodo],
        ]);
    }
    $response['success']      = true;
    $response['message']      = $abono > 0 ? ('Pago registrado: ' . eco_money($abono) . '.') : 'Monto actualizado.';
    $response['estado_pago']  = $estado;
    $response['monto_total']  = $monto_total;
    $response['monto_pagado'] = $nuevo_pagado;
    $response['saldo']        = round(max($monto_total - $nuevo_pagado, 0), 2);
} else {
    error_log('registrar_pago: ' . $conex->error);
    $response['message'] = 'No se pudo registrar el pago.';
}

$conex->close();
echo json_encode($response);
