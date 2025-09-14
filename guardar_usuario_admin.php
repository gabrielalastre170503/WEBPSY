<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre_completo'];
    $cedula = $_POST['cedula'];
    $correo = $_POST['correo'];
    $rol = $_POST['rol']; // Rol seleccionado por el admin

    // Validar que el rol sea uno de los permitidos
    if (!in_array($rol, ['psicologo', 'psiquiatra', 'secretaria'])) {
        die("Rol no válido.");
    }

    // Verificar si el correo o la cédula ya existen
    $check_stmt = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
    $check_stmt->bind_param("ss", $correo, $cedula);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        die("Error: El correo electrónico o la cédula ya están registrados. <a href='crear_usuario_admin.php'>Volver</a>");
    }
    $check_stmt->close();

    // AHORA (reemplaza las dos líneas de arriba por estas dos):
    $contrasena = $_POST['contrasena']; // Tomamos la contraseña del formulario
    $contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT); // La encriptamos
    
    $estado = 'aprobado'; // El admin crea usuarios ya aprobados

    $insert_stmt = $conex->prepare("INSERT INTO usuarios (nombre_completo, cedula, correo, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("ssssss", $nombre, $cedula, $correo, $contrasena_hasheada, $rol, $estado);
    
    if ($insert_stmt->execute()) {
        $_SESSION['nuevo_paciente_nombre'] = $nombre; // Reutilizamos la variable de sesión
        $_SESSION['contrasena_temporal'] = $contrasena_temporal;
        header('Location: panel.php?vista=admin-dashboard'); // Volver al dashboard del admin
    } else {
        header('Location: crear_usuario_admin.php?error=guardado');
    }
    $insert_stmt->close();
}
$conex->close();
?>