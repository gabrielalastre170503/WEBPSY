<?php
session_start();
include 'conexion.php'; // Incluir la conexión

// Si el usuario no ha iniciado sesión, lo redirigimos a login.php
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Obtenemos los datos del usuario de la sesión
$nombre_usuario = $_SESSION['nombre_completo'];
$rol_usuario = $_SESSION['rol'];

// --- Lógica solo para el Administrador ---
$stats = [];
if ($rol_usuario == 'administrador') {
    // Contar usuarios pendientes
    $result_pendientes = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE estado = 'pendiente'");
    $stats['pendientes'] = $result_pendientes->fetch_assoc()['total'];

    // Contar total de usuarios aprobados
    $result_aprobados = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE estado = 'aprobado'");
    $stats['aprobados'] = $result_aprobados->fetch_assoc()['total'];
    
    // Contar total de doctores (psicologos + psiquiatras)
    $result_doctores = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra') AND estado = 'aprobado'");
    $stats['doctores'] = $result_doctores->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Estilos Generales del Dashboard */
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; }
        .dashboard-header { background-color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .dashboard-header .logo { color: #02b1f4; font-weight: 700; font-size: 24px; text-decoration: none; }
        .user-info a { color: #dc3545; text-decoration: none; margin-left: 20px; font-weight: 500; }
        .dashboard-main { padding: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .stat-card-link { text-decoration: none; }
        .stat-card { background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: flex; align-items: center; }
        .stat-card .icon { font-size: 36px; margin-right: 20px; padding: 15px; border-radius: 50%; color: white; }
        .stat-card .info .number { font-size: 28px; font-weight: 600; color: #333; }
        .stat-card .info .label { color: #777; }
        .panel-seccion { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-top: 20px;}
        .approvals-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .approvals-table th, .approvals-table td { padding: 15px; text-align: left; border-bottom: 1px solid #e9e9e9; }
        .approvals-table th { background-color: #fafafa; font-weight: 600; color: #555; }
        .approvals-table tr:hover { background-color: #f7f7f7; }
        .approvals-table .action-links a { text-decoration: none; padding: 5px 10px; border-radius: 5px; font-weight: 500; }
        .action-links .approve { color: white; background-color: #28a745; }
        .action-links .reject { color: white; background-color: #dc3545; }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <a href="#" class="logo">WebPSY Dashboard</a>
        <div class="user-info">
            <span>Bienvenido, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong> (<?php echo htmlspecialchars($rol_usuario); ?>)</span>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
        </div>
    </header>

    <main class="dashboard-main">
        <h1>Panel de Control</h1>

        <?php if ($rol_usuario == 'administrador'): ?>
            <div class="stats-grid">
                <a href="ver_usuarios.php?filtro=pendientes" class="stat-card-link">
                    <div class="stat-card">
                        <div class="icon" style="background-color: #ffc107;"><i class="fa-solid fa-user-clock"></i></div>
                        <div class="info"><div class="number"><?php echo $stats['pendientes']; ?></div><div class="label">Usuarios Pendientes</div></div>
                    </div>
                </a>
                <a href="ver_usuarios.php?filtro=aprobados" class="stat-card-link">
                    <div class="stat-card">
                        <div class="icon" style="background-color: #17a2b8;"><i class="fa-solid fa-users"></i></div>
                        <div class="info"><div class="number"><?php echo $stats['aprobados']; ?></div><div class="label">Usuarios Totales</div></div>
                    </div>
                </a>
                <a href="ver_usuarios.php?filtro=doctores" class="stat-card-link">
                    <div class="stat-card">
                        <div class="icon" style="background-color: #28a745;"><i class="fa-solid fa-user-doctor"></i></div>
                        <div class="info"><div class="number"><?php echo $stats['doctores']; ?></div><div class="label">Doctores Activos</div></div>
                    </div>
                </a>
            </div>

            <div class="panel-seccion">
                <h2>Registros Pendientes de Aprobación</h2>
                <?php
                $consulta_pendientes = $conex->query("SELECT id, nombre_completo, correo, rol FROM usuarios WHERE estado = 'pendiente'");
                if ($consulta_pendientes->num_rows > 0) {
                    echo "<table class='approvals-table'>";
                    echo "<thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Acciones</th></tr></thead>";
                    echo "<tbody>";
                    while($usuario_pendiente = $consulta_pendientes->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($usuario_pendiente['nombre_completo']) . "</td>";
                        echo "<td>" . htmlspecialchars($usuario_pendiente['correo']) . "</td>";
                        echo "<td>" . htmlspecialchars($usuario_pendiente['rol']) . "</td>";
                        echo "<td class='action-links'>
                                <a href='gestionar_aprobacion.php?id=" . $usuario_pendiente['id'] . "&accion=aprobar' class='approve'>Aprobar</a> 
                                <a href='gestionar_aprobacion.php?id=" . $usuario_pendiente['id'] . "&accion=rechazar' class='reject'>Rechazar</a>
                              </td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<p>No hay registros pendientes de aprobación.</p>";
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if ($rol_usuario == 'psicologo' || $rol_usuario == 'psiquiatra'): ?>
    
    <div class="panel-seccion">
        <h2>Mis Pacientes</h2>
        <p>Selecciona un paciente para gestionar su información clínica.</p>
        <?php
        $consulta_pacientes = $conex->query("SELECT id, nombre_completo, correo, cedula FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'");
        if ($consulta_pacientes->num_rows > 0) {
            echo "<table class='approvals-table'>";
            echo "<thead><tr><th>Nombre Completo</th><th>Cédula</th><th>Correo</th><th>Acciones</th></tr></thead>";
            echo "<tbody>";
            while($paciente = $consulta_pacientes->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($paciente['nombre_completo']) . "</td>";
                echo "<td>" . htmlspecialchars($paciente['cedula']) . "</td>";
                echo "<td>" . htmlspecialchars($paciente['correo']) . "</td>";
                echo "<td class='action-links'>
                        <a href='gestionar_paciente.php?paciente_id=" . $paciente['id'] . "' class='approve'>Gestionar Paciente</a>
                      </td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No hay pacientes registrados.</p>";
        }
        ?>
    </div>

    <div class="panel-seccion">
        <h3>Solicitudes de Cita Pendientes</h3>
        <?php
        $consulta_pendientes = $conex->query("SELECT c.id, c.motivo_consulta, u.nombre_completo as paciente_nombre FROM citas c JOIN usuarios u ON c.paciente_id = u.id WHERE c.estado = 'pendiente'");
        if ($consulta_pendientes->num_rows > 0) {
            echo "<table class='approvals-table'>";
            echo "<thead><tr><th>Paciente</th><th>Motivo</th><th>Acción</th></tr></thead><tbody>";
            while($solicitud = $consulta_pendientes->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($solicitud['paciente_nombre']) . "</td>";
                echo "<td>" . htmlspecialchars($solicitud['motivo_consulta']) . "</td>";
                echo "<td><a href='programar_cita.php?cita_id=" . $solicitud['id'] . "' class='approve'>Programar</a></td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No hay solicitudes de cita pendientes.</p>";
        }
        ?>
    </div>

<?php endif; ?>

        <?php if ($rol_usuario == 'secretaria'): ?>
            <div class="panel-seccion"><h2>Panel de Secretaría</h2><p>Aquí puedes gestionar las citas de todos los doctores.</p></div>
        <?php endif; ?>

        <?php if ($rol_usuario == 'paciente'): ?>
    <div class="panel-seccion">
        <h2>Panel de Paciente</h2>
        <p>Aquí puedes solicitar una nueva cita y ver el estado de tus citas programadas.</p>
        
        <form action="solicitar_cita.php" method="POST" style="margin-top:20px;">
            <div class="form-group">
                <label for="motivo_consulta">Motivo de la consulta:</label>
                <textarea name="motivo_consulta" id="motivo_consulta" rows="4" required style="width:100%; padding:8px;"></textarea>
            </div>
            <button type="submit" class="btn">Solicitar Cita</button>
        </form>
    </div>

    <div class="panel-seccion">
        <h3>Mis Citas</h3>
        <?php
        $paciente_id = $_SESSION['usuario_id'];
        $consulta_citas = $conex->prepare("SELECT fecha_cita, estado, p.nombre_completo as psicologo_nombre FROM citas c LEFT JOIN usuarios p ON c.psicologo_id = p.id WHERE c.paciente_id = ? ORDER BY c.fecha_solicitud DESC");
        $consulta_citas->bind_param("i", $paciente_id);
        $consulta_citas->execute();
        $resultado_citas = $consulta_citas->get_result();

        if ($resultado_citas->num_rows > 0) {
            echo "<table class='approvals-table'>";
            echo "<thead><tr><th>Fecha Programada</th><th>Psicólogo</th><th>Estado</th></tr></thead><tbody>";
            while($cita = $resultado_citas->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . ($cita['fecha_cita'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita']))) : 'Por confirmar') . "</td>";
                echo "<td>" . ($cita['psicologo_nombre'] ? htmlspecialchars($cita['psicologo_nombre']) : 'No asignado') . "</td>";
                echo "<td>" . htmlspecialchars(ucfirst($cita['estado'])) . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No tienes ninguna cita solicitada.</p>";
        }
        ?>
    </div>
 <?php endif; ?>

    </main>
</body>
</html>