<?php
// Iniciar la sesión para poder usar variables de sesión
session_start();

// Si el usuario ya está logueado, redirigirlo a su panel
if (isset($_SESSION['usuario_id'])) {
    header('Location: panel.php');
    exit();
}

include 'conexion.php'; // Incluimos la conexión a la BD

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_POST['correo']) || empty($_POST['contrasena'])) {
        $error = 'Por favor, ingrese su correo y contraseña.';
    } else {
        $correo = $_POST['correo'];
        $contrasena = $_POST['contrasena'];

        // Usamos una sentencia preparada para seguridad                           
        $stmt = $conex->prepare("SELECT id, nombre_completo, correo, contrasena, rol, estado FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows == 1) {
            $usuario = $resultado->fetch_assoc();

            // Verificamos la contraseña hasheada
         if (password_verify($contrasena, $usuario['contrasena'])) {
          // Verificamos si la cuenta está aprobada
          if ($usuario['estado'] == 'aprobado') {
         // La contraseña y el estado son correctos, iniciamos la sesión
         $_SESSION['usuario_id'] = $usuario['id'];
         $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
         $_SESSION['correo'] = $usuario['correo'];
         $_SESSION['rol'] = $usuario['rol'];

         header('Location: panel.php');
         exit();
         } elseif ($usuario['estado'] == 'pendiente') {
         $error = 'Tu cuenta aún está pendiente de aprobación.';
         } else {
         $error = 'Tu cuenta ha sido rechazada o desactivada.';
         }
} else {
    $error = 'La contraseña es incorrecta.';
}
        } else {
            $error = 'El correo electrónico no existe.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* Estilos específicos para centrar el formulario en esta página */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: "Poppins", sans-serif; /* Mantiene la misma fuente */
            background-color: #fafafa;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Ajustamos el estilo del formulario para esta página */
        .login-form {
            padding: 50px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: white;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    
    <form method="POST" action="login.php" class="login-form">
    <h2>Iniciar Sesión</h2>
    <?php if ($error): ?>
        <p style="color: #dc3545;"><?php echo $error; ?></p>
    <?php endif; ?>

    <div class="input-container">
        <input type="email" name="correo" placeholder="Correo Electrónico" required>
    </div>
    <div class="input-container">
        <input type="password" name="contrasena" placeholder="Contraseña" required>
    </div>
    
    <input type="submit" class="btn" value="Entrar">

    <p style="margin-top: 20px;">¿No tienes una cuenta? <a href="seleccionar_registro.php">Regístrate aquí</a></p>
    
</form>

</body>
</html>