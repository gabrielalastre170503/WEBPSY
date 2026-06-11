<?php
/**
 * panel.php — Panel clásico retirado (deprecado).
 *
 * La UI por pestañas (?vista=) fue reemplazada por el shell nuevo y las
 * páginas dedicadas con rutas limpias (ver routes.php). Este archivo se
 * conserva solo como redirección para enlaces o marcadores antiguos;
 * el contenido original vive en el historial de git.
 */
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit();
}

header('Location: ' . eco_url('dashboard'));
exit();
