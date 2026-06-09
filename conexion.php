<?php
/*
 * conexion.php — Conexión central a la base de datos (MySQLi).
 *
 * Mejoras de seguridad aplicadas:
 *  - Ya NO se expone el error interno de MySQL al navegador (fuga de información).
 *    El detalle se registra en el log del servidor con error_log() y al usuario
 *    se le muestra un mensaje genérico.
 *  - Credenciales centralizadas. EN PRODUCCIÓN deben moverse a variables de
 *    entorno (getenv) y usar un usuario MySQL con privilegios mínimos, nunca 'root'.
 */

// --- Credenciales: se leen del .env (con fallback a XAMPP en desarrollo) ---
require_once __DIR__ . '/env_loader.php';
eco_load_env(__DIR__ . '/.env');

// Zona horaria del sistema (Venezuela, UTC-04:00). Se fija en PHP y, mas abajo,
// en la sesion de MySQL, para que NOW()/CURDATE() y date()/strtotime() coincidan
// (evita desfases en recordatorios, "hace ..." y throttling de login).
date_default_timezone_set(eco_env('APP_TZ', 'America/Caracas'));

$DB_HOST = eco_env('DB_HOST', 'localhost');
$DB_USER = eco_env('DB_USER', 'root');
$DB_PASS = eco_env('DB_PASS', '');
$DB_NAME = eco_env('DB_NAME', 'db_clinica_ecografias');

// Silenciamos el warning nativo (@) para controlar nosotros el manejo del error.
$conex = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conex) {
    // El detalle solo va al log del servidor; nunca al cliente.
    error_log('Error de conexion a la BD: ' . mysqli_connect_error());
    http_response_code(503);
    die('El servicio no está disponible en este momento. Inténtalo más tarde.');
}

mysqli_set_charset($conex, 'utf8mb4');

// Alinea el reloj de la sesion MySQL con PHP (UTC-04:00). Offset fijo: no
// depende de las tablas de zonas horarias de MySQL.
@mysqli_query($conex, "SET time_zone = '" . eco_env('DB_TZ_OFFSET', '-04:00') . "'");
