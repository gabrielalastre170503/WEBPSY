<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cita_id'], $_POST['fecha_cita'])) {
    $cita_id = $_POST['cita_id'];
    $fecha_cita = $_POST['fecha_cita'];
    $psicologo_id = $_SESSION['usuario_id'];

    $stmt = $conex->prepare("UPDATE citas SET fecha_cita = ?, psicologo_id = ?, estado = 'confirmada' WHERE id = ?");
    $stmt->bind_param("sii", $fecha_cita, $psicologo_id, $cita_id);

    if ($stmt->execute()) {
        header('Location: panel.php?status=cita_programada');
    } else {
        header('Location: panel.php?error=programacion_fallida');
    }
    $stmt->close();
}
$conex->close();
?>