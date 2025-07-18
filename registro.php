<?php
include 'conexion.php';
$mensaje = '';

// Determinar el rol y validarlo
$rol_seleccionado = 'paciente';
$roles_permitidos = ['psicologo', 'psiquiatra', 'secretaria', 'paciente'];

if (isset($_GET['rol']) && in_array($_GET['rol'], $roles_permitidos)) {
    $rol_seleccionado = $_GET['rol'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rol = $_POST['rol']; 
    $nombre = $_POST['nombre_completo'];
    $cedula = $_POST['cedula']; // <-- Recogemos la cédula
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];

    // Primero, verificamos si el correo O la cédula ya existen
    $check_stmt = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
    $check_stmt->bind_param("ss", $correo, $cedula);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $mensaje = "Error: El correo electrónico o la cédula ya están registrados.";
    } else {
        $check_stmt->close();

        // Decidir el estado basado en el rol
        if ($rol == 'paciente') {
            $estado = 'aprobado';
        } else {
            $estado = 'pendiente';
        }
        
        $contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT);

        // Actualizamos la consulta para incluir la cédula
        $insert_stmt = $conex->prepare("INSERT INTO usuarios (nombre_completo, cedula, correo, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("ssssss", $nombre, $cedula, $correo, $contrasena_hasheada, $rol, $estado);

        if ($insert_stmt->execute()) {
            if ($estado == 'pendiente') {
                $mensaje = "¡Registro exitoso! Tu cuenta está pendiente de aprobación por un administrador.";
            } else {
                $mensaje = "¡Registro exitoso! Ahora puedes <a href='login.php'>iniciar sesión</a>.";
            }
        } else {
            $mensaje = "Ocurrió un error inesperado. Por favor, intenta de nuevo.";
        }
        $insert_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Usuario</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; font-family: "Poppins", sans-serif; background-color: #fafafa; }
        body { display: flex; justify-content: center; align-items: center; }
        .registro-form { padding: 50px; width: 90%; max-width: 500px; text-align: center; box-shadow: 0 0 20px rgba(0, 0, 0, 0.1); background-color: white; border-radius: 15px; }
    </style>
</head>
<body>
    <form method="POST" action="registro.php" class="registro-form">
    <h2>Registro de <?php echo ucfirst(htmlspecialchars($rol_seleccionado)); ?></h2>
    
    <?php if ($mensaje): ?>
        <p style="margin-bottom: 20px;"><?php echo $mensaje; ?></p>
    <?php endif; ?>

    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol_seleccionado); ?>">

    <div class="input-container">
        <input type="text" name="nombre_completo" placeholder="Nombre Completo" required>
    </div>

    <div class="input-container">
        <input type="number" name="cedula" placeholder="Cédula de Identidad" required>
    </div>

    <div class="input-container">
        <input type="email" name="correo" placeholder="Correo Electrónico" required>
    </div>
    <div class="input-container">
        <input type="password" name="contrasena" placeholder="Contraseña" required>
    </div>
    
    <input type="submit" class="btn" value="Registrarse">
    <p style="margin-top: 20px;">¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
</form>
</body>
</html>