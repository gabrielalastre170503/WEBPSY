<?php
session_start();
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';
api_json();

if (!isset($_SESSION['usuario_id'])) { exit(); }
$ecografista_id = $_SESSION['usuario_id'];
$eventos = [];

// 1. Obtener horario recurrente para mostrarlo como fondo
$stmt = $conex->prepare("SELECT dia_semana, hora_inicio, hora_fin FROM horarios_recurrentes WHERE ecografista_id = ?");
$stmt->bind_param("i", $ecografista_id);
$stmt->execute();
$horario_recurrente = $stmt->get_result();
while ($fila = $horario_recurrente->fetch_assoc()) {
    $eventos[] = [
        // Día laborable (DISPONIBLE): fondo verde claro en TODA la celda, claramente
        // visible en las tres vistas. Sin texto, para mantener el calendario limpio.
        'title' => '',
        'daysOfWeek' => [ $fila['dia_semana'] == 7 ? 0 : $fila['dia_semana'] ], // Domingo es 0 en JS, 7 en MySQL
        'allDay' => true,
        'display' => 'background',
        'color' => 'rgba(34, 197, 94, 0.22)'
    ];
}
$stmt->close();

// 2. Obtener excepciones (días libres) para mostrarlos como eventos
$stmt = $conex->prepare("SELECT id, fecha FROM disponibilidad_excepciones WHERE ecografista_id = ? AND tipo = 'no_disponible'");
$stmt->bind_param("i", $ecografista_id);
$stmt->execute();
$excepciones = $stmt->get_result();
while ($fila = $excepciones->fetch_assoc()) {
    $eventos[] = [
        'id' => $fila['id'],
        'title' => 'No disponible',
        'start' => $fila['fecha'],
        'allDay' => true,
        'backgroundColor' => '#ef4444',
        'borderColor' => '#ef4444',
        'textColor' => '#ffffff',
        'extendedProps' => [ 'tipo' => 'no_disponible' ]
    ];
}
$stmt->close();

echo json_encode($eventos);
$conex->close();
?>