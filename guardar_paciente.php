<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Ocurrió un error inesperado.'];

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador', 'secretaria'])) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validamos que todos los campos lleguen
    if (empty($_POST['nombre_completo']) || empty($_POST['fecha_nacimiento']) || empty($_POST['cedula_tipo']) || empty($_POST['cedula_numero']) || empty($_POST['correo'])) {
        $response['message'] = 'Todos los campos son obligatorios.';
        echo json_encode($response);
        exit();
    }

    $nombre = $_POST['nombre_completo'];
    $fecha_nacimiento = $_POST['fecha_nacimiento']; // <-- Campo nuevo
    $correo = $_POST['correo'];
    $cedula_tipo = $_POST['cedula_tipo'];
    $cedula_numero = $_POST['cedula_numero'];

    $usuarioActualId = $_SESSION['usuario_id'];
    $rolUsuario = $_SESSION['rol'];
    $psicologo_id = $usuarioActualId;

    if ($rolUsuario === 'secretaria') {
        if (empty($_POST['profesional_asignado'])) {
            $response['message'] = 'Selecciona el profesional responsable para este paciente.';
            http_response_code(400);
            echo json_encode($response);
            exit();
        }

        $psicologo_id = (int)$_POST['profesional_asignado'];

        $validarProfesional = $conex->prepare("SELECT id FROM usuarios WHERE id = ? AND rol IN ('psicologo','psiquiatra') AND estado = 'aprobado'");
        $validarProfesional->bind_param("i", $psicologo_id);
        $validarProfesional->execute();
        if ($validarProfesional->get_result()->num_rows === 0) {
            $response['message'] = 'El profesional seleccionado no es válido.';
            $validarProfesional->close();
            http_response_code(400);
            echo json_encode($response);
            exit();
        }
        $validarProfesional->close();
    }

    if (strlen($cedula_numero) < 7 || strlen($cedula_numero) > 8) {
        $response['message'] = 'El número de cédula debe tener entre 7 y 8 dígitos.';
        echo json_encode($response);
        exit();
    }
    
    $cedula = $cedula_tipo . $cedula_numero;

    // Verificar si el correo o la cédula ya existen
    $check_stmt = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
    $check_stmt->bind_param("ss", $correo, $cedula);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $response['message'] = 'El correo electrónico o la cédula ya están registrados en el sistema.';
        echo json_encode($response);
        exit();
    }
    $check_stmt->close();

    // --- LÓGICA PARA CALCULAR LA EDAD ---
    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;
    // --- FIN DE LA LÓGICA ---

    // Generar contraseña temporal
    $contrasena_temporal = bin2hex(random_bytes(4));
    $contrasena_hasheada = password_hash($contrasena_temporal, PASSWORD_DEFAULT);
    
    $rol = 'paciente';
    $estado = 'aprobado';

    // --- CONSULTA Y BIND_PARAM ACTUALIZADOS ---
    $insert_stmt = $conex->prepare("INSERT INTO usuarios (nombre_completo, fecha_nacimiento, edad, cedula, correo, contrasena, rol, estado, creado_por_psicologo_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("ssisssssi", $nombre, $fecha_nacimiento, $edad, $cedula, $correo, $contrasena_hasheada, $rol, $estado, $psicologo_id);
    
    if ($insert_stmt->execute()) {
        $response['success'] = true;
        $response['message'] = '¡Paciente creado con éxito!';
        $response['nombre'] = $nombre;
        $response['password'] = $contrasena_temporal;
    } else {
        $response['message'] = 'Error al guardar en la base de datos.';
    }
    $insert_stmt->close();
}

$conex->close();
echo json_encode($response);
?>