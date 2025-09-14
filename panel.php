<?php
session_start();
include 'conexion.php';

// 1. Seguridad: Si no hay sesión, redirigir al login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// 2. Obtener datos del usuario de la sesión.
$usuario_id = $_SESSION['usuario_id'];
$nombre_usuario = $_SESSION['nombre_completo'];
$rol_usuario = $_SESSION['rol'];

// 3. Lógica para obtener estadísticas (para admin Y psicólogo).
$stats = [];
if ($rol_usuario == 'administrador') {
    $result_pendientes = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE estado = 'pendiente'");
    $stats['pendientes'] = $result_pendientes->fetch_assoc()['total'];

    $result_aprobados = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE estado = 'aprobado'");
    $stats['aprobados'] = $result_aprobados->fetch_assoc()['total'];
    
    $result_doctores = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra') AND estado = 'aprobado'");
    $stats['doctores'] = $result_doctores->fetch_assoc()['total'];

} elseif ($rol_usuario == 'psicologo' || $rol_usuario == 'psiquiatra') {
    $psicologo_id = $_SESSION['usuario_id'];
    
    // Citas para hoy
    $hoy_inicio = date('Y-m-d 00:00:00');
    $hoy_fin = date('Y-m-d 23:59:59');
    $stmt_citas_hoy = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE psicologo_id = ? AND estado = 'confirmada' AND fecha_cita BETWEEN ? AND ?");
    $stmt_citas_hoy->bind_param("iss", $psicologo_id, $hoy_inicio, $hoy_fin);
    $stmt_citas_hoy->execute();
    $stats['citas_hoy'] = $stmt_citas_hoy->get_result()->fetch_assoc()['total'];
    $stmt_citas_hoy->close();

    // Solicitudes pendientes
    $stmt_pendientes = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE psicologo_id = ? AND estado = 'pendiente'");
    $stmt_pendientes->bind_param("i", $psicologo_id);
    $stmt_pendientes->execute();
    $stats['pendientes'] = $stmt_pendientes->get_result()->fetch_assoc()['total'];
    $stmt_pendientes->close();

    // Pacientes activos
    $stmt_pacientes_activos = $conex->prepare("SELECT COUNT(DISTINCT u.id) as total FROM usuarios u LEFT JOIN citas c ON u.id = c.paciente_id WHERE u.rol = 'paciente' AND u.estado = 'aprobado' AND (u.creado_por_psicologo_id = ? OR c.psicologo_id = ?)");
    $stmt_pacientes_activos->bind_param("ii", $psicologo_id, $psicologo_id);
    $stmt_pacientes_activos->execute();
    $stats['pacientes_activos'] = $stmt_pacientes_activos->get_result()->fetch_assoc()['total'];
    $stmt_pacientes_activos->close();

    // --- AÑADE ESTE NUEVO BLOQUE DE CÓDIGO ---
    // 4. Obtener las próximas 5 citas confirmadas
    $proximas_citas_stmt = $conex->prepare("
        SELECT c.fecha_cita, u.nombre_completo 
        FROM citas c 
        JOIN usuarios u ON c.paciente_id = u.id 
        WHERE c.psicologo_id = ? 
        AND c.estado = 'confirmada' 
        AND c.fecha_cita >= NOW() 
        ORDER BY c.fecha_cita ASC 
        LIMIT 5
    ");
    $proximas_citas_stmt->bind_param("i", $psicologo_id);
    $proximas_citas_stmt->execute();
    $proximas_citas = $proximas_citas_stmt->get_result();
    // --- FIN DEL BLOQUE A AÑADIR ---
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
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
    /* --- ESTILOS GENERALES Y LAYOUT --- */
    body { margin: 0; background-color: #f0f2f5; font-family: "Poppins", sans-serif; color: #333; }
    .dashboard-container { display: flex; min-height: 100vh; }

    /* --- BARRA LATERAL (SIDEBAR) --- */
    .sidebar { width: 220px; background-color: #ffffff; box-shadow: 2px 0 5px rgba(0,0,0,0.05); display: flex; flex-direction: column; padding: 20px; }
    .sidebar-header { text-align: center; margin-bottom: 30px; }
    .sidebar-header .logo { color: #02b1f4; font-weight: 700; font-size: 28px; text-decoration: none; }
    .sidebar-nav a { display: flex; align-items: center; padding: 15px; color: #555; text-decoration: none; border-radius: 8px; margin-bottom: 10px; font-weight: 500; transition: all 0.3s; }
    .sidebar-nav a:hover { background-color: #f0f2f5; color: #02b1f4; }
    .sidebar-nav a.active { background-color: #02b1f4; color: white; }
    .sidebar-nav a i { margin-right: 15px; width: 20px; }
    .sidebar-footer { margin-top: auto; text-align: center; }
    .sidebar-footer .user-info { margin-bottom: 15px; }
    .sidebar-footer .logout-btn { color: #dc3545; font-weight: 500; text-decoration: none; }

    /* --- CONTENIDO PRINCIPAL --- */
    .main-content { flex-grow: 1; padding: 30px; }
    .panel-vista { display: none; }
    .panel-vista.active { display: block; }
    .panel-seccion { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
    
    /* --- ESTILOS PARA LAS TARJETAS DE ESTADÍSTICAS (ADMIN) --- */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; }
    .stat-card-link { text-decoration: none; }
    .stat-card { background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: flex; align-items: center; transition: all 0.3s; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.12); }
    .stat-card .icon {
    font-size: 28px;
    margin-right: 20px;
    padding: 15px;
    border-radius: 8px; /* <-- Esto lo hace un cuadro con bordes suaves */
    color: var(--color-primario); /* <-- Cambia el color del ícono al azul principal */
    background-color: #f0f2f5; /* <-- Le da un fondo gris muy sutil */
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
}
    .stat-card .info .number { font-size: 28px; font-weight: 600; color: #333; }
    .stat-card .info .label { color: #777; font-size: 14px; }
    
    /* --- ESTILOS PARA LAS TABLAS (TODOS LOS PANELES) --- */
    .approvals-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .approvals-table th, .approvals-table td { padding: 15px; text-align: left; border-bottom: 1px solid #e9e9e9; }
    .approvals-table th { background-color: #fafafa; font-weight: 600; color: #555; }
    .approvals-table tr:hover { background-color: #f7f7f7; }
    .action-links a { text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; color: white !important; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.15); transition: all 0.2s; margin-right: 8px; }
    .action-links a:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    .action-links a.approve { background-color: #02b1f4; }
    .action-links a.reject { background-color: #dc3545; }

    /* --- ESTILOS PARA EL FORMULARIO DE PACIENTE --- */
    .info-paciente-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; background-color: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; margin-top: 15px; }
    .info-item strong { display: block; color: #555; font-size: 14px; margin-bottom: 5px; }
    .info-item span { font-size: 16px; color: #333; }
    .panel-seccion .form-group label { display: block; font-weight: 500; margin-bottom: 8px; }
    .panel-seccion .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; font-family: "Poppins", sans-serif; resize: vertical; box-sizing: border-box; }
    #vista-solicitar .btn { border: none; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3); }
    #vista-solicitar .btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4); background-color: #028ac7; }
    
    /* --- ESTILOS PARA TARJETAS DE ESTADÍSTICAS (DISEÑO PROFESIONAL) --- */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 25px;
}
.stat-card-link {
    text-decoration: none;
    color: inherit;
}
.stat-card {
    background-color: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e9e9e9;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}
.card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    color: #555;
    border: none;
    padding: 0;
}
.card-header .icon {
    font-size: 22px;
    color: #aaa;
}
.card-body .stat-number {
    font-size: 38px;
    font-weight: 600;
    color: #333;
    margin: 0 0 5px 0;
    line-height: 1;
}
.card-body .stat-label {
    color: #777;
    font-size: 14px;
}
/* Estilos para la lista de próximas citas */
.appointment-list {
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 71px; /* Altura máxima de la lista */
    overflow-y: auto;  /* Muestra la barra de scroll si es necesario */
}
.appointment-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px 5px;
    border-bottom: 1px solid #f0f2f5;
}
.appointment-list li:last-child {
    border-bottom: none;
}
.appointment-list .patient-name {
    font-weight: 500;
    font-size: 14px;
}
.appointment-list .appointment-time {
    color: #555;
    font-size: 13px;
}
.appointment-list .no-appointments {
    color: #777;
    padding: 20px 0;
    text-align: center;
    border-bottom: none;
    display: block;
}
/* Contenedor para el gráfico para controlar su tamaño */
.chart-container {
    position: relative;
    height: 150px; /* Altura fija */
    width: 100%;
}
/* --- ESTILO PARA EL TEXTO DE LAS CITAS EN EL CALENDARIO --- */
.fc-daygrid-event .fc-event-title {
    font-size: 11px !important; /* Puedes cambiar este valor a 11px, 13px, etc. */
    white-space: normal !important; /* Permite que el texto se divida en varias líneas si no cabe */
    text-align: center !important; /* <-- LÍNEA AÑADIDA */
}
/* Estilos para la rejilla de widgets inferiores */
.dashboard-widgets-grid {
    display: grid;
    grid-template-columns: 1fr 1fr; /* <-- CAMBIO CLAVE AQUÍ */
    gap: 25px;
    margin-top: 25px;
}
/* --- ESTILOS PARA EL BUSCADOR DE PACIENTES --- */
.search-container {
    position: relative;
    margin: 0px 0; /* Le da un poco de espacio arriba y abajo */
}
.search-container i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%); /* Centra el ícono verticalmente */
    color: #aaa;
}
.search-container input {
    width: 100%;
    max-width: 1140px; /* Limita el ancho en pantallas grandes */
    padding: 12px 20px 12px 45px; /* Espacio para el ícono a la izquierda */
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    box-sizing: border-box; /* Asegura que el padding no desborde */
}
/* --- ESTILOS MEJORADOS PARA LAS CITAS DEL CALENDARIO --- */

/* Estilo general para todos los eventos (citas) */
.fc-event {
    border: none !important; /* Quita el borde azul por defecto */
    cursor: pointer;
    transition: background-color 0.2s ease;
}

/* Estilo específico para la vista de MES (dayGrid) */
.fc-daygrid-event {
    background-color: rgba(2, 177, 244, 0.15) !important; /* Fondo azul muy suave y transparente */
    border-left: 3px solid #02b1f4 !important; /* Borde izquierdo de color para destacar */
    padding: 3px 5px !important;
}

.fc-daygrid-event .fc-event-title {
    color: #333 !important; /* Texto oscuro para que se lea bien */
    font-weight: 500 !important;
    font-size: 8.3px !important; /* Un tamaño de letra legible */
    white-space: normal !important; /* Permite que el texto ocupe varias líneas si es largo */
}

/* Estilo específico para las vistas de SEMANA y DÍA (timeGrid) */
.fc-timegrid-event {
    border-radius: 4px !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}

/* Estilo para la vista de Mes */
.fc-daygrid-event {
    background-color: rgba(2, 177, 244, 0.15) !important; /* Fondo azul claro semi-transparente */
    border-left: 1px solid #02b1f4 !important; /* Borde izquierdo de color */
}

.fc-daygrid-event .fc-event-title {
    color: #333 !important; /* Color de texto oscuro para legibilidad */
}

/* Estilo para las vistas de Semana y Día */
.fc-timegrid-event {
    background-color: #02b1f4 !important; /* Fondo azul sólido */
}

.fc-timegrid-event .fc-event-title {
    color: white !important; /* Texto blanco */
}
/* --- Estilos para el formulario de perfil (CORREGIDOS) --- */
.profile-form-container {
    max-width: 1200px;
    margin: 20px auto 0 auto;
}
.input-group {
    position: relative;
    margin-bottom: 20px;
}
.input-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 8px;
    color: #555;
}
.input-group input {
    width: 100%;
    padding: 12px 12px 12px 45px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 16px;
    box-sizing: border-box;
}

/* Estilo para los íconos DENTRO de los campos de input (contraseña) */
.input-group > i { /* Usamos '>' para ser más específicos */
    position: absolute;
    left: 15px;
    top: 44px; /* Ajustado para mejor alineación vertical */
    color: #aaa;
}

.input-readonly {
    position: relative; /* Hacemos este el contenedor de referencia */
    display: flex;
    align-items: center;
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background-color: #f8f9fa;
    color: #777;
    box-sizing: border-box;
}
/* Estilo para los íconos DENTRO de los campos de solo lectura (nombre y correo) */
.input-readonly i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%); /* Centrado perfecto */
}
/* --- ESTILO DEFINITIVO PARA EL BOTÓN DEL FORMULARIO DE PERFIL (TRANSPARENTE) --- */
.profile-form-container .btn-submit {
    /* width: 100%; <-- Eliminamos esta línea */
    padding: 14px 495px;
    margin-top: 25px;
    font-size: 9.3px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s ease;

    /* --- CAMBIOS CLAVE --- */
    background-color: transparent; /* Fondo transparente */
    border: 2px solid #02b1f4;     /* Borde de color azul */
    color: #02b1f4;               /* Texto de color azul */
    box-shadow: none;              /* Quitamos la sombra inicial */
}

.profile-form-container .btn-submit:hover {
    background-color: #02b1f4; /* Al pasar el mouse, se rellena de azul */
    color: #fff;               /* Y el texto se vuelve blanco */
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
}
/* --- ESTILO DEFINITIVO PARA EL BOTÓN DE SOLICITAR CITA --- */
.btn-solicitud {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
    font-size: 15px;
    font-weight: 300;
    color: #fff;
    background: linear-gradient(45deg, #02b1f4, #00c2ff);
    border: none;
    border-radius: 7px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3);
    transition: all 0.3s ease;
}

.btn-solicitud:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
    background: linear-gradient(45deg, #028ac7, #00a2d9);
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
                <?php if ($rol_usuario == 'administrador'): ?>
    <a href="#" class="nav-link active" onclick="mostrarVista('admin-dashboard', event)">
        <i class="fa-solid fa-chart-line"></i> Dashboard
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('admin-aprobaciones', event)">
        <i class="fa-solid fa-user-check"></i> Aprobaciones
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('perfil', event)">
        <i class="fa-solid fa-user-gear"></i> <span>Mi Perfil</span>
    </a>
<?php endif; ?>
                <?php if ($rol_usuario == 'psicologo' || $rol_usuario == 'psiquiatra'): ?>
    <a href="#" id="nav-dashboard" class="nav-link active" onclick="mostrarVista('dashboard', event)">
        <i class="fa-solid fa-chart-line"></i> <span>Dashboard</span>
    </a>
    <a href="#" id="nav-pacientes" class="nav-link" onclick="mostrarVista('pacientes', event)">
        <i class="fa-solid fa-users"></i> <span>Mis Pacientes</span>
    </a>
    <a href="#" id="nav-citas" class="nav-link" onclick="mostrarVista('citas', event)">
        <i class="fa-solid fa-inbox"></i> <span>Solicitudes de Cita</span>
    </a>
    <a href="#" id="nav-agenda" class="nav-link" onclick="mostrarVista('agenda', event)">
        <i class="fa-solid fa-calendar-days"></i> <span>Mi Agenda</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('perfil', event)">
        <i class="fa-solid fa-user-gear"></i> <span>Mi Perfil</span>
    </a>
<?php endif; ?>
                
                <?php if ($rol_usuario == 'paciente'): ?>
     <a href="#" class="nav-link active" onclick="mostrarVista('miscitas', event)">
        <i class="fa-solid fa-calendar-check"></i> <span>Mis Citas</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('solicitar', event)">
        <i class="fa-solid fa-file-circle-plus"></i> <span>Solicitar Cita</span>
    </a>
    <!-- ENLACE MODIFICADO -->
    <a href="#" class="nav-link" onclick="mostrarVista('psicologos', event)">
        <i class="fa-solid fa-user-doctor"></i> <span>Psicólogos</span>
    </a>
    <!-- ENLACE NUEVO -->
    <a href="#" class="nav-link" onclick="mostrarVista('psiquiatras', event)">
        <i class="fa-solid fa-brain"></i> <span>Psiquiatras</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('perfil', event)">
        <i class="fa-solid fa-user-gear"></i> <span>Mi Perfil</span>
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
            

        <!-- Bloque para mostrar mensajes de éxito o error -->
<?php if (isset($_GET['status']) && $_GET['status'] == 'pass_success'): ?>
    <div class="panel-seccion" style="background-color: #d4edda; border-left: 5px solid #28a745; color: #155724;">
        <p><strong>Éxito:</strong> Tu contraseña ha sido actualizada correctamente.</p>
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="panel-seccion" style="background-color: #f8d7da; border-left: 5px solid #dc3545; color: #721c24;">
        <?php
            if ($_GET['error'] == 'mismatch') {
                echo "<p><strong>Error:</strong> Las contraseñas no coinciden.</p>";
            } elseif ($_GET['error'] == 'short') {
                echo "<p><strong>Error:</strong> La contraseña debe tener al menos 6 caracteres.</p>";
            } else {
                echo "<p><strong>Error:</strong> No se pudo actualizar la contraseña.</p>";
            }
        ?>
    </div>
<?php endif; ?>


            <?php
        
// Mostrar mensaje con la contraseña temporal si existe
if (isset($_SESSION['nuevo_paciente_nombre']) && isset($_SESSION['contrasena_temporal'])) {
    echo '<div class="alert-box success">';
    echo '  <span>';
    echo '      <strong>¡Éxito!</strong> Paciente <strong>' . htmlspecialchars($_SESSION['nuevo_paciente_nombre']) . '</strong> creado. ';
    echo '      Su contraseña temporal es: <strong class="temp-pass">' . htmlspecialchars($_SESSION['contrasena_temporal']) . '</strong>';
    echo '  </span>';
    echo '  <span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span>';
    echo '</div>';

    // Limpiar las variables de sesión
    unset($_SESSION['nuevo_paciente_nombre']);
    unset($_SESSION['contrasena_temporal']);
}
?>

<!-- VISTA UNIVERSAL PARA EL PERFIL DE USUARIO (DISEÑO MEJORADO) -->
<div id="vista-perfil" class="panel-vista">
    <div class="panel-seccion">
        

        <div class="profile-form-container">
            <form action="actualizar_perfil.php" method="POST">
                <h3>Datos de la Cuenta</h3>
                <div class="input-group">
                    <label>Nombre Completo</label>
                    <div class="input-readonly">
                        <i class="fa-solid fa-user"></i>
                        <span><?php echo htmlspecialchars($nombre_usuario); ?></span>
                    </div>
                </div>
                <div class="input-group">
                    <label>Correo Electrónico</label>
                    <div class="input-readonly">
                        <i class="fa-solid fa-envelope"></i>
                        <span><?php echo htmlspecialchars($_SESSION['correo']); ?></span>
                    </div>
                </div>

                <h3 style="margin-top: 30px;">Cambiar Contraseña</h3>
                <div class="input-group">
                    <label for="nueva_pass">Nueva Contraseña</label>
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="nueva_pass" id="nueva_pass" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="input-group">
                    <label for="confirmar_pass">Confirmar Nueva Contraseña</label>
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="confirmar_pass" id="confirmar_pass" placeholder="Repite la nueva contraseña" required>
                </div>

                <button type="submit" class="btn-submit">Actualizar Contraseña</button>
            </form>
        </div>
    </div>
</div>

            <?php if ($rol_usuario == 'administrador'): ?>
    <div id="vista-admin-dashboard" class="panel-vista active">
        <div class="stats-grid">
    <a href="ver_usuarios.php?filtro=pendientes" class="stat-card-link">
        <div class="stat-card">
            <div class="icon"><i class="fa-solid fa-user-clock"></i></div>
            <div class="info"><div class="number"><?php echo $stats['pendientes']; ?></div><div class="label">Usuarios Pendientes</div></div>
        </div>
    </a>
    <a href="ver_usuarios.php?filtro=aprobados" class="stat-card-link">
        <div class="stat-card">
            <div class="icon"><i class="fa-solid fa-users"></i></div>
            <div class="info"><div class="number"><?php echo $stats['aprobados']; ?></div><div class="label">Usuarios Totales</div></div>
        </div>
    </a>
    <a href="ver_usuarios.php?filtro=doctores" class="stat-card-link">
        <div class="stat-card">
            <div class="icon"><i class="fa-solid fa-user-doctor"></i></div>
            <div class="info"><div class="number"><?php echo $stats['doctores']; ?></div><div class="label">Doctores Activos</div></div>
        </div>
    </a>
</div>
    </div>
    <div id="vista-admin-aprobaciones" class="panel-vista">
        <div class="panel-seccion">
            <h2>Registros Pendientes de Aprobación</h2>
            <?php
            $consulta_pendientes = $conex->query("SELECT id, nombre_completo, correo, rol FROM usuarios WHERE estado = 'pendiente'");
            if ($consulta_pendientes->num_rows > 0) {
                echo "<table class='approvals-table'><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Acciones</th></tr></thead><tbody>";
                while($usuario_pendiente = $consulta_pendientes->fetch_assoc()) {
                    echo "<tr><td>" . htmlspecialchars($usuario_pendiente['nombre_completo']) . "</td><td>" . htmlspecialchars($usuario_pendiente['correo']) . "</td><td>" . htmlspecialchars($usuario_pendiente['rol']) . "</td><td class='action-links'><a href='gestionar_aprobacion.php?id=" . $usuario_pendiente['id'] . "&accion=aprobar' class='approve'>Aprobar</a> <a href='gestionar_aprobacion.php?id=" . $usuario_pendiente['id'] . "&accion=rechazar' class='reject'>Rechazar</a></td></tr>";
                }
                echo "</tbody></table>";
            } else { echo "<p>No hay registros pendientes de aprobación.</p>"; }
            ?>
        </div>
    </div>
<?php endif; ?>
            <?php if ($rol_usuario == 'psicologo' || $rol_usuario == 'psiquiatra'): ?>

    <div id="vista-dashboard" class="panel-vista active">
        <div class="panel-seccion" style="margin-top: 0px;">
    <h3>Próximas Citas</h3>
    <ul class="appointment-list">
        <?php if ($proximas_citas && $proximas_citas->num_rows > 0): ?>
            <?php while($cita = $proximas_citas->fetch_assoc()): ?>
                <li>
                    <span class="patient-name"><?php echo htmlspecialchars($cita['nombre_completo']); ?></span>
                    <span class="appointment-time"><?php echo date('d/m/Y h:i A', strtotime($cita['fecha_cita'])); ?></span>
                </li>
            <?php endwhile; ?>
        <?php else: ?>
            <li class="no-appointments">No tienes próximas citas programadas.</li>
        <?php endif; ?>
    </ul>
</div>
        
        <?php
        // Lógica para obtener las estadísticas del dashboard
        $psicologo_id_stats = $_SESSION['usuario_id'];
        
        // Citas para hoy
        $hoy_inicio = date('Y-m-d 00:00:00');
        $hoy_fin = date('Y-m-d 23:59:59');
        $stmt_citas_hoy = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE psicologo_id = ? AND estado = 'confirmada' AND fecha_cita BETWEEN ? AND ?");
        $stmt_citas_hoy->bind_param("iss", $psicologo_id_stats, $hoy_inicio, $hoy_fin);
        $stmt_citas_hoy->execute();
        $citas_hoy = $stmt_citas_hoy->get_result()->fetch_assoc()['total'];
        $stmt_citas_hoy->close();

        // Solicitudes pendientes
        $stmt_pendientes = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE psicologo_id = ? AND estado = 'pendiente'");
        $stmt_pendientes->bind_param("i", $psicologo_id_stats);
        $stmt_pendientes->execute();
        $solicitudes_pendientes = $stmt_pendientes->get_result()->fetch_assoc()['total'];
        $stmt_pendientes->close();

        // Pacientes activos
        $stmt_pacientes_activos = $conex->prepare("SELECT COUNT(DISTINCT u.id) as total FROM usuarios u LEFT JOIN citas c ON u.id = c.paciente_id WHERE u.rol = 'paciente' AND u.estado = 'aprobado' AND (u.creado_por_psicologo_id = ? OR c.psicologo_id = ?)");
        $stmt_pacientes_activos->bind_param("ii", $psicologo_id_stats, $psicologo_id_stats);
        $stmt_pacientes_activos->execute();
        $pacientes_activos = $stmt_pacientes_activos->get_result()->fetch_assoc()['total'];
        $stmt_pacientes_activos->close();
        ?>

        <div class="stats-grid">
    <a href="#" onclick="mostrarVista('agenda', event); return false;" class="stat-card-link">
        <div class="stat-card">
            <div class="icon"><i class="fa-solid fa-calendar-day"></i></div>
            <div class="info"><div class="number"><?php echo $stats['citas_hoy']; ?></div><div class="label">Citas para Hoy</div></div>
        </div>
    </a>
    <a href="#" onclick="mostrarVista('citas', event); return false;" class="stat-card-link">
        <div class="stat-card">
            <div class="icon"><i class="fa-solid fa-inbox"></i></div>
            <div class="info"><div class="number"><?php echo $stats['pendientes']; ?></div><div class="label">Solicitudes Pendientes</div></div>
        </div>
    </a>
    <a href="#" onclick="mostrarVista('pacientes', event); return false;" class="stat-card-link">
        <div class="stat-card">
            <div class="icon"><i class="fa-solid fa-users"></i></div>
            <div class="info"><div class="number"><?php echo $stats['pacientes_activos']; ?></div><div class="label">Pacientes Activos</div></div>
        </div>
    </a>
</div>

 <!-- 👇 ESTE ES EL CONTENEDOR QUE FALTABA 👇 -->
    <div class="dashboard-widgets-grid">
    <div class="panel-seccion chart-widget">
        <h3>Consultas por Mes (Últimos 6 meses)</h3>
        <div class="chart-container">
            <canvas id="citasChart"></canvas>
        </div>
    </div>
    <div class="panel-seccion chart-widget">
        <h3>Nuevos Pacientes (Últimos 7 días)</h3>
        <div class="chart-container">
            <canvas id="newPatientsChart"></canvas>
        </div>
    </div>
 </div>
</div>

    <div id="vista-pacientes" class="panel-vista">
    <div class="panel-seccion">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Mis Pacientes</h2>
            <a href="crear_paciente.php" class="action-links approve" style="text-decoration: none;">
                <i class="fa-solid fa-plus"></i> Añadir Paciente
            </a>
        </div>
        
        <div class="search-container">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="buscador-pacientes" placeholder="Buscar paciente por nombre o cédula...">
        </div>

        <div id="tabla-pacientes-container">
            <p>Aquí aparecen los pacientes que has añadido manualmente o aquellos a los que les has confirmado una cita.</p>
            <?php
            $psicologo_id = $_SESSION['usuario_id'];
            $sql_pacientes = "SELECT DISTINCT u.id, u.nombre_completo, u.correo, u.cedula 
                              FROM usuarios u
                              LEFT JOIN citas c ON u.id = c.paciente_id
                              WHERE u.rol = 'paciente' AND u.estado = 'aprobado'
                              AND (u.creado_por_psicologo_id = ? OR c.psicologo_id = ?)";
                              
            $consulta_pacientes = $conex->prepare($sql_pacientes);
            $consulta_pacientes->bind_param("ii", $psicologo_id, $psicologo_id);
            $consulta_pacientes->execute();
            $resultado_pacientes = $consulta_pacientes->get_result();
            if ($resultado_pacientes->num_rows > 0) {
                echo "<table class='approvals-table'><thead><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Acciones</th></tr></thead><tbody>";
                while($paciente = $resultado_pacientes->fetch_assoc()) {
                    echo "<tr><td>" . htmlspecialchars($paciente['nombre_completo']) . "</td><td>" . htmlspecialchars($paciente['cedula']) . "</td><td>" . htmlspecialchars($paciente['correo']) . "</td><td class='action-links'><a href='gestionar_paciente.php?paciente_id=" . $paciente['id'] . "' class='approve'>Gestionar</a></td></tr>";
                }
                echo "</tbody></table>";
            } else { echo "<p>Aún no tienes pacientes en tu lista.</p>"; }
            ?>
        </div>
    </div>
</div>
    <div id="vista-citas" class="panel-vista">
        <div class="panel-seccion">
            <h2>Solicitudes de Cita Pendientes</h2>
             <?php
            $consulta_pendientes = $conex->prepare("SELECT c.id, c.motivo_consulta, u.nombre_completo as paciente_nombre FROM citas c JOIN usuarios u ON c.paciente_id = u.id WHERE c.estado = 'pendiente' AND c.psicologo_id = ?");
            $consulta_pendientes->bind_param("i", $usuario_id);
            $consulta_pendientes->execute();
            $resultado_pendientes_citas = $consulta_pendientes->get_result();
            if ($resultado_pendientes_citas->num_rows > 0) {
                echo "<table class='approvals-table'><thead><tr><th>Paciente</th><th>Motivo</th><th>Acción</th></tr></thead><tbody>";
                while($solicitud = $resultado_pendientes_citas->fetch_assoc()) {
                    echo "<tr><td>" . htmlspecialchars($solicitud['paciente_nombre']) . "</td><td>" . htmlspecialchars($solicitud['motivo_consulta']) . "</td><td class='action-links'><a href='programar_cita.php?cita_id=" . $solicitud['id'] . "' class='approve'>Programar</a></td></tr>";
                }
                echo "</tbody></table>";
            } else { echo "<p>No tienes solicitudes de cita pendientes.</p>"; }
            ?>
        </div>
    </div>
    
    <div id="vista-agenda" class="panel-vista">
        <div class="panel-seccion">
            <h2>Mi Agenda de Citas</h2>
            <div id="calendario"></div>
        </div>
    </div>

<?php endif; ?>

            <?php if ($rol_usuario == 'paciente'): ?>
    <?php
    // Obtener los datos del paciente para el formulario de solicitud
    $stmt_paciente_info = $conex->prepare("SELECT nombre_completo, cedula, correo FROM usuarios WHERE id = ?");
    $stmt_paciente_info->bind_param("i", $usuario_id);
    $stmt_paciente_info->execute();
    $paciente_info = $stmt_paciente_info->get_result()->fetch_assoc();
    $stmt_paciente_info->close();
    ?>

    <!-- VISTA 1: MIS CITAS -->
    <div id="vista-miscitas" class="panel-vista active">
        <div class="panel-seccion">
            <h2>Mis Citas</h2>
            <p>Aquí puedes ver el estado de tus citas programadas.</p>
            <?php
            $consulta_citas = $conex->prepare("SELECT fecha_cita, c.estado, p.nombre_completo as psicologo_nombre FROM citas c LEFT JOIN usuarios p ON c.psicologo_id = p.id WHERE c.paciente_id = ? ORDER BY c.fecha_solicitud DESC");
            $consulta_citas->bind_param("i", $usuario_id);
            $consulta_citas->execute();
            $resultado_citas = $consulta_citas->get_result();
            if ($resultado_citas->num_rows > 0) {
                echo "<table class='approvals-table'><thead><tr><th>Fecha Programada</th><th>Profesional</th><th>Estado</th></tr></thead><tbody>";
                while($cita = $resultado_citas->fetch_assoc()) {
                    echo "<tr><td>" . ($cita['fecha_cita'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita']))) : 'Por confirmar') . "</td><td>" . ($cita['psicologo_nombre'] ? htmlspecialchars($cita['psicologo_nombre']) : 'No asignado') . "</td><td>" . htmlspecialchars(ucfirst($cita['estado'])) . "</td></tr>";
                }
                echo "</tbody></table>";
            } else { echo "<p>No tienes ninguna cita solicitada o programada.</p>"; }
            $consulta_citas->close();
            ?>
        </div>
    </div>

    <!-- VISTA 2: SOLICITAR NUEVA CITA -->
    <div id="vista-solicitar" class="panel-vista">
        <div class="panel-seccion">
            <h2>Solicitar Nueva Cita</h2>
            <p>Tus datos serán enviados junto con tu solicitud. Por favor, describe el motivo de tu consulta y elige un profesional.</p>
            <div class="info-paciente-grid">
                <div class="info-item"><strong>Nombre Completo:</strong> <span><?php echo htmlspecialchars($paciente_info['nombre_completo']); ?></span></div>
                <div class="info-item"><strong>Cédula:</strong> <span><?php echo htmlspecialchars($paciente_info['cedula']); ?></span></div>
                <div class="info-item"><strong>Correo:</strong> <span><?php echo htmlspecialchars($paciente_info['correo']); ?></span></div>
            </div>
            <form action="solicitar_cita.php" method="POST" style="margin-top:20px;">
                <div class="form-group">
                    <label for="psicologo_id">Seleccionar Profesional:</label>
                    <select name="psicologo_id" id="psicologo_id" required style="width:100%; padding:12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;">
                        <option value="">-- Elige un profesional --</option>
                        <?php 
                        $profesionales_result = $conex->query("SELECT id, nombre_completo, rol FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra') AND estado = 'aprobado'");
                        if ($profesionales_result) {
                            while($prof = $profesionales_result->fetch_assoc()){
                                echo '<option value="' . $prof['id'] . '">' . htmlspecialchars($prof['nombre_completo']) . ' (' . ucfirst($prof['rol']) . ')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group"><label for="motivo_consulta">Motivo de la consulta:</label><textarea name="motivo_consulta" id="motivo_consulta" rows="5" required placeholder="Describe brevemente por qué solicitas la cita..."></textarea></div>
                <button type="submit" class="btn-solicitud">Enviar Solicitud de Cita</button>
            </form>
        </div>
    </div>

    <!-- VISTA 3: PSICÓLOGOS -->
    <div id="vista-psicologos" class="panel-vista">
        <div class="panel-seccion">
            <h2>Nuestros Psicólogos</h2>
            <?php
             $psicologos_result = $conex->query("SELECT nombre_completo FROM usuarios WHERE rol = 'psicologo' AND estado = 'aprobado'");
             if ($psicologos_result->num_rows > 0) {
                echo "<table class='approvals-table'><thead><tr><th>Nombre del Profesional</th></tr></thead><tbody>";
                while($psicologo = $psicologos_result->fetch_assoc()) {
                    echo "<tr><td>" . htmlspecialchars($psicologo['nombre_completo']) . "</td></tr>";
                }
                echo "</tbody></table>";
            } else { echo "<p>No hay psicólogos disponibles en este momento.</p>"; }
            ?>
        </div>
    </div>

    <!-- VISTA 4: PSIQUIATRAS (NUEVA) -->
    <div id="vista-psiquiatras" class="panel-vista">
        <div class="panel-seccion">
            <h2>Nuestros Psiquiatras</h2>
            <?php
             $psiquiatras_result = $conex->query("SELECT nombre_completo FROM usuarios WHERE rol = 'psiquiatra' AND estado = 'aprobado'");
             if ($psiquiatras_result->num_rows > 0) {
                echo "<table class='approvals-table'><thead><tr><th>Nombre del Profesional</th></tr></thead><tbody>";
                while($psiquiatra = $psiquiatras_result->fetch_assoc()) {
                    echo "<tr><td>" . htmlspecialchars($psiquiatra['nombre_completo']) . "</td></tr>";
                }
                echo "</tbody></table>";
            } else { echo "<p>No hay psiquiatras disponibles en este momento.</p>"; }
            ?>
        </div>
    </div>

<?php endif; ?>
        </main>
    </div>

    <script>
    // Variables globales para que las funciones puedan acceder a ellas
    let calendar;
    let isCalendarRendered = false;

    // --- FUNCIÓN ÚNICA PARA CAMBIAR DE VISTA (PESTAÑAS) ---
    function mostrarVista(vista, event) {
        if (event) {
            event.preventDefault(); // Evita que el enlace recargue la página
        }

        // Ocultar todas las vistas
        document.querySelectorAll('.panel-vista').forEach(v => v.classList.remove('active'));
        
        // Quitar la clase 'active' de todos los enlaces de la barra lateral
        document.querySelectorAll('.sidebar-nav a').forEach(link => link.classList.remove('active'));
        
        // Mostrar la vista seleccionada
        const vistaAMostrar = document.getElementById('vista-' + vista);
        if (vistaAMostrar) {
            vistaAMostrar.classList.add('active');
        }
        
        // Marcar el enlace correspondiente como activo
        if (event) {
            event.currentTarget.classList.add('active');
        } else {
            const linkActivo = document.querySelector(`.sidebar-nav a[onclick*="'${vista}'"]`);
            if (linkActivo) {
                linkActivo.classList.add('active');
            }
        }

        // Si la vista es la agenda y el calendario no ha sido dibujado, lo dibujamos ahora.
        if (vista === 'agenda' && !isCalendarRendered) {
            if (calendar) {
                calendar.render();
                isCalendarRendered = true;
            }
        }
    }

    // --- CÓDIGO QUE SE EJECUTA UNA SOLA VEZ CUANDO LA PÁGINA CARGA ---
    document.addEventListener('DOMContentLoaded', function() {

        // LÓGICA DEL CALENDARIO (FullCalendar)
const calendarEl = document.getElementById('calendario');
if (calendarEl) {
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: { 
            today: 'Hoy', 
            month: 'Mes', 
            week: 'Semana', 
            day: 'Día' 
        },
        allDayText: 'Hora',
        events: 'get_citas.php',

        height: 520, // Puedes cambiar este número (ej: 600, 700, etc.)

        // --- LÍNEAS RESTAURADAS PARA FORMATO 12 HORAS ---
        slotLabelFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short',
            hour12: true
        },
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short',
            hour12: true
        },
        // --- CÓDIGO AÑADIDO ---
        eventClick: function(info) {
            // Previene cualquier acción por defecto
            info.jsEvent.preventDefault(); 
            
            // Obtenemos el ID del paciente que guardamos en el paso 1
            const pacienteId = info.event.extendedProps.paciente_id;

            // Si el ID existe, redirigimos a la página de gestión
            if (pacienteId) {
                window.location.href = 'gestionar_paciente.php?paciente_id=' + pacienteId;
            }
        }
        // --- FIN DEL CÓDIGO AÑADIDO ---
        
    });
}
        
        // LÓGICA DEL GRÁFICO DE CITAS (Chart.js)
        const chartCanvas = document.getElementById('citasChart');
        if (chartCanvas) {
            fetch('get_chart_data.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(chartCanvas, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Citas Confirmadas',
                                data: chartData.data,
                                backgroundColor: 'rgba(2, 177, 244, 0.6)',
                                borderColor: 'rgba(2, 177, 244, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico:', error));
        }

        // LÓGICA DEL BUSCADOR DE PACIENTES
        const buscador = document.getElementById('buscador-pacientes');
        const contenedorTabla = document.getElementById('tabla-pacientes-container');
        if (buscador) {
            buscador.addEventListener('keyup', function() {
                const query = this.value;
                fetch('buscar_pacientes.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'query=' + encodeURIComponent(query)
                })
                .then(response => response.text())
                .then(data => {
                    contenedorTabla.innerHTML = data;
                })
                .catch(error => console.error('Error en la búsqueda:', error));
            });
        }

        // ACTIVAR LA PRIMERA VISTA POR DEFECTO AL CARGAR LA PÁGINA
        const firstViewLink = document.querySelector('.sidebar-nav a.active');
        if (firstViewLink) {
            const vistaInicial = firstViewLink.getAttribute('onclick').match(/'([^']+)'/)[1];
            mostrarVista(vistaInicial, null);
        }
    });
        // --- LÓGICA MEJORADA PARA ABRIR LA PESTAÑA CORRECTA ---
    const urlParams = new URLSearchParams(window.location.search);
    const vistaDesdeUrl = urlParams.get('vista');

    if (vistaDesdeUrl) {
        // Si la URL dice qué vista mostrar (ej: ?vista=pacientes), la mostramos.
        mostrarVista(vistaDesdeUrl, null);
        
        // Limpiamos la URL para que no se quede "pegada" al recargar.
        history.replaceState(null, '', window.location.pathname);

    } else {
        // Si no, mostramos la que esté marcada como 'active' por defecto en el HTML.
        const firstViewLink = document.querySelector('.sidebar-nav a.active');
        if (firstViewLink) {
            const vistaInicial = firstViewLink.getAttribute('onclick').match(/'([^']+)'/)[1];
            mostrarVista(vistaInicial, null);
        }
    }

    // LÓGICA DEL GRÁFICO DE NUEVOS PACIENTES (Bar Chart)
const newPatientsCanvas = document.getElementById('newPatientsChart');
if (newPatientsCanvas) {
    fetch('get_weekly_patients_data.php')
        .then(response => response.json())
        .then(chartData => {
            new Chart(newPatientsCanvas, {
                type: 'bar', // Tipo de gráfico: barras
                data: {
                    labels: chartData.labels, // ['Lun 21', 'Mar 22', ...]
                    datasets: [{
                        label: 'Nuevos Pacientes',
                        data: chartData.data, // [1, 0, 2, ...]
                        backgroundColor: 'rgba(40, 167, 69, 0.6)', // Color verde
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1 // Asegura que el eje Y vaya de 1 en 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // Ocultamos la leyenda para un look más limpio
                        }
                    }
                }
            });
        })
        .catch(error => console.error('Error al cargar datos del gráfico de nuevos pacientes:', error));
}

</script>
</body>
</html>