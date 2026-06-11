<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: ' . eco_url('login'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $tipo   = $_POST['tipo'] ?? '';

    if ($accion === 'agregar' && $tipo === 'faq') {
        $pregunta  = trim($_POST['pregunta']  ?? '');
        $respuesta = trim($_POST['respuesta'] ?? '');
        $stmt = $conex->prepare("INSERT INTO faqs (pregunta, respuesta) VALUES (?, ?)");
        $stmt->bind_param("ss", $pregunta, $respuesta);
        $ok = $stmt->execute();
        $stmt->close();
        header("Location: gestionar_faq.php?status=" . ($ok ? 'added' : 'error'));
        exit();
    }

    if ($accion === 'actualizar' && $tipo === 'textos_web') {
        $mision  = $_POST['mision']  ?? '';
        $vision  = $_POST['vision']  ?? '';
        $valores = $_POST['valores'] ?? '';
        $stmt = $conex->prepare("INSERT INTO contenido_web (clave, valor) VALUES ('mision', ?), ('vision', ?), ('valores', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->bind_param("sss", $mision, $vision, $valores);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: gestionar_textos.php?status=' . ($ok ? 'updated' : 'error'));
        exit();
    }

    if ($accion === 'agregar' && $tipo === 'eco_tipo') {
        $nombre      = trim($_POST['nombre'] ?? '');
        $codigo      = trim($_POST['codigo'] ?? '');
        $categoria   = trim($_POST['categoria'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $icono       = trim($_POST['icono'] ?? '') ?: 'fa-solid fa-wave-square';

        if ($nombre === '') {
            header('Location: gestionar_estudios_ecograficos.php?status=error');
            exit();
        }

        if ($codigo === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $nombre));
            $codigo = strtoupper(trim($slug, '_'));
        }

        $esquema = json_encode(['version' => 1, 'secciones' => []], JSON_UNESCAPED_UNICODE);
        $stmt = $conex->prepare(
            "INSERT INTO tipos_ecografias (codigo, nombre, categoria, descripcion, icono, esquema_campos, activo)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param('ssssss', $codigo, $nombre, $categoria, $descripcion, $icono, $esquema);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: gestionar_estudios_ecograficos.php?status=' . ($ok ? 'added' : 'error'));
        exit();
    }
}

if (isset($_GET['accion']) && $_GET['accion'] === 'borrar') {
    $tipo = $_GET['tipo'] ?? '';
    $id   = (int)($_GET['id'] ?? 0);

    if ($tipo === 'faq' && $id > 0) {
        $stmt = $conex->prepare("DELETE FROM faqs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: gestionar_faq.php?status=' . ($ok ? 'deleted' : 'error'));
        exit();
    }

    if ($tipo === 'eco_tipo' && $id > 0 && ($_GET['accion'] ?? '') === 'desactivar') {
        $stmt = $conex->prepare("UPDATE tipos_ecografias SET activo = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: gestionar_estudios_ecograficos.php?status=' . ($ok ? 'deleted' : 'error'));
        exit();
    }
}

$conex->close();
header('Location: panel.php');
exit();
