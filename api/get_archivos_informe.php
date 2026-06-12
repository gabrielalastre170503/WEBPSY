<?php
/**
 * Lista (JSON) los archivos de un informe. Acceso: autor/admin o paciente dueno.
 */
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/informes/informes.php';
require_once __DIR__ . '/../lib/informes/archivos.php';

api_json();
if (api_uid() <= 0) {
    api_fail('No autorizado', 403, ['archivos' => []]);
}

$informe_id = api_get_int('informe_id');
if ($informe_id <= 0) {
    api_fail('Informe invalido', 200, ['archivos' => []]);
}

$st = $conex->prepare("SELECT ecografista_id, paciente_id FROM informes_estudios WHERE id = ?");
$st->bind_param('i', $informe_id);
$st->execute();
$inf = $st->get_result()->fetch_assoc();
$st->close();
if (!$inf) {
    api_fail('Informe no encontrado', 200, ['archivos' => []]);
}

$rol = api_rol();
$uid = api_uid();
$puede_editar = eco_puede_gestionar_informe($rol, $uid, (int)$inf['ecografista_id']);
$puede_ver    = $puede_editar || ($rol === 'paciente' && $uid === (int)$inf['paciente_id']);
if (!$puede_ver) {
    api_fail('Sin acceso', 403, ['archivos' => []]);
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

api_ok(['archivos' => $archivos, 'puede_editar' => $puede_editar]);
