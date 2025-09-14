<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores pueden ver esta página
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de usuario no válido.");
}

$usuario_id = $_GET['id'];

// Obtener todos los datos del usuario seleccionado
$stmt = $conex->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("Usuario no encontrado.");
}
$usuario = $resultado->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de Usuario - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; margin: 0; padding: 30px; }
        .profile-container { max-width: 700px; margin: 0 auto; background-color: white; padding: 30px 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
        .profile-header { text-align: center; margin-bottom: 30px; }
        .profile-header .avatar { font-size: 50px; color: #02b1f4; margin-bottom: 10px; }
        .profile-header h1 { margin: 0; font-size: 24px; }
        .profile-header .role { font-size: 16px; color: #777; }
        .profile-details { list-style: none; padding: 0; }
        .profile-details li { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .profile-details li:last-child { border-bottom: none; }
        .profile-details li strong { color: #555; }
        .profile-actions { margin-top: 30px; display: flex; gap: 15px; justify-content: center; }
        .btn-action { text-decoration: none; padding: 10px 25px; border-radius: 6px; font-weight: 500; color: white !important; transition: all 0.2s; }
        .btn-reset { background-color: #02b1f4; }
        .btn-delete { background-color: #dc3545; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #818181; text-decoration: none; }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <div class="avatar"><i class="fa-solid fa-user-circle"></i></div>
            <h1><?php echo htmlspecialchars($usuario['nombre_completo']); ?></h1>
            <p class="role"><?php echo htmlspecialchars(ucfirst($usuario['rol'])); ?></p>
        </div>
        
        <ul class="profile-details">
            <li><strong>Cédula:</strong> <span><?php echo htmlspecialchars($usuario['cedula']); ?></span></li>
            <li><strong>Correo Electrónico:</strong> <span><?php echo htmlspecialchars($usuario['correo']); ?></span></li>
            <li><strong>Estado de la Cuenta:</strong> <span><?php echo htmlspecialchars(ucfirst($usuario['estado'])); ?></span></li>
            <li><strong>Fecha de Registro:</strong> <span><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></span></li>
        </ul>

        <div class="profile-actions">
            <?php if ($_SESSION['usuario_id'] != $usuario['id']): ?>
                <a href="reset_password.php?id=<?php echo $usuario['id']; ?>" class="btn-action btn-reset" onclick="return confirm('¿Seguro que quieres restablecer la contraseña de este usuario?');">Restablecer Contraseña</a>
                <a href="borrar_usuario.php?id=<?php echo $usuario['id']; ?>" class="btn-action btn-delete" onclick="return confirm('¿Estás seguro de que quieres borrar a este usuario? Esta acción es irreversible.');">Borrar Usuario</a>
            <?php else: ?>
                <p>No puedes realizar acciones sobre tu propia cuenta desde aquí.</p>
            <?php endif; ?>
        </div>
        <a href="panel.php?vista=admin-personal" class="back-link">Volver a Gestión de Personal</a>
    </div>
</body>
</html>