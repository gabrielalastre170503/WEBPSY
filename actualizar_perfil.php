<?php
session_start();
include 'conexion.php';

// Seguridad: El usuario debe estar logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    
    // --- LÓGICA PARA CAMBIAR LA CONTRASEÑA (SIMPLIFICADA) ---
    if ($_POST['accion'] == 'cambiar_contrasena') {
        $nueva_contrasena = $_POST['nueva_contrasena'];
        $confirmar_nueva_contrasena = $_POST['confirmar_nueva_contrasena'];

        // 1. Verificar que la nueva contraseña y su confirmación coincidan
        if ($nueva_contrasena !== $confirmar_nueva_contrasena) {
            header('Location: panel.php?vista=perfil&error=pass_no_coincide');
            exit();
        }

        // 2. Verificar la seguridad de la nueva contraseña
        if (strlen($nueva_contrasena) < 8 || !preg_match('/[A-Z]/', $nueva_contrasena) || !preg_match('/[\W_]/', $nueva_contrasena)) {
            header('Location: panel.php?vista=perfil&error=pass_no_segura');
            exit();
        }

        // 3. Si todo es correcto, hashear y actualizar la nueva contraseña
        $nueva_contrasena_hasheada = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
        $update_stmt = $conex->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
        $update_stmt->bind_param("si", $nueva_contrasena_hasheada, $usuario_id);

        if ($update_stmt->execute()) {
            header('Location: panel.php?vista=perfil&status=perfil_actualizado');
        } else {
            header('Location: panel.php?vista=perfil&error=actualizacion_fallida');
        }
        $update_stmt->close();
        exit();
    }
}

$conex->close();
header('Location: panel.php'); // Redirigir si se accede al archivo directamente
?>
