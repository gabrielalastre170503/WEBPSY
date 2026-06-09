<?php
/**
 * Lista (JSON) los archivos de un informe. Acceso: autor/admin o paciente dueno.
 */
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/informes.php';
require_once __DIR__ . '/lib/archivos.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'archivos' => []]);
    exit;
}

$informe_id = isset($_GET['informe_id']) ? (int)$_GET['informe_id'] : 0;
if ($informe_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Informe invalido', 'archivos' => []]);
    exit;
}

$st = $conex->prepare("SELECT ecografista_id, paciente_id FROM informes_estudios WHERE id = ?");
$st->bind_param('i', $informe_id);
$st->execute();
$inf = $st->get_result()->fetch_assoc();
$st->close();
if (!$inf) {
    echo json_encode(['success' => false, 'message' => 'Informe no encontrado', 'archivos' => []]);
    exit;
}

$rol = (string)($_SESSION['rol'] ?? '');
$uid = (int)$_SESSION['usuario_id'];
$puede_editar = eco_puede_gestionar_informe($rol, $uid, (int)$inf['ecografista_id']);
$puede_ver    = $puede_editar || ($rol === 'paciente' && $uid === (int)$inf['paciente_id']);
if (!$puede_ver) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso', 'archivos' => []]);
    exit;
}

$archivos = array_map(static function (array $a): array {
    return [
        'id'        => (int)$a['id'],
        'categoria' => $a['categoria'],
        'nombre'    => $a['nombre_original'],
        'mime'      => $a['mime'],
        'tamano'    => (int)$a['tamano'],
        'es_imagen' => (strpos((string)$a['mime'], 'image/') === 0),
        'url'       => 'descargar_archivo_informe.php?id=' . (int)$a['id'],
        'creado_en' => $a['creado_en'],
    ];
}, eco_archivos_de_informe($conex, $informe_id));

echo json_encode(['success' => true, 'archivos' => $archivos, 'puede_editar' => $puede_editar]);
