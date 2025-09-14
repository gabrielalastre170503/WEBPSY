<?php
session_start();
include 'conexion.php';

// Seguridad: Solo roles autorizados pueden borrar informes
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

// Validar que se recibió un ID de informe
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Parámetros no válidos.");
}

$informe_id = $_GET['id'];

// Preparamos y ejecutamos la consulta de eliminación de forma segura
$stmt = $conex->prepare("DELETE FROM informes_psicologicos WHERE id = ?");
$stmt->bind_param("i", $informe_id);

if ($stmt->execute()) {
    // Redirigir de vuelta al panel principal. La modal de gestión se actualizará sola.
    header('Location: panel.php?vista=pacientes&status=informe_borrado');
} else {
    // Si hubo un error, redirigir con un mensaje de error
    header('Location: panel.php?vista=pacientes&error=borrado_fallido');
}

$stmt->close();
$conex->close();
?>