<?php
require_once __DIR__ . '/lib/core/api.php';
include 'conexion.php';
require_once __DIR__ . '/lib/seguridad/seguridad.php';
require_once __DIR__ . '/lib/citas/citas.php';

api_json();
if (!in_array(api_rol(), ['ecografista'], true)) {
    api_fail('Acceso no autorizado.', 403);
}

api_require_csrf();

$response = ['success' => false, 'message' => 'Datos inválidos.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cita_id'], $_POST['nueva_fecha_cita'], $_POST['motivo_reprogramacion'])) {
    $cita_id = $_POST['cita_id'];
    $nueva_fecha = $_POST['nueva_fecha_cita'];
    $motivo = $_POST['motivo_reprogramacion'];
    $ecografista_id = api_uid();

    $fecha_formateada = date('d/m/Y \a \l\a\s h:i A', strtotime($nueva_fecha));
    $notificacion = "Su cita ha sido reprogramada para el <strong>{$fecha_formateada}</strong>. Motivo: <em>\"" . htmlspecialchars($motivo) . "\"</em>. Si no está de acuerdo, por favor contáctenos a través de la sección de Ayuda.";

    // --- LÓGICA CORREGIDA ---
    // La nueva fecha se guarda en 'fecha_propuesta' y el estado cambia a 'pendiente_paciente'
    $stmt = $conex->prepare("UPDATE citas SET fecha_propuesta = ?, reprogramacion_motivo = ?, notificacion_paciente = ?, estado = 'pendiente_paciente' WHERE id = ? AND ecografista_id = ?");
    $stmt->bind_param("sssii", $nueva_fecha, $motivo, $notificacion, $cita_id, $ecografista_id);

    if ($stmt->execute()) {
        eco_auditar($conex, 'cita_reprogramada', ['entidad' => 'cita', 'entidad_id' => $cita_id, 'detalle' => ['nueva_fecha' => $nueva_fecha]]);
        eco_cita_evento($conex, (int)$cita_id, 'reprogramada', ['estado_nuevo' => 'pendiente_paciente', 'detalle' => ['fecha_propuesta' => $nueva_fecha, 'motivo' => $motivo]]);
        $response['success'] = true;
        $response['message'] = 'Cita reprogramada y paciente notificado.';
    } else {
        $response['message'] = 'Error al actualizar la base de datos.';
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
