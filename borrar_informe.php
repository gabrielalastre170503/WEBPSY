<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['informe_id'])) {
    die("Error: No se ha especificado un informe para borrar.");
}

$informe_id = $_GET['informe_id'];

// Antes de borrar, necesitamos el ID del paciente para redirigir correctamente
$stmt_get_paciente = $conex->prepare("SELECT paciente_id FROM informes_psicologicos WHERE id = ?");
$stmt_get_paciente->bind_param("i", $informe_id);
$stmt_get_paciente->execute();
$result = $stmt_get_paciente->get_result();
$informe = $result->fetch_assoc();
$paciente_id = $informe['paciente_id'];
$stmt_get_paciente->close();

if (!$paciente_id) {
    die("No se pudo encontrar el paciente asociado a este informe.");
}

// Proceder con la eliminación
$stmt_delete = $conex->prepare("DELETE FROM informes_psicologicos WHERE id = ?");
$stmt_delete->bind_param("i", $informe_id);

if ($stmt_delete->execute()) {
    header('Location: ver_informes.php?paciente_id=' . $paciente_id . '&status=informe_borrado');
} else {
    header('Location: ver_informe_detalle.php?informe_id=' . $informe_id . '&error=borrado');
}

$stmt_delete->close();
$conex->close();
?>