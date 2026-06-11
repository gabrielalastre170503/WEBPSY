<?php
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/seguridad/seguridad.php';

// 1. Seguridad: Solo los administradores pueden acceder.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    die("Acceso denegado.");
}

// 2. Seguridad: requiere POST + token CSRF (cierra el hueco de CSRF por enlace GET).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: ver_usuarios.php');
    exit();
}
require_csrf();

$id_a_borrar = (int)$_POST['id'];

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
    eco_auditar($conex, 'usuario_borrado', ['entidad' => 'usuario', 'entidad_id' => $id_a_borrar]);
    header('Location: ver_usuarios.php?status=deleted');
} else {
    // Error al borrar
    header('Location: ver_usuarios.php?error=deletefailed');
}

$stmt->close();
$conex->close();
?>