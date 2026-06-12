<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../conexion.php';

api_json();
$response = ['success' => false, 'message' => ''];

api_require_roles(['administrador']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

api_require_csrf();

$rol = trim((string)($_POST['rol'] ?? ''));
if (!in_array($rol, ['ecografista', 'recepcionista'], true)) {
    $response['message'] = 'Rol no válido.';
    echo json_encode($response);
    exit;
}

$nombre_completo  = trim((string)($_POST['nombre_completo'] ?? ''));
$fecha_nacimiento = (string)($_POST['fecha_nacimiento'] ?? '');
$cedula_tipo      = (string)($_POST['cedula_tipo'] ?? '');
$cedula_numero    = trim((string)($_POST['cedula_numero'] ?? ''));
$direccion        = trim((string)($_POST['direccion'] ?? ''));
$telefono         = trim((string)($_POST['telefono'] ?? ''));
$correo           = trim((string)($_POST['correo'] ?? ''));
$contrasena       = (string)($_POST['contrasena'] ?? '');
$confirmar        = (string)($_POST['confirmar_contrasena'] ?? '');

if ($nombre_completo === '' || $fecha_nacimiento === '' || $cedula_tipo === '' || $cedula_numero === '' || $correo === '') {
    $response['message'] = 'Complete todos los campos obligatorios.';
    echo json_encode($response);
    exit;
}

if (!preg_match('/^\d{7,8}$/', $cedula_numero)) {
    $response['message'] = 'El número de cédula debe tener entre 7 y 8 dígitos.';
    echo json_encode($response);
    exit;
}

if ($contrasena !== $confirmar) {
    $response['message'] = 'Las contraseñas no coinciden.';
    echo json_encode($response);
    exit;
}

if (strlen($contrasena) < 8 || !preg_match('/[A-Z]/', $contrasena) || !preg_match('/[\W_]/', $contrasena)) {
    $response['message'] = 'La contraseña debe tener al menos 8 caracteres, una mayúscula y un símbolo.';
    echo json_encode($response);
    exit;
}

$cedula = $cedula_tipo . $cedula_numero;

try {
    $fecha_nac = new DateTime($fecha_nacimiento);
    $edad = (new DateTime('today'))->diff($fecha_nac)->y;
} catch (Exception $e) {
    $response['message'] = 'Fecha de nacimiento inválida.';
    echo json_encode($response);
    exit;
}

$check = $conex->prepare('SELECT id FROM usuarios WHERE correo = ? OR cedula = ?');
$check->bind_param('ss', $correo, $cedula);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $check->close();
    $response['message'] = 'El correo o la cédula ya están registrados.';
    echo json_encode($response);
    exit;
}
$check->close();

$contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT);
$estado = 'aprobado';

$email_verificado = 1; // creado por el administrador → cuenta de confianza
$insert = $conex->prepare('INSERT INTO usuarios (nombre_completo, fecha_nacimiento, cedula, direccion, telefono, correo, contrasena, rol, estado, email_verificado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->bind_param('sssssssssi', $nombre_completo, $fecha_nacimiento, $cedula, $direccion, $telefono, $correo, $contrasena_hasheada, $rol, $estado, $email_verificado);

if ($insert->execute()) {
    $response['success'] = true;
    $response['message'] = 'Cuenta creada correctamente.';
    $response['nombre'] = $nombre_completo;
} else {
    // FIX SEGURIDAD: log interno + mensaje genérico; detecta duplicado (correo/cédula).
    error_log('registrar_personal_admin: ' . $insert->error);
    $response['message'] = ($insert->errno === 1062)
        ? 'El correo o la cédula ya están registrados.'
        : 'No se pudo guardar el registro. Inténtalo de nuevo.';
}

$insert->close();
$conex->close();
echo json_encode($response);
