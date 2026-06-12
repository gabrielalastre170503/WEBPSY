<?php
/*
 * env_loader.php — Cargador minimalista de variables de entorno desde .env
 *
 * No requiere Composer. Lee el archivo .env (clave=valor por línea), ignora
 * comentarios (#) y líneas vacías, y publica los valores en putenv()/$_ENV/$_SERVER.
 * Soporta comillas simples/dobles y valores con signos '='.
 *
 * Uso:
 *   eco_load_env(__DIR__ . '/.env');
 *   $host = eco_env('DB_HOST', 'localhost');
 */

if (!function_exists('eco_load_env')) {

    function eco_load_env($path)
    {
        static $loaded = [];
        if (isset($loaded[$path])) {
            return;
        }
        $loaded[$path] = true;

        if (!is_file($path) || !is_readable($path)) {
            return; // Silencioso: se usan los valores por defecto de eco_env().
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Quitar comillas envolventes.
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last  = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key === '') {
                continue;
            }
            // No sobrescribir variables ya definidas en el entorno real del servidor.
            if (getenv($key) !== false) {
                continue;
            }
            putenv("$key=$value");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Lee una variable de entorno con valor por defecto y casteo básico de booleanos.
     */
    function eco_env($key, $default = null)
    {
        $val = getenv($key);
        if ($val === false) {
            return $default;
        }
        $lower = strtolower(trim($val));
        if ($lower === 'true')  return true;
        if ($lower === 'false') return false;
        if ($lower === 'null')  return null;
        return $val;
    }
}
