<?php
session_start();

// Seguridad: Solo pacientes pueden enviar mensajes de ayuda
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['asunto'], $_POST['mensaje'])) {
    $paciente_id = $_SESSION['usuario_id'];
    $paciente_nombre = $_SESSION['nombre_completo'];
    $asunto = $_POST['asunto'];
    $mensaje = $_POST['mensaje'];

    // --- Lógica para enviar el correo (simulada por ahora) ---
    // En un sistema real, aquí usarías una librería como PHPMailer
    // para enviar un correo al administrador con la consulta del paciente.
    
    // Por ahora, simplemente redirigimos con un mensaje de éxito.
    header('Location: panel.php?vista=ayuda&status=mensaje_enviado');
    exit();
} else {
    // Si faltan datos, redirigir con error
    header('Location: panel.php?vista=ayuda&error=campos_vacios');
    exit();
}
?>