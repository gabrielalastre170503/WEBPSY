<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

$psicologo_id = $_SESSION['usuario_id'];
$accion = $_REQUEST['accion'] ?? ''; // Usamos $_REQUEST para aceptar GET y POST

// --- ACCIÓN: Guardar el horario semanal ---
if ($accion == 'guardar_recurrente' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // Primero, borramos el horario anterior para no duplicar
    $conex->query("DELETE FROM horarios_recurrentes WHERE psicologo_id = $psicologo_id");

    if (isset($_POST['dias'])) {
        foreach ($_POST['dias'] as $dia_num => $horario) {
            if (isset($horario['activo'])) {
                $inicio = $horario['inicio'];
                $fin = $horario['fin'];
                $stmt = $conex->prepare("INSERT INTO horarios_recurrentes (psicologo_id, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $psicologo_id, $dia_num, $inicio, $fin);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    header('Location: gestionar_disponibilidad.php?status=ok');
    exit();
}

// --- ACCIÓN: Marcar/Desmarcar un día como no disponible ---
if ($accion == 'alternar_dia_libre' && isset($_GET['fecha'])) {
    $fecha = $_GET['fecha'];
    
    // Revisar si ya existe una excepción para ese día
    $stmt = $conex->prepare("SELECT id FROM disponibilidad_excepciones WHERE psicologo_id = ? AND fecha = ? AND tipo = 'no_disponible'");
    $stmt->bind_param("is", $psicologo_id, $fecha);
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
        $insert_stmt = $conex->prepare("INSERT INTO disponibilidad_excepciones (psicologo_id, fecha, tipo) VALUES (?, ?, 'no_disponible')");
        $insert_stmt->bind_param("is", $psicologo_id, $fecha);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    $stmt->close();
    header('Location: gestionar_disponibilidad.php?status=ok');
    exit();
}

// --- ACCIÓN: Eliminar una excepción (cuando se hace clic en un evento "No disponible") ---
if ($accion == 'eliminar_excepcion' && isset($_GET['id'])) {
    $excepcion_id = $_GET['id'];
    $stmt = $conex->prepare("DELETE FROM disponibilidad_excepciones WHERE id = ? AND psicologo_id = ?");
    $stmt->bind_param("ii", $excepcion_id, $psicologo_id);
    $stmt->execute();
    $stmt->close();
    header('Location: gestionar_disponibilidad.php?status=ok');
    exit();
}

// Si no hay acción válida, redirigir
header('Location: panel.php');
?>