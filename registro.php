<?php
include 'conexion.php';
$mensaje = '';
$rol_seleccionado = 'paciente';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_completo = trim($_POST['nombre_completo']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $cedula_tipo = $_POST['cedula_tipo'];
    $cedula_numero = trim($_POST['cedula_numero']);
    $cedula = $cedula_tipo . $cedula_numero;
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];
    $rol = 'paciente';
    $estado = 'aprobado';

    // --- LÓGICA PARA CALCULAR LA EDAD ---
    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;

    // --- VALIDACIÓN COMPLETA EN EL SERVIDOR ---

    // 1. Verificar longitud de la cédula
    if (strlen($cedula_numero) < 7 || strlen($cedula_numero) > 8) {
        $mensaje = "El número de documento debe tener entre 7 y 8 dígitos.";
    } 
    // 2. Verificar que las contraseñas coincidan
    elseif ($contrasena !== $confirmar_contrasena) {
        $mensaje = "Las contraseñas no coinciden.";
    } 
    // 3. Verificar longitud mínima de 8 caracteres
    elseif (strlen($contrasena) < 8) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres.";
    } 
    // 4. Verificar que contenga al menos una letra mayúscula
    elseif (!preg_match('/[A-Z]/', $contrasena)) {
        $mensaje = "La contraseña debe contener al menos una letra mayúscula.";
    } 
    // 5. Verificar que contenga al menos un carácter especial
    elseif (!preg_match('/[\W_]/', $contrasena)) {
        $mensaje = "La contraseña debe contener al menos un carácter especial (ej: !@#$%).";
    } 
    else {
        // Si todo es válido, el código continúa para guardar en la base de datos...
        $check_stmt = $conex->prepare("SELECT id FROM usuarios WHERE correo = ? OR cedula = ?");
        $check_stmt->bind_param("ss", $correo, $cedula);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $mensaje = "El correo electrónico o la cédula ya están registrados.";
        } else {
            $contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT);
            
            $insert_stmt = $conex->prepare("INSERT INTO usuarios (nombre_completo, fecha_nacimiento, edad, cedula, correo, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssisssss", $nombre_completo, $fecha_nacimiento, $edad, $cedula, $correo, $contrasena_hasheada, $rol, $estado);
            
            if ($insert_stmt->execute()) {
                header('Location: login.php?status=registro_exitoso');
                exit();
            } else {
                $mensaje = "Error al registrar el usuario.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Paciente - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background-color: #f0f2f5; font-family: "Poppins", sans-serif; }
        .registro-form { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); width: 100%; max-width: 500px; text-align: center; }
        .registro-form h2 { margin-top: 0; margin-bottom: 30px; color: #333; }
        .input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .input-group label { font-weight: 500; margin-bottom: 8px; color: #555; font-size: 14px; display: block; }
        .input-group input { width: 100%; padding: 12px 15px 12px 45px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; font-family: "Poppins", sans-serif; box-sizing: border-box; }
        .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: left; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 8px; background: linear-gradient(45deg, #02b1f4, #00c2ff); color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4); }
        .login-link { margin-top: 20px; font-size: 14px; color: #555; }
        .login-link a { color: #02b1f4; font-weight: 600; text-decoration: none; }
        .cedula-input-group { display: flex; align-items: center; }
        .cedula-input-group select { width: 80px; padding: 12px; border: 1px solid #ccc; border-radius: 8px 0 0 8px; border-right: none; font-size: 16px; background-color: #f8f9fa; -webkit-appearance: none; -moz-appearance: none; appearance: none; text-align: center; cursor: pointer; height: 48px; }
        .cedula-input-group input { flex-grow: 1; border-radius: 0 8px 8px 0; padding-left: 15px; }

                /* --- ESTILOS PARA AJUSTAR LA ALTURA DEL CALENDARIO FLATPICKR --- */
        .flatpickr-day {
            height: 60px !important;      /* <-- AUMENTA LA ALTURA DE CADA DÍA */
            line-height: 60px !important; /* <-- CENTRA EL NÚMERO VERTICALMENTE */
        }
    </style>
</head>
<body>
    <form method="POST" action="registro.php" class="registro-form">
        <h2>Registro de Paciente</h2>
        
        <?php if ($mensaje): ?>
            <p class="error-message"><?php echo $mensaje; ?></p>
        <?php endif; ?>

        <div class="input-group">
            <i class="fa-solid fa-user"></i>
            <input type="text" name="nombre_completo" placeholder="Nombre Completo" required>
        </div>
        
        <div class="input-group">
            <div class="input-wrapper" style="position: relative;">
                <i class="fa-solid fa-calendar-day" style="top: 50%; transform: translateY(-50%);"></i>
                <input type="text" name="fecha_nacimiento" id="fecha_nacimiento" placeholder="Fecha de nacimiento" required>
            </div>
        </div>
        
        <div class="input-group">
            <div class="cedula-input-group">
                <select name="cedula_tipo">
                    <option value="V-">V</option>
                    <option value="E-">E</option>
                    <option value="P-">P</option>
                </select>
                <input type="text" name="cedula_numero" placeholder="N° de Documento" required minlength="7" maxlength="8" pattern="\d{7,8}" title="El número debe tener entre 7 y 8 dígitos.">
            </div>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" name="correo" placeholder="Correo Electrónico" required>
        </div>
        <div class="input-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="contrasena" id="contrasena" placeholder="Contraseña" required 
                   minlength="8" 
                   pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}" 
                   title="Mínimo 8 caracteres, una mayúscula y un símbolo.">
        </div>
        <div class="input-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="confirmar_contrasena" id="confirmar_contrasena" placeholder="Confirmar Contraseña" required>
        </div>
        
        <input type="submit" class="btn" value="Registrarse">
        <p class="login-link">¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        // Validar que las contraseñas coincidan
        const password = document.getElementById("contrasena");
        const confirm_password = document.getElementById("confirmar_contrasena");
        function validatePassword(){
          if(password.value !== confirm_password.value) {
            confirm_password.setCustomValidity("Las contraseñas no coinciden.");
          } else {
            confirm_password.setCustomValidity('');
          }
        }
        password.onchange = validatePassword;
        confirm_password.onkeyup = validatePassword;

        // Inicializar Flatpickr para el campo de fecha de nacimiento
        flatpickr("#fecha_nacimiento", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            locale: "es",
            maxDate: "today",
            position: function(self, dom) {
                self.calendarContainer.style.position = 'fixed';
                const topPosition = (window.innerHeight - self.calendarContainer.offsetHeight) / 2;
                const leftPosition = (window.innerWidth - self.calendarContainer.offsetWidth) / 2;
                self.calendarContainer.style.top = `${topPosition}px`;
                self.calendarContainer.style.left = `${leftPosition}px`;
            }
        });
    </script>
</body>
</html>