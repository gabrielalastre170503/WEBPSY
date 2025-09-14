<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo secretarias y administradores pueden ver todas las citas
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['secretaria', 'administrador'])) {
    http_response_code(403); // Código de error "Prohibido"
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

$eventos = [];
// Paleta de colores para diferenciar a los psicólogos en el calendario
$colores = ['#02b1f4', '#28a745', '#ffc107', '#6f42c1', '#fd7e14', '#dc3545'];
$psicologo_colores = [];
$color_index = 0;

// Consulta para obtener todas las citas confirmadas, uniendo con las tablas de usuarios
// para obtener los nombres del paciente y del psicólogo.
$sql = "SELECT 
            c.id, 
            c.fecha_cita, 
            p.nombre_completo as paciente_nombre, 
            ps.nombre_completo as psicologo_nombre, 
            ps.id as psicologo_id
        FROM citas c
        JOIN usuarios p ON c.paciente_id = p.id
        JOIN usuarios ps ON c.psicologo_id = ps.id
        WHERE c.estado = 'confirmada'";

$resultado = $conex->query($sql);

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        // Asignar un color único a cada psicólogo para diferenciarlo en el calendario
        if (!isset($psicologo_colores[$fila['psicologo_id']])) {
            $psicologo_colores[$fila['psicologo_id']] = $colores[$color_index % count($colores)];
            $color_index++;
        }
        $color_cita = $psicologo_colores[$fila['psicologo_id']];

        // Formateamos los datos para que FullCalendar los entienda
        $eventos[] = [
            'title' => $fila['paciente_nombre'] . ' (con ' . $fila['psicologo_nombre'] . ')',
            'start' => $fila['fecha_cita'],
            'backgroundColor' => $color_cita,
            'borderColor' => $color_cita
        ];
    }
}

echo json_encode($eventos);

$conex->close();
?> 
