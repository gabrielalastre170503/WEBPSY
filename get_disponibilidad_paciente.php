<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'paciente' || !isset($_GET['psicologo_id'])) {
    http_response_code(403);
    exit();
}

$psicologo_id = (int)$_GET['psicologo_id'];
$start_str = $_GET['start'];
$end_str = $_GET['end'];

// 1. Obtener datos de la BD (citas, excepciones, horario)
$citas_confirmadas = [];
$stmt_citas = $conex->prepare("SELECT fecha_cita FROM citas WHERE psicologo_id = ? AND estado IN ('confirmada', 'reprogramada') AND fecha_cita BETWEEN ? AND ?");
$stmt_citas->bind_param("iss", $psicologo_id, $start_str, $end_str);
$stmt_citas->execute();
$resultado_citas = $stmt_citas->get_result();
while ($fila = $resultado_citas->fetch_assoc()) {
    $citas_confirmadas[] = $fila['fecha_cita'];
}
$stmt_citas->close();

$dias_no_disponibles = [];
$stmt_excepciones = $conex->prepare("SELECT fecha FROM disponibilidad_excepciones WHERE psicologo_id = ? AND tipo = 'no_disponible'");
$stmt_excepciones->bind_param("i", $psicologo_id);
$stmt_excepciones->execute();
$resultado_excepciones = $stmt_excepciones->get_result();
while ($fila = $resultado_excepciones->fetch_assoc()) {
    $dias_no_disponibles[] = $fila['fecha'];
}
$stmt_excepciones->close();

$horario_semanal = [];
$stmt_horario = $conex->prepare("SELECT dia_semana, hora_inicio, hora_fin FROM horarios_recurrentes WHERE psicologo_id = ?");
$stmt_horario->bind_param("i", $psicologo_id);
$stmt_horario->execute();
$resultado_horario = $stmt_horario->get_result();
while ($fila = $resultado_horario->fetch_assoc()) {
    $horario_semanal[$fila['dia_semana']] = ['inicio' => $fila['hora_inicio'], 'fin' => $fila['hora_fin']];
}
$stmt_horario->close();

// 2. Generar eventos
$eventos = [];
$duracion_cita = 60;
$intervalo = new DateInterval('PT' . $duracion_cita . 'M');
$periodo = new DatePeriod(new DateTime($start_str), new DateInterval('P1D'), new DateTime($end_str));

foreach ($periodo as $fecha) {
    $dia_semana = $fecha->format('N');
    $fecha_str = $fecha->format('Y-m-d');

    // Condición 1: El día es una excepción (vacaciones, etc.)
    if (in_array($fecha_str, $dias_no_disponibles)) {
        $eventos[] = ['title' => 'No Disponible', 'start' => $fecha_str, 'allDay' => true, 'display' => 'background', 'backgroundColor' => '#f8d7da'];
        continue;
    }

    // Condición 2: El día no es laborable según el horario semanal
    if (!isset($horario_semanal[$dia_semana])) {
        continue; // Simplemente no mostramos nada, el día se ve vacío
    }

    // Condición 3: El día es laborable, generamos los huecos
    $hora_inicio = new DateTime($fecha->format('Y-m-d') . ' ' . $horario_semanal[$dia_semana]['inicio']);
    $hora_fin = new DateTime($fecha->format('Y-m-d') . ' ' . $horario_semanal[$dia_semana]['fin']);
    $huecos_generados = 0;

    for ($hora_actual = clone $hora_inicio; $hora_actual < $hora_fin; $hora_actual->add($intervalo)) {
        $slot_str = $hora_actual->format('Y-m-d H:i:s');
        if (!in_array($slot_str, $citas_confirmadas) && $hora_actual > new DateTime()) {
            $eventos[] = [
                'title' => date('h:i A', strtotime($slot_str)),
                'start' => $slot_str,
                'backgroundColor' => '#28a745',
                'borderColor' => '#28a745'
            ];
            $huecos_generados++;
        }
    }
    
    // Condición 4: El día era laborable pero todos los huecos están ocupados
    if ($huecos_generados === 0 && $fecha >= new DateTime('today')) {
         $eventos[] = ['title' => 'Completo', 'start' => $fecha_str, 'allDay' => true, 'display' => 'background', 'backgroundColor' => '#f8d7da'];
    }
}

echo json_encode($eventos);
$conex->close();
?>