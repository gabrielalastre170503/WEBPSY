<?php
require_once __DIR__ . '/lib/api.php';
include 'conexion.php';

api_json();
if (!in_array(api_rol(), ['recepcionista', 'administrador'], true)) {
    api_fail('Acceso denegado.', 403, ['pacientes' => []]);
}

$q = api_get_str('q');
if (mb_strlen($q) < 2) {
    api_ok(['pacientes' => []]);
}

$busqueda = '%' . $q . '%';
$sql = "SELECT id, nombre_completo, cedula
    FROM usuarios
    WHERE rol = 'paciente' AND estado = 'aprobado'
    AND (nombre_completo LIKE ? OR cedula LIKE ?)
    ORDER BY nombre_completo ASC
    LIMIT 15";

$stmt = $conex->prepare($sql);
if (!$stmt) {
    api_fail('Error de consulta.', 200, ['pacientes' => []]);
}

$stmt->bind_param('ss', $busqueda, $busqueda);
$stmt->execute();
$res = $stmt->get_result();
$pacientes = [];
while ($row = $res->fetch_assoc()) {
    $pacientes[] = [
        'id'     => (int)$row['id'],
        'nombre' => $row['nombre_completo'],
        'cedula' => $row['cedula'] ?? '',
    ];
}
$stmt->close();
$conex->close();

api_ok(['pacientes' => $pacientes]);
