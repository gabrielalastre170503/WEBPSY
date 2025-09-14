<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $paciente_id = $_POST['paciente_id'];
    $psicologo_id = $_SESSION['usuario_id'];

    $sql = "INSERT INTO informes_psicologicos (paciente_id, psicologo_id, numero_historia, fecha_evaluacion, motivo_referencia, referido_por, actitud_ante_evaluacion, area_visomotriz, area_intelectual, area_emocional, resultados_adicionales, recomendaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conex->prepare($sql);
    $stmt->bind_param("iissssssssss",
        $paciente_id,
        $psicologo_id,
        $_POST['numero_historia'],
        $_POST['fecha_evaluacion'],
        $_POST['motivo_referencia'],
        $_POST['referido_por'],
        $_POST['actitud_ante_evaluacion'],
        $_POST['area_visomotriz'],
        $_POST['area_intelectual'],
        $_POST['area_emocional'],
        $_POST['resultados_adicionales'],
        $_POST['recomendaciones']
    );

    if ($stmt->execute()) {
        header('Location: gestionar_paciente.php?paciente_id=' . $paciente_id . '&status=informe_guardado');
    } else {
        header('Location: crear_informe.php?paciente_id=' . $paciente_id . '&error=guardado');
    }
    $stmt->close();
}
$conex->close();
?>