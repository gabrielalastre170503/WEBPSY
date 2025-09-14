<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: login.php');
    exit();
}

// --- LÓGICA PARA AÑADIR (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $tipo = $_POST['tipo'];
    $redirect_url = 'panel.php?vista=admin-contenido'; // URL por defecto

    if ($tipo == 'terapia') {
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $stmt = $conex->prepare("INSERT INTO terapias (nombre, descripcion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $descripcion);
        $redirect_url = 'gestionar_terapias.php';
    } 
    elseif ($tipo == 'farmaco') {
        $nombre_comercial = $_POST['nombre_comercial'];
        $principio_activo = $_POST['principio_activo'];
        $descripcion_uso = $_POST['descripcion_uso'];
        $stmt = $conex->prepare("INSERT INTO farmacos (nombre_comercial, principio_activo, descripcion_uso) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre_comercial, $principio_activo, $descripcion_uso);
        $redirect_url = 'gestionar_farmacos.php';
    }
    // --- LÓGICA AÑADIDA PARA FAQS ---
    elseif ($tipo == 'faq') {
        $pregunta = $_POST['pregunta'];
        $respuesta = $_POST['respuesta'];
        $stmt = $conex->prepare("INSERT INTO faqs (pregunta, respuesta) VALUES (?, ?)");
        $stmt->bind_param("ss", $pregunta, $respuesta);
        $redirect_url = 'gestionar_faq.php';
    }

    if (isset($stmt)) {
        if ($stmt->execute()) {
            header("Location: $redirect_url?status=added");
        } else {
            header("Location: $redirect_url?error=add_failed");
        }
        $stmt->close();
    }
}

// --- LÓGICA AÑADIDA PARA ACTUALIZAR TEXTOS WEB ---
    if ($tipo == 'textos_web' && $accion == 'actualizar') {
        $mision = $_POST['mision'];
        $vision = $_POST['vision'];
        $valores = $_POST['valores'];

        // Usamos una consulta preparada con "ON DUPLICATE KEY UPDATE" para actualizar
        $stmt = $conex->prepare("INSERT INTO contenido_web (clave, valor) VALUES ('mision', ?), ('vision', ?), ('valores', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->bind_param("sss", $mision, $vision, $valores);
        
        if ($stmt->execute()) {
            header('Location: gestionar_textos.php?status=updated');
        } else {
            header('Location: gestionar_textos.php?error=update_failed');
        }
        $stmt->close();
        exit();
    }

// --- LÓGICA PARA BORRAR (GET) ---
if (isset($_GET['accion']) && $_GET['accion'] == 'borrar') {
    $tipo = $_GET['tipo'];
    $id = $_GET['id'];
    $redirect_url = 'panel.php?vista=admin-contenido';

    if ($tipo == 'terapia') {
        $stmt = $conex->prepare("DELETE FROM terapias WHERE id = ?");
        $redirect_url = 'gestionar_terapias.php';
    }
    elseif ($tipo == 'farmaco') {
        $stmt = $conex->prepare("DELETE FROM farmacos WHERE id = ?");
        $redirect_url = 'gestionar_farmacos.php';
    }
    // --- LÓGICA AÑADIDA PARA FAQS ---
    elseif ($tipo == 'faq') {
        $stmt = $conex->prepare("DELETE FROM faqs WHERE id = ?");
        $redirect_url = 'gestionar_faq.php';
    }

    if (isset($stmt)) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: $redirect_url?status=deleted");
        } else {
            header("Location: $redirect_url?error=delete_failed");
        }
        $stmt->close();
    }
}

$conex->close();
exit();
?>