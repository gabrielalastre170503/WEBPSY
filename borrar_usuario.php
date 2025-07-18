<?php
session_start();
include 'conexion.php';

// 1. Seguridad: Solo los administradores pueden acceder.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    die("Acceso denegado.");
}

// 2. Seguridad: Asegurarse de que se envió un ID.
if (!isset($_GET['id'])) {
    header('Location: ver_usuarios.php');
    exit();
}

$id_a_borrar = $_GET['id'];

// 3. Seguridad: El administrador no se puede borrar a sí mismo.
if ($id_a_borrar == $_SESSION['usuario_id']) {
    // Redirigir con un mensaje de error (opcional)
    header('Location: ver_usuarios.php?error=autodelete');
    exit();
}

// 4. Borrar el usuario de forma segura con una sentencia preparada.
$stmt = $conex->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_a_borrar);

if ($stmt->execute()) {
    // Éxito al borrar
    header('Location: ver_usuarios.php?status=deleted');
} else {
    // Error al borrar
    header('Location: ver_usuarios.php?error=deletefailed');
}

$stmt->close();
$conex->close();
?>