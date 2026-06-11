<?php
/**
 * lib/core/Router.php — Router mínimo (front controller).
 *
 * Mapea URLs limpias a HANDLERS EXISTENTES (alias aditivo): no mueve archivos
 * ni cambia los .php actuales. Las URLs `.php?query` viejas siguen funcionando
 * en paralelo (el .htaccess solo enruta rutas que NO son un archivo real).
 *
 * Patrones: '/informe/{informe_id}' -> captura {informe_id} en $_GET.
 * Handler: string (ruta a un .php existente, se incluye) o callable.
 */
if (!class_exists('EcoRouter')) {

    class EcoRouter
    {
        /** @var array<string,array<string,array{handler:mixed,params:string[]}>> */
        private array $routes = [];
        private string $urlBase;   // prefijo de URL del proyecto, ej. /Sistema_EcoMadelleineV1
        private string $fileBase;  // ruta absoluta a la raíz del proyecto

        public function __construct(string $urlBase, string $fileBase)
        {
            $this->urlBase  = rtrim($urlBase, '/');
            $this->fileBase = rtrim(str_replace('\\', '/', $fileBase), '/');
        }

        public function add(string $method, string $pattern, $handler): void
        {
            $params = [];
            $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$params) {
                $params[] = $m[1];
                return '([^/]+)';
            }, $pattern);
            $regex = '#^' . $regex . '/?$#';
            $this->routes[strtoupper($method)][$regex] = ['handler' => $handler, 'params' => $params];
        }

        public function get(string $p, $h): void  { $this->add('GET', $p, $h); }
        public function post(string $p, $h): void { $this->add('POST', $p, $h); }
        public function any(string $p, $h): void  { $this->add('GET', $p, $h); $this->add('POST', $p, $h); }

        public function dispatch(string $method, string $uri): void
        {
            $path = parse_url($uri, PHP_URL_PATH);
            $path = $path !== null ? rawurldecode($path) : '/';
            // quitar el prefijo de subcarpeta del proyecto
            if ($this->urlBase !== '' && strpos($path, $this->urlBase) === 0) {
                $path = substr($path, strlen($this->urlBase));
            }
            if ($path === '' || $path === false) {
                $path = '/';
            }
            $method = strtoupper($method);

            foreach (($this->routes[$method] ?? []) as $regex => $r) {
                if (preg_match($regex, $path, $m)) {
                    array_shift($m);
                    foreach ($r['params'] as $i => $name) {
                        if (isset($m[$i])) {
                            $_GET[$name]     = $m[$i];
                            $_REQUEST[$name] = $m[$i];
                        }
                    }
                    $h = $r['handler'];
                    if (is_callable($h)) {
                        $h();
                        return;
                    }
                    if (is_string($h)) {
                        $file = $this->fileBase . '/' . ltrim($h, '/');
                        if (is_file($file)) {
                            require $file;
                            return;
                        }
                    }
                    http_response_code(500);
                    echo 'Handler no disponible.';
                    return;
                }
            }

            $this->notFound();
        }

        private function notFound(): void
        {
            http_response_code(404);
            $home = htmlspecialchars($this->urlBase !== '' ? $this->urlBase . '/' : '/', ENT_QUOTES);
            echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>404 · No encontrado</title></head>'
                . '<body style="font-family:system-ui,-apple-system,sans-serif;display:flex;min-height:100vh;'
                . 'align-items:center;justify-content:center;margin:0;background:#f5f9ff;color:#0c1a2e">'
                . '<div style="text-align:center;padding:40px">'
                . '<div style="font-size:64px;font-weight:800;color:#02b1f4;line-height:1">404</div>'
                . '<p style="font-size:16px;color:#4a5870;margin:12px 0 22px">Página no encontrada.</p>'
                . '<a href="' . $home . '" style="display:inline-block;padding:11px 22px;border-radius:10px;'
                . 'background:linear-gradient(135deg,#02b1f4,#014a82);color:#fff;text-decoration:none;font-weight:600">'
                . 'Volver al inicio</a></div></body></html>';
        }
    }
}
