<?php
/**
 * Sirve un archivo de informe (imagen/adjunto) con control de acceso (Fase 3).
 * Acceso: el ecografista autor / un administrador, o el paciente dueno del informe.
 * Los binarios viven fuera del alcance HTTP directo; este handler es la unica via.
 */
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/informes/informes.php';
require_once __DIR__ . '/../lib/informes/archivos.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

$archivo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($archivo_id <= 0) {
    http_response_code(400);
    exit('Solicitud invalida.');
}

$a = eco_archivo_con_informe($conex, $archivo_id);
if (!$a) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$rol = (string)($_SESSION['rol'] ?? '');
$uid = (int)$_SESSION['usuario_id'];

$puede = eco_puede_gestionar_informe($rol, $uid, (int)$a['ecografista_id'])
      || ($rol === 'paciente' && $uid === (int)$a['paciente_id']);
if (!$puede) {
    http_response_code(403);
    exit('No tienes acceso a este archivo.');
}

$abs = eco_uploads_base() . '/' . $a['ruta_rel'];
if (!is_file($abs)) {
    http_response_code(404);
    exit('El archivo ya no existe.');
}

$mime   = $a['mime'] ?: 'application/octet-stream';
$inline = (strpos($mime, 'image/') === 0);   // imagenes inline; lo demas, descarga
$nombre = preg_replace('/[\r\n"]/', '', (string)($a['nombre_original'] ?: ('archivo_' . $archivo_id)));

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($abs));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nombre . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($abs);
exit;
