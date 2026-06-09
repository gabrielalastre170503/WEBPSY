<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'] ?? '', ['recepcionista', 'administrador'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'pacientes' => [], 'message' => 'Acceso denegado.']);
    exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'pacientes' => []]);
    exit;
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
    echo json_encode(['success' => false, 'pacientes' => [], 'message' => 'Error de consulta.']);
    exit;
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

echo json_encode(['success' => true, 'pacientes' => $pacientes]);
