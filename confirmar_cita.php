<?php
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/citas/citas.php';
require_once __DIR__ . '/lib/comunicaciones/notificaciones.php';

// Seguridad: Solo ecografistas pueden confirmar citas
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista'])) {
    header('Location: ' . eco_url('login'));
    exit();
}

if (!isset($_GET['cita_id']) || !is_numeric($_GET['cita_id'])) {
    die("ID de cita no válido.");
}

$cita_id        = (int)$_GET['cita_id'];
$ecografista_id = (int)$_SESSION['usuario_id'];

// Confirma la cita (solo si está pendiente y asignada a este ecografista)
$stmt = $conex->prepare("UPDATE citas SET estado = 'confirmada', fecha_respuesta = NOW() WHERE id = ? AND ecografista_id = ? AND estado = 'pendiente'");
$stmt->bind_param("ii", $cita_id, $ecografista_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        eco_cita_evento($conex, $cita_id, 'confirmada', ['estado_anterior' => 'pendiente', 'estado_nuevo' => 'confirmada']);
        if ($c = $conex->query("SELECT paciente_id, fecha_cita FROM citas WHERE id = " . (int)$cita_id)->fetch_assoc()) {
            $cuando = !empty($c['fecha_cita']) ? date('d/m/Y H:i', strtotime($c['fecha_cita'])) : '';
            eco_notificar($conex, (int)$c['paciente_id'], 'cita_confirmada', 'Cita confirmada', [
                'mensaje' => $cuando ? ('Tu cita fue confirmada para el ' . $cuando . '.') : 'Tu cita fue confirmada.',
                'url'     => 'mis_citas_paciente.php',
                'icono'   => 'fa-solid fa-calendar-check',
            ]);
        }
    }
    header('Location: mis_solicitudes.php?status=cita_confirmada');
} else {
    header('Location: mis_solicitudes.php?error=confirmacion_fallida');
}

$stmt->close();
$conex->close();
exit();
