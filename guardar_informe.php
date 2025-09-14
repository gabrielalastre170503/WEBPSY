<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Ocurrió un error inesperado.'];

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paciente_id'])) {
    $paciente_id = $_POST['paciente_id'];
    $psicologo_id = $_SESSION['usuario_id'];

    // --- VALIDACIÓN DE CAMPOS EN EL SERVIDOR ---
    $campos_requeridos = [
        'numero_historia', 'fecha_evaluacion', 'referido_por', 'motivo_referencia',
        'actitud_ante_evaluacion', 'area_visomotriz', 'area_intelectual', 'area_emocional',
        'resultados_adicionales', 'recomendaciones'
    ];

    foreach ($campos_requeridos as $campo) {
        if (empty(trim($_POST[$campo]))) {
            $response['message'] = 'Error: Todos los campos del formulario son obligatorios.';
            echo json_encode($response);
            exit();
        }
    }
    // --- FIN DE LA VALIDACIÓN ---

    $sql = "INSERT INTO informes_psicologicos (
        paciente_id, psicologo_id, numero_historia, fecha_evaluacion, 
        motivo_referencia, referido_por, actitud_ante_evaluacion, 
        area_visomotriz, area_intelectual, area_emocional, 
        resultados_adicionales, recomendaciones
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
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
        $response['success'] = true;
        $response['message'] = 'Informe guardado con éxito.';
    } else {
        $response['message'] = 'Error al guardar el informe en la base de datos.';
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
?>