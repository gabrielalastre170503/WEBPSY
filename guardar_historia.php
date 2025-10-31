<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Ocurrió un error.'];

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador', 'secretaria'])) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_historia = $_POST['tipo_historia'] ?? '';
    $paciente_id = isset($_POST['paciente_id']) ? (int)$_POST['paciente_id'] : 0;
    $historia_id = isset($_POST['historia_id']) ? (int)$_POST['historia_id'] : 0;

    if (!$paciente_id) {
        echo json_encode([
            'success' => false,
            'message' => 'El ID del paciente no fue recibido.'
        ]);
        exit;
    }

    try {
        if ($tipo_historia === 'adulto') {
            if ($historia_id > 0) {
                // Actualización de historia adulta
                $telefonoField = trim($_POST['telefono_numero'] ?? '');
                if (!$telefonoField) {
                    $tipoTel = trim($_POST['telefono_tipo'] ?? '');
                    $codigoTel = trim($_POST['telefono_codigo_pais'] ?? '');
                    $numeroTel = trim($_POST['telefono_numero'] ?? '');
                    if ($numeroTel) {
                        $telefonoField = $tipoTel && $codigoTel
                            ? sprintf('%s (%s) %s', $tipoTel, $codigoTel, $numeroTel)
                            : $numeroTel;
                    }
                }

                $sql = "UPDATE historias_adultos SET numero_historia = ?, centro_salud = ?, fecha = ?, ci_paciente = ?, sexo = ?, telefono = ?, estado_civil = ?, nacionalidad = ?, hijos = ?, religion = ?, grado_instruccion = ?, ocupacion = ?, direccion = ?, motivo_consulta = ?, antecedentes_personales = ?, antecedentes_familiares = ?, antecedentes_psiquiatricos = ?, antecedentes_medicos = ?, antecedentes_pareja = ?, impresion_diagnostica = ? WHERE id = ? AND paciente_id = ?";
                $stmt = $conex->prepare($sql);

                $params = [
                    $_POST['numero_historia'] ?? '',
                    $_POST['centro_salud'] ?? '',
                    $_POST['fecha'] ?? '',
                    $_POST['ci_paciente'] ?? '',
                    $_POST['sexo'] ?? '',
                    $telefonoField,
                    $_POST['estado_civil'] ?? '',
                    $_POST['nacionalidad'] ?? '',
                    $_POST['hijos'] ?? '',
                    $_POST['religion'] ?? '',
                    $_POST['grado_instruccion'] ?? '',
                    $_POST['ocupacion'] ?? '',
                    $_POST['direccion'] ?? '',
                    $_POST['motivo_consulta'] ?? '',
                    $_POST['antecedentes_personales'] ?? '',
                    $_POST['antecedentes_familiares'] ?? '',
                    $_POST['antecedentes_psiquiatricos'] ?? '',
                    $_POST['antecedentes_medicos'] ?? '',
                    $_POST['antecedentes_pareja'] ?? '',
                    $_POST['impresion_diagnostica'] ?? '',
                    $historia_id,
                    $paciente_id
                ];

                $types = str_repeat('s', 20) . 'ii';
                $stmt->bind_param($types, ...$params);
                $mensajeExito = 'Historia clínica actualizada con éxito.';
            } else {
                // Inserción de historia adulta
                $telefono_tipo = trim($_POST['telefono_tipo'] ?? '');
                $telefono_codigo_pais = trim($_POST['telefono_codigo_pais'] ?? '');
                $telefono_numero = trim($_POST['telefono_numero'] ?? '');
                $telefono_completo = $telefono_numero
                    ? sprintf('%s (%s) %s', $telefono_tipo ?: 'Móvil', $telefono_codigo_pais ?: '+58', $telefono_numero)
                    : '';

                $sql = "INSERT INTO historias_adultos (paciente_id, entrevistador_id, numero_historia, centro_salud, fecha, ci_paciente, sexo, telefono, estado_civil, nacionalidad, hijos, religion, grado_instruccion, ocupacion, direccion, motivo_consulta, antecedentes_personales, antecedentes_familiares, antecedentes_psiquiatricos, antecedentes_medicos, antecedentes_pareja, impresion_diagnostica) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conex->prepare($sql);
                $stmt->bind_param(
                    "iissssssssssssssssssss",
                    $paciente_id,
                    $_SESSION['usuario_id'],
                    $_POST['numero_historia'] ?? '',
                    $_POST['centro_salud'] ?? '',
                    $_POST['fecha'] ?? '',
                    $_POST['ci_paciente'] ?? '',
                    $_POST['sexo'] ?? '',
                    $telefono_completo,
                    $_POST['estado_civil'] ?? '',
                    $_POST['nacionalidad'] ?? '',
                    $_POST['hijos'] ?? '',
                    $_POST['religion'] ?? '',
                    $_POST['grado_instruccion'] ?? '',
                    $_POST['ocupacion'] ?? '',
                    $_POST['direccion'] ?? '',
                    $_POST['motivo_consulta'] ?? '',
                    $_POST['antecedentes_personales'] ?? '',
                    $_POST['antecedentes_familiares'] ?? '',
                    $_POST['antecedentes_psiquiatricos'] ?? '',
                    $_POST['antecedentes_medicos'] ?? '',
                    $_POST['antecedentes_pareja'] ?? '',
                    $_POST['impresion_diagnostica'] ?? ''
                );
                $mensajeExito = 'Historia clínica guardada con éxito.';
            }
        } elseif ($tipo_historia === 'infantil') {
            $hermanos_array = [];
            if (isset($_POST['hermano_nombre']) && is_array($_POST['hermano_nombre'])) {
                $totalHermanos = count($_POST['hermano_nombre']);
                for ($i = 0; $i < $totalHermanos; $i++) {
                    $nombreHermano = trim($_POST['hermano_nombre'][$i] ?? '');
                    if ($nombreHermano === '') {
                        continue;
                    }
                    $hermanos_array[] = [
                        'nombre' => $nombreHermano,
                        'edad' => $_POST['hermano_edad'][$i] ?? '',
                        'sexo' => $_POST['hermano_sexo'][$i] ?? '',
                        'ocupacion' => $_POST['hermano_ocupacion'][$i] ?? '',
                        'vive_hogar' => $_POST['hermano_vive_hogar'][$i] ?? ''
                    ];
                }
            }
            $hermanos_json = json_encode($hermanos_array, JSON_UNESCAPED_UNICODE);

            $params = [
                $_POST['numero_historia'] ?? '',
                $_POST['centro_salud'] ?? '',
                $_POST['fecha'] ?? '',
                $_POST['lugar_nacimiento'] ?? '',
                $_POST['institucion_escolar'] ?? '',
                $_POST['ci_infante'] ?? '',
                $_POST['padre_nombre'] ?? '',
                $_POST['padre_edad'] ?? '',
                $_POST['padre_ci'] ?? '',
                $_POST['padre_nacionalidad'] ?? '',
                $_POST['padre_religion'] ?? '',
                $_POST['padre_instruccion'] ?? '',
                $_POST['padre_ocupacion'] ?? '',
                $_POST['padre_telefono'] ?? '',
                $_POST['padre_direccion'] ?? '',
                $_POST['madre_nombre'] ?? '',
                $_POST['madre_edad'] ?? '',
                $_POST['madre_ci'] ?? '',
                $_POST['madre_nacionalidad'] ?? '',
                $_POST['madre_religion'] ?? '',
                $_POST['madre_instruccion'] ?? '',
                $_POST['madre_ocupacion'] ?? '',
                $_POST['madre_telefono'] ?? '',
                $_POST['madre_direccion'] ?? '',
                $_POST['padres_viven_juntos'] ?? '',
                $_POST['motivo_separacion'] ?? '',
                $_POST['estan_casados'] ?? '',
                $hermanos_json,
                $_POST['motivo_consulta'] ?? '',
                $_POST['antecedentes_embarazo'] ?? '',
                $_POST['antecedentes_parto'] ?? '',
                $_POST['estado_nino_nacer'] ?? '',
                $_POST['desarrollo_psicomotor'] ?? '',
                $_POST['habitos_independencia'] ?? '',
                $_POST['condiciones_salud'] ?? '',
                $_POST['vida_social'] ?? '',
                $_POST['plan_psicoterapeutico'] ?? ''
            ];

            if ($historia_id > 0) {
                $sql = "UPDATE historias_infantiles SET numero_historia = ?, centro_salud = ?, fecha = ?, lugar_nacimiento = ?, institucion_escolar = ?, ci_infante = ?, padre_nombre = ?, padre_edad = ?, padre_ci = ?, padre_nacionalidad = ?, padre_religion = ?, padre_instruccion = ?, padre_ocupacion = ?, padre_telefono = ?, padre_direccion = ?, madre_nombre = ?, madre_edad = ?, madre_ci = ?, madre_nacionalidad = ?, madre_religion = ?, madre_instruccion = ?, madre_ocupacion = ?, madre_telefono = ?, madre_direccion = ?, padres_viven_juntos = ?, motivo_separacion = ?, estan_casados = ?, hermanos = ?, motivo_consulta = ?, antecedentes_embarazo = ?, antecedentes_parto = ?, estado_nino_nacer = ?, desarrollo_psicomotor = ?, habitos_independencia = ?, condiciones_salud = ?, vida_social = ?, plan_psicoterapeutico = ? WHERE id = ? AND paciente_id = ?";
                $stmt = $conex->prepare($sql);
                $types = str_repeat('s', 37) . 'ii';
                $stmt->bind_param($types, ...array_merge($params, [$historia_id, $paciente_id]));
                $mensajeExito = 'Historia clínica actualizada con éxito.';
            } else {
                $sql = "INSERT INTO historias_infantiles (paciente_id, entrevistador_id, numero_historia, centro_salud, fecha, lugar_nacimiento, institucion_escolar, ci_infante, padre_nombre, padre_edad, padre_ci, padre_nacionalidad, padre_religion, padre_instruccion, padre_ocupacion, padre_telefono, padre_direccion, madre_nombre, madre_edad, madre_ci, madre_nacionalidad, madre_religion, madre_instruccion, madre_ocupacion, madre_telefono, madre_direccion, padres_viven_juntos, motivo_separacion, estan_casados, hermanos, motivo_consulta, antecedentes_embarazo, antecedentes_parto, estado_nino_nacer, desarrollo_psicomotor, habitos_independencia, condiciones_salud, vida_social, plan_psicoterapeutico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conex->prepare($sql);
                if ($stmt === false) {
                    throw new Exception('Error en la preparación de la consulta infantil: ' . $conex->error);
                }

                $stmt->bind_param(
                    "iisssssssissssssssissssssssssssssssssss",
                    $paciente_id,
                    $_SESSION['usuario_id'],
                    ...$params
                );
                $mensajeExito = 'Historia clínica guardada con éxito.';
            }
        } else {
            throw new Exception('Error: Tipo de historia no válido.');
        }

        if (!$stmt->execute()) {
            throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
        }

        $response['success'] = true;
        $response['message'] = $mensajeExito;
        $response['paciente_id'] = $paciente_id;
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
}

$conex->close();
echo json_encode($response);
?>