<?php
session_start();
require 'conexion.php';
require 'enviar_correo.php';

// Seguridad: solo pacientes pueden enviar mensajes de ayuda
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'paciente') {
    header('Location: ' . eco_url('login'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: paciente_ayuda.php');
    exit;
}

$asunto  = trim((string)($_POST['asunto'] ?? ''));
$mensaje = trim((string)($_POST['mensaje'] ?? ''));

if ($asunto === '' || $mensaje === '') {
    header('Location: paciente_ayuda.php?error=campos_vacios');
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$nombre     = $_SESSION['nombre_completo'] ?? 'Paciente';

// Correo del paciente (para responderle: Reply-To)
$correo_paciente = '';
if ($stmt = $conex->prepare('SELECT correo FROM usuarios WHERE id = ?')) {
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $correo_paciente = (string)$row['correo'];
    }
    $stmt->close();
}

// Configuración SMTP
$cfg = @include __DIR__ . '/config_correo.php';
if (!is_array($cfg) || empty($cfg['smtp_pass']) || $cfg['smtp_pass'] === 'PEGA_AQUI_TU_APP_PASSWORD') {
    error_log('[ayuda] config_correo.php sin contraseña de aplicación configurada.');
    header('Location: paciente_ayuda.php?error=config_correo');
    exit;
}

// Armar el mensaje
$subject = 'Centro de Ayuda: ' . $asunto;
$body  = "Nuevo mensaje desde el Centro de Ayuda de EcoMadelleine\n";
$body .= "------------------------------------------------------\n";
$body .= 'Paciente: ' . $nombre . "\n";
$body .= 'Correo:   ' . ($correo_paciente !== '' ? $correo_paciente : 'no registrado') . "\n";
$body .= 'ID:       ' . $usuario_id . "\n";
$body .= 'Fecha:    ' . date('d/m/Y H:i') . "\n\n";
$body .= 'Asunto: ' . $asunto . "\n\n";
$body .= "Mensaje:\n" . $mensaje . "\n";

$destino = $cfg['to_email'] ?? 'madelleine.toro8@gmail.com';

$err = null;
$ok  = enviar_correo_smtp($cfg, $destino, $subject, $body, $correo_paciente, $nombre, $err);

if ($ok) {
    header('Location: paciente_ayuda.php?status=mensaje_enviado');
    exit;
}

error_log('[ayuda] Fallo al enviar correo: ' . $err);
header('Location: paciente_ayuda.php?error=envio_fallido');
exit;
