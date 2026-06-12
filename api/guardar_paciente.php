<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

api_json();
$response = ['success' => false, 'message' => 'Ocurrio un error inesperado.'];

api_require_roles(['ecografista', 'administrador', 'recepcionista']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit();
}

api_require_csrf();

if (empty($_POST['nombre_completo']) || empty($_POST['fecha_nacimiento']) || empty($_POST['cedula_tipo']) || empty($_POST['cedula_numero']) || empty($_POST['correo'])) {
    $response['message'] = 'Todos los campos son obligatorios.';
    echo json_encode($response);
    exit();
}

$nombre           = trim($_POST['nombre_completo']);
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$correo           = trim($_POST['correo']);
$cedula_tipo      = $_POST['cedula_tipo'];
$cedula_numero    = trim($_POST['cedula_numero']);
$direccion        = trim((string)($_POST['direccion'] ?? ''));
$telefono         = trim((string)($_POST['telefono'] ?? ''));
$creado_por_id    = (int)$_SESSION['usuario_id'];

if (!preg_match('/^\d{7,8}$/', $cedula_numero)) {
    $response['message'] = 'El numero de cedula debe tener entre 7 y 8 digitos.';
    echo json_encode($response);
    exit();
}

$cedula = $cedula_tipo . $cedula_numero;

$check = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
$check->bind_param("ss", $correo, $cedula);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $response['message'] = 'El correo electronico o la cedula ya estan registrados.';
    $check->close();
    echo json_encode($response);
    exit();
}
$check->close();

try {
    $fecha_nac = new DateTime($fecha_nacimiento);
    $edad = (new DateTime('today'))->diff($fecha_nac)->y;
} catch (Exception $e) {
    $response['message'] = 'Fecha de nacimiento invalida.';
    echo json_encode($response);
    exit();
}

$contrasena_temporal = bin2hex(random_bytes(4));
$contrasena_hash     = password_hash($contrasena_temporal, PASSWORD_DEFAULT);
$rol = 'paciente';
$estado = 'aprobado';

$email_verificado = 1; // creado por un profesional → cuenta de confianza
$insert = $conex->prepare("INSERT INTO usuarios (nombre_completo, fecha_nacimiento, cedula, direccion, telefono, correo, contrasena, rol, estado, email_verificado, creado_por_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insert->bind_param("sssssssssii", $nombre, $fecha_nacimiento, $cedula, $direccion, $telefono, $correo, $contrasena_hash, $rol, $estado, $email_verificado, $creado_por_id);

if ($insert->execute()) {
    eco_auditar($conex, 'paciente_creado', ['entidad' => 'usuario', 'entidad_id' => $insert->insert_id, 'detalle' => ['correo' => $correo]]);
    $response['success']  = true;
    $response['message']  = 'Paciente creado con exito.';
    $response['nombre']   = $nombre;
    $response['password'] = $contrasena_temporal;
} else {
    // FIX SEGURIDAD: log interno + mensaje genérico; detecta duplicado (correo/cédula).
    error_log('guardar_paciente: ' . $insert->error);
    $response['message'] = ($insert->errno === 1062)
        ? 'El correo o la cédula ya están registrados.'
        : 'No se pudo crear el paciente. Inténtalo de nuevo.';
}

$insert->close();
$conex->close();
echo json_encode($response);
