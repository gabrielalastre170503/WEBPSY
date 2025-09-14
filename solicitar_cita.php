<?php
session_start();
include 'conexion.php';

// Seguridad: Solo pacientes pueden solicitar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['motivo_consulta']) && !empty($_POST['psicologo_id'])) {
    $paciente_id = $_SESSION['usuario_id'];
    $psicologo_id = $_POST['psicologo_id'];
    $motivo = $_POST['motivo_consulta'];

    // Consulta actualizada para incluir el psicologo_id
    $stmt = $conex->prepare("INSERT INTO citas (paciente_id, psicologo_id, motivo_consulta, estado) VALUES (?, ?, ?, 'pendiente')");
    $stmt->bind_param("iis", $paciente_id, $psicologo_id, $motivo);
    
    if ($stmt->execute()) {
        header('Location: panel.php?status=cita_solicitada');
    } else {
        header('Location: panel.php?error=solicitud_fallida');
    }
    $stmt->close();
} else {
    // Si faltan datos, redirigir con error
    header('Location: panel.php?error=datos_incompletos');
}
$conex->close();
?>