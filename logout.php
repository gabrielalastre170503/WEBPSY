<?php
session_start();

// Auditoria: registra el cierre de sesion antes de destruirla.
if (isset($_SESSION['usuario_id'])) {
    include 'conexion.php';
    require_once __DIR__ . '/lib/seguridad/seguridad.php';
    eco_auditar($conex, 'logout', ['usuario_id' => (int)$_SESSION['usuario_id']]);
}

session_unset();
session_destroy();

header('Location: ' . eco_url('login'));
exit();
?>