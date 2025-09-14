<?php
session_start();
include 'conexion.php';

// Seguridad: Solo roles autorizados pueden borrar historias
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

// Validar que los parámetros necesarios existan
if (!isset($_GET['historia_id']) || !is_numeric($_GET['historia_id']) || !isset($_GET['tipo']) || !isset($_GET['paciente_id'])) {
    die("Parámetros no válidos.");
}

$historia_id = $_GET['historia_id'];
$tipo = $_GET['tipo'];
$paciente_id = $_GET['paciente_id'];

// Determinar de qué tabla borrar
if ($tipo == 'adulto') {
    $tabla = 'historias_adultos';
} elseif ($tipo == 'infantil') {
    $tabla = 'historias_infantiles';
} else {
    die("Tipo de historia no válido.");
}

// Preparamos y ejecutamos la consulta de eliminación de forma segura
$stmt = $conex->prepare("DELETE FROM $tabla WHERE id = ?");
$stmt->bind_param("i", $historia_id);

if ($stmt->execute()) {
    // Redirigir de vuelta al panel principal. La modal de gestión se actualizará sola.
    header('Location: panel.php?vista=pacientes&status=historia_borrada');
} else {
    // Si hubo un error, redirigir con un mensaje de error
    header('Location: panel.php?vista=pacientes&error=borrado_fallido');
}

$stmt->close();
$conex->close();
?>