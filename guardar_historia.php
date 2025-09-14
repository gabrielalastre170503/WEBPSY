<?php
session_start();
include 'conexion.php';

// Seguridad: Solo roles autorizados pueden acceder
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_historia = $_POST['tipo_historia'] ?? '';
    $paciente_id = $_POST['paciente_id'] ?? 0;
    $entrevistador_id = $_SESSION['usuario_id'];

    if ($tipo_historia == 'adulto') {
        $sql = "INSERT INTO historias_adultos (
            paciente_id, entrevistador_id, numero_historia, centro_salud, fecha, 
            ci_paciente, sexo, telefono, estado_civil, nacionalidad, hijos, 
            religion, grado_instruccion, ocupacion, direccion, motivo_consulta, 
            antecedentes_personales, antecedentes_familiares, antecedentes_psiquiatricos, 
            antecedentes_medicos, antecedentes_pareja, impresion_diagnostica
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conex->prepare($sql);
        // Asignar valores a variables antes de pasarlas por referencia
        $numero_historia = $_POST['numero_historia'] ?? '';
        $centro_salud = $_POST['centro_salud'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $ci_paciente = $_POST['ci_paciente'] ?? '';
        $sexo = $_POST['sexo'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $estado_civil = $_POST['estado_civil'] ?? '';
        $nacionalidad = $_POST['nacionalidad'] ?? '';
        $hijos = $_POST['hijos'] ?? '';
        $religion = $_POST['religion'] ?? '';
        $grado_instruccion = $_POST['grado_instruccion'] ?? '';
        $ocupacion = $_POST['ocupacion'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $motivo_consulta = $_POST['motivo_consulta'] ?? '';
        $antecedentes_personales = $_POST['antecedentes_personales'] ?? '';
        $antecedentes_familiares = $_POST['antecedentes_familiares'] ?? '';
        $antecedentes_psiquiatricos = $_POST['antecedentes_psiquiatricos'] ?? '';
        $antecedentes_medicos = $_POST['antecedentes_medicos'] ?? '';
        $antecedentes_pareja = $_POST['antecedentes_pareja'] ?? '';
        $impresion_diagnostica = $_POST['impresion_diagnostica'] ?? '';

        $stmt->bind_param("iissssssssssssssssssss", 
            $paciente_id, $entrevistador_id,
            $numero_historia, $centro_salud, $fecha,
            $ci_paciente, $sexo, $telefono, $estado_civil,
            $nacionalidad, $hijos, $religion, $grado_instruccion,
            $ocupacion, $direccion, $motivo_consulta,
            $antecedentes_personales, $antecedentes_familiares,
            $antecedentes_psiquiatricos, $antecedentes_medicos,
            $antecedentes_pareja, $impresion_diagnostica
        );
    
    } elseif ($tipo_historia == 'infantil') {

        // Procesar hermanos
        $hermanos_array = [];
        if (isset($_POST['hermano_nombre']) && is_array($_POST['hermano_nombre'])) {
            foreach ($_POST['hermano_nombre'] as $i => $nombre) {
                if (!empty($nombre)) {
                    $hermanos_array[] = [
                        'nombre' => $nombre,
                        'edad' => $_POST['hermano_edad'][$i] ?? '',
                        'sexo' => $_POST['hermano_sexo'][$i] ?? '',
                        'ocupacion' => $_POST['hermano_ocupacion'][$i] ?? '',
                        'vive_hogar' => $_POST['hermano_vive_hogar'][$i] ?? ''
                    ];
                }
            }
        }
        $hermanos_json = json_encode($hermanos_array, JSON_UNESCAPED_UNICODE);

        // Consulta SQL
        $sql = "INSERT INTO historias_infantiles (
            paciente_id, entrevistador_id, numero_historia, centro_salud, fecha, 
            lugar_nacimiento, institucion_escolar, padre_nombre, padre_edad, padre_ci, 
            padre_nacionalidad, padre_religion, padre_instruccion, padre_ocupacion, padre_telefono, 
            padre_direccion, madre_nombre, madre_edad, madre_ci, madre_nacionalidad, 
            madre_religion, madre_instruccion, madre_ocupacion, madre_telefono, madre_direccion, 
            padres_viven_juntos, motivo_separacion, estan_casados, hermanos, motivo_consulta, 
            antecedentes_embarazo, antecedentes_parto, estado_nino_nacer, desarrollo_psicomotor, 
            habitos_independencia, condiciones_salud, vida_social, plan_psicoterapeutico
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Asignar valores a variables antes de pasarlas por referencia
        $numero_historia = $_POST['numero_historia'] ?? '';
        $centro_salud = $_POST['centro_salud'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $lugar_nacimiento = $_POST['lugar_nacimiento'] ?? '';
        $institucion_escolar = $_POST['institucion_escolar'] ?? '';
        $padre_nombre = $_POST['padre_nombre'] ?? '';
        $padre_edad = $_POST['padre_edad'] ?? 0;
        $padre_ci = $_POST['padre_ci'] ?? '';
        $padre_nacionalidad = $_POST['padre_nacionalidad'] ?? '';
        $padre_religion = $_POST['padre_religion'] ?? '';
        $padre_instruccion = $_POST['padre_instruccion'] ?? '';
        $padre_ocupacion = $_POST['padre_ocupacion'] ?? '';
        $padre_telefono = $_POST['padre_telefono'] ?? '';
        $padre_direccion = $_POST['padre_direccion'] ?? '';
        $madre_nombre = $_POST['madre_nombre'] ?? '';
        $madre_edad = $_POST['madre_edad'] ?? 0;
        $madre_ci = $_POST['madre_ci'] ?? '';
        $madre_nacionalidad = $_POST['madre_nacionalidad'] ?? '';
        $madre_religion = $_POST['madre_religion'] ?? '';
        $madre_instruccion = $_POST['madre_instruccion'] ?? '';
        $madre_ocupacion = $_POST['madre_ocupacion'] ?? '';
        $madre_telefono = $_POST['madre_telefono'] ?? '';
        $madre_direccion = $_POST['madre_direccion'] ?? '';
        $padres_viven_juntos = $_POST['padres_viven_juntos'] ?? '';
        $motivo_separacion = $_POST['motivo_separacion'] ?? '';
        $estan_casados = $_POST['estan_casados'] ?? '';
        $motivo_consulta = $_POST['motivo_consulta'] ?? '';
        $antecedentes_embarazo = $_POST['antecedentes_embarazo'] ?? '';
        $antecedentes_parto = $_POST['antecedentes_parto'] ?? '';
        $estado_nino_nacer = $_POST['estado_nino_nacer'] ?? '';
        $desarrollo_psicomotor = $_POST['desarrollo_psicomotor'] ?? '';
        $habitos_independencia = $_POST['habitos_independencia'] ?? '';
        $condiciones_salud = $_POST['condiciones_salud'] ?? '';
        $vida_social = $_POST['vida_social'] ?? '';
        $plan_psicoterapeutico = $_POST['plan_psicoterapeutico'] ?? '';

        $stmt = $conex->prepare($sql);
        $stmt->bind_param("iiis" . str_repeat("s", 34), // total de 38 letras
            $paciente_id,
            $entrevistador_id,
            $numero_historia, $centro_salud, $fecha,
            $lugar_nacimiento, $institucion_escolar,
            $padre_nombre, $padre_edad, $padre_ci, $padre_nacionalidad,
            $padre_religion, $padre_instruccion, $padre_ocupacion, $padre_telefono,
            $padre_direccion, $madre_nombre, $madre_edad, $madre_ci,
            $madre_nacionalidad, $madre_religion, $madre_instruccion,
            $madre_ocupacion, $madre_telefono, $madre_direccion,
            $padres_viven_juntos, $motivo_separacion, $estan_casados,
            $hermanos_json, $motivo_consulta, $antecedentes_embarazo,
            $antecedentes_parto, $estado_nino_nacer, $desarrollo_psicomotor,
            $habitos_independencia, $condiciones_salud, $vida_social, $plan_psicoterapeutico
        );
    }

    if (isset($stmt)) {
        if ($stmt->execute()) {
            header("Location: gestionar_paciente.php?paciente_id={$paciente_id}&status=historia_guardada");
        } else {
            // Puedes descomentar para depurar:
            // die('Error al guardar: ' . $stmt->error);
            header("Location: historia_clinica.php?paciente_id={$paciente_id}&error=guardado");
        }
        $stmt->close();
        exit();
    }
}
$conex->close();
?>