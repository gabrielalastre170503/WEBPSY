<?php
/**
 * Anula un informe (finalizado/firmado -> anulado) con motivo obligatorio.
 * Los informes clinicos NUNCA se borran: se anulan dejando rastro de auditoria.
 * Solo el ecografista autor o un administrador.
 */
session_start();
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/informes/informes.php';
require_once __DIR__ . '/lib/seguridad/seguridad.php';

api_json();
$response = ['success' => false, 'message' => 'Ocurrio un error inesperado.'];

api_require_roles(['ecografista', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo no permitido.';
    echo json_encode($response);
    exit();
}

api_require_csrf();

$rol        = (string)$_SESSION['rol'];
$usuario_id = (int)$_SESSION['usuario_id'];
$informe_id = isset($_POST['informe_id']) ? (int)$_POST['informe_id'] : 0;
$motivo     = trim((string)($_POST['motivo'] ?? ''));

if ($informe_id <= 0) {
    $response['message'] = 'Informe no valido.';
    echo json_encode($response);
    exit();
}
if (mb_strlen($motivo) < 5) {
    $response['message'] = 'Indica el motivo de la anulacion (minimo 5 caracteres).';
    echo json_encode($response);
    exit();
}
if (mb_strlen($motivo) > 255) {
    $motivo = mb_substr($motivo, 0, 255);
}

$sel = $conex->prepare("SELECT ecografista_id, estado FROM informes_estudios WHERE id = ?");
$sel->bind_param("i", $informe_id);
$sel->execute();
$inf = $sel->get_result()->fetch_assoc();
$sel->close();

if (!$inf) {
    $response['message'] = 'Informe no encontrado.';
    echo json_encode($response);
    exit();
}
if (!eco_puede_gestionar_informe($rol, $usuario_id, (int)$inf['ecografista_id'])) {
    http_response_code(403);
    $response['message'] = 'No puedes anular un informe de otro profesional.';
    echo json_encode($response);
    exit();
}
if (!in_array($inf['estado'], ['finalizado', 'firmado'], true)) {
    $response['message'] = 'Solo se puede anular un informe finalizado o firmado (estado actual: ' . eco_informe_estado_label($inf['estado']) . ').';
    echo json_encode($response);
    exit();
}

$up = $conex->prepare(
    "UPDATE informes_estudios
        SET estado = 'anulado', anulado_por = ?, fecha_anulacion = NOW(), motivo_anulacion = ?
      WHERE id = ? AND estado IN ('finalizado','firmado')"
);
$up->bind_param("isi", $usuario_id, $motivo, $informe_id);

if ($up->execute() && $up->affected_rows === 1) {
    eco_auditar($conex, 'informe_anulado', ['entidad' => 'informe', 'entidad_id' => $informe_id, 'detalle' => ['motivo' => $motivo]]);
    $response['success'] = true;
    $response['message'] = 'Informe anulado.';
} else {
    error_log('anular_informe: ' . $up->error);
    $response['message'] = 'No se pudo anular el informe. Actualiza e inténtalo de nuevo.';
}
$up->close();
$conex->close();
echo json_encode($response);
