<?php
/**
 * exportar_reporte.php — Descarga CSV de un reporte (Fase 6 BI).
 *
 * Parametros GET:
 *   r      = resumen | tipos | ecografistas   (default: resumen)
 *   desde  = Y-m-d   hasta = Y-m-d            (default: mes actual)
 * Acceso: administrador o recepcionista.
 */
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/reportes/reportes.php';

api_require_roles(['administrador', 'recepcionista', 'ecografista']);

// Un ecografista solo exporta sus propios datos (null = sin filtro para admin/recep).
$ecoId = (api_rol() === 'ecografista') ? api_uid() : null;

[$desde, $hasta] = eco_reporte_rango($_GET['desde'] ?? null, $_GET['hasta'] ?? null);
$r = in_array($_GET['r'] ?? '', ['tipos', 'ecografistas'], true) ? $_GET['r'] : 'resumen';
// El desglose por ecografista no aplica cuando el reporte ya está scopeado a uno.
if ($ecoId && $r === 'ecografistas') { $r = 'resumen'; }

/* ── Formato PDF: reporte completo (resumen + desgloses) ── */
if (($_GET['formato'] ?? '') === 'pdf') {
    require_once __DIR__ . '/../lib/core/pdf_simple.php';
    require_once __DIR__ . '/../lib/facturacion/facturacion.php';

    $k       = eco_reporte_resumen($conex, $desde, $hasta, $ecoId);
    $tipos   = eco_reporte_por_tipo($conex, $desde, $hasta, $ecoId);
    $ecos    = $ecoId ? [] : eco_reporte_por_ecografista($conex, $desde, $hasta);
    $metodos = eco_reporte_por_metodo_pago($conex, $desde, $hasta, $ecoId);

    $pdf = new EcoPdf();
    $pdf->setFont(17, true);
    $pdf->setColor(2, 177, 244);
    $pdf->text('Reporte de actividad y facturacion');
    $pdf->setFont(10);
    $pdf->setColor(90, 90, 90);
    $pdf->text('Periodo: ' . $desde . ' a ' . $hasta . '  ·  Generado: ' . date('d/m/Y H:i'));
    $pdf->setColor(0, 0, 0);

    $pdf->heading('Resumen');
    $pdf->setFont(11);
    $pdf->keyValue('Citas totales', (string)$k['citas']);
    $pdf->keyValue('Completadas', (string)$k['completadas']);
    $pdf->keyValue('Confirmadas', (string)$k['confirmadas']);
    $pdf->keyValue('Pendientes', (string)$k['pendientes']);
    $pdf->keyValue('Canceladas', (string)$k['canceladas']);
    $pdf->keyValue('Pacientes distintos', (string)$k['pacientes']);
    $pdf->keyValue('Facturado', eco_money($k['facturado']));
    $pdf->keyValue('Cobrado', eco_money($k['cobrado']));
    $pdf->keyValue('Saldo pendiente', eco_money($k['saldo']));
    $pdf->keyValue('Tasa de cobro', $k['tasa_cobro'] . '%');
    $pdf->keyValue('Ausencias (no-show)', $k['no_show'] . '  (' . $k['tasa_no_show'] . '%)');
    $sat = eco_reporte_satisfaccion($conex, $desde, $hasta, $ecoId);
    $pdf->keyValue('Satisfaccion', $sat['respuestas'] > 0 ? ($sat['promedio'] . '/5  (' . $sat['respuestas'] . ' resp.)') : 'Sin respuestas');

    $pdf->heading('Por tipo de estudio');
    if (!$tipos) { $pdf->text('Sin datos.'); }
    foreach ($tipos as $t) {
        $pdf->keyValue($t['tipo'], $t['citas'] . ' citas  ·  ' . $t['completadas'] . ' compl.  ·  ' . eco_money($t['cobrado']));
    }

    if (!$ecoId) {
        $pdf->heading('Por ecografista');
        if (!$ecos) { $pdf->text('Sin datos.'); }
        foreach ($ecos as $e) {
            $pdf->keyValue($e['ecografista'], $e['citas'] . ' citas  ·  ' . $e['pacientes'] . ' pac.  ·  ' . eco_money($e['cobrado']));
        }
    }

    $pdf->heading('Por metodo de pago');
    if (!$metodos) { $pdf->text('Sin pagos registrados.'); }
    foreach ($metodos as $m) {
        $pdf->keyValue($m['metodo'], $m['pagos'] . ' pagos  ·  ' . eco_money($m['cobrado']));
    }

    $bin = $pdf->output();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="reporte_' . $desde . '_a_' . $hasta . '.pdf"');
    header('Content-Length: ' . strlen($bin));
    echo $bin;
    $conex->close();
    exit;
}

$fname = "reporte_{$r}_{$desde}_a_{$hasta}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 para que Excel respete acentos

if ($r === 'tipos') {
    fputcsv($out, ['Tipo de estudio', 'Citas', 'Completadas', 'Facturado', 'Cobrado']);
    foreach (eco_reporte_por_tipo($conex, $desde, $hasta, $ecoId) as $fila) {
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
    $k = eco_reporte_resumen($conex, $desde, $hasta, $ecoId);
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
    fputcsv($out, ['Ausencias (no-show)', $k['no_show']]);
    fputcsv($out, ['Tasa no-show (%)', $k['tasa_no_show']]);
    $sat = eco_reporte_satisfaccion($conex, $desde, $hasta, $ecoId);
    fputcsv($out, ['Satisfacción (promedio 1-5)', $sat['respuestas'] > 0 ? $sat['promedio'] : '']);
    fputcsv($out, ['Respuestas de encuesta', $sat['respuestas']]);
}

fclose($out);
$conex->close();
