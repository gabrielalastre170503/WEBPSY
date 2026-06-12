<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';
require_once __DIR__ . '/../lib/citas/citas.php';

// Seguridad: Solo roles autorizados pueden guardar una cita
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'recepcionista', 'administrador'], true)) {
    header('Location: ' . eco_url('login'));
    exit();
}

api_require_csrf();

$rol = $_SESSION['rol'];
$wants_json = (!empty($_POST['ajax']) && $_POST['ajax'] === '1');

function guardar_cita_redirect_base(): string
{
    global $rol;
    if ($rol === 'recepcionista') {
        return eco_url('citas-pendientes');
    }
    if ($rol === 'administrador') {
        return eco_url('dashboard');
    }
    return eco_url('mi-agenda');
}

// Validar que los datos necesarios del formulario fueron enviados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cita_id'], $_POST['fecha_cita'])) {

    $cita_id      = $_POST['cita_id'];
    $fecha_cita   = $_POST['fecha_cita'];
    $ecografista_id = null;

    if ($rol === 'ecografista') {
        $ecografista_id = (int)$_SESSION['usuario_id'];
    } elseif (($rol === 'recepcionista' || $rol === 'administrador') && !empty($_POST['ecografista_id'])) {
        $ecografista_id = (int)$_POST['ecografista_id'];
    }

    if ($ecografista_id === null || $ecografista_id <= 0) {
        if ($wants_json) {
            api_json();
            echo json_encode(['success' => false, 'message' => 'Debes seleccionar un ecografista.']);
            exit();
        }
        header('Location: ' . guardar_cita_redirect_base() . '?error=no_psicologo');
        exit();
    }

    $stmt = $conex->prepare('UPDATE citas SET fecha_cita = ?, ecografista_id = ?, estado = \'confirmada\' WHERE id = ?');
    $stmt->bind_param('sii', $fecha_cita, $ecografista_id, $cita_id);

    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        eco_auditar($conex, 'cita_programada', ['entidad' => 'cita', 'entidad_id' => $cita_id, 'detalle' => ['ecografista_id' => $ecografista_id, 'fecha' => $fecha_cita]]);
        eco_cita_evento($conex, (int)$cita_id, 'confirmada', ['estado_nuevo' => 'confirmada', 'detalle' => ['ecografista_id' => $ecografista_id, 'fecha' => $fecha_cita]]);
    }

    if ($wants_json) {
        api_json();
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Cita programada correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo guardar la cita.']);
        }
        exit();
    }

    if ($ok) {
        header('Location: ' . guardar_cita_redirect_base() . '?status=cita_programada');
    } else {
        header('Location: ' . guardar_cita_redirect_base() . '?error=programacion_fallida');
    }
    exit();
}

if ($wants_json) {
    api_json();
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
    exit();
}

header('Location: ' . guardar_cita_redirect_base());
if (isset($conex) && $conex instanceof mysqli) {
    $conex->close();
}