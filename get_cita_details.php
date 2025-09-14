<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo pacientes logueados pueden ver sus citas
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cita no válido']);
    exit();
}

$cita_id = $_GET['id'];
$paciente_id = $_SESSION['usuario_id'];

// Obtener detalles de la cita y del profesional
$sql = "SELECT c.*, p.nombre_completo as psicologo_nombre, p.rol as psicologo_rol
        FROM citas c
        LEFT JOIN usuarios p ON c.psicologo_id = p.id
        WHERE c.id = ? AND c.paciente_id = ?";
$stmt = $conex->prepare($sql);
$stmt->bind_param("ii", $cita_id, $paciente_id);
$stmt->execute();
$resultado = $stmt->get_result();
$cita = $resultado->fetch_assoc();

if (!$cita) {
    http_response_code(404);
    echo json_encode(['error' => 'Cita no encontrada']);
    exit();
}

$datos_extra = [];
if ($cita['psicologo_rol'] == 'psicologo') {
    $titulo_extra = "Terapias Sugeridas";
    $resultado_extra = $conex->query("SELECT nombre, descripcion FROM terapias LIMIT 4"); // Muestra 4 terapias de ejemplo
    while($fila = $resultado_extra->fetch_assoc()) {
        $datos_extra[] = $fila;
    }
} elseif ($cita['psicologo_rol'] == 'psiquiatra') {
    $titulo_extra = "Fármacos de Referencia";
    $resultado_extra = $conex->query("SELECT nombre_comercial, descripcion_uso FROM farmacos LIMIT 4"); // Muestra 4 fármacos de ejemplo
    while($fila = $resultado_extra->fetch_assoc()) {
        $datos_extra[] = $fila;
    }
}

// Preparar los datos para enviar como JSON
$respuesta = [
    'fecha_cita' => $cita['fecha_cita'] ? date('d/m/Y h:i A', strtotime($cita['fecha_cita'])) : 'Por confirmar',
    'estado' => ucfirst($cita['estado']),
    'psicologo_nombre' => $cita['psicologo_nombre'] ?? 'No asignado',
    'motivo_consulta' => $cita['motivo_consulta'],
    'reprogramacion_motivo' => $cita['reprogramacion_motivo'],
    'titulo_extra' => $titulo_extra ?? null,
    'datos_extra' => $datos_extra
];

echo json_encode($respuesta);

$stmt->close();
$conex->close();
?>