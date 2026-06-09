<?php
/**
 * Firma un informe: transicion finalizado -> firmado (Fase 3c).
 * Solo el ecografista autor (o un administrador). Al firmar:
 *   - se congela el contenido en una huella SHA-256 (integridad),
 *   - se sella con HMAC-SHA256 atado a firmante + fecha (autenticidad),
 *   - se genera un PDF firmado autocontenido (categoria 'pdf_firmado'),
 *   - el informe queda bloqueado para edicion.
 */
session_start();
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/informes.php';
require_once __DIR__ . '/lib/archivos.php';
require_once __DIR__ . '/lib/firma.php';
require_once __DIR__ . '/lib/seguridad.php';
require_once __DIR__ . '/lib/notificaciones.php';

api_json();
$response = ['success' => false, 'message' => 'Ocurrio un error inesperado.'];

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador'], true)) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo no permitido.';
    echo json_encode($response);
    exit();
}
api_require_csrf();

$rol        = (string)$_SESSION['rol'];
$usuario_id = (int)$_SESSION['usuario_id'];
$informe_id = isset($_POST['informe_id']) ? (int)$_POST['informe_id'] : 0;

if ($informe_id <= 0) {
    $response['message'] = 'Informe no valido.';
    echo json_encode($response);
    exit();
}

$sql = "SELECT inf.id, inf.numero_informe, inf.estado, inf.datos_clinicos, inf.esquema_version,
               inf.fecha_estudio, inf.paciente_id, inf.ecografista_id, inf.tipo_ecografia_id, inf.creado_en,
               pac.nombre_completo AS paciente_nombre, pac.cedula AS paciente_cedula,
               eco.nombre_completo AS ecografista_nombre,
               t.nombre AS tipo_nombre, t.esquema_campos
        FROM informes_estudios inf
        JOIN usuarios pac       ON pac.id = inf.paciente_id
        JOIN usuarios eco       ON eco.id = inf.ecografista_id
        JOIN tipos_ecografias t ON t.id   = inf.tipo_ecografia_id
        WHERE inf.id = ?";
$sel = $conex->prepare($sql);
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
    $response['message'] = 'Solo el profesional autor puede firmar este informe.';
    echo json_encode($response);
    exit();
}
if ($inf['estado'] !== 'finalizado') {
    $response['message'] = 'Solo se puede firmar un informe finalizado (estado actual: '
        . eco_informe_estado_label($inf['estado']) . ').';
    echo json_encode($response);
    exit();
}

// Sello criptografico
$datosRaw  = (string)$inf['datos_clinicos'];
$fechaFirma = date('Y-m-d H:i:s');
$canonical = eco_firma_canonical($inf, $datosRaw);
$docHash   = eco_firma_hash($canonical);
$sello     = eco_firma_sello($informe_id, (string)$inf['numero_informe'], $docHash, $usuario_id, $fechaFirma);

// URL de verificacion para incrustar en el PDF
$https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443);
$dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
$verifyUrl = ($https ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost')
    . $dir . '/verificar_firma.php?informe_id=' . $informe_id . '&c=' . substr($sello, 0, 16);

$esquema = json_decode((string)$inf['esquema_campos'], true) ?: ['secciones' => []];
$datos   = json_decode($datosRaw, true) ?: [];
$fechaEstudioFmt = !empty($inf['fecha_estudio'])
    ? date('d/m/Y', strtotime($inf['fecha_estudio']))
    : date('d/m/Y', strtotime($inf['creado_en']));

try {
    $pdfBytes = eco_firma_pdf([
        'numero_informe'     => $inf['numero_informe'],
        'paciente_nombre'    => $inf['paciente_nombre'],
        'paciente_cedula'    => $inf['paciente_cedula'],
        'ecografista_nombre' => $inf['ecografista_nombre'],
        'tipo_nombre'        => $inf['tipo_nombre'],
        'esquema'            => $esquema,
        'datos'              => $datos,
        'fecha_estudio'      => $fechaEstudioFmt,
        'fecha_firma'        => date('d/m/Y H:i', strtotime($fechaFirma)),
        'docHash'            => $docHash,
        'sello'              => $sello,
        'verify_url'         => $verifyUrl,
    ]);
} catch (\Throwable $e) {
    error_log('firmar_informe(pdf): ' . $e->getMessage());
    $response['message'] = 'No se pudo generar el PDF firmado.';
    echo json_encode($response);
    exit();
}

$conex->begin_transaction();
try {
    $up = $conex->prepare(
        "UPDATE informes_estudios
            SET estado='firmado', firmado_por=?, fecha_firma=?,
                documento_sha256=?, sello_firma=?, sello_version=?
          WHERE id=? AND estado='finalizado'"
    );
    $ver = ECO_FIRMA_VERSION;
    $up->bind_param('issssi', $usuario_id, $fechaFirma, $docHash, $sello, $ver, $informe_id);
    $up->execute();
    $aff = $up->affected_rows;
    $up->close();

    if ($aff !== 1) {
        throw new RuntimeException('El informe cambio de estado. Actualiza e intentalo de nuevo.');
    }

    $nombrePdf = 'Informe_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$inf['numero_informe']) . '_firmado.pdf';
    $archId = eco_archivo_guardar_contenido(
        $conex, $informe_id, $pdfBytes, 'pdf', 'application/pdf', 'pdf_firmado', $usuario_id, $nombrePdf
    );

    $conex->commit();

    eco_auditar($conex, 'informe_firmado', [
        'entidad' => 'informe', 'entidad_id' => $informe_id,
        'detalle' => ['hash' => $docHash, 'pdf_archivo_id' => $archId, 'sello_version' => $ver],
    ]);

    eco_notificar($conex, (int)$inf['paciente_id'], 'informe_firmado', 'Informe firmado disponible', [
        'mensaje' => 'Tu informe ' . $inf['numero_informe'] . ' está firmado y disponible para descargar.',
        'url'     => 'mis_informes_paciente.php',
        'icono'   => 'fa-solid fa-file-signature',
    ]);

    $response['success'] = true;
    $response['message'] = 'Informe firmado correctamente.';
    $response['hash']    = $docHash;
} catch (\Throwable $e) {
    $conex->rollback();
    error_log('firmar_informe: ' . $e->getMessage());
    $response['message'] = 'No se pudo firmar el informe. ' . $e->getMessage();
}

echo json_encode($response);
