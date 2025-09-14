<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    die("Acceso denegado.");
}

if (!isset($_GET['id'])) {
    header('Location: ver_usuarios.php');
    exit();
}

$id_usuario_a_resetear = $_GET['id'];

// Generar nueva contraseña temporal
$nueva_contrasena = bin2hex(random_bytes(4));
$contrasena_hasheada = password_hash($nueva_contrasena, PASSWORD_DEFAULT);

// Actualizar en la base de datos
$stmt = $conex->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
$stmt->bind_param("si", $contrasena_hasheada, $id_usuario_a_resetear);

if ($stmt->execute()) {
    // Guardar en sesión para mostrarla al admin
    $_SESSION['reset_user_id'] = $id_usuario_a_resetear;
    $_SESSION['reset_temp_pass'] = $nueva_contrasena;
}

header('Location: ver_usuarios.php');
exit();
?>