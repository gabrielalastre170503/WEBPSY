<?php
session_start();
include 'conexion.php';

// Seguridad: Solo pacientes pueden solicitar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar que todos los datos necesarios llegaron
    if (empty($_POST['psicologo_id']) || empty($_POST['fecha_seleccionada']) || empty($_POST['hora_seleccionada']) || empty($_POST['motivo_consulta'])) {
        header('Location: panel.php?vista=solicitar&error=faltan_datos');
        exit();
    }

    $paciente_id = $_SESSION['usuario_id'];
    $psicologo_id = $_POST['psicologo_id'];
    $motivo = $_POST['motivo_consulta'];
    
    // Nuevos campos
    $tipo_cita = $_POST['tipo_cita'];
    $modalidad = $_POST['modalidad'];
    $motivo_principal = $_POST['motivo_principal'];
    $notas_paciente = $_POST['notas_paciente'];

    // Combinar la fecha y la hora seleccionadas
    $fecha_cita_str = $_POST['fecha_seleccionada'] . ' ' . $_POST['hora_seleccionada'];
    $fecha_cita = date('Y-m-d H:i:s', strtotime($fecha_cita_str));

    // Insertar la cita con todos los nuevos detalles
    $stmt = $conex->prepare("
        INSERT INTO citas (paciente_id, psicologo_id, motivo_consulta, tipo_cita, modalidad, motivo_principal, notas_paciente, fecha_cita, estado) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    $stmt->bind_param("iissssss", $paciente_id, $psicologo_id, $motivo, $tipo_cita, $modalidad, $motivo_principal, $notas_paciente, $fecha_cita);
    
    if ($stmt->execute()) {
        header('Location: panel.php?vista=miscitas&status=cita_creada');
    } else {
        header('Location: panel.php?vista=solicitar&error=error_guardar');
    }
    $stmt->close();
}
$conex->close();
?>