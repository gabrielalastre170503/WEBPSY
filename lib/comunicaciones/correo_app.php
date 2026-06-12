<?php
/*
 * lib/comunicaciones/correo_app.php — Utilidades de correo e identidad (Fase 1).
 *
 * Reutiliza el motor SMTP existente (enviar_correo.php / config_correo.php)
 * para enviar correos transaccionales: verificación de cuenta, recuperación
 * de contraseña y códigos OTP de 2FA.
 */

require_once __DIR__ . '/enviar_correo.php';

if (!function_exists('eco_base_url')) {

    /**
     * Devuelve la URL base del sistema, p.ej. http://localhost/Sistema_EcoMadelleineV1
     * Detecta esquema, host y subcarpeta de instalación automáticamente.
     */
    function eco_base_url(): string
    {
        $https  = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Subcarpeta: a partir de la ruta del script, sube hasta la raíz del proyecto.
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir    = rtrim(str_replace('/lib', '', dirname($script)), '/');
        // Si el script está en la raíz del proyecto, dirname ya da la subcarpeta.
        if ($dir === '' || $dir === '.') {
            $dir = '';
        }
        return $scheme . '://' . $host . $dir;
    }

    /** Token aleatorio seguro (64 hex). */
    function eco_token(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** Código OTP numérico de 6 dígitos (string, con ceros a la izquierda). */
    function eco_otp_codigo(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Envía un correo de texto plano usando la configuración SMTP del sistema.
     * Devuelve true/false; el detalle del error queda en $err (para error_log).
     */
    function eco_enviar_correo(string $to, string $subject, string $body, ?string &$err = null): bool
    {
        $cfg = @include __DIR__ . '/../../config/config_correo.php';
        if (!is_array($cfg) || empty($cfg['smtp_pass'])) {
            $err = 'config_correo.php sin contraseña SMTP configurada (.env: SMTP_PASS).';
            error_log('[correo_app] ' . $err);
            return false;
        }
        $ok = enviar_correo_smtp($cfg, $to, $subject, $body, '', '', $err);
        if (!$ok) {
            error_log('[correo_app] Fallo SMTP a ' . $to . ': ' . (string)$err);
        }
        return $ok;
    }
}
