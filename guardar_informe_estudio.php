<?php
session_start();
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/estudios_render.php';
require_once __DIR__ . '/lib/informes.php';
require_once __DIR__ . '/lib/facturacion.php';

/**
 * Asienta la facturacion (estudio + servicios adicionales) en una cita, reusando
 * la cita abierta del paciente. Best-effort: si falla solo se registra en el log,
 * nunca tumba el guardado del informe. Solo corre si el formulario trae 'servicios'
 * (es decir, viene del flujo de expediente del ecografista, no de una edicion suelta).
 */
function eco_facturar_si_aplica(mysqli $conex, int $pacienteId, int $ecografistaId, int $tipoEcoId, array &$response): void
{
    if (!isset($_POST['servicios'])) {
        return;
    }
    $keys = array_filter(array_map('trim', explode(',', (string)$_POST['servicios'])));
    try {
        $fact = eco_facturar_cita_reuso($conex, $pacienteId, $ecografistaId, $tipoEcoId, $keys);
        if ($fact) {
            $response['cita_facturada'] = $fact[0];
            $response['monto_total']    = $fact[1];
        }
    } catch (\Throwable $e) {
        error_log('eco_facturar_cita_reuso: ' . $e->getMessage());
    }
}

api_json();
$response = ['success' => false, 'message' => 'Ocurrio un error inesperado.'];

api_require_roles(['ecografista', 'administrador']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo no permitido.';
    echo json_encode($response);
    exit();
}

api_require_csrf();

$rol            = (string)$_SESSION['rol'];
$ecografista_id = (int)$_SESSION['usuario_id'];

$informe_id        = isset($_POST['informe_id'])        ? (int)$_POST['informe_id']        : 0;
$paciente_id       = isset($_POST['paciente_id'])       ? (int)$_POST['paciente_id']       : 0;
$tipo_ecografia_id = isset($_POST['tipo_ecografia_id']) ? (int)$_POST['tipo_ecografia_id'] : 0;
$datos_post        = $_POST['campo'] ?? [];
$accion            = ($_POST['accion'] ?? 'finalizar') === 'borrador' ? 'borrador' : 'finalizar';

if ($paciente_id <= 0 || $tipo_ecografia_id <= 0) {
    $response['message'] = 'Faltan datos basicos del informe (paciente o tipo de estudio).';
    echo json_encode($response);
    exit();
}

// Paciente valido
$stmt = $conex->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $response['message'] = 'Paciente no encontrado.';
    echo json_encode($response);
    exit();
}
$stmt->close();

// Tipo de estudio valido + esquema
$stmt = $conex->prepare("SELECT esquema_campos, esquema_version FROM tipos_ecografias WHERE id = ? AND activo = 1");
$stmt->bind_param("i", $tipo_ecografia_id);
$stmt->execute();
$tipo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tipo) {
    $response['message'] = 'Tipo de ecografia no encontrado o inactivo.';
    echo json_encode($response);
    exit();
}

$esquema = json_decode($tipo['esquema_campos'], true);
if (!is_array($esquema) || empty($esquema['secciones'])) {
    $response['message'] = 'El esquema del tipo de ecografia es invalido.';
    echo json_encode($response);
    exit();
}

// Validacion: estricta al finalizar; permisiva (datos parciales) en borrador.
$validacion = eco_validar_datos($esquema, is_array($datos_post) ? $datos_post : []);
if ($accion === 'finalizar' && !empty($validacion['errores'])) {
    $response['message'] = implode(' ', $validacion['errores']);
    echo json_encode($response);
    exit();
}

$datos_json = json_encode($validacion['datos'], JSON_UNESCAPED_UNICODE);
if ($datos_json === false) {
    $response['message'] = 'No se pudieron codificar los datos a JSON.';
    echo json_encode($response);
    exit();
}

$esquema_version = (int)$tipo['esquema_version'];

