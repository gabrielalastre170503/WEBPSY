<?php
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/seguridad/seguridad.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador'])) {
    header('Location: ' . eco_url('login'));
    exit();
}

$ecografista_id = $_SESSION['usuario_id'];
$accion = $_REQUEST['accion'] ?? ''; // Usamos $_REQUEST para aceptar GET y POST

// --- ACCIÓN: Guardar el horario semanal ---
if ($accion == 'guardar_recurrente' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    require_csrf();
    // Primero, borramos el horario anterior para no duplicar.
    // FIX SEGURIDAD: sentencia preparada en lugar de interpolar la variable
    // directamente en el SQL (defensa en profundidad contra inyección SQL).
    $del = $conex->prepare("DELETE FROM horarios_recurrentes WHERE ecografista_id = ?");
    $del->bind_param("i", $ecografista_id);
    $del->execute();
    $del->close();

    if (isset($_POST['dias'])) {
        foreach ($_POST['dias'] as $dia_num => $horario) {
            if (isset($horario['activo'])) {
                $inicio = $horario['inicio'];
                $fin = $horario['fin'];
                $stmt = $conex->prepare("INSERT INTO horarios_recurrentes (ecografista_id, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $ecografista_id, $dia_num, $inicio, $fin);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    eco_auditar($conex, 'disponibilidad_actualizada', ['detalle' => ['accion' => 'horario_recurrente']]);
    header('Location: gestionar_disponibilidad.php?status=ok');
    exit();
}

// --- ACCIÓN: Marcar/Desmarcar un día como no disponible ---
if ($accion == 'alternar_dia_libre' && isset($_POST['fecha'])) {
    require_csrf();
    $fecha = $_POST['fecha'];
    
    // Revisar si ya existe una excepción para ese día
    $stmt = $conex->prepare("SELECT id FROM disponibilidad_excepciones WHERE ecografista_id = ? AND fecha = ? AND tipo = 'no_disponible'");
    $stmt->bind_param("is", $ecografista_id, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Si ya existe, la borramos (vuelve a estar disponible)
        $excepcion = $result->fetch_assoc();
        $delete_stmt = $conex->prepare("DELETE FROM disponibilidad_excepciones WHERE id = ?");
        $delete_stmt->bind_param("i", $excepcion['id']);
        $delete_stmt->execute();
        $delete_stmt->close();
    } else {
        // Si no existe, la creamos (se marca como no disponible)
        $insert_stmt = $conex->prepare("INSERT INTO disponibilidad_excepciones (ecografista_id, fecha, tipo) VALUES (?, ?, 'no_disponible')");
        $insert_stmt->bind_param("is", $ecografista_id, $fecha);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    $stmt->close();
    eco_auditar($conex, 'disponibilidad_dia_alternado', ['detalle' => ['fecha' => $fecha]]);
    header('Location: gestionar_disponibilidad.php?status=ok');
    exit();
}

// --- ACCIÓN: Eliminar una excepción (cuando se hace clic en un evento "No disponible") ---
if ($accion == 'eliminar_excepcion' && isset($_POST['id'])) {
    require_csrf();
    $excepcion_id = (int)$_POST['id'];
    $stmt = $conex->prepare("DELETE FROM disponibilidad_excepciones WHERE id = ? AND ecografista_id = ?");
    $stmt->bind_param("ii", $excepcion_id, $ecografista_id);
    $stmt->execute();
    $stmt->close();
    eco_auditar($conex, 'disponibilidad_excepcion_eliminada', ['entidad' => 'disponibilidad_excepcion', 'entidad_id' => $excepcion_id]);
    header('Location: gestionar_disponibilidad.php?status=ok');
    exit();
}

// Si no hay acción válida, redirigir
header('Location: panel.php');
?>