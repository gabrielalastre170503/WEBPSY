<?php
session_start();
include 'conexion.php';

// 1. Seguridad: Solo los administradores pueden borrar citas.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    // Redirigir o mostrar un error si no es admin
    header('Location: login.php');
    exit();
}

// 2. Validar que se recibió un ID de cita.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirigir si no hay un ID válido
    header('Location: ver_citas_admin.php?error=invalid_id');
    exit();
}

$cita_id = $_GET['id'];

// 3. Preparar y ejecutar la consulta de eliminación de forma segura.
$stmt = $conex->prepare("DELETE FROM citas WHERE id = ?");
$stmt->bind_param("i", $cita_id);

if ($stmt->execute()) {
    // Si se borró correctamente, redirigir con un mensaje de éxito.
    header('Location: ver_citas_admin.php?status=deleted');
} else {
    // Si hubo un error, redirigir con un mensaje de error.
    header('Location: ver_citas_admin.php?error=delete_failed');
}

$stmt->close();
$conex->close();
exit();
?>