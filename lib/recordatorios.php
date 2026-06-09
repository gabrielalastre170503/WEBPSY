<?php
/**
 * lib/recordatorios.php — Fase 4 (B): recordatorios de cita.
 *
 * Dispatcher que el cron (cron_recordatorios.php) ejecuta: busca citas
 * confirmadas/reprogramadas dentro de una ventana (24 h por defecto) que aun no
 * tienen recordatorio, y por cada una:
 *   - email automatico al paciente (motor SMTP de Fase 1),
 *   - notificacion in-app (Fase 4A),
 *   - marca en cita_recordatorios (idempotente: no se repite).
 *
 * WhatsApp: no hay API (de pago). Se genera un enlace "click-to-send" wa.me que
 * el staff puede usar manualmente; el envio queda como funcion pluggable
 * (eco_wa_link) para enchufar una API en el futuro.
 *
 * Migracion: database/migrations/2026_fase4_02_cita_recordatorios.sql
 */

require_once __DIR__ . '/correo_app.php';      // eco_enviar_correo
require_once __DIR__ . '/notificaciones.php';  // eco_notificar

if (!function_exists('eco_wa_link')) {

    /** Normaliza un telefono a digitos con codigo de pais (Venezuela +58 por defecto). */
    function eco_wa_numero(string $telefono): string
    {
        $d = preg_replace('/\D+/', '', $telefono);
        if ($d === '') return '';
        if (strlen($d) === 11 && $d[0] === '0') {
            $d = '58' . substr($d, 1);        // 0XXXXXXXXXX -> 58XXXXXXXXXX
        } elseif (strlen($d) === 10) {
            $d = '58' . $d;                   // sin 0 ni codigo de pais
        }
        return $d;
    }

    /** Enlace wa.me con mensaje pre-escrito. '' si el telefono no sirve. */
    function eco_wa_link(string $telefono, string $mensaje): string
    {
        $num = eco_wa_numero($telefono);
        if ($num === '') return '';
        return 'https://wa.me/' . $num . '?text=' . rawurlencode($mensaje);
    }

    /** Textos del recordatorio a partir de una fila de cita (con joins). */
    function eco_recordatorio_textos(array $c): array
    {
        $cuando = !empty($c['fecha_cita']) ? date('d/m/Y \a \l\a\s H:i', strtotime($c['fecha_cita'])) : '';
        $tipo   = $c['tipo_nombre'] ?: 'tu estudio';
        $nombre = trim((string)($c['paciente_nombre'] ?? ''));
        $primer = $nombre !== '' ? explode(' ', $nombre)[0] : '';
        $saludo = $primer !== '' ? ('Hola ' . $primer . ', ') : '';
        $texto  = $saludo . 'te recordamos tu cita de ' . $tipo . ($cuando ? ' el ' . $cuando : '') . ' en EcoMadelleine.';
        return [
            'titulo'        => 'Recordatorio de cita',
            'mensaje'       => 'Tu cita de ' . $tipo . ($cuando ? ' es el ' . $cuando : '') . '.',
            'email_subject' => 'Recordatorio de tu cita — EcoMadelleine',
            'email_body'    => $texto . "\n\nSi necesitas reprogramar o cancelar, ingresa a tu cuenta en EcoMadelleine.\n\nEste es un mensaje automatico, por favor no respondas a este correo.",
            'wa_texto'      => $texto,
        ];
    }

    /**
     * Procesa los recordatorios pendientes.
     *
     * @param array $opts ventana_horas (24), limit (100), dry_run (false),
     *                    canales (['email','in_app']), cita_id (0 = todas)
     * @return array resumen { ventana, dry_run, encontradas, email_ok, email_fail,
     *                         in_app, sin_correo, items[] }
     */
    function eco_procesar_recordatorios(mysqli $conex, array $opts = []): array
    {
        $ventanaH = isset($opts['ventana_horas']) ? max(1, (int)$opts['ventana_horas']) : 24;
        $limit    = isset($opts['limit']) ? max(1, min((int)$opts['limit'], 500)) : 100;
        $dry      = !empty($opts['dry_run']);
        $canales  = isset($opts['canales']) && is_array($opts['canales']) ? $opts['canales'] : ['email', 'in_app'];
        $citaId   = isset($opts['cita_id']) ? (int)$opts['cita_id'] : 0;
        $ecoId    = isset($opts['ecografista_id']) ? (int)$opts['ecografista_id'] : 0;
        $ventana  = $ventanaH . 'h';

        $usaEmail = in_array('email', $canales, true);
        $usaInApp = in_array('in_app', $canales, true);

        $out = [
            'ventana' => $ventana, 'dry_run' => $dry, 'encontradas' => 0,
            'email_ok' => 0, 'email_fail' => 0, 'in_app' => 0, 'sin_correo' => 0, 'items' => [],
        ];

        // Filtros opcionales: se arma el tipo/parametros de bind dinamicamente.
        $types  = 'is';                       // ventanaH (i), ventana (s)
        $params = [$ventanaH, $ventana];
        $filtroEco = '';
        if ($ecoId > 0)  { $filtroEco  = ' AND c.ecografista_id = ? '; $types .= 'i'; $params[] = $ecoId; }
        $filtroCita = '';
        if ($citaId > 0) { $filtroCita = ' AND c.id = ? ';            $types .= 'i'; $params[] = $citaId; }
        $types .= 'i'; $params[] = $limit;    // LIMIT

        $sql = "SELECT c.id, c.fecha_cita, c.paciente_id,
                       p.nombre_completo AS paciente_nombre, p.correo AS paciente_correo, p.telefono AS paciente_tel,
                       t.nombre AS tipo_nombre
                FROM citas c
                JOIN usuarios p ON p.id = c.paciente_id
                LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
                WHERE c.estado IN ('confirmada','reprogramada')
                  AND c.fecha_cita >= NOW()
                  AND c.fecha_cita <= DATE_ADD(NOW(), INTERVAL ? HOUR)
                  AND NOT EXISTS (SELECT 1 FROM cita_recordatorios r WHERE r.cita_id = c.id AND r.ventana = ?)
                  {$filtroEco}{$filtroCita}
                ORDER BY c.fecha_cita ASC
                LIMIT ?";
        if (!($st = $conex->prepare($sql))) {
            $out['error'] = 'prepare';
            return $out;
        }
        $st->bind_param($types, ...$params);
        $st->execute();
        $res  = $st->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $st->close();

        $out['encontradas'] = count($rows);

        foreach ($rows as $c) {
            $txt    = eco_recordatorio_textos($c);
            $waLink = eco_wa_link((string)$c['paciente_tel'], $txt['wa_texto']);
            $emailEstado = 'omitido';

            if (!$dry) {
                if ($usaEmail) {
                    if (!empty($c['paciente_correo'])) {
                        $err = null;
                        if (eco_enviar_correo($c['paciente_correo'], $txt['email_subject'], $txt['email_body'], $err)) {
                            $emailEstado = 'enviado'; $out['email_ok']++;
                        } else {
                            $emailEstado = 'fallido'; $out['email_fail']++;
                        }
                    } else {
                        $emailEstado = 'sin_correo'; $out['sin_correo']++;
                    }
                }
                if ($usaInApp) {
                    eco_notificar($conex, (int)$c['paciente_id'], 'recordatorio', $txt['titulo'], [
                        'mensaje' => $txt['mensaje'], 'url' => 'mis_citas_paciente.php', 'icono' => 'fa-solid fa-bell',
                    ]);
                    $out['in_app']++;
                }
                // Marca idempotente (una vez por cita+ventana)
                $det = json_encode(['email' => $emailEstado, 'in_app' => $usaInApp], JSON_UNESCAPED_UNICODE);
                $est = ($emailEstado === 'fallido') ? 'parcial' : 'enviado';
                if ($ins = $conex->prepare(
                    "INSERT IGNORE INTO cita_recordatorios (cita_id, ventana, canal, estado, detalle) VALUES (?, ?, 'multi', ?, ?)"
                )) {
                    $ins->bind_param('isss', $c['id'], $ventana, $est, $det);
                    $ins->execute();
                    $ins->close();
                }
            } else {
                if ($usaEmail && empty($c['paciente_correo'])) $out['sin_correo']++;
            }

            $out['items'][] = [
                'cita_id'  => (int)$c['id'],
                'paciente' => $c['paciente_nombre'],
                'fecha'    => $c['fecha_cita'],
                'correo'   => !empty($c['paciente_correo']),
                'email'    => $emailEstado,
                'wa_link'  => $waLink,
            ];
        }

        return $out;
    }
}