// ---------------------------------------------------------------------------
// MODO EDICION: actualizar un informe existente (borrador o finalizado).
// ---------------------------------------------------------------------------
if ($informe_id > 0) {
    $sel = $conex->prepare("SELECT ecografista_id, estado, numero_informe FROM informes_estudios WHERE id = ?");
    $sel->bind_param("i", $informe_id);
    $sel->execute();
    $actual = $sel->get_result()->fetch_assoc();
    $sel->close();

    if (!$actual) {
        $response['message'] = 'Informe no encontrado.';
        echo json_encode($response);
        exit();
    }
    if (!eco_puede_gestionar_informe($rol, $ecografista_id, (int)$actual['ecografista_id'])) {
        http_response_code(403);
        $response['message'] = 'No puedes editar un informe de otro profesional.';
        echo json_encode($response);
        exit();
    }
    if (in_array($actual['estado'], ['firmado', 'anulado'], true)) {
        $response['message'] = 'Un informe ' . eco_informe_estado_label($actual['estado']) . ' no se puede editar.';
        echo json_encode($response);
        exit();
    }

    $conex->begin_transaction();
    try {
        if ($accion === 'finalizar') {
            $numero = $actual['numero_informe'] ?: eco_siguiente_numero_informe($conex);
            $up = $conex->prepare(
                "UPDATE informes_estudios
                    SET datos_clinicos = ?, esquema_version = ?, tipo_ecografia_id = ?,
                        estado = 'finalizado', numero_informe = ?, finalizado_en = NOW()
                  WHERE id = ?"
            );
            $up->bind_param("sissi", $datos_json, $esquema_version, $tipo_ecografia_id, $numero, $informe_id);
        } else {
            $numero = $actual['numero_informe'];
            $up = $conex->prepare(
                "UPDATE informes_estudios
                    SET datos_clinicos = ?, esquema_version = ?, tipo_ecografia_id = ?, estado = 'borrador'
                  WHERE id = ?"
            );
            $up->bind_param("siii", $datos_json, $esquema_version, $tipo_ecografia_id, $informe_id);
        }
        $up->execute();
        $up->close();
        $conex->commit();

        $response['success']        = true;
        $response['message']        = $accion === 'finalizar' ? 'Informe finalizado.' : 'Borrador actualizado.';
        $response['informe_id']     = $informe_id;
        $response['numero_informe'] = $numero;

        eco_facturar_si_aplica($conex, $paciente_id, $ecografista_id, $tipo_ecografia_id, $response);
    } catch (\Throwable $e) {
        $conex->rollback();
        error_log('guardar_informe_estudio update: ' . $e->getMessage());
        $response['message'] = 'No se pudo guardar el informe. Inténtalo de nuevo.';
    }
    echo json_encode($response);
    $conex->close();
    exit();
}

// ---------------------------------------------------------------------------
// MODO CREACION: nuevo informe (borrador sin numero / finalizado con correlativo).
// ---------------------------------------------------------------------------
$conex->begin_transaction();
try {
    if ($accion === 'finalizar') {
        $numero        = eco_siguiente_numero_informe($conex);
        $estado        = 'finalizado';
        $ins = $conex->prepare(
            "INSERT INTO informes_estudios
                (paciente_id, ecografista_id, tipo_ecografia_id, numero_informe, estado, finalizado_en, datos_clinicos, esquema_version)
             VALUES (?, ?, ?, ?, 'finalizado', NOW(), ?, ?)"
        );
        $ins->bind_param("iiissi", $paciente_id, $ecografista_id, $tipo_ecografia_id, $numero, $datos_json, $esquema_version);
    } else {
        $numero = null;
        $ins = $conex->prepare(
            "INSERT INTO informes_estudios
                (paciente_id, ecografista_id, tipo_ecografia_id, numero_informe, estado, datos_clinicos, esquema_version)
             VALUES (?, ?, ?, NULL, 'borrador', ?, ?)"
        );
        $ins->bind_param("iiisi", $paciente_id, $ecografista_id, $tipo_ecografia_id, $datos_json, $esquema_version);
    }
    $ins->execute();
    $nuevo_id = $ins->insert_id;
    $ins->close();
    $conex->commit();

    $response['success']        = true;
    $response['message']        = $accion === 'finalizar' ? 'Informe finalizado correctamente.' : 'Borrador guardado.';
    $response['informe_id']     = $nuevo_id;
    $response['numero_informe'] = $numero;

    eco_facturar_si_aplica($conex, $paciente_id, $ecografista_id, $tipo_ecografia_id, $response);
} catch (\Throwable $e) {
    $conex->rollback();
    error_log('guardar_informe_estudio insert: ' . $e->getMessage());
    $response['message'] = 'No se pudo guardar el informe. Inténtalo de nuevo.';
}

$conex->close();
echo json_encode($response);
