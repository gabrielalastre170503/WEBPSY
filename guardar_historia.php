<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Ocurrió un error.'];

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_historia = $_POST['tipo_historia'] ?? '';
    $paciente_id = $_POST['paciente_id'] ?? null;

if (empty($paciente_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'El ID del paciente no fue recibido.'
    ]);
    exit;
}
    
       if ($tipo_historia == 'adulto') {
        // --- LÓGICA PARA UNIR EL NÚMERO DE TELÉFONO ---
        $telefono_tipo = $_POST['telefono_tipo'];
        $telefono_codigo_pais = $_POST['telefono_codigo_pais'];
        $telefono_numero = $_POST['telefono_numero'];
        $telefono_completo = $telefono_tipo . ' (' . $telefono_codigo_pais . ') ' . $telefono_numero;
        // --- FIN DE LA LÓGICA ---

        // (Aquí va tu validación de campos requeridos)



        $sql = "INSERT INTO historias_adultos (paciente_id, entrevistador_id, numero_historia, centro_salud, fecha, ci_paciente, sexo, telefono, estado_civil, nacionalidad, hijos, religion, grado_instruccion, ocupacion, direccion, motivo_consulta, antecedentes_personales, antecedentes_familiares, antecedentes_psiquiatricos, antecedentes_medicos, antecedentes_pareja, impresion_diagnostica) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conex->prepare($sql);
        
        $stmt->bind_param("iissssssssssssssssssss", 
            $paciente_id, $_SESSION['usuario_id'],
            $_POST['numero_historia'], $_POST['centro_salud'], $_POST['fecha'],
            $_POST['ci_paciente'], $_POST['sexo'], $telefono_completo, $_POST['estado_civil'],
            $_POST['nacionalidad'], $_POST['hijos'], $_POST['religion'], $_POST['grado_instruccion'],
            $_POST['ocupacion'], $_POST['direccion'], $_POST['motivo_consulta'],
            $_POST['antecedentes_personales'], $_POST['antecedentes_familiares'],
            $_POST['antecedentes_psiquiatricos'], $_POST['antecedentes_medicos'],
            $_POST['antecedentes_pareja'], $_POST['impresion_diagnostica']
        );

    } elseif ($tipo_historia == 'infantil') {
        
        $hermanos_array = [];
        if (isset($_POST['hermano_nombre']) && is_array($_POST['hermano_nombre'])) {
            for ($i = 0; $i < count($_POST['hermano_nombre']); $i++) {
                if (!empty($_POST['hermano_nombre'][$i])) {
                    $hermanos_array[] = [
                        'nombre' => $_POST['hermano_nombre'][$i] ?? '', 'edad' => $_POST['hermano_edad'][$i] ?? '',
                        'sexo' => $_POST['hermano_sexo'][$i] ?? '', 'ocupacion' => $_POST['hermano_ocupacion'][$i] ?? '',
                        'vive_hogar' => $_POST['hermano_vive_hogar'][$i] ?? ''
                    ];
                }
            }
        }
        $hermanos_json = json_encode($hermanos_array, JSON_UNESCAPED_UNICODE);

        
        $sql = "INSERT INTO historias_infantiles (paciente_id, entrevistador_id, numero_historia, centro_salud, fecha, lugar_nacimiento, institucion_escolar, ci_infante, padre_nombre, padre_edad, padre_ci, padre_nacionalidad, padre_religion, padre_instruccion, padre_ocupacion, padre_telefono, padre_direccion, madre_nombre, madre_edad, madre_ci, madre_nacionalidad, madre_religion, madre_instruccion, madre_ocupacion, madre_telefono, madre_direccion, padres_viven_juntos, motivo_separacion, estan_casados, hermanos, motivo_consulta, antecedentes_embarazo, antecedentes_parto, estado_nino_nacer, desarrollo_psicomotor, habitos_independencia, condiciones_salud, vida_social, plan_psicoterapeutico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conex->prepare($sql);
        if ($stmt === false) {
            $response['message'] = 'Error en la preparación de la consulta infantil: ' . $conex->error;
            echo json_encode($response);
            exit();
        }
        
        // Asignar cada valor a una variable antes de pasarlo por referencia
        $paciente_id = $_POST['paciente_id'] ?? null; // ¡Aquí está la variable clave!
        $entrevistador_id = $_SESSION['usuario_id'] ?? null; // Asumo que el ID del entrevistador viene de la sesión
        $numero_historia = $_POST['numero_historia'] ?? '';
        $centro_salud = $_POST['centro_salud'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $lugar_nacimiento = $_POST['lugar_nacimiento'] ?? '';
        $institucion_escolar = $_POST['institucion_escolar'] ?? '';
        $ci_infante = $_POST['ci_infante'] ?? '';
        $padre_nombre = $_POST['padre_nombre'] ?? '';
        $padre_edad = $_POST['padre_edad'] ?? null;
        $padre_ci = $_POST['padre_ci'] ?? '';
        $padre_nacionalidad = $_POST['padre_nacionalidad'] ?? '';
        $padre_religion = $_POST['padre_religion'] ?? '';
        $padre_instruccion = $_POST['padre_instruccion'] ?? '';
        $padre_ocupacion = $_POST['padre_ocupacion'] ?? '';
        $padre_telefono = $_POST['padre_telefono'] ?? '';
        $padre_direccion = $_POST['padre_direccion'] ?? '';
        $madre_nombre = $_POST['madre_nombre'] ?? '';
        $madre_edad = $_POST['madre_edad'] ?? null;
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

        


    // Vincular los parámetros a la consulta

        // Ajuste de tipos: tratar número de historia, cédulas y teléfonos como strings
        $stmt->bind_param(
    "iisssssssissssssssissssssssssssssssssss",
    $paciente_id,          // i
    $entrevistador_id,     // i
    $numero_historia,      // s (se guarda como texto para evitar conversiones a 0)
    $centro_salud,         // s
    $fecha,                // s
    $lugar_nacimiento,     // s
    $institucion_escolar,  // s
    $ci_infante,           // s (guardar cedula como texto)
    $padre_nombre,         // s
    $padre_edad,           // i
    $padre_ci,             // s (guardar cedula como texto)
    $padre_nacionalidad,   // s
    $padre_religion,       // s
    $padre_instruccion,    // s
    $padre_ocupacion,      // s
    $padre_telefono,       // s (guardar teléfono como texto)
    $padre_direccion,      // s
    $madre_nombre,         // s
    $madre_edad,           // i
    $madre_ci,             // s
    $madre_nacionalidad,   // s
    $madre_religion,       // s
    $madre_instruccion,    // s
    $madre_ocupacion,      // s
    $madre_telefono,       // s
    $madre_direccion,      // s
    $padres_viven_juntos,  // s
    $motivo_separacion,    // s
    $estan_casados,        // s
    $hermanos_json,        // s
    $motivo_consulta,      // s
    $antecedentes_embarazo,// s
    $antecedentes_parto,   // s
    $estado_nino_nacer,    // s
    $desarrollo_psicomotor,// s
    $habitos_independencia,// s
    $condiciones_salud,    // s
    $vida_social,          // s
    $plan_psicoterapeutico // s
);
    } else {
        $response['message'] = 'Error: Tipo de historia no válido.';
        echo json_encode($response);
        exit();
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Historia clínica guardada con éxito.';
        // 👇 AÑADE ESTA LÍNEA
        $response['paciente_id'] = $paciente_id; // Devuelve el ID que ya tenías
    } else {
        $response['message'] = 'Error al ejecutar la consulta: ' . $stmt->error;
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
?>