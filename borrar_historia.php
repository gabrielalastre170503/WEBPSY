<?php
session_start();
include 'conexion.php';

// Seguridad: Solo roles autorizados
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

// Validar que los parámetros necesarios existan
if (!isset($_GET['historia_id']) || !isset($_GET['tipo']) || !isset($_GET['paciente_id'])) {
    die("Error: Faltan parámetros para la eliminación.");
}

$historia_id = $_GET['historia_id'];
$tipo_historia = $_GET['tipo'];
$paciente_id = $_GET['paciente_id']; // Para la redirección

// Determinar la tabla correcta y preparar la consulta
if ($tipo_historia == 'adulto') {
    $stmt = $conex->prepare("DELETE FROM historias_adultos WHERE id = ?");
} elseif ($tipo_historia == 'infantil') {
    $stmt = $conex->prepare("DELETE FROM historias_infantiles WHERE id = ?");
} else {
    die("Error: Tipo de historia no válido.");
}

$stmt->bind_param("i", $historia_id);

if ($stmt->execute()) {
    // Redirigir a la página de gestión del paciente
    header('Location: gestionar_paciente.php?paciente_id=' . $paciente_id . '&status=historia_borrada');
} else {
    // Redirigir con error
    header('Location: historia_clinica.php?paciente_id=' . $paciente_id . '&error=borrado');
}

$stmt->close();
$conex->close();
?>