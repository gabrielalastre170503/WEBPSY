<?php
/**
 * router.php — Front controller.
 *
 * Recibe (vía .htaccess) solo las peticiones cuya ruta NO corresponde a un
 * archivo o carpeta real. Resuelve la URL limpia contra routes.php e incluye
 * el handler existente. Todo lo demás (los .php actuales, assets, AJAX) lo
 * sigue sirviendo Apache directo, sin pasar por aquí.
 */

require_once __DIR__ . '/lib/core/Router.php';

// urlBase = subcarpeta del proyecto (ej. /Sistema_EcoMadelleineV1), autodetectada.
$urlBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

$r = new EcoRouter($urlBase, __DIR__);
require __DIR__ . '/routes.php';

$r->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
