<?php
session_start();
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/seguridad.php';
require_once __DIR__ . '/lib/citas.php';

api_json();
$response = ['success' => false, 'message' => 'Datos invalidos.'];

api_require_roles(['ecografista', 'administrador', 'recepcionista']);

api_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['paciente_id'], $_POST['fecha_cita'], $_POST['tipo_ecografia_id'])) {
    echo json_encode($response);
    exit();
}

$paciente_id       = (int)$_POST['paciente_id'];
$tipo_ecografia_id = (int)$_POST['tipo_ecografia_id'];
$fecha_cita        = $_POST['fecha_cita'];
$motivo            = trim($_POST['motivo_consulta'] ?? '');

$rol_sesion = $_SESSION['rol'] ?? '';
if ($rol_sesion === 'recepcionista' || $rol_sesion === 'administrador') {
    $ecografista_id = (int)($_POST['ecografista_id'] ?? 0);
    if ($ecografista_id <= 0) {
        $response['message'] = 'Seleccione un ecografista responsable.';
        echo json_encode($response);
        exit();
    }
    $chkEco = $conex->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'ecografista' AND estado = 'aprobado'");
    $chkEco->bind_param('i', $ecografista_id);
    $chkEco->execute();
    if (!$chkEco->get_result()->fetch_assoc()) {
        $chkEco->close();
        $response['message'] = 'Ecografista no válido.';
        echo json_encode($response);
        exit();
    }
    $chkEco->close();
} else {
    $ecografista_id = (int)$_SESSION['usuario_id'];
}

$stmt = $conex->prepare("SELECT nombre, precio FROM tipos_ecografias WHERE id = ? AND activo = 1");
$stmt->bind_param("i", $tipo_ecografia_id);
$stmt->execute();
$tipo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tipo) {
    $response['message'] = 'Tipo de ecografia invalido.';
    echo json_encode($response);
    exit();
}

$monto_total = (float)$tipo['precio']; // tarifa del estudio como cargo inicial

$fecha_formateada = date('d/m/Y \a \l\a\s h:i A', strtotime($fecha_cita));
$notificacion = "Tu cita para <strong>" . htmlspecialchars($tipo['nombre']) . "</strong> ha sido programada para el <strong>{$fecha_formateada}</strong>.";

$stmt = $conex->prepare("INSERT INTO citas
    (paciente_id, ecografista_id, tipo_ecografia_id, fecha_cita, motivo_consulta, estado, notificacion_paciente, monto_total)
    VALUES (?, ?, ?, ?, ?, 'confirmada', ?, ?)");
$stmt->bind_param("iiisssd", $paciente_id, $ecografista_id, $tipo_ecografia_id, $fecha_cita, $motivo, $notificacion, $monto_total);

if ($stmt->execute()) {
    $nueva_cita_id = (int)$stmt->insert_id;
    eco_auditar($conex, 'cita_creada', ['entidad' => 'cita', 'entidad_id' => $nueva_cita_id, 'detalle' => ['paciente_id' => $paciente_id, 'ecografista_id' => $ecografista_id, 'tipo' => $tipo['nombre']]]);
    eco_cita_evento($conex, $nueva_cita_id, 'creada', ['estado_nuevo' => 'confirmada', 'detalle' => ['tipo' => $tipo['nombre'], 'fecha' => $fecha_cita]]);
    $response['success'] = true;
    $response['message'] = 'Cita creada y notificada al paciente.';
    $response['cita_id'] = $nueva_cita_id;
} else {
    // FIX SEGURIDAD: no exponer el detalle interno de MySQL al cliente.
    error_log('guardar_cita_directa: ' . $stmt->error);
    $response['message'] = 'No se pudo guardar la cita. Inténtalo de nuevo.';
}

$stmt->close();
$conex->close();
echo json_encode($response);
