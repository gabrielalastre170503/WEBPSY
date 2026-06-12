<?php
/**
 * descargar_historial.php — PDF del historial clínico del paciente en sesión.
 * Solo el propio paciente (datos propios). Incluye datos personales, informes
 * de estudios (finalizados/firmados) y citas. Sin dependencias externas (EcoPdf).
 */
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/core/pdf_simple.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'paciente') {
    http_response_code(403);
    exit('Acceso no autorizado.');
}
$uid = (int)$_SESSION['usuario_id'];

$fmt = static function (?string $v): string {
    return ($v && $v !== '0000-00-00' && strtotime($v)) ? date('d/m/Y', strtotime($v)) : '—';
};

// --- Datos del paciente ---
$st = $conex->prepare("SELECT nombre_completo, cedula,
        TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad,
        correo, telefono, direccion, fecha_nacimiento, fecha_registro
    FROM usuarios WHERE id = ? AND rol = 'paciente'");
$st->bind_param('i', $uid);
$st->execute();
$pac = $st->get_result()->fetch_assoc();
$st->close();

if (!$pac) {
    http_response_code(404);
    exit('Paciente no encontrado.');
}

// --- Informes de estudios (solo finalizados/firmados) ---
$informes = [];
$st = $conex->prepare("SELECT ie.numero_informe, ie.estado,
        COALESCE(ie.fecha_estudio, DATE(ie.creado_en)) AS fecha,
        t.nombre AS tipo, u.nombre_completo AS eco
    FROM informes_estudios ie
    LEFT JOIN tipos_ecografias t ON t.id = ie.tipo_ecografia_id
    LEFT JOIN usuarios u ON u.id = ie.ecografista_id
    WHERE ie.paciente_id = ? AND ie.estado IN ('finalizado','firmado')
    ORDER BY fecha DESC");
$st->bind_param('i', $uid);
$st->execute();
$informes = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// --- Citas ---
$citas = [];
$st = $conex->prepare("SELECT c.estado,
        COALESCE(c.fecha_cita, c.fecha_solicitud) AS fecha,
        t.nombre AS tipo, u.nombre_completo AS eco
    FROM citas c
    LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
    LEFT JOIN usuarios u ON u.id = c.ecografista_id
    WHERE c.paciente_id = ?
    ORDER BY fecha DESC");
$st->bind_param('i', $uid);
$st->execute();
$citas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
$conex->close();

// --- PDF ---
$pdf = new EcoPdf();
$pdf->setFont(18, true);
$pdf->setColor(2, 177, 244);
$pdf->text('Historial clínico');
$pdf->setFont(10);
$pdf->setColor(90, 90, 90);
$pdf->text('EcoMadelleine · Centro de Diagnóstico  ·  Generado: ' . date('d/m/Y H:i'));
$pdf->setColor(0, 0, 0);

$pdf->heading('Datos del paciente');
$pdf->setFont(11);
$pdf->keyValue('Nombre', (string)$pac['nombre_completo']);
$pdf->keyValue('Cédula', (string)($pac['cedula'] ?: '—'));
$pdf->keyValue('Edad', ($pac['edad'] !== null && $pac['edad'] !== '') ? $pac['edad'] . ' años' : '—');
$pdf->keyValue('Fecha de nacimiento', $fmt($pac['fecha_nacimiento'] ?? null));
$pdf->keyValue('Correo', (string)($pac['correo'] ?: '—'));
$pdf->keyValue('Teléfono', (string)($pac['telefono'] ?: '—'));
$pdf->keyValue('Dirección', (string)($pac['direccion'] ?: '—'));
$pdf->keyValue('Paciente desde', $fmt($pac['fecha_registro'] ?? null));

$pdf->heading('Informes de estudios (' . count($informes) . ')');
$pdf->setFont(11);
if (!$informes) {
    $pdf->text('Sin informes disponibles.');
} else {
    foreach ($informes as $i) {
        $linea = $fmt($i['fecha']) . '   ' . ($i['tipo'] ?: 'Ecografía')
            . '   (Nº ' . ($i['numero_informe'] ?: '—') . ', ' . ucfirst((string)$i['estado'])
            . ($i['eco'] ? ', ' . $i['eco'] : '') . ')';
        $pdf->text('•  ' . $linea, 4);
    }
}

$pdf->heading('Citas (' . count($citas) . ')');
$pdf->setFont(11);
if (!$citas) {
    $pdf->text('Sin citas registradas.');
} else {
    foreach ($citas as $c) {
        $linea = $fmt($c['fecha']) . '   ' . ($c['tipo'] ?: 'Cita')
            . '   (' . ucfirst((string)$c['estado'])
            . ($c['eco'] ? ', ' . $c['eco'] : '') . ')';
        $pdf->text('•  ' . $linea, 4);
    }
}

$pdf->ln(10);
$pdf->setFont(9);
$pdf->setColor(120, 120, 120);
$pdf->text('Documento generado automáticamente. Para la versión completa de cada informe, consulta la sección "Mis Informes".');

$bin = $pdf->output();
$slug = preg_replace('/[^A-Za-z0-9]+/', '_', (string)($pac['cedula'] ?: $uid));
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="historial_clinico_' . $slug . '_' . date('Ymd') . '.pdf"');
header('Content-Length: ' . strlen($bin));
echo $bin;
