<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Seguridad: Solo roles autorizados
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador', 'secretaria'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

if (!isset($_GET['informe_id']) || !is_numeric($_GET['informe_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de informe no válido']);
    exit();
}

$informe_id = (int)$_GET['informe_id'];

// --- CONSULTA MEJORADA PARA OBTENER TODOS LOS DATOS ---
$sql = "SELECT i.*, u.nombre_completo as paciente_nombre, u.cedula as paciente_cedula
        FROM informes_psicologicos i
        JOIN usuarios u ON i.paciente_id = u.id
        WHERE i.id = ?";
$stmt = $conex->prepare($sql);
$stmt->bind_param("i", $informe_id);
$stmt->execute();
$resultado = $stmt->get_result();
$informe = $resultado->fetch_assoc();

if (!$informe) {
    http_response_code(404);
    echo json_encode(['error' => 'Informe no encontrado']);
    exit();
}

// Formateamos la fecha para mostrarla
$informe['fecha_evaluacion_formateada'] = date('d/m/Y', strtotime($informe['fecha_evaluacion']));

// Devolvemos todos los datos del informe
echo json_encode($informe);

$stmt->close();
$conex->close();
?>