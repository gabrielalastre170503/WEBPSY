<?php
/**
 * exportar_reporte.php — Descarga CSV de un reporte (Fase 6 BI).
 *
 * Parametros GET:
 *   r      = resumen | tipos | ecografistas   (default: resumen)
 *   desde  = Y-m-d   hasta = Y-m-d            (default: mes actual)
 * Acceso: administrador o recepcionista.
 */
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/reportes.php';

api_require_roles(['administrador', 'recepcionista']);

[$desde, $hasta] = eco_reporte_rango($_GET['desde'] ?? null, $_GET['hasta'] ?? null);
$r = in_array($_GET['r'] ?? '', ['tipos', 'ecografistas'], true) ? $_GET['r'] : 'resumen';

$fname = "reporte_{$r}_{$desde}_a_{$hasta}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 para que Excel respete acentos

if ($r === 'tipos') {
    fputcsv($out, ['Tipo de estudio', 'Citas', 'Completadas', 'Facturado', 'Cobrado']);
    foreach (eco_reporte_por_tipo($conex, $desde, $hasta) as $fila) {
        fputcsv($out, [$fila['tipo'], $fila['citas'], $fila['completadas'],
            number_format($fila['facturado'], 2, '.', ''), number_format($fila['cobrado'], 2, '.', '')]);
    }
} elseif ($r === 'ecografistas') {
    fputcsv($out, ['Ecografista', 'Citas', 'Completadas', 'Pacientes', 'Cobrado']);
    foreach (eco_reporte_por_ecografista($conex, $desde, $hasta) as $fila) {
        fputcsv($out, [$fila['ecografista'], $fila['citas'], $fila['completadas'],
            $fila['pacientes'], number_format($fila['cobrado'], 2, '.', '')]);
    }
} else {
    $k = eco_reporte_resumen($conex, $desde, $hasta);
    fputcsv($out, ['Reporte de actividad y facturacion']);
    fputcsv($out, ['Periodo', $desde . ' a ' . $hasta]);
    fputcsv($out, []);
    fputcsv($out, ['Metrica', 'Valor']);
    fputcsv($out, ['Citas totales', $k['citas']]);
    fputcsv($out, ['Completadas', $k['completadas']]);
    fputcsv($out, ['Confirmadas', $k['confirmadas']]);
    fputcsv($out, ['Pendientes', $k['pendientes']]);
    fputcsv($out, ['Canceladas', $k['canceladas']]);
    fputcsv($out, ['Pacientes distintos', $k['pacientes']]);
    fputcsv($out, ['Facturado', number_format($k['facturado'], 2, '.', '')]);
    fputcsv($out, ['Cobrado', number_format($k['cobrado'], 2, '.', '')]);
    fputcsv($out, ['Saldo pendiente', number_format($k['saldo'], 2, '.', '')]);
    fputcsv($out, ['Exonerados', $k['exonerados']]);
    fputcsv($out, ['Tasa de cobro (%)', $k['tasa_cobro']]);
}

fclose($out);
$conex->close();
