<?php
/**
 * Borra un archivo de informe (registro + binario). Solo autor/admin del informe.
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../lib/informes/informes.php';
require_once __DIR__ . '/../lib/informes/archivos.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

api_json();
$response = ['success' => false, 'message' => 'Ocurrio un error inesperado.'];

api_require_roles(['ecografista', 'administrador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo no permitido.';
    echo json_encode($response);
    exit();
}
api_require_csrf();

$archivo_id = isset($_POST['archivo_id']) ? (int)$_POST['archivo_id'] : 0;
if ($archivo_id <= 0) {
    $response['message'] = 'Archivo no valido.';
    echo json_encode($response);
    exit();
}

$a = eco_archivo_con_informe($conex, $archivo_id);
if (!$a) {
    $response['message'] = 'Archivo no encontrado.';
    echo json_encode($response);
    exit();
}

$rol = (string)$_SESSION['rol'];
$uid = (int)$_SESSION['usuario_id'];
if (!eco_puede_gestionar_informe($rol, $uid, (int)$a['ecografista_id'])) {
    http_response_code(403);
    $response['message'] = 'No puedes borrar archivos de un informe de otro profesional.';
    echo json_encode($response);
    exit();
}
if ($a['informe_estado'] === 'firmado') {
    $response['message'] = 'No se pueden borrar archivos de un informe firmado.';
    echo json_encode($response);
    exit();
}

if (eco_archivo_borrar($conex, $a)) {
    eco_auditar($conex, 'informe_archivo_borrado', [
        'entidad' => 'informe', 'entidad_id' => (int)$a['informe_id'],
        'detalle' => ['archivo_id' => $archivo_id],
    ]);
    $response = ['success' => true, 'message' => 'Archivo eliminado.'];
}

echo json_encode($response);
