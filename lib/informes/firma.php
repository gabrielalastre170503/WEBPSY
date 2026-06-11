<?php
/**
 * lib/informes/firma.php — Fase 3 (c): firma digital trazable de informes.
 *
 * Modelo (honesto sobre su alcance): NO es una firma cualificada con PKI/TSA
 * externa. Es un SELLO DEL SERVIDOR, criptograficamente verificable, que da:
 *   - INTEGRIDAD: huella SHA-256 del contenido canonico del informe. Si alguien
 *     altera los datos clinicos, la huella deja de coincidir.
 *   - AUTENTICIDAD: HMAC-SHA256 (clave secreta del servidor, ECO_FIRMA_KEY) que
 *     ata huella + identidad del firmante + fecha. Sin la clave no se puede
 *     falsificar el sello.
 *   - SELLO DE TIEMPO: la fecha/hora de firma queda atada dentro del HMAC.
 *
 * El artefacto firmado es un PDF autocontenido (lib/core/pdf_simple.php) que incrusta
 * la huella, el sello y la fecha, mas una URL de verificacion.
 */

require_once __DIR__ . '/../core/pdf_simple.php';

if (!function_exists('eco_firma_key')) {

    if (!defined('ECO_FIRMA_VERSION')) {
        define('ECO_FIRMA_VERSION', 'eco-hmac-sha256-v1');
    }

    /** Clave secreta del sello (de .env). Degrada con aviso si falta. */
    function eco_firma_key(): string
    {
        $k = function_exists('eco_env') ? (string)eco_env('ECO_FIRMA_KEY', '') : (string)getenv('ECO_FIRMA_KEY');
        if ($k === '') {
            error_log('firma: ECO_FIRMA_KEY no definida en .env; usando clave derivada (menos segura).');
            $k = hash('sha256', __DIR__ . '|eco-firma-fallback');
        }
        return $k;
    }

    /** Cadena canonica deterministica del contenido a firmar. */
    function eco_firma_canonical(array $meta, string $datosRaw): string
    {
        return "ECOFIRMA-v1\n"
            . 'informe_id:'      . (int)($meta['id'] ?? 0) . "\n"
            . 'numero:'          . (string)($meta['numero_informe'] ?? '') . "\n"
            . 'paciente_id:'     . (int)($meta['paciente_id'] ?? 0) . "\n"
            . 'tipo:'            . (int)($meta['tipo_ecografia_id'] ?? 0) . "\n"
            . 'fecha_estudio:'   . (string)($meta['fecha_estudio'] ?? '') . "\n"
            . 'esquema_version:' . (int)($meta['esquema_version'] ?? 0) . "\n"
            . 'datos:' . $datosRaw;
    }

    function eco_firma_hash(string $canonical): string
    {
        return hash('sha256', $canonical);
    }

    /** Sello HMAC que ata huella + identidad + tiempo. */
    function eco_firma_sello(int $informeId, string $numero, string $docHash, int $firmanteId, string $fechaFirma): string
    {
        $payload = ECO_FIRMA_VERSION . '|' . $informeId . '|' . $numero . '|' . $docHash
            . '|' . $firmanteId . '|' . $fechaFirma;
        return hash_hmac('sha256', $payload, eco_firma_key());
    }

    /**
     * Verifica integridad (huella) y autenticidad (sello) de un informe firmado
     * contra su contenido actual.
     *
     * @param array  $row      fila de informes_estudios (id, numero_informe,
     *                         paciente_id, tipo_ecografia_id, fecha_estudio,
     *                         esquema_version, firmado_por, fecha_firma,
     *                         documento_sha256, sello_firma)
     * @param string $datosRaw datos_clinicos crudos (tal cual columna)
     * @return array{integro:bool, sello_valido:bool, hash_calculado:string, hash_guardado:string}
     */
    function eco_firma_verificar(array $row, string $datosRaw): array
    {
        $canonical = eco_firma_canonical($row, $datosRaw);
        $hashCalc  = eco_firma_hash($canonical);
        $hashGuard = (string)($row['documento_sha256'] ?? '');
        $integro   = $hashGuard !== '' && hash_equals($hashGuard, $hashCalc);

        $selloCalc = eco_firma_sello(
            (int)$row['id'], (string)($row['numero_informe'] ?? ''), $hashGuard,
            (int)($row['firmado_por'] ?? 0), (string)($row['fecha_firma'] ?? '')
        );
        $selloOk = !empty($row['sello_firma']) && hash_equals((string)$row['sello_firma'], $selloCalc);

        return [
            'integro'        => $integro,
            'sello_valido'   => $selloOk,
            'hash_calculado' => $hashCalc,
            'hash_guardado'  => $hashGuard,
        ];
    }

    /** Pares [etiqueta, valor] de los campos no vacios de una seccion. */
    function eco_firma_pares(array $campos, array $vals): array
    {
        $pares = [];
        foreach ($campos as $c) {
            $tipo = $c['tipo'] ?? 'text';
            if ($tipo === 'info') continue;
            $nombre = $c['nombre'] ?? '';
            $et     = $c['etiqueta'] ?? $nombre;
            $v      = $vals[$nombre] ?? null;
            if ($tipo === 'checkbox') {
                $v = !empty($v) ? 'Sí' : 'No';
            }
            $v = is_array($v) ? implode(', ', $v) : (string)($v ?? '');
            if (trim($v) === '') continue;          // omitir vacios en el PDF
            $u = $c['unidad'] ?? '';
            if ($u !== '') $v .= ' ' . $u;
            $pares[] = [$et, $v];
        }
        return $pares;
    }

    /** Aplana esquema+datos a [ [titulo, [[label,value],...]], ... ]. */
    function eco_firma_sumario(array $esquema, array $datos): array
    {
        $out = [];
        foreach (($esquema['secciones'] ?? []) as $sec) {
            $sid    = $sec['id'] ?? '';
            $tipo   = $sec['tipo_seccion'] ?? 'normal';
            $titulo = $sec['titulo'] ?? '';
            $campos = $sec['campos'] ?? [];
            if ($tipo === 'par') {
                $lados = $sec['lados'] ?? ['A', 'B'];
                $ids   = $sec['ids_lados'] ?? [$sid . '_a', $sid . '_b'];
                foreach ($lados as $idx => $lado) {
                    $slid  = $ids[$idx] ?? ($sid . '_' . $idx);
                    $pares = eco_firma_pares($campos, $datos[$slid] ?? []);
                    if ($pares) $out[] = [trim($titulo . ' — ' . $lado), $pares];
                }
            } else {
                $pares = eco_firma_pares($campos, $datos[$sid] ?? []);
                if ($pares) $out[] = [$titulo, $pares];
            }
        }
        return $out;
    }

    /**
     * Construye el PDF firmado del informe. Devuelve string binario.
     *
     * @param array $ctx numero_informe, paciente_nombre, paciente_cedula,
     *                   ecografista_nombre, tipo_nombre, esquema, datos,
     *                   fecha_estudio, fecha_firma, docHash, sello, verify_url
     */
    function eco_firma_pdf(array $ctx): string
    {
        $pdf = new EcoPdf();

        // Membrete
        $pdf->setColor(1, 74, 130); $pdf->setFont(17, true);
        $pdf->text('EcoMadelleine');
        $pdf->setColor(90, 104, 120); $pdf->setFont(10, false);
        $pdf->text('Centro de Diagnóstico por Ecografía');
        $pdf->setColor(2, 177, 244); $pdf->rule(1.2);

        $pdf->setColor(26, 35, 50); $pdf->setFont(14, true);
        $titulo = mb_strtoupper('Reporte Ecográfico '
            . preg_replace('/^Ecograf[ií]a\s+/i', '', (string)$ctx['tipo_nombre']), 'UTF-8');
        $pdf->text($titulo);
        $pdf->ln(6);

        // Metadatos
        $pdf->setFont(10.5, false); $pdf->setColor(26, 35, 50);
        $pdf->keyValue('Informe N.º', (string)$ctx['numero_informe']);
        $ci = $ctx['paciente_cedula'] ? '  ·  CI ' . $ctx['paciente_cedula'] : '';
        $pdf->keyValue('Paciente', $ctx['paciente_nombre'] . $ci);
        $pdf->keyValue('Fecha del estudio', (string)$ctx['fecha_estudio']);
        $pdf->keyValue('Profesional', (string)$ctx['ecografista_nombre']);

        // Contenido clinico
        foreach (eco_firma_sumario($ctx['esquema'], $ctx['datos']) as [$tit, $pares]) {
            $pdf->heading($tit);
            $pdf->setFont(10.5, false); $pdf->setColor(26, 35, 50);
            foreach ($pares as [$l, $v]) {
                $pdf->keyValue($l, $v);
            }
        }

        // Bloque de firma / sello
        $pdf->ln(12);
        $pdf->setColor(7, 89, 133); $pdf->setFont(9.5, false);
        $pdf->box([
            'DOCUMENTO FIRMADO ELECTRÓNICAMENTE',
            'Firmado por: ' . $ctx['ecografista_nombre'],
            'Fecha y hora de firma: ' . $ctx['fecha_firma'],
            'Algoritmo: SHA-256 + HMAC (sello del servidor)',
            'Huella SHA-256: ' . $ctx['docHash'],
            'Sello: ' . substr((string)$ctx['sello'], 0, 48) . '…',
        ]);
        $pdf->ln(8);
        $pdf->setColor(120, 130, 145); $pdf->setFont(8.5, false);
        if (!empty($ctx['verify_url'])) {
            $pdf->text('Verifique la integridad de este documento en: ' . $ctx['verify_url']);
        }
        $pdf->text('La huella SHA-256 identifica de forma única el contenido del informe. '
            . 'Cualquier alteración posterior modifica la huella y delata el cambio.');

        return $pdf->output();
    }
}
