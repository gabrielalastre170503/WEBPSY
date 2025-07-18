<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_historia = $_POST['tipo_historia'];
    $paciente_id = $_POST['paciente_id'];
    $entrevistador_id = $_SESSION['usuario_id']; // El psicólogo logueado

    if ($tipo_historia == 'adulto') {
        $sql = "INSERT INTO historias_adultos (paciente_id, entrevistador_id, numero_historia, centro_salud, fecha, ci_paciente, sexo, telefono, estado_civil, nacionalidad, hijos, religion, grado_instruccion, ocupacion, direccion, motivo_consulta, antecedentes_personales, antecedentes_familiares, antecedentes_psiquiatricos, antecedentes_medicos, antecedentes_pareja, impresion_diagnostica) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conex->prepare($sql);
        $stmt->bind_param("iissssssssssssssssssss", $paciente_id, $entrevistador_id, $_POST['numero_historia'], $_POST['centro_salud'], $_POST['fecha'], $_POST['ci_paciente'], $_POST['sexo'], $_POST['telefono'], $_POST['estado_civil'], $_POST['nacionalidad'], $_POST['hijos'], $_POST['religion'], $_POST['grado_instruccion'], $_POST['ocupacion'], $_POST['direccion'], $_POST['motivo_consulta'], $_POST['antecedentes_personales'], $_POST['antecedentes_familiares'], $_POST['antecedentes_psiquiatricos'], $_POST['antecedentes_medicos'], $_POST['antecedentes_pareja'], $_POST['impresion_diagnostica']);
    } elseif ($tipo_historia == 'infantil') {
        $sql = "INSERT INTO historias_infantiles (paciente_id, entrevistador_id, numero_historia, centro_salud, fecha, lugar_nacimiento, institucion_escolar, datos_padre, datos_madre, padres_viven_juntos, estan_casados, hermanos, motivo_consulta, antecedentes_embarazo, antecedentes_parto, estado_nino_nacer, desarrollo_psicomotor, habitos_independencia, condiciones_salud, vida_social, plan_psicoterapeutico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conex->prepare($sql);
        $stmt->bind_param("iisssssssssssssssssss", $paciente_id, $entrevistador_id, $_POST['numero_historia'], $_POST['centro_salud'], $_POST['fecha'], $_POST['lugar_nacimiento'], $_POST['institucion_escolar'], $_POST['datos_padre'], $_POST['datos_madre'], $_POST['padres_viven_juntos'], $_POST['estan_casados'], $_POST['hermanos'], $_POST['motivo_consulta'], $_POST['antecedentes_embarazo'], $_POST['antecedentes_parto'], $_POST['estado_nino_nacer'], $_POST['desarrollo_psicomotor'], $_POST['habitos_independencia'], $_POST['condiciones_salud'], $_POST['vida_social'], $_POST['plan_psicoterapeutico']);
    }

    if (isset($stmt)) {
        if ($stmt->execute()) {
            // REDIRIGIR A LA PÁGINA DE GESTIÓN, NO AL PANEL GENERAL
            header('Location: gestionar_paciente.php?paciente_id=' . $paciente_id . '&status=historia_guardada');
        } else {
            header('Location: historia_clinica.php?paciente_id=' . $paciente_id . '&error=guardado');
        }
        $stmt->close();
        exit(); // Añadir exit() es una buena práctica
    }
}
$conex->close();
?>