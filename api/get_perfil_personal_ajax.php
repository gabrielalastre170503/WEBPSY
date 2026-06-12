<?php
session_start();
include __DIR__ . '/../core/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $response['message'] = 'ID no válido.';
    echo json_encode($response);
    exit;
}

$stmt = $conex->prepare('SELECT id, nombre_completo, correo, cedula, rol, estado, fecha_registro, fecha_nacimiento, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM usuarios WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    $response['message'] = 'Usuario no encontrado.';
    echo json_encode($response);
    exit;
}

$rolLabels = [
    'ecografista'   => 'Ecografista',
    'recepcionista' => 'Recepcionista',
    'administrador' => 'Administrador',
    'paciente'      => 'Paciente',
];

$estadoLabels = [
    'aprobado'     => 'Aprobado',
    'pendiente'    => 'Pendiente',
    'inhabilitado' => 'Inhabilitado',
];

$iniciales = '';
foreach (preg_split('/\s+/u', trim($row['nombre_completo'] ?? '')) as $part) {
    if ($part !== '' && mb_strlen($iniciales) < 2) {
        $iniciales .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
if ($iniciales === '') {
    $iniciales = '?';
}

$fnac = '';
if (!empty($row['fecha_nacimiento'])) {
    $ts = strtotime($row['fecha_nacimiento']);
    if ($ts) {
        $fnac = date('d/m/Y', $ts);
    }
}

$freg = '';
if (!empty($row['fecha_registro'])) {
    $ts = strtotime($row['fecha_registro']);
    if ($ts) {
        $freg = date('d/m/Y', $ts);
    }
}

$rol = $row['rol'] ?? '';
$esSelf = (int)$_SESSION['usuario_id'] === (int)$row['id'];

$response['success'] = true;
$response['perfil'] = [
    'id'               => (int)$row['id'],
    'nombre'           => $row['nombre_completo'],
    'correo'           => $row['correo'],
    'cedula'           => $row['cedula'] ?? '',
    'rol'              => $rol,
    'rol_label'        => $rolLabels[$rol] ?? ucfirst($rol),
    'estado'           => $row['estado'],
    'estado_label'     => $estadoLabels[$row['estado']] ?? ucfirst($row['estado'] ?? ''),
    'fecha_registro'   => $freg,
    'fecha_nacimiento' => $fnac,
    'edad'             => isset($row['edad']) ? (int)$row['edad'] : null,
    'iniciales'        => $iniciales,
    'avatar_class'     => $rol === 'recepcionista' ? 'staff-perfil-avatar--rx' : ($rol === 'ecografista' ? 'staff-perfil-avatar--eco' : 'staff-perfil-avatar--default'),
    'puede_acciones'   => !$esSelf,
];

$conex->close();
echo json_encode($response);
