<?php
/**
 * Genera un enlace de resultados por token (sin login) para un informe. Fase 3 (b).
 * Solo el ecografista autor del informe o un administrador, y solo para informes
 * finalizados o firmados.
 */
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/informes/informes.php';
require_once __DIR__ . '/../lib/core/tokens.php';
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

$rol = (string)$_SESSION['rol'];
$uid = (int)$_SESSION['usuario_id'];
$informe_id = isset($_POST['informe_id']) ? (int)$_POST['informe_id'] : 0;
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
if (!eco_puede_gestionar_informe($rol, $uid, (int)$inf['ecografista_id'])) {
    http_response_code(403);
    $response['message'] = 'No puedes compartir un informe de otro profesional.';
    echo json_encode($response);
    exit();
}
if (!in_array($inf['estado'], ['finalizado', 'firmado'], true)) {
    $response['message'] = 'Solo se pueden compartir informes finalizados o firmados.';
    echo json_encode($response);
    exit();
}

// Parametros opcionales con topes de seguridad.
$horas = isset($_POST['expira_horas']) ? (int)$_POST['expira_horas'] : 72;
$horas = max(1, min($horas, 24 * 30));                       // 1 h .. 30 dias
$maxUsosIn = (string)($_POST['max_usos'] ?? '');
$maxUsos = ($maxUsosIn === '' || $maxUsosIn === '0')         // vacio/0 = sin tope
    ? null
    : max(1, min((int)$maxUsosIn, 100));

try {
    $tk = eco_token_crear($conex, $informe_id, [
        'expira_horas' => $horas,
        'max_usos'     => $maxUsos,
        'creado_por'   => $uid,
    ]);
    eco_auditar($conex, 'resultado_enlace_generado', [
        'entidad' => 'informe', 'entidad_id' => $informe_id,
        'detalle' => ['token_id' => $tk['id'], 'expira_en' => $tk['expira_en'], 'max_usos' => $maxUsos],
    ]);
    $response = [
        'success'   => true,
        'message'   => 'Enlace generado.',
        'url'       => eco_token_url($tk['raw']),
        'expira_en' => $tk['expira_en'],
        'max_usos'  => $maxUsos,
        'token_id'  => $tk['id'],
    ];
} catch (\Throwable $e) {
    error_log('generar_enlace_resultado: ' . $e->getMessage());
    $response['message'] = 'No se pudo generar el enlace.';
}

echo json_encode($response);
