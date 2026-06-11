<?php
session_start();
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/informes/estudios_render.php';
require_once __DIR__ . '/lib/informes/informes.php';

api_json();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$paciente_id = isset($_GET['paciente_id']) && is_numeric($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
$tipo_id     = isset($_GET['tipo_id'])     && is_numeric($_GET['tipo_id'])     ? (int)$_GET['tipo_id']     : 0;
$informe_id  = isset($_GET['informe_id'])  && is_numeric($_GET['informe_id'])  ? (int)$_GET['informe_id']  : 0;

// Modo edicion: el informe define paciente y tipo. Bloqueado si esta firmado/anulado.
$informe_existente = null;
if ($informe_id > 0) {
    $stmt = $conex->prepare("SELECT id, paciente_id, tipo_ecografia_id, datos_clinicos, estado, ecografista_id FROM informes_estudios WHERE id = ?");
    $stmt->bind_param('i', $informe_id);
    $stmt->execute();
    $informe_existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$informe_existente) {
        echo json_encode(['error' => 'Informe no encontrado.']);
        exit();
    }
    $rol_sesion = (string)$_SESSION['rol'];
    $uid_sesion = (int)$_SESSION['usuario_id'];
    if (!eco_puede_gestionar_informe($rol_sesion, $uid_sesion, (int)$informe_existente['ecografista_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'No puedes editar un informe de otro profesional.']);
        exit();
    }
    if (in_array($informe_existente['estado'], ['firmado', 'anulado'], true)) {
        echo json_encode(['error' => 'Un informe ' . eco_informe_estado_label($informe_existente['estado']) . ' no se puede editar.']);
        exit();
    }
    $paciente_id = (int)$informe_existente['paciente_id'];
    $tipo_id     = (int)$informe_existente['tipo_ecografia_id'];
}

if (!$paciente_id || !$tipo_id) {
    echo json_encode(['error' => 'Parámetros inválidos.']);
    exit();
}

// Paciente
$stmt = $conex->prepare("SELECT id, nombre_completo, cedula, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param('i', $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$paciente) {
    echo json_encode(['error' => 'Paciente no encontrado.']);
    exit();
}

// Tipo de ecografía
$stmt = $conex->prepare("SELECT id, nombre, categoria, descripcion, icono, esquema_campos, esquema_version FROM tipos_ecografias WHERE id = ? AND activo = 1");
$stmt->bind_param('i', $tipo_id);
$stmt->execute();
$tipo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tipo) {
    echo json_encode(['error' => 'Tipo de ecografía no encontrado o inactivo.']);
    exit();
}

$esquema = json_decode($tipo['esquema_campos'], true) ?: ['secciones' => []];

// Pre-rellenar datos del paciente en la sección encabezado
$datos_iniciales = [
    'encabezado' => [
        'nombres_apellidos' => $paciente['nombre_completo'] ?? '',
        'cedula'            => $paciente['cedula'] ?? '',
        'edad'              => $paciente['edad'] ?? '',
        'fecha'             => date('Y-m-d'),
    ],
];

// En edicion, los datos guardados tienen prioridad sobre los valores por defecto.
if ($informe_existente) {
    $datos_guardados = json_decode($informe_existente['datos_clinicos'], true);
    if (is_array($datos_guardados)) {
        $datos_iniciales = array_replace_recursive($datos_iniciales, $datos_guardados);
    }
}

$form_html = eco_render_formulario($esquema, $datos_iniciales);

$payload = [
    'html'     => $form_html,
    'tipo'     => [
        'id'              => (int)$tipo['id'],
        'nombre'          => $tipo['nombre'],
        'categoria'       => $tipo['categoria'] ?? '',
        'descripcion'     => $tipo['descripcion'] ?? '',
        'icono'           => $tipo['icono'] ?? 'fa-solid fa-wave-square',
        'esquema_version' => (int)$tipo['esquema_version'],
    ],
    'paciente' => [
        'id'     => (int)$paciente['id'],
        'nombre' => $paciente['nombre_completo'],
        'cedula' => $paciente['cedula'] ?? '',
        'edad'   => $paciente['edad'] ?? '',
    ],
];

if ($informe_existente) {
    $payload['informe'] = [
        'id'     => (int)$informe_existente['id'],
        'estado' => $informe_existente['estado'],
    ];
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
