<?php
session_start();
include 'conexion.php';

// 1. Seguridad: Si no hay sesión, redirigir al login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// 2. Obtener datos del usuario de la sesión para usarlos en la página.
$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$rol_usuario = $_SESSION['rol'];

// 3. Lógica específica para cada rol (ej. estadísticas para el admin).
$stats = [];
if ($rol_usuario == 'administrador') {
    $result_pendientes = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE estado = 'pendiente'");
    $stats['pendientes'] = $result_pendientes->fetch_assoc()['total'];

    $result_aprobados = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE estado = 'aprobado'");
    $stats['aprobados'] = $result_aprobados->fetch_assoc()['total'];
    
    $result_doctores = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra') AND estado = 'aprobado'");
    $stats['doctores'] = $result_doctores->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
    /* --- ESTILOS GENERALES Y LAYOUT --- */
    body {
        margin: 0;
        background-color: #f0f2f5;
        font-family: "Poppins", sans-serif;
        color: #333;
    }
    .dashboard-container {
        display: flex;
        min-height: 100vh;
    }

    /* --- BARRA LATERAL (SIDEBAR) --- */
    .sidebar {
        width: 220px;
        background-color: #ffffff;
        box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        padding: 20px;
        transition: width 0.3s ease;
    }
    .sidebar-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .sidebar-header .logo {
        color: #02b1f4;
        font-weight: 700;
        font-size: 28px;
        text-decoration: none;
    }
    .sidebar-nav a {
        display: flex;
        align-items: center;
        padding: 15px;
        color: #555;
        text-decoration: none;
        border-radius: 8px;
        margin-bottom: 10px;
        font-weight: 500;
        transition: background-color 0.3s, color 0.3s;
    }
    .sidebar-nav a:hover {
        background-color: #f0f2f5;
        color: #02b1f4;
    }
    .sidebar-nav a.active {
        background-color: #02b1f4;
        color: white;
    }
    .sidebar-nav a i {
        margin-right: 15px;
        width: 20px;
    }
    .sidebar-footer {
        margin-top: auto;
        text-align: center;
    }
    .sidebar-footer .user-info {
        margin-bottom: 15px;
    }
    .sidebar-footer .logout-btn {
        color: #dc3545;
        font-weight: 500;
        text-decoration: none;
    }

    /* --- CONTENIDO PRINCIPAL --- */
    .main-content {
        flex-grow: 1;
        padding: 30px;
    }
    .panel-vista {
        display: none; /* Oculto por defecto */
    }
    .panel-vista.active {
        display: block; /* Visible si es la vista activa */
    }
    .panel-seccion {
        background-color: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    .approvals-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 20px; 
    }
    .approvals-table th, .approvals-table td { 
        padding: 15px; 
        text-align: left; 
        border-bottom: 1px solid #e9e9e9; 
    }
    .approvals-table th { 
        background-color: #fafafa; 
        font-weight: 600; 
        color: #555; 
    }
    .approvals-table tr:hover { 
        background-color: #f7f7f7; 
    }
    
    /* --- ESTILOS DE BOTONES Y ENLACES DE ACCIÓN --- */
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
    }
    .action-links a:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    .action-links a.approve {
        background-color: #02b1f4;
    }
    .action-links a.approve:hover {
        background-color: #028ac7;
    }
    .action-links a.reject {
        background-color: #dc3545;
    }

    /* --- DISEÑO RESPONSIVE BÁSICO --- */
    @media (max-width: 768px) {
        .dashboard-container {
            flex-direction: column;
        }
        .sidebar {
            width: 100%;
            height: auto;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .sidebar-nav {
            display: flex;
        }
        .sidebar-nav a {
            padding: 10px;
            margin: 0 5px;
        }
        .sidebar-nav a span { /* Ocultar texto en móvil */
            display: none;
        }
        .sidebar-header, .sidebar-footer {
            display: none; /* Ocultar para simplificar */
        }
        .main-content {
            padding: 20px;
        }
    }
</style>
</head>
<body>
    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="panel.php" class="logo">WebPSY</a>
            </div>

            <nav class="sidebar-nav">
                <?php if ($rol_usuario == 'psicologo' || $rol_usuario == 'psiquiatra'): ?>
                    <a href="#" class="nav-link active" onclick="mostrarVista('pacientes', event)">
                        <i class="fa-solid fa-users"></i> Mis Pacientes
                    </a>
                    <a href="#" class="nav-link" onclick="mostrarVista('citas', event)">
                        <i class="fa-solid fa-inbox"></i> Solicitudes de Cita
                    </a>
                <?php endif; ?>
                
                <?php if ($rol_usuario == 'paciente'): ?>
                     <a href="#" class="nav-link active" onclick="mostrarVista('miscitas', event)">
                        <i class="fa-solid fa-calendar-check"></i> Mis Citas
                    </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong><br>
                    <small><?php echo htmlspecialchars(ucfirst($rol_usuario)); ?></small>
                </div>
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
            </div>
        </aside>

        <main class="main-content">
            <?php if ($rol_usuario == 'psicologo' || $rol_usuario == 'psiquiatra'): ?>
                <div id="vista-pacientes" class="panel-vista active">
                    <div class="panel-seccion">
                        <h2>Mis Pacientes</h2>
                        <p>Selecciona un paciente para gestionar su información clínica.</p>
                         <?php
                        $consulta_pacientes = $conex->query("SELECT id, nombre_completo, correo, cedula FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'");
                        if ($consulta_pacientes->num_rows > 0) {
                            echo "<table class='approvals-table'><thead><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Acciones</th></tr></thead><tbody>";
                            while($paciente = $consulta_pacientes->fetch_assoc()) {
                                echo "<tr><td>" . htmlspecialchars($paciente['nombre_completo']) . "</td><td>" . htmlspecialchars($paciente['cedula']) . "</td><td>" . htmlspecialchars($paciente['correo']) . "</td><td class='action-links'><a href='gestionar_paciente.php?paciente_id=" . $paciente['id'] . "' class='approve'>Gestionar</a></td></tr>";
                            }
                            echo "</tbody></table>";
                        } else { echo "<p>No hay pacientes registrados.</p>"; }
                        ?>
                    </div>
                </div>

                <div id="vista-citas" class="panel-vista">
                    <div class="panel-seccion">
                        <h2>Solicitudes de Cita Pendientes</h2>
                         <?php
                        $consulta_pendientes = $conex->query("SELECT c.id, c.motivo_consulta, u.nombre_completo as paciente_nombre FROM citas c JOIN usuarios u ON c.paciente_id = u.id WHERE c.estado = 'pendiente'");
                        if ($consulta_pendientes->num_rows > 0) {
                            echo "<table class='approvals-table'><thead><tr><th>Paciente</th><th>Motivo</th><th>Acción</th></tr></thead><tbody>";
                            while($solicitud = $consulta_pendientes->fetch_assoc()) {
                                echo "<tr><td>" . htmlspecialchars($solicitud['paciente_nombre']) . "</td><td>" . htmlspecialchars($solicitud['motivo_consulta']) . "</td><td class='action-links'><a href='programar_cita.php?cita_id=" . $solicitud['id'] . "' class='approve'>Programar</a></td></tr>";
                            }
                            echo "</tbody></table>";
                        } else { echo "<p>No hay solicitudes de cita pendientes.</p>"; }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($rol_usuario == 'paciente'): ?>
                <div id="vista-miscitas" class="panel-vista active">
                    <div class="panel-seccion">
                        <h2>Solicitar Nueva Cita</h2>
                        <form action="solicitar_cita.php" method="POST">
                            <div class="form-group"><label for="motivo_consulta">Motivo de la consulta:</label><textarea name="motivo_consulta" rows="4" required style="width:100%; padding:8px;"></textarea></div>
                            <button type="submit" class="btn">Solicitar Cita</button>
                        </form>
                    </div>
                    <div class="panel-seccion">
                        <h3>Mis Citas</h3>
                        <?php
                        $consulta_citas = $conex->prepare("SELECT fecha_cita, estado, p.nombre_completo as psicologo_nombre FROM citas c LEFT JOIN usuarios p ON c.psicologo_id = p.id WHERE c.paciente_id = ? ORDER BY c.fecha_solicitud DESC");
                        $consulta_citas->bind_param("i", $usuario_id);
                        $consulta_citas->execute();
                        $resultado_citas = $consulta_citas->get_result();
                        if ($resultado_citas->num_rows > 0) {
                            echo "<table class='approvals-table'><thead><tr><th>Fecha Programada</th><th>Psicólogo</th><th>Estado</th></tr></thead><tbody>";
                            while($cita = $resultado_citas->fetch_assoc()) {
                                echo "<tr><td>" . ($cita['fecha_cita'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita']))) : 'Por confirmar') . "</td><td>" . ($cita['psicologo_nombre'] ? htmlspecialchars($cita['psicologo_nombre']) : 'No asignado') . "</td><td>" . htmlspecialchars(ucfirst($cita['estado'])) . "</td></tr>";
                            }
                            echo "</tbody></table>";
                        } else { echo "<p>No tienes ninguna cita solicitada.</p>"; }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function mostrarVista(vista, event) {
            if (event) event.preventDefault();
            document.querySelectorAll('.panel-vista').forEach(v => v.classList.remove('active'));
            document.querySelectorAll('.sidebar-nav a').forEach(link => link.classList.remove('active'));
            document.getElementById('vista-' + vista).classList.add('active');
            if (event) event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>