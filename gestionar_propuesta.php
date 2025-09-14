<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    header('Location: login.php');
    exit();
}

if (isset($_GET['cita_id']) && isset($_GET['accion'])) {
    $cita_id = $_GET['cita_id'];
    $accion = $_GET['accion'];
    $paciente_id = $_SESSION['usuario_id'];

    if ($accion == 'aceptar') {
        // Mueve la fecha propuesta a la fecha final y confirma la cita
        $stmt = $conex->prepare("UPDATE citas SET fecha_cita = fecha_propuesta, fecha_propuesta = NULL, estado = 'confirmada' WHERE id = ? AND paciente_id = ?");
    } elseif ($accion == 'rechazar') {
        // Cancela la cita
        $stmt = $conex->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ? AND paciente_id = ?");
    }

    if (isset($stmt)) {
        $stmt->bind_param("ii", $cita_id, $paciente_id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: panel.php?vista=miscitas');
exit();
?>