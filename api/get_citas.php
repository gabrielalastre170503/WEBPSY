<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';

api_json();

// Seguridad: Asegurarse de que un psicólogo está logueado
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$ecografista_id = $_SESSION['usuario_id'];
$eventos = [];

// Consulta actualizada para incluir el ID del paciente (u.id) y el estado.
$sql = "SELECT c.id, c.fecha_cita, c.estado, u.nombre_completo, u.id as paciente_id
        FROM citas c
        JOIN usuarios u ON c.paciente_id = u.id
        WHERE c.ecografista_id = ? AND c.estado IN ('confirmada', 'reprogramada')";

$stmt = $conex->prepare($sql);
$stmt->bind_param("i", $ecografista_id);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    // Formateamos los datos para que FullCalendar los entienda
    $eventos[] = [
        'id' => $fila['id'],
        'title' => $fila['nombre_completo'], // El nombre del paciente
        'start' => $fila['fecha_cita'],      // La fecha y hora de la cita
        'backgroundColor' => $fila['estado'] === 'reprogramada' ? '#f59e0b' : '#02b1f4',
        'borderColor' => $fila['estado'] === 'reprogramada' ? '#f59e0b' : '#02b1f4',
        'extendedProps' => [
            'paciente_id' => $fila['paciente_id'],
            'estado' => $fila['estado']
        ]
    ];
}

echo json_encode($eventos);

$stmt->close();
$conex->close();
?>