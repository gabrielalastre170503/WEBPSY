<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo pacientes pueden ver la disponibilidad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['psicologo_id']) || !isset($_GET['fecha'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros']);
    exit();
}

$psicologo_id = (int)$_GET['psicologo_id'];
$fecha_seleccionada = $_GET['fecha'];
$horas_disponibles = [];

try {
    // 1. Obtener el horario semanal para el día seleccionado
    $dia_semana = date('N', strtotime($fecha_seleccionada)); // 1 (Lunes) a 7 (Domingo)
    $stmt_horario = $conex->prepare("SELECT hora_inicio, hora_fin FROM horarios_recurrentes WHERE psicologo_id = ? AND dia_semana = ?");
    $stmt_horario->bind_param("ii", $psicologo_id, $dia_semana);
    $stmt_horario->execute();
    $resultado_horario = $stmt_horario->get_result();
    
    // Si no hay horario definido para este día de la semana, devolvemos un array vacío.
    if ($resultado_horario->num_rows === 0) {
        echo json_encode([]);
        $conex->close();
        exit();
    }
    $horario_dia = $resultado_horario->fetch_assoc();
    $stmt_horario->close();

    // 2. Si hay horario, obtener las citas ya confirmadas para ESE DÍA
    $citas_confirmadas = [];
    $stmt_citas = $conex->prepare("SELECT fecha_cita FROM citas WHERE psicologo_id = ? AND DATE(fecha_cita) = ? AND estado IN ('confirmada', 'reprogramada')");
    $stmt_citas->bind_param("is", $psicologo_id, $fecha_seleccionada);
    $stmt_citas->execute();
    $resultado_citas = $stmt_citas->get_result();
    while ($fila = $resultado_citas->fetch_assoc()) {
        $citas_confirmadas[] = date('H:i:s', strtotime($fila['fecha_cita']));
    }
    $stmt_citas->close();

    // 3. Generar los huecos de hora disponibles
    $duracion_cita = 60; // en minutos
    $intervalo = new DateInterval('PT' . $duracion_cita . 'M');
    $hora_inicio = new DateTime($horario_dia['hora_inicio']);
    $hora_fin = new DateTime($horario_dia['hora_fin']);

    for ($hora_actual = $hora_inicio; $hora_actual < $hora_fin; $hora_actual->add($intervalo)) {
        $slot_str = $hora_actual->format('H:i:s');
        if (!in_array($slot_str, $citas_confirmadas)) {
            $horas_disponibles[] = $hora_actual->format('H:i');
        }
    }

    echo json_encode($horas_disponibles);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}

$conex->close();
?>