<?php
session_start();
include 'conexion.php';

// Seguridad: Asegurarse de que el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar que el formulario fue enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nueva_pass = $_POST['nueva_pass'];
    $confirmar_pass = $_POST['confirmar_pass'];
    $usuario_id = $_SESSION['usuario_id'];

    // 1. Validar que las contraseñas no estén vacías y coincidan
    if (empty($nueva_pass) || empty($confirmar_pass)) {
        header('Location: panel.php?vista=perfil&error=empty');
        exit();
    }
    if ($nueva_pass !== $confirmar_pass) {
        header('Location: panel.php?vista=perfil&error=mismatch');
        exit();
    }
    // 2. Validar longitud mínima (opcional pero recomendado)
    if (strlen($nueva_pass) < 6) {
        header('Location: panel.php?vista=perfil&error=short');
        exit();
    }

    // 3. Encriptar la nueva contraseña
    $contrasena_hasheada = password_hash($nueva_pass, PASSWORD_DEFAULT);

    // 4. Actualizar la contraseña en la base de datos
    $stmt = $conex->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
    $stmt->bind_param("si", $contrasena_hasheada, $usuario_id);

    if ($stmt->execute()) {
        // Éxito: redirigir con mensaje de éxito
        header('Location: panel.php?vista=perfil&status=pass_success');
    } else {
        // Error: redirigir con mensaje de error
        header('Location: panel.php?vista=perfil&error=db_error');
    }
    $stmt->close();
    $conex->close();
    exit();
} else {
    // Si alguien intenta acceder a este archivo directamente, lo redirigimos
    header('Location: panel.php');
    exit();
}
?>
