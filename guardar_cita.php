<?php
session_start();
include 'conexion.php';

// Seguridad: Solo roles autorizados pueden guardar una cita
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'secretaria', 'administrador'])) {
    header('Location: login.php');
    exit();
}

// Validar que los datos necesarios del formulario fueron enviados
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cita_id'], $_POST['fecha_cita'])) {
    
    $cita_id = $_POST['cita_id'];
    $fecha_cita = $_POST['fecha_cita'];
    $psicologo_id = null;

    // --- LÓGICA CORREGIDA PARA ASIGNAR EL PSICÓLOGO ---
    
    // Si el usuario es psicólogo/psiquiatra, se asigna la cita a sí mismo.
    if ($_SESSION['rol'] == 'psicologo' || $_SESSION['rol'] == 'psiquiatra') {
        $psicologo_id = $_SESSION['usuario_id'];
    } 
    // Si es secretaria/admin, toma el ID del psicólogo que seleccionó en el formulario.
    elseif (($_SESSION['rol'] == 'secretaria' || $_SESSION['rol'] == 'administrador') && !empty($_POST['psicologo_id'])) {
        $psicologo_id = $_POST['psicologo_id'];
    }

    // Si por alguna razón no tenemos un psicólogo asignado, detenemos el proceso.
    if ($psicologo_id === null) {
        header('Location: panel.php?error=no_psicologo');
        exit();
    }

    // Actualizar la cita en la base de datos con la fecha, el psicólogo y el nuevo estado
    $stmt = $conex->prepare("UPDATE citas SET fecha_cita = ?, psicologo_id = ?, estado = 'confirmada' WHERE id = ?");
    $stmt->bind_param("sii", $fecha_cita, $psicologo_id, $cita_id);

    if ($stmt->execute()) {
        // Redirigir de vuelta al panel con un mensaje de éxito
        header('Location: panel.php?status=cita_programada');
    } else {
        // Redirigir con un mensaje de error
        header('Location: panel.php?error=programacion_fallida');
    }
    
    $stmt->close();
    exit();
}

// Si no se enviaron los datos correctos, simplemente redirigir al panel.
header('Location: panel.php');
$conex->close();
?>