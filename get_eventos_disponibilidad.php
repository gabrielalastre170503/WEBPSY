<?php
session_start();
include 'conexion.php';
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) { exit(); }
$psicologo_id = $_SESSION['usuario_id'];
$eventos = [];

// 1. Obtener horario recurrente para mostrarlo como fondo
$stmt = $conex->prepare("SELECT dia_semana, hora_inicio, hora_fin FROM horarios_recurrentes WHERE psicologo_id = ?");
$stmt->bind_param("i", $psicologo_id);
$stmt->execute();
$horario_recurrente = $stmt->get_result();
while ($fila = $horario_recurrente->fetch_assoc()) {
    $eventos[] = [
        'title' => date("g:ia", strtotime($fila['hora_inicio'])) . ' - ' . date("g:ia", strtotime($fila['hora_fin'])),
        'daysOfWeek' => [ $fila['dia_semana'] == 7 ? 0 : $fila['dia_semana'] ], // Domingo es 0 en JS, 7 en MySQL
        'display' => 'background',
        'color' => '#d4edda' // Un verde claro
    ];
}
$stmt->close();

// 2. Obtener excepciones (días libres) para mostrarlos como eventos
$stmt = $conex->prepare("SELECT id, fecha FROM disponibilidad_excepciones WHERE psicologo_id = ? AND tipo = 'no_disponible'");
$stmt->bind_param("i", $psicologo_id);
$stmt->execute();
$excepciones = $stmt->get_result();
while ($fila = $excepciones->fetch_assoc()) {
    $eventos[] = [
        'id' => $fila['id'],
        'title' => 'Día no disponible',
        'start' => $fila['fecha'],
        'allDay' => true,
        'backgroundColor' => '#f8d7da',
        'borderColor' => '#f5c6cb',
        'textColor' => '#721c24',
        'extendedProps' => [ 'tipo' => 'no_disponible' ]
    ];
}
$stmt->close();

echo json_encode($eventos);
$conex->close();
?>