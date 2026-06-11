<?php
/**
 * Sube una imagen ecografica o adjunto a un informe (Fase 3).
 * Solo el ecografista autor del informe o un administrador.
 */
session_start();
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/informes/informes.php';
require_once __DIR__ . '/lib/informes/archivos.php';
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
$categoria  = (string)($_POST['categoria'] ?? 'imagen');

if ($informe_id <= 0) {
    $response['message'] = 'Informe no valido.';
    echo json_encode($response);
    exit();
}

$sel = $conex->prepare("SELECT ecografista_id, estado FROM informes_estudios WHERE id = ?");
$sel->bind_param('i', $informe_id);
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
    $response['message'] = 'No puedes adjuntar archivos a un informe de otro profesional.';
    echo json_encode($response);
    exit();
}
if ($inf['estado'] === 'anulado') {
    $response['message'] = 'No se pueden adjuntar archivos a un informe anulado.';
    echo json_encode($response);
    exit();
}
if (!isset($_FILES['archivo'])) {
    $response['message'] = 'No se recibio ningun archivo.';
    echo json_encode($response);
    exit();
}

try {
    $id = eco_archivo_guardar($conex, $informe_id, $_FILES['archivo'], $categoria, $usuario_id);
    eco_auditar($conex, 'informe_archivo_subido', [
        'entidad' => 'informe', 'entidad_id' => $informe_id,
        'detalle' => ['archivo_id' => $id, 'categoria' => $categoria],
    ]);
    $response = ['success' => true, 'message' => 'Archivo subido.', 'archivo_id' => $id];
} catch (\Throwable $e) {
    error_log('subir_archivo_informe: ' . $e->getMessage());
    // Los RuntimeException de la lib llevan mensajes seguros para el usuario.
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
