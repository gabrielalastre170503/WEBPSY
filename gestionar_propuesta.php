<?php
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/citas.php';

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
        if ($stmt->affected_rows > 0) {
            if ($accion == 'aceptar') {
                eco_cita_evento($conex, (int)$cita_id, 'aceptada', ['estado_nuevo' => 'confirmada']);
            } else {
                eco_cita_evento($conex, (int)$cita_id, 'rechazada', ['estado_nuevo' => 'cancelada']);
            }
        }
        $stmt->close();
    }
}

header('Location: mis_citas_paciente.php');
exit();
?>