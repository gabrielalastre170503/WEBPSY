<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    die("Acceso denegado.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ver_usuarios.php?error=parametros_invalidos');
    exit();
}

$usuario_id_a_resetear = $_GET['id'];
$filtro_origen = $_GET['filtro'] ?? 'aprobados';

// Seguridad: El administrador no puede restablecer su propia contraseña desde aquí
if ($usuario_id_a_resetear == $_SESSION['usuario_id']) {
    header('Location: ver_usuarios.php?filtro=' . urlencode($filtro_origen) . '&error=auto_reset');
    exit();
}

// 1. Generar una nueva contraseña temporal
$contrasena_temporal = bin2hex(random_bytes(4));
$contrasena_hasheada = password_hash($contrasena_temporal, PASSWORD_DEFAULT);

// 2. Actualizar la contraseña del usuario
$stmt = $conex->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
$stmt->bind_param("si", $contrasena_hasheada, $usuario_id_a_resetear);

if ($stmt->execute()) {
    // 3. Redirigir de vuelta a la lista de usuarios con la contraseña en la URL
    $redirect_url = 'ver_usuarios.php?filtro=' . urlencode($filtro_origen) . '&status=password_reset&temp_pass=' . urlencode($contrasena_temporal);
    header('Location: ' . $redirect_url);
} else {
    header('Location: ver_usuarios.php?filtro=' . urlencode($filtro_origen) . '&error=reset_failed');
}

$stmt->close();
$conex->close();
?>