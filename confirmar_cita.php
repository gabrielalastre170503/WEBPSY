<?php
session_start();
include 'conexion.php';

// Seguridad: Solo psicólogos pueden confirmar citas
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['cita_id']) || !is_numeric($_GET['cita_id'])) {
    die("ID de cita no válido.");
}

$cita_id = $_GET['cita_id'];
$psicologo_id = $_SESSION['usuario_id'];

// --- LÓGICA AÑADIDA ---
// Se establece la fecha de respuesta al momento de confirmar
$stmt = $conex->prepare("UPDATE citas SET estado = 'confirmada', fecha_respuesta = NOW() WHERE id = ? AND psicologo_id = ? AND estado = 'pendiente'");
$stmt->bind_param("ii", $cita_id, $psicologo_id);

if ($stmt->execute()) {
    header('Location: panel.php?vista=citas&status=cita_confirmada');
} else {
    header('Location: panel.php?vista=citas&error=confirmacion_fallida');
}


// Actualizamos el estado de la cita a 'confirmada'
// Nos aseguramos de que el psicólogo solo pueda confirmar citas asignadas a él.
$stmt = $conex->prepare("UPDATE citas SET estado = 'confirmada' WHERE id = ? AND psicologo_id = ? AND estado = 'pendiente'");
$stmt->bind_param("ii", $cita_id, $psicologo_id);

if ($stmt->execute()) {
    // Éxito
    header('Location: panel.php?vista=citas&status=cita_confirmada');
} else {
    // Error
    header('Location: panel.php?vista=citas&error=confirmacion_fallida');
}

$stmt->close();
$conex->close();
?>