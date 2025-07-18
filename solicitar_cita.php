<?php
session_start();
include 'conexion.php';

// Seguridad: Solo pacientes pueden solicitar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['motivo_consulta'])) {
    $paciente_id = $_SESSION['usuario_id'];
    $motivo = $_POST['motivo_consulta'];

    $stmt = $conex->prepare("INSERT INTO citas (paciente_id, motivo_consulta, estado) VALUES (?, ?, 'pendiente')");
    $stmt->bind_param("is", $paciente_id, $motivo);
    
    if ($stmt->execute()) {
        header('Location: panel.php?status=cita_solicitada');
    } else {
        header('Location: panel.php?error=solicitud_fallida');
    }
    $stmt->close();
}
$conex->close();
?>