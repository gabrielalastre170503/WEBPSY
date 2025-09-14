<?php
session_start();
include 'conexion.php';

// Seguridad: Solo los administradores pueden acceder
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: login.php');
    exit();
}

$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'aprobados'; // Filtro por defecto
$titulo = 'Usuarios Totales';
$sql = "SELECT id, nombre_completo, correo, rol, estado FROM usuarios WHERE estado = 'aprobado'";

switch ($filtro) {
    case 'pendientes':
        $titulo = 'Usuarios Pendientes de Aprobación';
        $sql = "SELECT id, nombre_completo, correo, rol, estado FROM usuarios WHERE estado = 'pendiente'";
        break;
    case 'doctores':
        $titulo = 'Doctores Activos';
        $sql = "SELECT id, nombre_completo, correo, rol, estado FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra') AND estado = 'aprobado'";
        break;
}

$resultado = $conex->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reutilizamos los estilos del dashboard */
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; }
        .dashboard-header { background-color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .dashboard-header .logo { color: #02b1f4; font-weight: 700; font-size: 24px; text-decoration: none; }
        .user-info a { color: #dc3545; text-decoration: none; margin-left: 20px; font-weight: 500; }
        .dashboard-main { padding: 30px; }
        .panel-seccion { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .panel-seccion h1 { margin-top: 0; }
        .users-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .users-table th, .users-table td { padding: 15px; text-align: left; border-bottom: 1px solid #e9e9e9; }
        .users-table th { background-color: #fafafa; font-weight: 600; color: #555; }
        .users-table tr:hover { background-color: #f7f7f7; }
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }
        .back-button i {
            margin-right: 8px;
        }
        /* --- ESTILOS DE BOTONES CORREGIDOS --- */
        .action-links a {
            display: inline-block;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            border-radius: 6px;
            cursor: pointer;
            color: white !important;
            text-decoration: none !important;
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease-in-out;
            margin-right: 8px; /* <-- ESTA LÍNEA AÑADE LA SEPARACIÓN */
        }
        .action-links a:last-child {
            margin-right: 0; /* Opcional: quita el margen del último botón */
        }
        .action-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .action-links a.approve { background-color: #02b1f4; }
        .action-links a.approve:hover { background-color: #028ac7; }
        .action-links a.reject { background-color: #dc3545; }
        .action-links a.reject:hover { background-color: #c82333; }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <a href="panel.php" class="logo">WebPSY Dashboard</a>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></strong></span>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
        </div>
    </header>

    <main class="dashboard-main">
        <div class="panel-seccion">
            <a href="panel.php" class="back-button"><i class="fa-solid fa-arrow-left"></i> Volver al Panel</a>
            <h1><?php echo htmlspecialchars($titulo); ?></h1>
            <?php
// Mostrar mensaje con la nueva contraseña temporal si existe
if (isset($_SESSION['reset_user_id']) && isset($_SESSION['reset_temp_pass'])) {
    echo '<div class="panel-seccion" style="background-color: #d1ecf1; border-left: 5px solid #0c5460;">';
    echo '<h4>¡Contraseña Restablecida!</h4>';
    echo '<p>La nueva contraseña temporal para el usuario con ID ' . htmlspecialchars($_SESSION['reset_user_id']) . ' es: <strong style="font-size: 1.2em; background: #fff; padding: 2px 8px; border-radius: 4px;">' . htmlspecialchars($_SESSION['reset_temp_pass']) . '</strong></p>';
    echo '</div>';

    // Limpiar las variables
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_temp_pass']);
}
?>
            
            <?php if ($resultado->num_rows > 0): ?>
                <table class="users-table">
                    <thead>
    <tr>
        <th>Nombre Completo</th>
        <th>Correo</th>
        <th>Rol</th>
        <th>Estado</th>
        <th>Acciones</th> </tr>
</thead>
                    <tbody>
    <?php while($usuario = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
            <td><?php echo htmlspecialchars($usuario['correo']); ?></td>
            <td><?php echo htmlspecialchars($usuario['rol']); ?></td>
            <td><?php echo htmlspecialchars($usuario['estado']); ?></td>
            <td class="action-links">
                <?php if ($_SESSION['usuario_id'] != $usuario['id']): ?>
                    <a href="reset_password.php?id=<?php echo $usuario['id']; ?>" class="approve" onclick="return confirm('¿Seguro que quieres restablecer la contraseña de este usuario?');">Restablecer</a>
                    <a href="borrar_usuario.php?id=<?php echo $usuario['id']; ?>" class="reject" onclick="return confirm('¿Estás seguro de que quieres borrar a este usuario?');">Borrar</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>
</tbody>
                </table>
            <?php else: ?>
                <p>No se encontraron usuarios que coincidan con este filtro.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>