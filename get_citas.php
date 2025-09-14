<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Asegurarse de que un psicólogo está logueado
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$psicologo_id = $_SESSION['usuario_id'];
$eventos = [];

// Consulta actualizada para incluir el ID del paciente (u.id)
$sql = "SELECT c.id, c.fecha_cita, u.nombre_completo, u.id as paciente_id 
        FROM citas c
        JOIN usuarios u ON c.paciente_id = u.id
        WHERE c.psicologo_id = ? AND c.estado = 'confirmada'";

$stmt = $conex->prepare($sql);
$stmt->bind_param("i", $psicologo_id);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    // Formateamos los datos para que FullCalendar los entienda
    $eventos[] = [
        'title' => $fila['nombre_completo'], // El nombre del paciente
        'start' => $fila['fecha_cita'],      // La fecha y hora de la cita
        'extendedProps' => [
            'paciente_id' => $fila['paciente_id'] // <-- AÑADIMOS EL ID DEL PACIENTE AQUÍ
        ]
    ];
}

echo json_encode($eventos);

$stmt->close();
$conex->close();
?>s