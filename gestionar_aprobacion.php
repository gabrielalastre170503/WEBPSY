<?php
session_start();
include 'conexion.php';

// Seguridad: Solo los administradores pueden acceder
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    die("Acceso denegado.");
}

if (isset($_GET['id']) && isset($_GET['accion'])) {
    $id_usuario = $_GET['id'];
    $accion = $_GET['accion'];

    if ($accion == 'aprobar') {
        $nuevo_estado = 'aprobado';
        $stmt = $conex->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $id_usuario);
    } elseif ($accion == 'rechazar') {
        // Opción 1: Borrar el usuario
        $stmt = $conex->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id_usuario);
        // Opción 2: Marcar como rechazado
        // $nuevo_estado = 'rechazado';
        // $stmt = $conex->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        // $stmt->bind_param("si", $nuevo_estado, $id_usuario);
    }

    if (isset($stmt)) {
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: panel.php');
exit();
?>