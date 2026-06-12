<?php
/**
 * Sirve un archivo de informe via TOKEN publico (sin login). Fase 3 (b).
 *
 * Pensado para los binarios incrustados en resultado.php (imagenes inline y
 * descargas de adjuntos). NO consume aperturas del token: solo verifica que el
 * token siga vigente y que el archivo pertenezca al informe de ese token.
 */
include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../lib/informes/archivos.php';
require_once __DIR__ . '/../lib/core/tokens.php';

$raw        = isset($_GET['t']) ? (string)$_GET['t'] : '';
$archivo_id = isset($_GET['a']) ? (int)$_GET['a'] : 0;

$est = eco_token_verificar($conex, $raw);
if (!$est['ok'] || $archivo_id <= 0) {
    http_response_code(403);
    exit('Enlace no valido o expirado.');
}

$a = eco_archivo_con_informe($conex, $archivo_id);
if (!$a || (int)$a['informe_id'] !== (int)$est['informe_id']) {
    http_response_code(404);
    exit('Archivo no encontrado.');
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
