<?php
if (isset($_POST['send'])) {
    include "conexion.php";

    // 1. Validar que todos los campos necesarios están presentes
    if (
        !empty($_POST["name"]) &&
        !empty($_POST["cedula"]) &&
        !empty($_POST["email"]) &&
        !empty($_POST["password"]) && // <-- Verificamos que la contraseña no esté vacía
        !empty($_POST["message"])
    ) {
        $name = trim($_POST['name']);
        $cedula = trim($_POST['cedula']);
        $email = trim($_POST['email']);
        $password = $_POST['password']; // <-- Obtenemos la contraseña del formulario
        $message = trim($_POST['message']);

        // 2. Verificar si el correo o la cédula ya existen
        $check_stmt = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
        $check_stmt->bind_param("ss", $email, $cedula);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            header('Location: login.php?error=user_exists');
            exit();
        }
        $check_stmt->close();

        // 3. Encriptar la contraseña proporcionada por el usuario
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $rol = 'paciente';
        $estado = 'aprobado';

        // Insertar el nuevo usuario
        $insert_stmt = $conex->prepare("INSERT INTO usuarios (nombre_completo, cedula, correo, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("ssssss", $name, $cedula, $email, $hashed_password, $rol, $estado);
        
        if ($insert_stmt->execute()) {
            $new_user_id = $insert_stmt->insert_id;
            
            // Iniciar sesión para el nuevo usuario
            session_start();
            $_SESSION['usuario_id'] = $new_user_id;
            $_SESSION['nombre_completo'] = $name;
            $_SESSION['correo'] = $email;
            $_SESSION['rol'] = $rol;

            // Guardar el motivo de la consulta para usarlo en el panel
            $_SESSION['motivo_cita_temporal'] = $message;

            // Ya no guardamos la contraseña temporal
            header('Location: panel.php?vista=solicitar');
            exit();
        } else {
            header('Location: index.php?error=register_failed');
            exit();
        }
        $insert_stmt->close();

    } else {
        header('Location: index.php?error=missing_fields');
        exit();
    }
    $conex->close();
}
?>