<?php
/*
 * bootstrap.php — Arranque global de seguridad (Fase 0).
 *
 * Se carga automáticamente en CADA petición PHP mediante:
 *     php_value auto_prepend_file  (ver .htaccess)
 * Por eso NO debe producir ninguna salida.
 *
 * Responsabilidades:
 *   1. Cargar variables de entorno desde .env.
 *   2. Endurecer la cookie de sesión (HttpOnly, SameSite, Secure en HTTPS).
 *   3. Exponer helpers CSRF: csrf_token(), csrf_field(), csrf_meta(),
 *      csrf_validate(), require_csrf().
 *
 * Las páginas siguen llamando a session_start() como hasta ahora; al haberse
 * fijado antes los parámetros de cookie, la sesión hereda la configuración segura.
 */

if (defined('ECO_BOOTSTRAP')) {
    return;
}
define('ECO_BOOTSTRAP', 1);

require_once __DIR__ . '/env_loader.php';
eco_load_env(__DIR__ . '/.env');

/* ── 1. Endurecimiento de la sesión ───────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    // Mitiga session fixation aceptando solo IDs generados por el servidor.
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* ── 1b. Cabeceras de seguridad HTTP ──────────────────────────────────
 * CSP con allowlist de los CDNs realmente usados (jsdelivr, cdnjs, Google
 * Fonts, npmcdn/unpkg para el locale de flatpickr). Se permite 'unsafe-inline'
 * porque el código tiene mucho <script>/<style>/onclick inline. Aun así limita
 * orígenes externos, bloquea objetos y el framing por terceros (anti-clickjacking).
 * No se usa upgrade-insecure-requests para no romper el desarrollo en http. */
if (!headers_sent()) {
    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://npmcdn.com https://unpkg.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://npmcdn.com https://unpkg.com",
        "connect-src 'self'",
        "frame-src 'self'",
    ]));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/* ── 2. Helpers CSRF ──────────────────────────────────────────────── */
if (!function_exists('csrf_token')) {

    function eco_ensure_session()
    {
        // Solo arranca si no hay sesión y aún no se enviaron cabeceras
        // (evita el warning "headers already sent" en páginas que imprimen
        //  HTML antes de tocar la sesión).
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    /** Devuelve (creando si hace falta) el token CSRF de la sesión. */
    function csrf_token()
    {
        eco_ensure_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Campo oculto para formularios HTML. */
    function csrf_field()
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }

    /** Etiqueta <meta> para que el JavaScript pueda leer el token. */
    function csrf_meta()
    {
        return '<meta name="csrf-token" content="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }

    /** Compara en tiempo constante el token recibido con el de la sesión. */
    function csrf_validate($token)
    {
        eco_ensure_session();
        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Exige un token CSRF válido en peticiones que cambian estado.
     * Lee el token de $_POST['csrf_token'] o de la cabecera X-CSRF-Token.
     * Si falla, responde 419 y termina la ejecución.
     *
     * @param bool $force  Si es true, valida también métodos distintos de POST.
     */
    function require_csrf($force = false)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!$force && $method !== 'POST') {
            return;
        }

        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (csrf_validate($token)) {
            return;
        }

        // 403 Forbidden: estándar y compatible con Apache/mod_php.
        // (Se evita 419 porque Apache lo degrada a 500 al no reconocerlo.)
        http_response_code(403);

        // Responder en el formato esperado por el cliente.
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || isset($_SERVER['HTTP_X_CSRF_TOKEN'])
            || strpos($accept, 'application/json') !== false;

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'ok'      => false,
                'message' => 'Sesión expirada o token de seguridad inválido. Recarga la página e inténtalo de nuevo.',
            ]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Sesión expirada</title>'
                . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:80px auto;text-align:center;color:#1e2a44">'
                . '<h2>Sesión expirada</h2>'
                . '<p>Por seguridad, tu solicitud no pudo verificarse. Recarga la página e inténtalo de nuevo.</p>'
                . '<p><a href="javascript:history.back()" style="color:#0277bd">Volver</a></p></div>';
        }
        exit;
    }
}
