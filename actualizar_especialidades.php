<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel.php?vista=admin-especialidades');
    exit();
}

$usuarioObjetivo = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
$especialidadesEntrada = isset($_POST['especialidades']) ? trim($_POST['especialidades']) : '';

if ($usuarioObjetivo <= 0) {
    header('Location: panel.php?vista=admin-especialidades&esp=error');
    exit();
}

$verificarStmt = $conex->prepare("SELECT id FROM usuarios WHERE id = ? AND rol IN ('psicologo', 'psiquiatra') LIMIT 1");
$verificarStmt->bind_param('i', $usuarioObjetivo);
$verificarStmt->execute();
$verificarResultado = $verificarStmt->get_result();
$verificarStmt->close();

if ($verificarResultado->num_rows === 0) {
    header('Location: panel.php?vista=admin-especialidades&esp=error');
    exit();
}

$especialidadesFormateadas = preg_replace('/\s*,\s*/', ', ', $especialidadesEntrada);
$especialidadesFormateadas = trim($especialidadesFormateadas, ' ,');
$especialidadesFormateadas = preg_replace('/\s+/', ' ', $especialidadesFormateadas);

if ($especialidadesFormateadas === '') {
    $actualizarStmt = $conex->prepare('UPDATE usuarios SET especialidades = NULL WHERE id = ?');
    $actualizarStmt->bind_param('i', $usuarioObjetivo);
} else {
    $especialidadesFormateadas = substr($especialidadesFormateadas, 0, 255);
    $actualizarStmt = $conex->prepare('UPDATE usuarios SET especialidades = ? WHERE id = ?');
    $actualizarStmt->bind_param('si', $especialidadesFormateadas, $usuarioObjetivo);
}

$exito = $actualizarStmt->execute();
$actualizarStmt->close();

header('Location: panel.php?vista=admin-especialidades&esp=' . ($exito ? 'success' : 'error'));
exit();
