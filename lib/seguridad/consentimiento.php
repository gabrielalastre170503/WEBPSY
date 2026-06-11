<?php
/**
 * lib/seguridad/consentimiento.php — Consentimiento informado del paciente (cumplimiento médico).
 *
 * El paciente debe aceptar la versión vigente del texto antes de usar el sistema.
 * La aceptación queda registrada (versión, fecha, IP, user-agent) en `consentimientos`.
 * Al cambiar ECO_CONSENT_VERSION, los pacientes deben volver a aceptar.
 *
 * Tabla: database/migrations/2026_fase4_04_consentimientos.sql
 */

if (!defined('ECO_CONSENT_VERSION')) {
    // Subir esta versión obliga a re-aceptar a todos los pacientes.
    define('ECO_CONSENT_VERSION', '1.0');
}

if (!function_exists('eco_consentimiento_vigente')) {

    /**
     * ¿El paciente ya aceptó la versión vigente del consentimiento?
     * Fail-open: si la tabla aún no existe, NO bloquea (devuelve true) para no
     * romper el sistema antes de aplicar la migración.
     */
    function eco_consentimiento_vigente(mysqli $conex, int $pacienteId): bool
    {
        if ($pacienteId <= 0) {
            return true;
        }
        $v = ECO_CONSENT_VERSION;
        if (!($st = @$conex->prepare("SELECT 1 FROM consentimientos WHERE paciente_id = ? AND version = ? LIMIT 1"))) {
            return true; // tabla inexistente -> no bloquear
        }
        $st->bind_param('is', $pacienteId, $v);
        if (!@$st->execute()) {
            $st->close();
            return true;
        }
        $row = $st->get_result()->fetch_row();
        $st->close();
        return (bool)$row;
    }

    /** Registra (o actualiza) la aceptación de la versión vigente para el paciente. */
    function eco_consentimiento_registrar(mysqli $conex, int $pacienteId, string $ip, string $ua): bool
    {
        if ($pacienteId <= 0) {
            return false;
        }
        $v  = ECO_CONSENT_VERSION;
        $ip = substr($ip, 0, 45);
        $ua = substr($ua, 0, 255);
        try {
            $st = $conex->prepare("INSERT INTO consentimientos (paciente_id, version, ip, user_agent)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE aceptado_en = CURRENT_TIMESTAMP, ip = VALUES(ip), user_agent = VALUES(user_agent)");
            $st->bind_param('isss', $pacienteId, $v, $ip, $ua);
            $ok = $st->execute();
            $st->close();
            return $ok;
        } catch (mysqli_sql_exception $e) {
            error_log('eco_consentimiento_registrar: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Texto del consentimiento (HTML seguro).
     * PLACEHOLDER: el centro debe revisarlo/ajustarlo legalmente antes de producción.
     */
    function eco_consentimiento_texto(): string
    {
        return <<<HTML
<p>En <strong>EcoMadelleine · Centro de Diagnóstico</strong> tratamos tus datos personales y de salud
con el único fin de prestarte atención ecográfica, gestionar tus citas, emitir informes de estudio y
cumplir las obligaciones legales aplicables.</p>

<h4>¿Qué datos tratamos?</h4>
<ul>
    <li>Datos de identificación y contacto (nombre, cédula, correo, teléfono, dirección).</li>
    <li>Datos de salud derivados de tus estudios e informes ecográficos.</li>
    <li>Datos de tus citas, pagos y comunicaciones con el centro.</li>
</ul>

<h4>¿Con qué finalidad?</h4>
<p>Prestación del servicio clínico, seguimiento de tu historia, facturación, recordatorios y mejora de
la calidad asistencial. No vendemos tus datos ni los cedemos a terceros salvo obligación legal.</p>

<h4>Tus derechos</h4>
<p>Puedes solicitar el acceso, rectificación, actualización o eliminación de tus datos, así como una
copia de tu historial clínico, escribiendo a
<a href="mailto:soporte@ecomadelleine.com">soporte@ecomadelleine.com</a>.</p>

<h4>Conservación</h4>
<p>Conservamos tu información clínica durante el plazo exigido por la normativa sanitaria aplicable y,
cumplido este, se elimina o anonimiza de forma segura.</p>

<p>Al aceptar, declaras haber leído y comprendido esta información y otorgas tu
<strong>consentimiento informado</strong> para el tratamiento descrito.</p>
HTML;
    }
}
