<?php
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';
require_once __DIR__ . '/../lib/citas/citas.php';
require_once __DIR__ . '/../lib/comunicaciones/notificaciones.php';

api_json();
$response = ['success' => false];

// Seguridad: Solo ecografistas pueden marcar citas como completadas
if (!in_array(api_rol(), ['ecografista'], true)) {
    api_fail('Acceso denegado.', 200);
}

api_require_csrf();

if (isset($_POST['cita_id'])) {
    $cita_id = (int)$_POST['cita_id'];
    $ecografista_id = api_uid();

    // Preparamos la consulta para actualizar el estado de la cita a 'completada'
    // Nos aseguramos de que el ecografista solo pueda modificar sus propias citas
    $stmt = $conex->prepare("UPDATE citas SET estado = 'completada' WHERE id = ? AND ecografista_id = ?");
    $stmt->bind_param("ii", $cita_id, $ecografista_id);

    if ($stmt->execute()) {
        // Verificamos si alguna fila fue realmente afectada
        if ($stmt->affected_rows > 0) {
            eco_auditar($conex, 'cita_completada', ['entidad' => 'cita', 'entidad_id' => $cita_id]);
            eco_cita_evento($conex, (int)$cita_id, 'completada', ['estado_nuevo' => 'completada']);
            if ($c = $conex->query("SELECT paciente_id FROM citas WHERE id = " . (int)$cita_id)->fetch_assoc()) {
                eco_notificar($conex, (int)$c['paciente_id'], 'cita_completada', 'Estudio completado', [
                    'mensaje' => 'Tu estudio fue marcado como completado. Pronto tendrás tus resultados.',
                    'url'     => 'mis-informes',
                    'icono'   => 'fa-solid fa-clipboard-check',
                ]);
            }
            $response['success'] = true;
        } else {
            $response['message'] = 'No se encontró la cita o no tienes permiso para modificarla.';
        }
    } else {
        $response['message'] = 'Error al ejecutar la consulta.';
    }

    $stmt->close();
} else {
    $response['message'] = 'No se proporcionó el ID de la cita.';
}

$conex->close();
echo json_encode($response);
