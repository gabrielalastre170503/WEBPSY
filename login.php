<?php
session_start();
include 'conexion.php';

$error = ''; // Inicializar la variable de error

// Mensaje de éxito después del registro
$mensaje_exito = '';
if (isset($_GET['status']) && $_GET['status'] == 'registro_exitoso') {
    $mensaje_exito = '¡Registro exitoso! Ahora puedes iniciar sesión.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_POST['correo']) || empty($_POST['contrasena'])) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $correo = $_POST['correo'];
        $contrasena = $_POST['contrasena'];

        $stmt = $conex->prepare("SELECT id, nombre_completo, correo, contrasena, rol, estado FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            if (password_verify($contrasena, $usuario['contrasena'])) {
                if ($usuario['estado'] == 'aprobado') {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                    $_SESSION['correo'] = $usuario['correo'];
                    $_SESSION['rol'] = $usuario['rol'];
                    header('Location: panel.php');
                    exit();
                } elseif ($usuario['estado'] == 'pendiente') {
                    $error = 'Tu cuenta aún está pendiente de aprobación.';
                } elseif ($usuario['estado'] == 'inhabilitado') {
                    $error = 'Tu cuenta ha sido inhabilitada. Contacta al administrador.';
                } else {
                    $error = 'Tu cuenta ha sido rechazada o desactivada.';
                }
            } else {
                $error = 'Correo o contraseña incorrectos.';
            }
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
            font-family: "Poppins", sans-serif;
        }
        .login-form {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .login-form h2 {
            margin-top: 0;
            margin-bottom: 30px;
            color: #333;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        .input-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            font-family: "Poppins", sans-serif;
            box-sizing: border-box;
        }
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
        }
        .success-message {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #02b1f4, #00c2ff);
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
        }
        .form-links {
            margin-top: 20px;
            font-size: 14px;
            color: #555;
        }
        .form-links a {
            color: #02b1f4;
            font-weight: 600;
            text-decoration: none;
        }
        .back-to-home-link {
            display: block;
            margin-top: 15px;
            color: #818181;
        }
    </style>
</head>
<body>
    <form method="POST" action="login.php" class="login-form">
        <h2>Iniciar Sesión</h2>
        
        <?php if ($error): ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endif; ?>
        
        <?php if ($mensaje_exito): ?>
            <p class="success-message"><?php echo $mensaje_exito; ?></p>
        <?php endif; ?>

        <div class="input-group">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" name="correo" placeholder="Correo Electrónico" required>
        </div>
        <div class="input-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
        </div>
        
        <input type="submit" class="btn" value="Entrar">
        
        <p class="form-links">
            ¿No tienes una cuenta? <a href="registro.php">Regístrate aquí</a>
        </p>
        <a href="index.php" class="back-to-home-link">Volver a Inicio</a>
    </form>
</body>
</html>