<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre_completo'];
    $cedula = $_POST['cedula'];
    $correo = $_POST['correo'];
    $psicologo_id = $_SESSION['usuario_id'];

    // Verificar si el correo o la cédula ya existen
    $check_stmt = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
    $check_stmt->bind_param("ss", $correo, $cedula);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        die("Error: El correo electrónico o la cédula ya están registrados. <a href='crear_paciente.php'>Volver</a>");
    }
    $check_stmt->close();

    // --- NUEVA LÓGICA ---
    // 1. Generar una contraseña temporal segura
    $contrasena_temporal = bin2hex(random_bytes(4)); // Crea una contraseña aleatoria de 8 caracteres

    // 2. Encriptar la contraseña generada
    $contrasena_hasheada = password_hash($contrasena_temporal, PASSWORD_DEFAULT);
    
    $rol = 'paciente';
    $estado = 'aprobado';

    $insert_stmt = $conex->prepare("INSERT INTO usuarios (nombre_completo, cedula, correo, contrasena, rol, estado, creado_por_psicologo_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("ssssssi", $nombre, $cedula, $correo, $contrasena_hasheada, $rol, $estado, $psicologo_id);
    
    if ($insert_stmt->execute()) {
        // 3. Guardar la contraseña en la sesión para mostrarla después
        $_SESSION['nuevo_paciente_nombre'] = $nombre;
        $_SESSION['contrasena_temporal'] = $contrasena_temporal;
        header('Location: panel.php');
    } else {
        header('Location: crear_paciente.php?error=guardado');
    }
    $insert_stmt->close();
}
$conex->close();
?>