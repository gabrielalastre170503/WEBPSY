<?php
date_default_timezone_set('America/Caracas'); // <-- AÑADE ESTA LÍNEA
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
    
    // Contar todo el personal activo (psicólogos, psiquiatras, secretarias)
    $result_personal = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra', 'secretaria') AND estado = 'aprobado'");
    $stats['personal'] = $result_personal->fetch_assoc()['total'];

    // Contar todas las citas
    $result_citas = $conex->query("SELECT COUNT(id) as total FROM citas");
    $stats['total_citas'] = $result_citas->fetch_assoc()['total'];

    // Pacientes activos
    $result_pacientes = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'");
    $stats['pacientes_activos'] = $result_pacientes->fetch_assoc()['total'];

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
         // 4. Obtener las próximas 5 citas (con ID y más detalles)
    $proximas_citas_stmt = $conex->prepare("
        SELECT c.id, c.fecha_cita, c.motivo_consulta, u.nombre_completo, u.cedula 
        FROM citas c 
        JOIN usuarios u ON c.paciente_id = u.id 
        WHERE c.psicologo_id = ? 
        AND c.estado IN ('confirmada', 'reprogramada')
        AND c.fecha_cita >= NOW() 
        ORDER BY c.fecha_cita ASC 
        LIMIT 5
    ");
    $proximas_citas_stmt->bind_param("i", $psicologo_id);
    $proximas_citas_stmt->execute();
    $proximas_citas = $proximas_citas_stmt->get_result();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <style>
    /* --- ESTILOS GENERALES Y LAYOUT --- */
    body { margin: 0; background-color: #f0f2f5; font-family: "Poppins", sans-serif; color: #333; }
    .dashboard-container {
    display: flex;
    height: 100vh; /* Fija la altura del contenedor a la pantalla */
    overflow: hidden; /* Evita que el body principal tenga scroll */
}

    /* --- BARRA LATERAL (SIDEBAR) --- */
    .sidebar {
    width: 220px;
    background-color: #ffffff;
    box-shadow: 2px 0 5px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    padding: 20px;
    flex-shrink: 0; /* Evita que la barra lateral se encoja */
}
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
    .main-content {
    flex-grow: 1;
    padding: 30px;
    overflow-y: auto; /* <-- ESTA ES LA CLAVE: Añade el scroll solo a esta área */
}
    .panel-vista { display: none; }
    .panel-vista.active { display: block; }
    .panel-seccion { background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
    
    /* --- ESTILOS PARA LAS TARJETAS DE ESTADÍSTICAS (ADMIN) --- */
    /* Estilo base para la rejilla de tarjetas (sin margen superior) */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
}

/* Añade el margen superior ÚNICAMENTE a la rejilla del dashboard del administrador */
#vista-admin-dashboard .stats-grid {
    margin-top: -8px !important;
}
    .stat-card-link { text-decoration: none; }
    .stat-card { background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: flex; align-items: center; transition: all 0.3s; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.12); }
    .stat-card .icon {
    font-size: 28px;
    margin-right: 20px;
    padding: 10px 30px; /* 15px arriba/abajo (altura), 25px izquierda/derecha (ancho) */
    border-radius: 8px; /* <-- Esto lo hace un cuadro con bordes suaves */
    color: var(--color-primario); /* <-- Cambia el color del ícono al azul principal */
    background-color: #f0f2f5; /* <-- Le da un fondo gris muy sutil */
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 60px;
}
    .stat-card .info .number { font-size: 28px; font-weight: 600; color: #333; }
    .stat-card .info .label { color: #777; font-size: 14px; }
    
    /* --- ESTILOS PARA LAS TABLAS (TODOS LOS PANELES) --- */
    .approvals-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    /* Hemos eliminado el background-color, border, border-radius y overflow */
}
    .approvals-table th, .approvals-table td { padding: 15px 25px; /* 10px arriba/abajo, 15px a los lados */; text-align: left; border-bottom: 1px solid #e9e9e9; }
    .approvals-table th { background-color: #fafafa; font-weight: 600; color: #555; }
    .approvals-table tr:hover { background-color: #f7f7f7; }
    /* --- ESTILOS ELEGANTES PARA BOTONES DE ACCIÓN EN TABLAS --- */
.action-links a {
    display: inline-block;
    padding: 6px 14px; /* Un poco más compactos */
    font-size: 13px;
    font-weight: 600; /* Un poco más de grosor para que resalte el texto */
    text-align: center;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none !important;
    border: 2px solid; /* Borde sólido que tomará el color de cada botón */
    background-color: transparent; /* Fondo transparente por defecto */
    transition: all 0.2s ease-in-out;
    margin-right: 8px;
}

/* Botón Principal (Gestionar, Programar) */
.action-links a.approve {
    border-color: #02b1f4;
    color: #02b1f4 !important;
}
.action-links a.approve:hover {
    background-color: #02b1f4;
    color: white !important;
    transform: translateY(-2px);
}

/* Botón Secundario (Reprogramar, etc.) */
.action-links a.schedule-btn {
    border-color: #6c757d;
    color: #6c757d !important;
}
.action-links a.schedule-btn:hover {
    background-color: #6c757d;
    color: white !important;
    transform: translateY(-2px);
}

/* Botón de Peligro (Rechazar, Borrar) */
.action-links a.reject {
    border-color: #dc3545;
    color: #dc3545 !important;
    background-color: transparent;
}
.action-links a.reject:hover {
    background-color: #dc3545;
    color: white !important;
    transform: translateY(-2px);
}
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






/* Contenedor para los gráficos para controlar su tamaño */
.chart-container {
    position: relative;
    height: 180px !important; /* <-- CAMBIA ESTE VALOR A TU GUSTO */
    width: 100%;
}
/* --- ESTILO PARA EL TEXTO DE LAS CITAS EN EL CALENDARIO --- */
.fc-daygrid-event .fc-event-title {
    font-size: 11px !important; /* Puedes cambiar este valor a 11px, 13px, etc. */
    white-space: normal !important; /* Permite que el texto se divida en varias líneas si no cabe */
    text-align: center !important; /* <-- LÍNEA AÑADIDA */
}
/* --- ESTILOS PARA LA REJILLA COMPLETA DE GRÁFICOS --- */
.dashboard-widgets-grid-full {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 0px 19px; /* 40px de espacio vertical, 25px de espacio horizontal */
    margin-top: 19px !important;
}
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

/* Define 2 columnas solo para el dashboard del psicólogo */
#vista-psicologo-dashboard .dashboard-widgets-grid {
    grid-template-columns: 1fr 1fr;
}

/* Define 2 columnas para el dashboard del admin, pero permite que un elemento ocupe todo el ancho */
#vista-admin-dashboard .dashboard-widgets-grid {
    grid-template-columns: 1fr 1fr;
}

/* Regla para que el gráfico de crecimiento ocupe las 2 columnas */
.full-width-widget {
    grid-column: 1 / -1; 
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
    width: 100%;
    padding: 15px 490px;
    margin-top: 30px;
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

/* --- AJUSTE DE TAMAÑO PARA TEXTO EN VISTA SEMANAL/DIARIA DEL CALENDARIO --- */
.fc-timegrid-event .fc-event-title {
    font-size: 11px !important; /* Puedes cambiar este valor (ej: 11px, 10px) */
    white-space: normal !important; /* Permite que el texto se divida en varias líneas */
}

/* Estilos para la rejilla de widgets inferiores (gráficos) */
.dashboard-widgets-grid {
    display: grid;
    grid-template-columns: 1fr 1fr; /* Dos columnas de igual tamaño */
    gap: 25px;
    margin-top: 25px;
}
/* --- ESTILOS ESPECÍFICOS PARA LOS CONTENEDORES DE GRÁFICOS --- */

/* Contenedor para los gráficos del Administrador */
#vista-admin-dashboard .chart-container {
    position: relative;
    height: 135px !important; /* <-- Puedes ajustar la altura para el ADMIN aquí */
    width: 100%;
}

/* Contenedor para el gráfico de crecimiento del Admin (el que ocupa todo el ancho) */
#vista-admin-dashboard .full-width-widget .chart-container {
    height: 137px !important; /* <-- Puedes ajustar la altura para ESTE GRÁFICO en específico */
}

/* Contenedor para los gráficos del Psicólogo */
#vista-psicologo-dashboard .chart-container {
    position: relative;
    height: 140px !important; /* <-- Puedes ajustar la altura para el PSICÓLOGO aquí */
    width: 100%;
}
/* Media query para que se apilen en pantallas pequeñas */
@media (max-width: 1200px) {
    .dashboard-widgets-grid {
        grid-template-columns: 1fr; /* Una sola columna */
    }
}
/* Estilo para que un widget ocupe todo el ancho de la rejilla */
.full-width-widget {
    grid-column: 1 / -1; /* Ocupa desde la primera hasta la última columna */
}

/* --- AJUSTE PARA TÍTULOS DE LOS GRÁFICOS --- */
.panel-seccion h3 {
    margin-top: -10px; /* Reduce el espacio superior a cero */
}

/* --- ESTILOS PARA LAS TARJETAS DE SELECCIÓN DE ROL --- */
.selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 30px;
}
.selection-card {
    display: block;
    padding: 30px;
    text-align: center;
    text-decoration: none;
    border-radius: 12px;
    background-color: #fafafa;
    border: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}
.selection-card:hover {
    transform: translateY(-5px);
    border-color: #02b1f4;
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}
.selection-card i {
    font-size: 40px;
    color: #02b1f4;
    margin-bottom: 15px;
}
.selection-card h3 {
    border: none;
    margin: 0;
    padding: 0;
    font-size: 1.2em;
    color: #333;
}

/* --- ESTILOS MEJORADOS PARA LAS TARJETAS DE PERSONAL --- */
.personal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 20px;

}
.personal-card {
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04);
    transition: transform 0.3s, box-shadow 0.3s;
    position: relative; /* Para posicionar el botón */
    padding-bottom: 70px; /* Espacio para el botón al final */
}
.personal-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

/* Línea de color distintiva para cada rol */
.personal-card.psicologo { border-top: 4px solid #02b1f4; }
.personal-card.psiquiatra { border-top: 4px solid #02b1f4; }
.personal-card.secretaria { border-top: 4px solid #02b1f4; }

.personal-card h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
    border-bottom: none;
    padding-bottom: 0;
    color: #333;
}
.personal-card p {
    margin: 0 0 15px 0;
    color: #777;
    font-size: 14px;
}
.personal-card p i {
    margin-right: 8px;
}
.personal-card .card-actions {
    position: absolute;
    bottom: 20px; /* Posiciona el botón en la parte inferior */
    right: 25px;   /* Alinea el botón a la derecha */
}

/* Estilo para que las tarjetas de personal se comporten como enlaces */
.personal-card-link {
    text-decoration: none;
    color: inherit;
}
.personal-card {
    padding-bottom: 25px; /* Ajustamos el padding ya que no hay botón */
}

/* Estilos para las etiquetas de estado de las citas */
.status-badge {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    color: white;
    text-transform: capitalize;
}
.status-pendiente { background-color: #ffc107; color: #333; }
.status-confirmada { background-color: #17a2b8; }
.status-cancelada { background-color: #dc3545; }
.status-completada { background-color: #28a745; }
.status-reprogramada { background-color: #fd7e14; } /* Color naranja */


/* --- ESTILOS PARA LAS TARJETAS DE ACCIÓN (PANEL Y MODAL) --- */
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

/* Estilo base para las tarjetas de acción */
.action-card {
    display: block;
    padding: 25px;
    text-align: center;
    text-decoration: none;
    border-radius: 12px;
    background-color: #fafafa;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    color: #333;
}
.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.08);
    border-color: #02b1f4;
}
.action-card i {
    font-size: 36px;
    margin-bottom: 15px;
}
.action-card h3 {
    margin: 0;
    font-size: 1.1em;
    border: none;
    padding: 0;
}
.historia { color: #02b1f4; }
.informe { color: #17a2b8; }




/* --- ESTILOS PARA LA LISTA DE ACCESOS DIRECTOS --- */
.shortcut-list {
    list-style: none;
    padding: 0;
    margin-top: 20px;
}
.shortcut-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-radius: 8px;
    transition: background-color 0.2s ease;
}
.shortcut-item:not(:last-child) {
    border-bottom: 1px solid #f0f0f0;
}
.shortcut-item:hover {
    background-color: #f8f9fa;
}
.shortcut-info {
    display: flex;
    align-items: center;
    font-weight: 500;
}
.shortcut-info i {
    margin-right: 15px;
    color: #02b1f4;
    width: 20px;
    text-align: center;
}
.shortcut-actions a {
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
    margin-left: 10px;
}
.btn-view {
    background-color: #e9ecef;
    color: #495057;
}
.btn-view:hover {
    background-color: #dee2e6;
}
.btn-manage {
    background-color: #02b1f4;
    color: white;
}
.btn-manage:hover {
    background-color: #028ac7;
}

/* --- AJUSTE DE ALTURA PARA EL PANEL DE GESTIÓN DE CONTENIDO --- */
#vista-admin-contenido .panel-seccion {
    padding-top: 25px;    /* Reduce el espacio superior */
    padding-bottom: 25px; /* Reduce el espacio inferior */
}

/* --- AJUSTE DE ALTURA PARA EL PANEL DE ACCESOS DIRECTOS (FORZADO) --- */
#vista-admin-contenido .shortcut-panel {
    padding-top: 5px !important;    /* Reduce el espacio superior */
    padding-bottom: 5px !important; /* Reduce el espacio inferior */
}

/* --- AJUSTE DE MARGEN PARA EL TÍTULO DE ACCESOS DIRECTOS --- */
.shortcut-panel h2 {
    margin-top: 15px !important; /* <-- CAMBIA ESTE VALOR */
}

/* Estilo para el botón de reprogramar */
.action-links a.reschedule {
    background-color: #ffc107; /* Amarillo */
}
.action-links a.reschedule:hover {
    background-color: #e0a800; /* Amarillo más oscuro */
}


/* Estilos para la lista de próximas citas */
.appointment-list {
    list-style: none;
    padding: 0;
    margin: 0;
    height: 100px;
    overflow-y: auto;  /* Muestra la barra de scroll si es necesario */
    flex-direction: column;
    /* --- ESTA ES LA LÓGICA CORREGIDA --- */
    /* Creamos un fondo con líneas horizontales que se repiten */
    background-image: linear-gradient(to bottom, #f0f0f0 1px, transparent 1px);
    background-size: 100% 38px; /* Altura de cada "renglón" */
}
.appointment-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px 30px 5px 5px; /* Top, Right, Bottom, Left */
    border-bottom: 1px solid #f0f2f5;
    flex-shrink: 0; /* Evita que los items se encojan */
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
/* Estilo para el mensaje cuando no hay citas */
.appointment-list .no-appointments {
    flex-grow: 1;
    display: flex;
    justify-content: center; /* Mantiene el centrado horizontal */
    
    /* --- CAMBIOS CLAVE --- */
    align-items: flex-start; /* Alinea el texto en la parte superior */
    padding-top: 30px;      /* <-- CAMBIA ESTE VALOR para ajustar el espacio */

    color: #999;
    font-style: italic;
    border-bottom: none;
}










/* --- ESTILOS PARA LA SECCION DEL SIDEBAR DE LISTA DE PRÓXIMAS CITAS (CORREGIDO) --- */
.upcoming-appointments-list {
    list-style: none;
    padding: 0;
    margin-top: 20px;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}
.upcoming-appointments-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}
.upcoming-appointments-list li:last-child {
    border-bottom: none;
}
.upcoming-appointments-list li:hover {
    background-color: #f8f9fa;
}
.appointment-details .patient-name {
    font-weight: 600;
    font-size: 16px;
    color: #333;
    display: block;
    margin-bottom: 5px;
}
.appointment-details .appointment-info {
    font-size: 14px;
    color: #777;
    display: block;
}
.appointment-details .appointment-info i {
    margin-right: 5px;
}
.no-appointments-item {
    text-align: center;
    color: #777;
    padding: 30px;
    font-style: italic;
}


/* --- ESTILOS PARA LA CAJA DE NOTIFICACIONES --- */
.alert-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    margin-bottom: 25px;
    border-radius: 8px;
    font-size: 16px;
    border-left-width: 5px;
    border-left-style: solid;
}
.alert-box.info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}
.alert-box .close-btn {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
    color: inherit;
    opacity: 0.7;
}


/* --- ESTILO PARA EL BOTÓN PERSONALIZADO DEL CALENDARIO --- */
.fc .fc-manageAvailabilityButton-button {
    background-color: #6c757d; /* Color gris secundario */
    border-color: #6c757d;
    color: white;
    text-transform: none;
    font-size: 0.9em;
    padding: 6px 12px;
    margin-right: 5px !important; /* Espacio a la derecha */
}

.fc .fc-manageAvailabilityButton-button:hover {
    background-color: #5a6268; /* Gris más oscuro */
}

/* --- AJUSTE DE POSICIÓN PARA EL TÍTULO DEL CALENDARIO --- */
#calendario .fc-toolbar-title {
    position: relative;
    left: -60px; /* <-- Mueve el título 60 píxeles a la izquierda */
}

/* Estilos para los botones de selección de hora */
.time-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
}
.time-slot-btn {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    background-color: #fff;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}
.time-slot-btn:hover {
    background-color: #e9f7fe;
    border-color: #02b1f4;
}
.time-slot-btn.selected {
    background-color: #02b1f4;
    color: white;
    border-color: #02b1f4;
}
















/* --- ESTILOS PARA LOS CAMPOS CON ÍCONO EN EL FORMULARIO DE CITAS --- */
#vista-solicitar .input-wrapper {
    position: relative;
}
#vista-solicitar .input-wrapper i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
    pointer-events: none; /* Para que el clic llegue al input */
}
#vista-solicitar .input-wrapper input {
    width: 100%;
    padding-left: 45px; /* Espacio para el ícono */
    box-sizing: border-box;
}

/* --- ESTILOS PREMIUM PARA EL FORMULARIO DE SOLICITUD DE CITA --- */
.appointment-form-container {
    max-width: 1050px; /* Ancho del formulario */
    margin: 0 auto;
    background-color: #ffffff;
    padding: 30px 40px;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.form-step {
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid #f0f0f0;
}
.form-step:last-of-type {
    border-bottom: none;
    margin-bottom: 20px;
}
.step-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}
.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    background-color: #02b1f4;
    color: white;
    border-radius: 50%;
    font-weight: 600;
    font-size: 16px;
    margin-right: 15px;
    flex-shrink: 0;
}
.step-label {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.form-grid-appointment {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}
.full-width {
    grid-column: 1 / -1;
}

#vista-solicitar .form-group label {
    font-size: 14px;
    color: #555;
    font-weight: 500;
    margin-bottom: 8px;
}

#vista-solicitar select,
#vista-solicitar input[type="text"],
#vista-solicitar textarea {
    width: 100%;
    padding: 14px 15px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 15px;
    font-family: "Poppins", sans-serif;
    background-color: white;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

#vista-solicitar select {
    cursor: pointer;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23888' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 16px;
}

#vista-solicitar input:focus,
#vista-solicitar textarea:focus,
#vista-solicitar select:focus {
    outline: none;
    border-color: #02b1f4;
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.15);
}

.time-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 10px;
    margin-top: 10px;
}
.time-slot-btn {
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    background-color: #fff;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
    font-weight: 500;
}
.time-slot-btn:hover {
    background-color: #e9f7fe;
    border-color: #02b1f4;
}
.time-slot-btn.selected {
    background-color: #02b1f4;
    color: white;
    border-color: #02b1f4;
    font-weight: 600;
}

#btn-enviar-solicitud {
    width: 100%;
    padding: 15px;
    border: 2px solid #02b1f4; /* Borde azul */
    border-radius: 8px;
    background: transparent; /* Fondo transparente */
    color: #02b1f4; /* Texto azul */
    font-size: 16px;
    font-weight: 100;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: none; /* Quitamos la sombra inicial */
}

#btn-enviar-solicitud:hover:not(:disabled) {
    background: white; /* Se rellena de blanco al pasar el mouse */
    color: #02b1f4;
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(2, 177, 244, 0.4);
}

#btn-enviar-solicitud:disabled {
    background: transparent;
    border-color: #ccc;
    color: #ccc;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

/* --- ESTILO PARA EL ENCABEZADO DE SECCIÓN EN LÍNEA --- */
.section-header {
    display: flex; /* Pone los elementos en una fila */
    justify-content: space-between; /* Empuja los elementos a los extremos */
    align-items: baseline; /* Alinea los textos por su base */
    margin-bottom: 30px; /* Mantiene el espacio inferior */
}

/* Ajustes para el título y subtítulo dentro del nuevo encabezado */
.section-header h2 {
    margin: 0px 8px; /* Quitamos los márgenes por defecto */
}
.section-header p {
    margin: 0;
    text-align: right; /* Alineamos el subtítulo a la derecha */
    color: #777;
}























/* --- ESTILOS PARA AUMENTAR EL TAMAÑO DEL CALENDARIO FLATPICKR --- */

/* Contenedor principal del calendario */
.flatpickr-calendar {
    font-size: 95%; /* Aumenta el tamaño de todo el calendario en un 10% */
    width: 315px !important; /* Le damos un ancho fijo más grande */
    margin-left: 30px;
}

/* Ajusta el tamaño de los días */
.flatpickr-day {
    height: 55px; /* Hace cada celda de día más alta */
    line-height: 55px; /* Centra el número del día verticalmente */
}

/* Ajusta el tamaño de la hora */
.flatpickr-time {
    font-size: 1.1em;
}

.flatpickr-time input.flatpickr-hour,
.flatpickr-time input.flatpickr-minute {
    padding: 8px; /* Hace los campos de hora un poco más grandes */
}

/* Estilo para la etiqueta de estado "Pospuesta" */
.status-pendiente_paciente { 
    background-color: #6c757d; /* Gris secundario */
}


/* --- ESTILOS ELEGANTES PARA BOTONES DE ACCIÓN EN TABLAS (UNIFICADO Y CORREGIDO) --- */
.action-links a,
.action-links button {
    display: inline-block;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 500;
    text-align: center;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none !important;
    border: 2px solid;
    background-color: transparent;
    transition: all 0.2s ease-in-out;
    margin-right: 8px;
    font-family: "Poppins", sans-serif;
}

.action-links a:last-child,
.action-links button:last-child {
    margin-right: 0;
}

.action-links a:hover,
.action-links button:hover {
    transform: translateY(-2px);
}

/* Botón Principal (Gestionar, Aprobar, Habilitar) - AZUL */
.action-links a.approve,
.action-links button.approve {
    border-color: #02b1f4;
    color: #02b1f4 !important;
}
.action-links a.approve:hover,
.action-links button.approve:hover {
    background-color: #02b1f4;
    color: white !important; /* <-- Texto a blanco */
}

/* Botón Secundario (Programar, Reprogramar, Posponer) - GRIS */
.action-links a.btn-secondary,
.action-links button.btn-secondary {
    border-color: #6c757d;
    color: #6c757d !important;
}
.action-links a.btn-secondary:hover,
.action-links button.btn-secondary:hover {
    background-color: #6c757d;
    color: white !important; /* <-- LÍNEA CORREGIDA Y AÑADIDA */
}

/* Botón de Peligro (Rechazar, Borrar) - ROJO */
.action-links a.reject,
.action-links button.reject {
    border-color: #dc3545;
    color: #dc3545 !important;
}
.action-links a.reject:hover,
.action-links button.reject:hover {
    background-color: #dc3545;
    color: white !important; /* <-- Texto a blanco */
}

/* --- AJUSTE DE TAMAÑO PARA EL PANEL DE CREACIÓN DE PERSONAL --- */
.creation-panel {
    max-width: 1200px; /* <-- CAMBIA ESTE VALOR PARA AJUSTAR EL ANCHO */
    margin-bottom: 20px;
    padding-top: 20px;    /* <-- Reduce el espacio superior */
    padding-bottom: 20px; /* <-- Reduce el espacio inferior */
}

/* --- ESTILOS PARA LA TABLA ORDENABLE --- */
.sortable-header {
    cursor: pointer;
    position: relative;
    user-select: none; /* Evita que el texto se seleccione al hacer clic */
}
.sortable-header::after {
    content: ' \2195'; /* Flecha arriba y abajo por defecto */
    font-size: 14px;
    color: #ccc;
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
}
.sortable-header.sort-asc::after {
    content: ' \25B2'; /* Flecha hacia arriba */
    color: #02b1f4;
}
.sortable-header.sort-desc::after {
    content: ' \25BC'; /* Flecha hacia abajo */
    color: #02b1f4;
}

/* ESTILOS PARA LA VENTANA MODAL */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: flex; justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background-color: white; padding: 30px 40px; border-radius: 10px; width: 90%; box-shadow: 0 5px 25px rgba(0,0,0,0.2); position: relative; animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .modal-close { position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .modal-content .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .modal-content .full-width { grid-column: 1 / -1; }
        .modal-content .input-group { position: relative; }
        .modal-content .input-group label { display: block; font-weight: 500; margin-bottom: 8px; color: #555; }
        .modal-content .input-group input { width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; box-sizing: border-box; }
        .modal-content .input-group i { position: absolute; left: 15px; top: 42px; color: #aaa; }

/* --- ESTILOS ELEGANTES PARA BOTONES DE GESTIÓN DE PACIENTES --- */

/* Botón "Añadir Paciente" (Estilo Contorno) */
.btn-outline-primary {
    padding: 8px 18px;
    font-size: 14px;
    font-weight: 500;
    color: #02b1f4;
    background-color: transparent;
    border: 2px solid #02b1f4;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: "Poppins", sans-serif; /* Asegura la misma fuente */
}

.btn-outline-primary:hover {
    background-color: #02b1f4;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(2, 177, 244, 0.2);
}

/* --- ESTILOS PARA ACCIONES DENTRO DE MODALES --- */
.modal-actions {
    text-align: center; /* Centra el botón */
    margin-top: 40px;
}

/* Ajustamos el botón de contorno para que funcione bien aquí */
.modal-actions .btn-outline-primary {
    padding: 8px 45px; /* Le damos un tamaño generoso */
}

.alert-box.error {
    background-color: #f8d7da;
    border-left: 5px solid #dc3545;
    color: #721c24;
}

/* --- ESTILOS PREMIUM PARA VENTANAS MODALES --- */
.modal-content-premium {
    display: flex;
    width: 100%;
    max-width: 850px; /* Ancho general de la modal */
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    overflow: hidden;
    animation: fadeIn 0.4s ease-out;
}

.modal-info-panel {
    background: linear-gradient(160deg, #02b1f4, #0082b3);
    color: white;
    padding: 40px 30px;
    width: 280px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
}
.modal-info-panel .info-icon { font-size: 40px; margin-bottom: 20px; }
.modal-info-panel h3 { margin: 0; font-size: 24px; border: none; padding: 0; color: white; }
.modal-info-panel p { font-size: 15px; opacity: 0.8; margin: 10px 0; }
.modal-info-panel strong { font-size: 18px; font-weight: 600; }
.modal-info-panel .info-footer { margin-top: auto; font-size: 13px; opacity: 0.7; }

.modal-form-panel {
    padding: 30px 40px;
    flex-grow: 1;
    position: relative;
}
.modal-form-panel h4 { margin-top: 0; margin-bottom: 25px; font-size: 20px; color: #333; }
.modal-form-panel .form-group { margin-bottom: 20px; }
.modal-form-panel .form-group label { font-weight: 500; margin-bottom: 8px; color: #555; font-size: 14px; }
.modal-form-panel .input-wrapper { position: relative; }
.modal-form-panel .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
.modal-form-panel .form-group input, .modal-form-panel .form-group textarea {
    width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; font-family: "Poppins", sans-serif; transition: all 0.3s; box-sizing: border-box;
}
.modal-form-panel .form-group input { padding-left: 45px; }
.modal-form-panel .form-group textarea { resize: vertical; }
.modal-form-panel .form-group input:focus, .modal-form-panel .form-group textarea:focus {
    outline: none; border-color: #02b1f4; box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.2);
}
.modal-form-panel .modal-actions { display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f0; }
.modal-form-panel .btn-submit, .modal-form-panel .btn-secondary { padding: 10px 25px; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; border: none; }
.modal-form-panel .btn-submit { background-color: #02b1f4; color: white; }
.modal-form-panel .btn-submit:hover { background-color: #028ac7; }
.modal-form-panel .btn-secondary { background-color: #e9ecef; color: #555; }
.modal-form-panel .btn-secondary:hover { background-color: #dee2e6; }

.modal-close-btn {
    position: absolute; top: 15px; right: 15px; width: 30px; height: 30px; border: none;
    background-color: #f0f2f5; color: #888; border-radius: 50%; cursor: pointer;
    font-size: 16px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.modal-close-btn:hover { background-color: #e2e6ea; color: #333; }


/* Estilo para el panel informativo de la modal de reprogramación */
.modal-info-panel.info-panel-warning {
    background: linear-gradient(160deg, #ffc107, #e0a800);
}




/* --- AJUSTES ESPECÍFICOS PARA LA MODAL DE GESTIÓN DE PACIENTE --- */

/* 1. Reducimos la altura de la modal ajustando el padding */
#modal-gestionar-paciente .modal-form-panel {
    padding: 30px 40px;
}

/* 2. Hacemos las tarjetas internas más elegantes */
#modal-gestionar-paciente .action-card {
    background-color: #ffffff;
    text-align: left; /* Alineamos el texto a la izquierda para un look más profesional */
    display: flex;
    align-items: center;
    padding: 20px;
}
#modal-gestionar-paciente .action-card .icon-wrapper {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px; /* Espacio entre el icono y el texto */
    flex-shrink: 0;
}
#modal-gestionar-paciente .action-card .icon-wrapper i {
    font-size: 22px;
    color: #fff;
    margin-bottom: 0; /* Reseteamos el margen del icono */
}

/* --- ESTILOS DE TEXTO UNIFICADOS (LA CORRECCIÓN ESTÁ AQUÍ) --- */
#modal-gestionar-paciente .action-card h3 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 700; /* Grosor seminegrita para el título */
    font-family: 'Poppins', sans-serif;
    color: #333;
    border: none; /* Nos aseguramos de quitar cualquier borde heredado */
    padding: 0;   /* Nos aseguramos de quitar cualquier padding heredado */
}
#modal-gestionar-paciente .action-card p {
    font-size: 13px;
    font-weight: 400; /* Grosor normal para la descripción */
    font-family: 'Poppins', sans-serif;
    color: #777;
    line-height: 1.5;
    margin: 0;
}

/* Estilos para la tarjeta desactivada */
.disabled-card { 
    background-color: #f8f9fa !important; 
    color: #adb5bd; 
    cursor: not-allowed; 
    border-color: #e9ecef;
}
.disabled-card:hover { 
    transform: none; 
    box-shadow: none;
}
.disabled-card h3, .disabled-card p { 
    color: #adb5bd !important; 
}
.disabled-card .icon-wrapper {
    background-color: #e9ecef !important;
}
.disabled-card .icon-wrapper i {
    color: #adb5bd !important;
}

/* --- ANIMACIÓN PREMIUM: ZOOM & FADE --- */
body.fade-out {
    animation: zoomOutFade 0.4s ease-in-out forwards;
}

@keyframes zoomOutFade {
    from {
        opacity: 1;
        transform: scale(1);
    }
    to {
        opacity: 0;
        transform: scale(0.99); /* Se encoge al 99% */
        visibility: hidden;
    }
}

/* --- ESTILOS PARA LA LISTA DE PACIENTES EN NOTAS DE SESIÓN --- */
.patient-list {
    list-style: none;
    padding: 0;
    margin-top: 20px;
}
.patient-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-radius: 8px;
    transition: background-color 0.2s;
}
.patient-item:not(:last-child) {
    border-bottom: 1px solid #f0f0f0;
}
.patient-item:hover {
    background-color: #f8f9fa;
}
.patient-info strong {
    font-size: 16px;
    color: #333;
}
.patient-info span {
    color: #777;
    font-size: 14px;
}
/* Estilo para el botón "Ver/Añadir Notas" (Contorno) */
.btn-view {
    text-decoration: none;
    padding: 7px 16px; /* Ajustamos el padding para el borde */
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
    transition: all 0.2s ease-in-out;

    /* --- ESTILO DE CONTORNO --- */
    background-color: transparent;
    border: 2px solid #02b1f4;
    color: #02b1f4 !important;
}

.btn-view:hover {
    background-color: #02b1f4;
    color: white !important;
    transform: translateY(-2px);
}

/* --- ESTILOS PARA LA MODAL DE NOTAS DE SESIÓN (COLOR CORREGIDO) --- */
.modal-info-panel.info-panel-history {
    background: linear-gradient(160deg, #02b1f4, #0082b3); /* Gradiente azul */
    color: white; /* Texto blanco para contraste */
    display: flex;
    width: 330px;
    flex-direction: column;
}
.info-panel-history .info-icon i { 
    color: white; /* Icono blanco */
    opacity: 0.8;
}
.info-panel-history h3 { 
    color: white; /* Título blanco */
}
.info-panel-history p { 
    color: white; /* Texto del nombre del paciente blanco */
    opacity: 0.9;
}
.info-panel-history strong { 
    color: white; 
}

/* Estilos para las notas dentro del historial */
.history-list {
    margin-top: 20px;
    flex-grow: 1;
    overflow-y: auto;
    padding-right: 15px;
    text-align: left;
}
.note-item {
    background-color: rgba(255, 255, 255, 0.1); /* Fondo semi-transparente para las notas */
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.note-header {
    font-weight: 600;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 8px;
}
.note-content {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    white-space: pre-wrap;
    word-wrap: break-word;
}
.history-list p { /* Para el mensaje "No hay notas" */
    text-align: center;
    padding: 20px;
    color: rgba(255, 255, 255, 0.8);
}

/* --- ESTILOS PARA SCROLLBAR Y BOTÓN LIMPIAR EN MODAL DE NOTAS --- */
.history-list {
    margin-top: 20px;
    flex-grow: 1;
    overflow-y: auto; /* Muestra el scroll si el contenido es muy largo */
    max-height: 400px; /* Altura máxima antes de que aparezca el scroll */
    padding-right: 15px;
    text-align: left;
}
.history-header {
    display: flex;
    align-items: center;
    gap: 15px;
}
.history-header div:nth-child(2) {
    flex-grow: 1;
}
.btn-clear-notes {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    margin-top: -30px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}
.btn-clear-notes:hover {
    background-color: #dc3545;
    color: white;
}


/* Estilo para el cuerpo de la modal con scroll */
.modal-body {
    overflow-y: auto; /* Añade scroll vertical si es necesario */
    max-height: 70vh; /* Altura máxima antes de que aparezca el scroll */
    padding-right: 15px; /* Espacio para la barra de scroll */
}

/* --- ESTILOS PREMIUM PARA MODAL CON ENCABEZADO (CORREGIDO) --- */
.modal-content-premium-header {
    width: 100%;
    max-width: 1200px;
    background: #fff; /* Fondo blanco para toda la modal */
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 95vh;
    animation: fadeIn 0.4s ease-out;
}

.modal-header-premium {
    background: linear-gradient(160deg, #02b1f4, #0c3b8cff);
    color: white;
    padding: 25px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.modal-header-premium .header-content {
    display: flex;
    align-items: center;
    gap: 15px;
}
.modal-header-premium i { font-size: 24px; opacity: 0.8; }
.modal-header-premium h2 { margin: 0; font-size: 20px; border: none; padding: 0; color: white; }
.modal-header-premium p { margin: 2px 0 0 0; font-size: 14px; opacity: 0.9; }

.modal-body-premium {
    padding: 10px 100px 30px 100px; /* <-- ESTA ES LA LÍNEA */
    overflow-y: auto; /* Scroll vertical para el formulario */
}

/* Títulos de sección dentro del formulario */
.modal-body-premium h3 {
    font-size: 16px;
    font-weight: 600;
    color: #02b1f4;
    margin-top: 25px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
    text-align: left;
}
.modal-body-premium h3:first-of-type {
    margin-top: 10px; /* Sin margen extra para el primer título */
}

.modal-body-premium .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr; /* <-- CAMBIADO A TRES COLUMNAS */
    gap: 20px 25px;
}
.modal-body-premium .full-width {
    grid-column: 1 / -1;
}
.modal-body-premium .form-group {
    margin-bottom: 15px;
}
.modal-body-premium .form-group label {
    font-size: 14px;
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
    display: block;
}




/* --- ESTILOS UNIFICADOS PARA TODOS LOS CAMPOS DE FORMULARIO EN MODALES --- */
.modal-body-premium .form-group input,
.modal-body-premium .form-group textarea,
.modal-body-premium .form-group select {
    width: 100%;
    padding: 12px;
    font-size: 15px;
    font-family: "Poppins", sans-serif;
    border: 1px solid #ced4da;
    border-radius: 3px;
    background-color: #ffffffff;
    transition: all 0.3s ease;
    box-sizing: border-box;
    /* Aseguramos que todos tengan la misma altura mínima */
    min-height: 48px; 
}

/* Estilos específicos para el selector (combo box) */
.modal-body-premium .form-group select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23888' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 16px;
    cursor: pointer;
    padding-right: 40px; /* Espacio para la flecha */
}

.modal-body-premium .form-group input:focus,
.modal-body-premium .form-group textarea:focus,
.modal-body-premium .form-group select:focus {
    outline: none;
    border-color: #02b1f4;
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.15);
}

.modal-body-premium textarea {
    resize: vertical;
    min-height: 80px;
}


/* Estilos para los botones al final del formulario */
.modal-body-premium .modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}
.modal-body-premium .btn-submit,
.modal-body-premium .btn-secondary {
    padding: 10px 25px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}
.modal-body-premium .btn-submit {
    background-color: #02b1f4;
    color: white;
}
.modal-body-premium .btn-submit:hover {
    background-color: #028ac7;
}
.modal-body-premium .btn-secondary {
    background-color: #e9ecef;
    color: #555;
}
.modal-body-premium .btn-secondary:hover {
    background-color: #dee2e6;
}

/* --- ESTILOS PREMIUM v2 PARA LA MODAL DE SELECCIÓN DE HISTORIA --- */
.modal-content-premium-select {
    width: 100%;
    max-width: 900px; /* Un poco más ancha para las tarjetas */
    background: #ffffff; /* Fondo blanco limpio */
    border-radius: 16px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.2);
    overflow: hidden;
    animation: fadeIn 0.4s ease-out;
}
.modal-header-select {
    padding: 35px 30px;
    text-align: center;
    position: relative;
    border-bottom: 1px solid #f0f0f0; /* Línea divisoria sutil */
}
.modal-header-select h2 {
    margin: 0;
    font-size: 22px;
    color: #333;
    border: none;
    padding: 0;
}
.modal-header-select p {
    margin: 5px 0 0 0;
    color: #6c757d;
}
.modal-body-select {
    padding: 45px 40px;
    margin-bottom: 15px;
}
.selection-grid-premium {
    display: flex;
    justify-content: center;
    gap: 30px; /* Espacio entre las tarjetas */
    flex-direction: row; /* Una al lado de la otra */
}
.selection-card-premium {
    display: flex;
    flex-direction: column; /* Contenido vertical */
    align-items: center;
    padding: 30px;
    background: linear-gradient(145deg, #fdfdfd, #f1f3f6); /* Degradado muy sutil */
    border: 1px solid #e9ecef;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-basis: 250px; /* Ancho base de cada tarjeta */
}
.selection-card-premium:hover {
    transform: translateY(-10px) scale(1.02);
    border-color: #cceeff;
    box-shadow: 0 12px 30px rgba(2, 177, 244, 0.15); /* Sombra de color azul */
}
.selection-card-premium .card-icon {
    font-size: 32px;
    color: #fff;
    margin-bottom: 20px;
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, #02b1f4, #00c2ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(2, 177, 244, 0.3);
}
.selection-card-premium .card-text {
    text-align: center;
}
.selection-card-premium .card-text h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    border: none;
    padding: 0;
}
.selection-card-premium .card-text p {
    margin: 0;
    font-size: 14px;
    color: #777;
    line-height: 1.5;
}
/* La flecha ahora está oculta y aparece al pasar el mouse */
.selection-card-premium .card-arrow {
    opacity: 0;
    font-size: 16px;
    color: #02b1f4;
    margin-top: 20px;
    transition: opacity 0.3s, margin-top 0.3s;
}
.selection-card-premium:hover .card-arrow {
    opacity: 1;
    margin-top: 25px; /* Se desliza hacia abajo */
}

/* --- CORRECCIÓN DE COLOR PARA LA LISTA DE INFORMES EN LA MODAL --- */
#historial-informes-container .note-header {
    color: #555; /* Color oscuro para el título de la nota */
}

#historial-informes-container .note-content {
    color: #333; /* Color oscuro para el contenido de la nota */
}

#historial-informes-container p {
    color: #777; /* Color para el mensaje "No hay informes" */
}




/* --- ESTILOS PREMIUM v2 PARA LA MODAL DE VER INFORMES --- */

/* 1. Ajustamos el tamaño y fondo de la modal */
#modal-ver-informes .modal-content-premium {
    max-width: 950px !important; /* Hacemos la ventana un poco más ancha */
    height: 70%; /* La altura se ajustará al contenido */
    max-height: 85vh; /* Pero con un máximo para que no se salga de la pantalla */
}

/* 2. Refinamos el panel informativo izquierdo */
#modal-ver-informes .modal-info-panel {
    background: linear-gradient(160deg, #6f42c1, #5a32a3); /* Gradiente morado */
}

/* 3. Rediseñamos el panel derecho (lista de informes) */
#modal-ver-informes .modal-form-panel {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#modal-ver-informes #historial-informes-container {
    flex-grow: 1;
    overflow-y: auto; /* Scroll si hay muchos informes */
    padding-right: 10px; /* Espacio para el scroll */
    margin-right: -10px; /* Compensa el padding para alinear */
}

/* 4. Nuevo diseño para cada tarjeta de informe en la lista */
.informe-list-item {
    display: flex;
    align-items: center;
    padding: 0px 31px; /* 15px arriba/abajo (altura), 20px izquierda/derecha (anchura) */
    border-radius: 12px;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    margin-bottom: 15px;
    transition: box-shadow 0.3s, border-color 0.3s, transform 0.3s;
}

.informe-list-item:hover {
    transform: scale(1.00);
    border-color: #dee2e6;
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

.informe-list-item .item-icon {
    font-size: 22px;
    color: #6f42c1; /* Morado, consistente con el panel informativo */
    margin-right: 20px;
    width: 50px;
    height: 50px;
    background-color: #f3f0f7;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.informe-list-item .item-info {
    flex-grow: 1;
}

.informe-list-item .item-info h4 {
    margin: 15px 0 -20px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.informe-list-item .item-info p {
    margin: 0;
    font-size: 14px;
    color: #777;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 300px;
}

.informe-list-item .item-actions .btn-view-details {
    background-color: #02b1f4;
    color: white;
    border: none;
    padding: 8px 18px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 13px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.informe-list-item .item-actions .btn-view-details:hover {
    background-color: #028ac7;
}

/* Estilo para el mensaje "No hay informes" */
#historial-informes-container p {
    text-align: center;
    padding: 40px;
    color: #999;
    font-style: italic;
}





/* Hacemos la ventana más ancha */
#modal-informe-detalle .modal-content-premium-header {
    max-width: 1200px !important; /* <-- ESTA ES LA LÍNEA QUE CONTROLA EL ANCHO */

}
/* --- ESTILO ESPECÍFICO PARA EL FONDO DEL MODAL DE DETALLES DE INFORME --- */
#modal-informe-detalle.modal-overlay {
    background-color: rgba(0, 0, 0, 0.1) !important; /* <-- Fondo oscuro al 10% de opacidad */
}

/* --- ESTILO ELEGANTE PARA EL BOTÓN DE BORRAR HISTORIA EN LA MODAL --- */
.btn-delete-historia {
    display: inline-flex; /* <-- Usa Flexbox para alinear */
    align-items: center;  /* <-- Centra verticalmente el icono y el texto */
    
    padding: 8px 15px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
    border: 2px solid #dc3545;
    background-color: transparent;
    color: #dc3545 !important;
}

.btn-delete-historia:hover {
    background-color: #dc3545;
    color: white !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.btn-delete-historia i {
    margin-right: 8px; /* Un poco más de espacio entre el icono y el texto */
}

/* --- ESTILO PARA EL COMBO BOX (SELECT) EN MODALES PREMIUM --- */
.modal-body-premium .form-group select {
    width: 100%;
    padding: 12px;
    font-size: 15px;
    font-family: "Poppins", sans-serif;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: #ffffffff;
    transition: all 0.3s ease;
    box-sizing: border-box;
    -webkit-appearance: none; /* Quita el estilo por defecto del navegador */
    -moz-appearance: none;
    appearance: none;
    /* Añade una flecha personalizada */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23888' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 16px;
    cursor: pointer;
}

.modal-body-premium .form-group select:focus {
    outline: none;
    border-color: #02b1f4;
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.15);
}

/* --- ESTILOS PARA EL CAMPO DE CÉDULA COMPUESTO --- */
.cedula-input-group {
    display: flex;
    align-items: center;
}
.cedula-input-group select {
    width: 60px; /* Ancho fijo para el selector de tipo */
    padding: 12px;
    border: 0.5px solid #ccc;
    border-radius: 8px 0 0 8px; /* Bordes redondeados solo a la izquierda */
    border-right: none; /* Quitamos el borde derecho para que se fusione */
    font-size: 14px;
    background-color: #f8f9fa;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    text-align: center;
    cursor: pointer;
    height: 48px; /* Misma altura que los otros campos */
}
.cedula-input-group input {
    flex-grow: 1; /* Ocupa el resto del espacio */
    border-radius: 0 8px 8px 0 !important; /* Bordes redondeados solo a la derecha */
    padding-left: 15px !important; /* Reseteamos el padding para el icono que ya no está */
}
.cedula-input-group input:focus {
    position: relative;
    z-index: 2;
}

/* --- ESTILO ELEGANTE PARA EL BOTÓN DE ELIMINAR HERMANO --- */
.remove-hermano-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background-color: transparent;
    color: #adb5bd; /* Un gris sutil */
    border: none;
    border-radius: 50%;
    width: 0px;
    height: 20px;
    font-size: 14px;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.remove-hermano-btn:hover {
    background-color: #f8d7da; /* Fondo rojo claro al pasar el mouse */
    color: #dc3545; /* Icono rojo */
}



/* --- ESTILOS PARA ÍCONOS DENTRO DE CAMPOS DE FORMULARIO --- */
.modal-body-premium .input-wrapper {
    position: relative;
}

/* Estilo para los íconos */
.modal-body-premium .input-wrapper i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
    transition: color 0.3s;
}

/* Estilo para los íconos en campos de texto de una línea */
.modal-body-premium .form-group input + i,
.modal-body-premium .form-group select + i {
    top: 50%;
    transform: translateY(-50%);
}

/* Estilo para los íconos en campos de texto de varias líneas (textarea) */
.modal-body-premium .form-group textarea + i {
    top: 15px; /* Alinea el icono con la primera línea de texto */
    transform: translateY(0);
}

/* Añade espacio a la izquierda en los campos para que el texto no se superponga con el icono */
.modal-body-premium .input-wrapper input,
.modal-body-premium .input-wrapper select,
.modal-body-premium .input-wrapper textarea {
    padding-left: 45px !important;
}

/* Cambia el color del icono cuando el campo está activo */
.modal-body-premium .form-group input:focus ~ i,
.modal-body-premium .form-group textarea:focus ~ i,
.modal-body-premium .form-group select:focus ~ i {
    color: #02b1f4;
}

/* --- ESTILOS PARA LOS BOTONES DEL ENCABEZADO DE LA MODAL --- */
.modal-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Estilo para el nuevo botón de Imprimir */
.btn-print-informe {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
    border: 2px solid #6c757d; /* Borde gris */
    background-color: transparent;
    color: #6c757d !important;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
}
/* Estilo para el nuevo botón de Imprimir (Color Azul Oscuro) */
.btn-print-informe {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
    border: 2px solid #dc3545; /* Borde rojo oscuro */
    background-color: transparent;
    color: #dc3545 !important; /* Texto rojo oscuro */
    cursor: pointer;
    font-family: "Poppins", sans-serif;
}
.btn-print-informe:hover {
    background-color: #dc3545; /* Se rellena de azul oscuro */
    color: white !important; /* El texto se vuelve blanco */
}
.btn-print-informe i {
    margin-right: 8px;
}

/* --- ESTILOS PARA LA IMPRESIÓN --- */
@media print {
    /* Oculta todo lo que no sea la modal del informe */
    body > *:not(#modal-informe-detalle) {
        display: none !important;
    }
    
    /* Asegura que la modal ocupe toda la página de impresión */
    #modal-informe-detalle, #modal-informe-detalle .modal-overlay {
        position: static !important;
        display: block !important;
        background: none !important;
    }
    
    #modal-informe-detalle .modal-content-premium-header {
        box-shadow: none !important;
        border: 1px solid #ccc;
        max-width: 100% !important;
        height: auto !important;
        max-height: none !important;
    }
    
    /* Oculta los botones en la versión impresa */
    #modal-informe-detalle .modal-header-premium .modal-close-btn,
    #modal-informe-detalle .modal-header-premium .modal-header-actions {
        display: none !important;
    }
    
    /* Asegura que todo el contenido del informe sea visible */
    #modal-informe-detalle .modal-body-premium {
        overflow: visible !important;
        max-height: none !important;
    }
}

/* --- ESTILOS PARA DETALLES EN LA MISMA LÍNEA EN LA LISTA DE CITAS --- */
.appointment-list .appointment-details {
    display: flex; /* Pone los elementos en una fila */
    align-items: baseline; /* Alinea los textos por su base */
    flex-grow: 1;
    gap: 10px; /* Espacio entre los elementos */
    white-space: nowrap; /* Evita que el texto se divida en varias líneas */
    overflow: hidden; /* Oculta el texto que se desborde */
    text-overflow: ellipsis; /* Añade "..." al final si el texto es muy largo */
}

.appointment-list .patient-name {
    font-weight: 500;
    font-size: 14px;
    color: #333;
    flex-shrink: 0; /* Evita que el nombre se encoja */
}

.appointment-list .patient-cedula {
    font-size: 13px;
    color: #777;
    flex-shrink: 0;
}

.appointment-list .appointment-motive {
    font-size: 13px;
    color: #555;
    font-style: italic;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* --- ESTILOS PREMIUM PARA LA LISTA DE SOLICITUDES (SECRETARIA) --- */
.solicitudes-list {
    margin-top: 20px;
}
.solicitud-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    margin-bottom: 15px;
}
.solicitud-info h4 { margin: 0 0 5px 0; font-size: 16px; }
.solicitud-info p { margin: 0; font-size: 14px; color: #555; }
.solicitud-info p strong { color: #333; }

/* Estilo para la caja de texto del motivo en la modal */
.info-text-box {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 12px;
    border-radius: 8px;
    font-size: 15px;
    min-height: 80px;
}

/* --- ESTILOS PARA DETALLES ADICIONALES EN TARJETA DE SOLICITUD --- */
.solicitud-details {
    display: flex;
    flex-wrap: wrap; /* Para que se adapte en pantallas pequeñas */
    gap: 10px 20px; /* Espacio vertical y horizontal */
    font-size: 13px;
    color: #555;
    margin: 8px 0;
    padding-left: 5px;
}
.solicitud-motivo {
    margin-top: 10px;
    padding-left: 5px;
}

/* --- ESTILO PARA MOSTRAR INFORMACIÓN EN LÍNEA (LABEL: VALOR) --- */
.form-group.inline-info {
    display: flex; /* Pone los elementos en una fila */
    align-items: baseline; /* Alinea los textos por su base */
    gap: 10px; /* Espacio entre la etiqueta y el valor */
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.form-group.inline-info label {
    margin-bottom: 0; /* Quita el margen inferior del label */
    flex-shrink: 0; /* Evita que la etiqueta se encoja */
    color: #555;
    font-weight: 500;
}
.form-group.inline-info span {
    font-weight: 600;
    color: #02b1f4;
    font-size: 16px;
}

/* --- ESTA ES LA REGLA CLAVE CORREGIDA --- */
#modal-asignar-cita .input-wrapper {
    position: relative;
}
#modal-asignar-cita .input-wrapper i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
    pointer-events: none; /* Para que el clic llegue al select */
}
#modal-asignar-cita .input-wrapper select,
#modal-asignar-cita .input-wrapper input {
    width: 100%;
    padding: 12px 15px 12px 45px; /* Padding izquierdo para el icono */
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 14px;
    font-family: "Poppins", sans-serif;
    box-sizing: border-box;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23888' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 16px;
    cursor: pointer;
}

/* --- ESTILOS PARA AJUSTAR LA MODAL DE DETALLES DE CITA --- */

/* --- ESTILOS PARA AJUSTAR LA MODAL DE DETALLES DE CITA --- */
#modal-detalle-cita-paciente .modal-content-premium-header {
    /* Para ajustar el ANCHO de la ventana */
    max-width: 900px !important; /* <-- Cambia este valor (ej: 800px para más ancha) */

    /* Para ajustar la ALTURA máxima de la ventana */
    max-height: 92vh; /* <-- Cambia este valor (ej: 70vh para más baja) */
}

/* 2. Para cambiar el COLOR del encabezado */
#modal-detalle-cita-paciente .modal-header-premium {
    background: linear-gradient(160deg, #6f42c1, #ffffffff); /* <-- CAMBIA ESTE COLOR (ej: morado) */
}

/* --- ESTILOS PARA UNA BARRA DE DESPLAZAMIENTO ELEGANTE EN LA MODAL DE DETALLES --- */

/* Selector específico para el cuerpo de la modal de detalles de cita */
#modal-detalle-cita-paciente .modal-body-premium {
    scrollbar-width: thin; /* Para Firefox */
    scrollbar-color: #02b1f4 #f0f2f5; /* Color del pulgar y del riel para Firefox */
}

/* Para navegadores WebKit (Chrome, Safari, Edge) */
#modal-detalle-cita-paciente .modal-body-premium::-webkit-scrollbar {
    width: 10px; /* Ancho de la barra */
}

#modal-detalle-cita-paciente .modal-body-premium::-webkit-scrollbar-track {
    background: #f0f2f5; /* Color del riel, un gris muy claro */
    border-radius: 10px;
}

#modal-detalle-cita-paciente .modal-body-premium::-webkit-scrollbar-thumb {
    background-color: #02b1f4; /* Color azul principal */
    border-radius: 10px; /* Bordes redondeados */
    border: 2px solid #f0f2f5; /* Un pequeño borde para que parezca flotar */
}

#modal-detalle-cita-paciente .modal-body-premium::-webkit-scrollbar-thumb:hover {
    background-color: #028ac7; /* Azul más oscuro al pasar el mouse */
}

/* --- ESTILOS PARA LA PROPUESTA EN LA MODAL DE DETALLES (BOTONES CORREGIDOS) --- */
.reprogramacion-info {
    margin-top: 15px;
    padding: 20px;
    background-color: #fff3cd; /* Amarillo claro */
    border-left: 5px solid #ffc107;
    border-radius: 8px;
}
.reprogramacion-info h4 {
    margin-top: 0;
    color: #856404;
    border-bottom: none;
    padding-bottom: 0;
}
.reprogramacion-info p {
    margin: 5px 0;
    border: none;
    padding: 0;
}

/* Contenedor de botones centrado */
.modal-actions-propuesta {
    display: flex;
    justify-content: center; /* <-- CAMBIADO para centrar */
    gap: 60px;
    margin-top: 20px;
    margin-bottom: 30px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

/* Estilo base para los botones de contorno */
.modal-actions-propuesta .btn-submit,
.modal-actions-propuesta .btn-secondary {
    padding: 9px 25px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    text-align: center;
    background-color: transparent;
    border: 2px solid; /* Borde que tomará el color */
    min-width: 262px; /* <-- LÍNEA AÑADIDA PARA AJUSTAR EL ANCHO */
}

/* Botón Aceptar (Verde) */
.modal-actions-propuesta .btn-submit {
    border-color: #28a745;
    color: #28a745 !important;
}
.modal-actions-propuesta .btn-submit:hover {
    background-color: #28a745;
    color: white !important;
}

/* Botón Rechazar (Rojo) */
.modal-actions-propuesta .btn-secondary {
    border-color: #dc3545;
    color: #dc3545 !important;
}
.modal-actions-propuesta .btn-secondary:hover {
    background-color: #dc3545;
    color: white !important;
}

/* --- ESTILOS MINIMALISTAS Y ELEGANTES PARA LISTA DE PROFESIONALES --- */
.professionals-list {
    margin-top: 30px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.professional-list-item {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 20px;
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    text-align: left;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: "Poppins", sans-serif;
}
.professional-list-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-color: #dee2e6;
}
.item-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    flex-shrink: 0;
    color: white;
    font-size: 20px;
}
/* Colores de avatar por rol (con gradiente) */
.item-avatar.psicologo { 
    background: linear-gradient(45deg, #02b1f4, #0082b3); 
}
.item-avatar.psiquiatra { 
    background: linear-gradient(45deg, #17a2b8, #107586); 
}

.item-info {
    flex-grow: 1;
}
.item-info h3 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
    border: none; padding: 0;
}
.item-info p {
    margin: 0;
    font-size: 14px;
    color: #777;
}
.item-stat {
    font-size: 14px;
    font-weight: 500;
    color: #555;
    background-color: #f8f9fa;
    padding: 8px 12px;
    border-radius: 8px;
    margin: 0 20px;
    flex-shrink: 0;
}
.item-stat i {
    color: #999;
}
.item-action {
    font-size: 14px;
    font-weight: 500;
    color: #02b1f4;
    opacity: 0; /* Oculto por defecto */
    transform: translateX(-10px); /* Ligeramente a la izquierda */
    transition: all 0.3s ease;
}
.professional-list-item:hover .item-action {
    opacity: 1; /* Aparece al pasar el mouse */
    transform: translateX(0);
}

/* --- ESTILOS PREMIUM PARA LA MODAL DE DETALLES DE PROFESIONAL --- */
#modal-profesional-detalle .modal-content-premium-header {
    width: 100%;
    max-width: 750px; /* <-- ESTA ES LA LÍNEA QUE PUEDES AJUSTAR */
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
    animation: fadeIn 0.4s ease-out;
}

#modal-profesional-detalle .modal-header-premium {
    background: linear-gradient(160deg, #02b1f4, #d6e8efff);
    color: white;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

#modal-profesional-detalle .header-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

#modal-profesional-detalle .modal-header-premium i {
    font-size: 24px;
    opacity: 0.8;
}

#modal-profesional-detalle .modal-header-premium h2 {
    margin: 0;
    font-size: 20px;
    border: none;
    padding: 0;
    color: white;
}

#modal-profesional-detalle .modal-header-premium p {
    margin: 2px 0 0 0;
    font-size: 14px;
    opacity: 0.9;
}

#modal-profesional-detalle .modal-body-premium {
    padding: 30px;
    overflow-y: auto;
}

#modal-profesional-detalle .dato-item {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid #f7f7f7;
}

#modal-profesional-detalle .dato-item strong {
    font-weight: 500;
    color: #555;
}

#modal-profesional-detalle .dato-item p {
    margin: 0;
    padding: 0;
    border: none;
    line-height: 1.6;
}

/* --- AJUSTE DE MARGEN SUPERIOR PARA LA SECCIÓN 'SOLICITAR CITA' --- */
#vista-solicitar .panel-seccion {
    padding-top: 50px; /* <-- CAMBIA ESTE VALOR */
}

/* --- ESTILO PARA EL ENCABEZADO DE LA MODAL DE CREAR INFORME --- */
#modal-crear-informe .modal-header-premium {
    background: linear-gradient(160deg, #17a2b8, #d9f1f6ff) !important; /* <-- CAMBIA ESTE COLOR */
}

/* --- ESTILOS PREMIUM PARA EL DASHBOARD DEL PACIENTE --- */
.patient-dashboard-grid {
    display: grid;
    /* Reducimos el tamaño mínimo de cada tarjeta para que quepan 4 en una fila */
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
    gap: 25px;
}
.dashboard-card {
    background-color: #ffffff;
    padding: 7px 25px; /* 15px arriba/abajo (altura), 25px izquierda/derecha (ancho) */
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}
.dashboard-card .card-icon {
    font-size: 22px;
    color: #02b1f4;
    margin-bottom: 4px;
    margin-top: 4px
}
.dashboard-card h4 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 500;
    color: #555;
}
.dashboard-card .main-data {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin: 0;
}
.dashboard-card .sub-data {
    font-size: 14px;
    color: #777;
    margin-top: 5px;
}
.dashboard-card.primary-card {
    background: linear-gradient(135deg, #02b1f4, #0082b3);
    color: white;
}
.dashboard-card.primary-card .card-icon,
.dashboard-card.primary-card h4,
.dashboard-card.primary-card .main-data,
.dashboard-card.primary-card .sub-data {
    color: white;
}
.dashboard-card.primary-card .sub-data {
    opacity: 0.8;
}
.dashboard-card.action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    cursor: pointer;
    background-color: #e9f7fe;
    border-style: dashed;
    transition: all 0.3s ease;
}
.dashboard-card.action-card:hover {
    border-color: #02b1f4;
    background-color: #d1ecf1;
}
.dashboard-card.action-card i {
    font-size: 28px;
    color: #02b1f4;
    margin-bottom: 15px;
}
.dashboard-card.action-card h3 {
    margin: 0;
    font-size: 18px;
    color: #028ac7;
}

/* --- ESTILO PARA ASEGURAR ALTURA FIJA DEL GRÁFICO DEL PACIENTE --- */
#vista-paciente-dashboard .chart-container {
    position: relative;
    height: 135px !important; /* <-- Altura fija deseada */
    width: 100%;
}

/* Regla adicional para que el lienzo del gráfico ocupe todo el contenedor */
#vista-paciente-dashboard .chart-container canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100% !important;
    height: 100% !important;
}

/* --- AJUSTE DE ALTURA PARA LA TABLA DE MIS PACIENTES --- */
#vista-pacientes .approvals-table th,
#vista-pacientes .approvals-table td {
    padding: 15px; /* <-- Restaura el padding original o ajústalo a tu gusto */
    vertical-align: middle; /* Asegura que el texto esté centrado verticalmente */
}

/* --- ESTILOS PARA EL ACORDEÓN DE PREGUNTAS FRECUENTES --- */
.faq-container {
    margin-top: 30px;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}
.faq-item {
    border-bottom: 1px solid #e9ecef;
}
.faq-item:last-child {
    border-bottom: none;
}
.faq-question {
    width: 100%;
    background-color: #ffffff;
    border: none;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    text-align: left;
    font-family: "Poppins", sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}
.faq-question:hover {
    background-color: #f8f9fa;
}
.faq-question i {
    transition: transform 0.3s ease;
}
.faq-question.active i {
    transform: rotate(180deg);
}
.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease-out, padding 0.4s ease-out;
    background-color: #f8f9fa;
}
.faq-answer p {
    padding: 0 20px 20px 20px;
    margin: 0;
    line-height: 1.7;
    color: #555;
}

/* --- ESTILOS PREMIUM PARA LA SECCIÓN DE AYUDA (CORREGIDO) --- */
.ayuda-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 40px;
    margin-top: 30px;
    align-items: start;
}
.ayuda-form-container h4, .ayuda-info-container h4 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-top: 0;
    margin-bottom: 20px;
}

/* --- AJUSTE DE ALINEACIÓN PARA LA COLUMNA DE INFORMACIÓN --- */
.ayuda-info-container > h4 {
    /* Mueve el título "Información de Contacto" (y todo lo de abajo) hacia arriba */
    margin-top: -99px !important; 
}

/* 1. Estilos para los campos del formulario (fondo blanco) */
.ayuda-form-container .form-group input,
.ayuda-form-container .form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 15px;
    font-family: "Poppins", sans-serif;
    background-color: white; /* <-- Fondo blanco */
    transition: all 0.3s ease;
    box-sizing: border-box;
}
.ayuda-form-container .form-group input:focus,
.ayuda-form-container .form-group textarea:focus {
    outline: none;
    border-color: #02b1f4;
    box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.15);
}

/* 2. Ajuste de margen superior para el campo de Mensaje */
.ayuda-form-container .form-group.message-group {
    margin-top: 15px;
}

/* 3. Estilo para el botón de Enviar (contorno azul) */
.ayuda-form-container .btn-submit {
    width: 100%;
    padding: 15px;
    margin-top: 30px;
    font-size: 16px;
    font-weight: 530;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: transparent;
    border: 2px solid #02b1f4;
    color: #02b1f4;
}
.ayuda-form-container .btn-submit:hover {
    background: #02b1f4;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
}

/* --- ESTILOS CORREGIDOS PARA LA INFORMACIÓN DE CONTACTO --- */
.contact-info-panel p {
    display: flex;
    align-items: flex-start; /* Alinea los items al inicio para textos largos */
    margin-bottom: 25px; /* Aumenta el espacio entre cada línea */
    font-size: 15px;
    line-height: 1.6; /* Mejora la legibilidad si el texto ocupa varias líneas */
}

.contact-info-panel p:last-child {
    margin-bottom: 0; /* Quita el margen extra del último elemento */
}

.contact-info-panel i {
    color: #02b1f4;
    margin-right: 15px;
    width: 20px;
    text-align: center;
    margin-top: 3px; /* Ajuste fino para alinear el icono con la primera línea de texto */
}

.contact-info-panel p strong {
    margin-right: 10px; /* Añade un espacio después de los dos puntos */
}
.emergency-info {
    margin-top: 30px;
    padding: 16px;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    color: #721c24;
}
.emergency-info h4 {
    color: #721c24;
}
.emergency-info i {
    margin-right: 10px;
}

/* --- AJUSTE DE ANCHO PARA COLUMNAS DE LA TABLA 'MIS CITAS' --- */
#vista-miscitas .approvals-table th:nth-child(1) { width: 25%; } /* Columna Fecha */
#vista-miscitas .approvals-table th:nth-child(2) { width: 20%; } /* Columna Profesional */
#vista-miscitas .approvals-table th:nth-child(3) { width: 20%; } /* Columna Especialidad */
#vista-miscitas .approvals-table th:nth-child(4) { width: 15%; } /* Columna Estado */
#vista-miscitas .approvals-table th:nth-child(5) { width: 20%; text-align: center; } /* Columna Acciones */

/* Centra el botón de la columna de Acciones */
#vista-miscitas .approvals-table td:nth-child(5) {
    text-align: center;
}


/* --- ESTILOS PARA LA MODAL DE ÉXITO --- */
.modal-icon.success-icon {
    font-size: 50px;
    color: #28a745; /* Verde de éxito */
    margin-bottom: 15px;
}
.temp-password-box {
    background-color: #e9ecef;
    border: 1px dashed #ced4da;
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
}
.temp-password-box span {
    font-family: 'Courier New', Courier, monospace;
    font-size: 20px;
    font-weight: 600;
    color: #333;
    letter-spacing: 2px;
}

/* --- ESTILOS PARA EL CAMPO DE TELÉFONO COMPUESTO (SIN FLECHA) --- */
.phone-input-group {
    display: flex;
    align-items: center;
}
.phone-input-group select, 
.phone-input-group input {
    height: 48px;
    border: 1px solid #ced4da;
    background-color: #f8f9fa;
    font-family: "Poppins", sans-serif;
    font-size: 15px;
    transition: all 0.3s ease;
    box-sizing: border-box;
}
.phone-input-group select:focus, 
.phone-input-group input:focus {
    position: relative;
    z-index: 2;
    outline: none;
    border-color: #02b1f4;
    box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.15);
}

/* --- ESTA ES LA REGLA CLAVE CORREGIDA --- */
/* Estilo para AMBOS selectores (Móvil/Fijo y Código de País) */
.phone-input-group select {
    -webkit-appearance: none; /* Quita la apariencia por defecto en Chrome/Safari */
    -moz-appearance: none;    /* Quita la apariencia por defecto en Firefox */
    appearance: none;         /* Quita la apariencia por defecto estándar */
    background-image: none !important;   /* Elimina cualquier flecha personalizada */
    cursor: pointer;
    padding: 0 15px;          /* Padding horizontal uniforme */
    text-align: center;       /* Centramos el texto para un look limpio */
}

/* Selector de Tipo (Móvil/Fijo) */
.phone-input-group select:first-child {
    width: 100px;
    border-radius: 8px 0 0 8px;
    border-right: none;
}

/* Selector de Código de País */
.phone-input-group select:nth-child(2) {
    width: 120px;
    border-radius: 0;
    border-right: none;
}

/* Campo del Número */
.phone-input-group input {
    flex-grow: 1;
    border-radius: 0 8px 8px 0;
    padding-left: 15px;
}

/* --- ESTILOS PARA LA FILA CLICABLE EN LA TABLA DE SOLICITUDES --- */
#vista-citas .approvals-table tbody tr {
    cursor: pointer; /* Muestra el cursor de "mano" al pasar el mouse */
}

/* --- AJUSTE DE ANCHO PARA LA MODAL DE DETALLES DE SOLICITUD --- */
#modal-solicitud-detalle .modal-content-premium-header {
    max-width: 850px !important; /* <-- CAMBIA ESTE VALOR PARA AJUSTAR EL ANCHO */
}

/* --- ESTILOS PARA LA FILA CLICABLE EN LA TABLA DE PRÓXIMAS CITAS --- */
#vista-proximas-citas .approvals-table tbody tr {
    cursor: pointer; /* Muestra el cursor de "mano" al pasar el mouse */
}

/* --- ESTILOS PREMIUM PARA LA LISTA DE PRÓXIMAS CITAS --- */
.appointments-list-premium {
    margin-top: 30px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.appointment-card-pro {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr; /* 3 columnas: Info, Fecha, Acciones */
    align-items: center;
    padding: 20px 25px;
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    border-left: 5px solid #c5efffff; /* Borde azul distintivo */
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
}
.appointment-card-pro:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.patient-info-pro h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}
.patient-info-pro span {
    display: block;
    font-size: 14px;
    color: #777;
    margin-top: 4px;
}
.patient-info-pro span i {
    margin-right: 8px;
    color: #aaa;
}
.date-info-pro p {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 500;
    color: #333;
}
.date-info-pro p i {
    margin-right: 8px;
    color: #9bddf8ff;
}
.actions-pro {
    text-align: right;
}

/* --- ESTILOS PARA LA TARJETA DE CITA CLICABLE --- */
.appointment-card-pro {
    cursor: pointer; /* Muestra el cursor de "mano" al pasar el mouse */
}

/* --- AJUSTE DE MARGEN SUPERIOR PARA EL MENSAJE "SIN CITAS" --- */
#vista-proximas-citas .panel-seccion p:only-child {
    margin-top: 10px; /* <-- CAMBIA ESTE VALOR PARA AJUSTAR EL ESPACIO */
    text-align: left;
    font-style: italic;
    color: #777;
}


/* --- AJUSTE DE ALTURA PARA LA TABLA DE SOLICITUDES DE CITA --- */
#vista-citas .approvals-table th,
#vista-citas .approvals-table td {
    padding: 15px 15px; /* <-- CAMBIA ESTE VALOR (12px es para la altura) */
}

/* --- ESTILOS PREMIUM PARA LA MODAL DE CONFLICTO DE HORARIO --- */

/* Contenedor principal de la modal */
#modal-conflicto-cita .modal-content {
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    border-top: 5px solid #ffc107; /* Borde superior amarillo de advertencia */
}

/* Icono de advertencia */
#modal-conflicto-cita .modal-icon {
    font-size: 48px;
    color: #ffc107;
    margin-bottom: 20px;
}

/* Título y texto */
#modal-conflicto-cita h3 {
    margin: 0 0 10px 0;
    font-size: 22px;
    color: #333;
    border: none;
    padding: 0;
}
#modal-conflicto-cita p {
    color: #555;
    line-height: 1.7;
    margin-bottom: 30px;
}

/* Contenedor de los botones */
#modal-conflicto-cita .modal-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
}

/* Estilo para los botones (Cancel y Proponer) */
#modal-conflicto-cita .btn-secondary,
#modal-conflicto-cita .btn-submit {
    padding: 10px 25px;
    font-size: 15px;
    font-weight: 500;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid;
    background-color: transparent;
    text-decoration: none;
}

/* Botón "Cancelar" (Gris) */
#modal-conflicto-cita .btn-secondary {
    border-color: #6c757d;
    color: #6c757d !important;
}
#modal-conflicto-cita .btn-secondary:hover {
    background-color: #6c757d;
    color: white !important;
}

/* Botón "Proponer Nueva Fecha" (Amarillo) */
#modal-conflicto-cita .btn-submit {
    border-color: #ffc107;
    color: #ffc107 !important;
}
#modal-conflicto-cita .btn-submit:hover {
    background-color: #ffc107;
    color: #333 !important; /* Texto oscuro para contraste */
}

/* --- ESTILOS PARA LA FILA CLICABLE EN LA TABLA DE HISTORIAL --- */
#tabla-historial-container .clickable-row {
    cursor: pointer !important; /* Muestra el cursor de "mano" al pasar el mouse */
    transition: background-color 0.2s !important;
}
#tabla-historial-container .clickable-row:hover {
    background-color: #f8f9fa !important; /* Color de fondo sutil al pasar el mouse */
}

/* --- ESTILOS PREMIUM PARA LA MODAL DE DETALLES DEL HISTORIAL --- */
#modal-historial-detalle .modal-content-premium-header {
    max-width: 800px; /* Ancho de la modal */
}

/* Encabezado de la modal */
#modal-historial-detalle .modal-header-premium {
    background: linear-gradient(160deg, #6c757d, #495057); /* Gradiente gris oscuro */
    color: white;
}

/* Cuerpo de la modal */
#modal-historial-detalle .modal-body-premium {
    padding: 20px 30px 30px 30px;
}

/* Rejilla para detalles */
#modal-historial-detalle .detalle-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px 25px;
    margin-bottom: 20px;
}

/* Estilo unificado para cada item y para la línea divisoria */
#modal-historial-detalle .detalle-item,
#modal-historial-detalle .detalle-item-full,
#modal-historial-detalle .modal-actions {
    padding-top: 15px;
    margin-top: 0px;
    border-top: 1px solid #f0f0f0;
}

/* El primer elemento NUNCA tendrá una línea arriba */
#modal-historial-detalle .modal-body-premium > *:first-child {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
}

#modal-historial-detalle .detalle-item-full {
    grid-column: 1 / -1;
}

#modal-historial-detalle .detalle-item strong,
#modal-historial-detalle .detalle-item-full strong {
    font-size: 14px;
    font-weight: 500;
    color: #555;
    display: block;
    margin-bottom: 5px;
}
#modal-historial-detalle .detalle-item span,
#modal-historial-detalle .detalle-item-full p {
    font-size: 15px;
    color: #333;
    margin: 0;
}

/* Acciones de la modal (botón) */
#modal-historial-detalle .modal-actions {
    text-align: center;
}

/* --- ESTILO ELEGANTE PARA EL BOTÓN DE LA MODAL DE HISTORIAL --- */
#modal-historial-detalle .modal-actions .btn-submit {
    padding: 10px 25px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    
    /* --- Diseño de Contorno --- */
    background-color: transparent;
    border: 2px solid #28a745; /* Borde verde */
    color: #28a745 !important; /* Texto verde */
}

#modal-historial-detalle .modal-actions .btn-submit:hover {
    background-color: #28a745; /* Se rellena de verde */
    color: white !important;   /* El texto se vuelve blanco */
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
}

/* --- ESTILOS PREMIUM PARA TARJETAS DE CREACIÓN DE USUARIO (TAMAÑO AJUSTADO) --- */
.user-creation-grid {
    display: grid;
    /* Reducimos el tamaño mínimo de cada tarjeta a 240px */
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
    gap: 25px;
    margin-top: 30px;
}
.creation-card {
    display: flex;
    flex-direction: column;
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px 30px; /* 15px arriba/abajo (altura), 25px izquierda/derecha (ancho) */
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
}
.creation-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    border-color: #dee2e6;
}
.creation-card .card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px auto;
    color: white;
    font-size: 24px;
}
.creation-card .card-icon.psicologo-icon { background: linear-gradient(45deg, #02b1f4, #0082b3); }
.creation-card .card-icon.psiquiatra-icon { background: linear-gradient(45deg, #17a2b8, #107586); }
.creation-card .card-icon.secretaria-icon { background: linear-gradient(45deg, #6f42c1, #5a32a3); }
.creation-card .card-icon.paciente-icon { background: linear-gradient(45deg, #28a745, #218838); }

.creation-card h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    border: none;
    padding: 0;
}
.creation-card p {
    margin: 0;
    font-size: 14px;
    color: #777;
    line-height: 1.6;
    flex-grow: 1; /* Empuja el botón hacia abajo */
}
.creation-card .card-action {
    margin-top: 20px;
    font-weight: 500;
    color: #02b1f4;
    text-decoration: none;
    opacity: 0; /* Oculto por defecto */
    transform: translateY(10px);
    transition: all 0.3s ease;
}
.creation-card:hover .card-action {
    opacity: 1;
    transform: translateY(0);
}

/* --- ESTILOS PREMIUM PARA TARJETAS DE GESTIÓN DE CONTENIDO --- */
.content-management-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 25px;
    margin-top: 30px;
}
.content-card {
    display: flex;
    flex-direction: column;
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px 30px; /* 15px arriba/abajo (altura), 25px izquierda/derecha (ancho) */
    text-align: left;
    text-decoration: none;
    transition: all 0.3s ease;
}
.content-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    border-color: #dee2e6;
}
.content-card .content-card-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    color: white;
    font-size: 22px;
}
.content-card h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    border: none;
    padding: 0;
}
.content-card p {
    margin: 0;
    font-size: 14px;
    color: #777;
    line-height: 1.6;
    flex-grow: 1; /* Empuja el botón hacia abajo */
}
.content-card .card-action {
    margin-top: 20px;
    font-weight: 500;
    color: #02b1f4;
    text-decoration: none;
    opacity: 0; /* Oculto por defecto */
    transform: translateY(10px);
    transition: all 0.3s ease;
}
.content-card:hover .card-action {
    opacity: 1;
    transform: translateY(0);
}

/* --- AJUSTE DE MÁRGENES SUPERIORES PARA TÍTULOS (ADMINISTRADOR) --- */

/* Para el título "Añadir Nuevo Usuario al Sistema" */
#vista-admin-personal .creation-panel h2 {
    margin-top: -3px; /* Ajusta este valor (ej: 0, 10px, etc.) */
}

/* Para los títulos de las listas de personal ("Psicólogos Activos", etc.) */
#vista-admin-personal .panel-seccion h2 {
    margin-top: -3px; /* Ajusta este valor */
}

/* Para el título "Gestión de Contenido Web" */
#vista-admin-contenido .panel-seccion h2 {
    margin-top: -3px; /* Ajusta este valor */
}

/* --- ESTILOS PREMIUM PARA LA SECCIÓN 'MI PERFIL' --- */
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr; /* Dos columnas */
    gap: 30px;
    margin-top: 30px;
}

.profile-card {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 30px;
}

.profile-card h4 {
    margin-top: 0;
    margin-bottom: 25px;
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.profile-card .form-group {
    margin-bottom: 20px;
}

.profile-card .form-group label {
    display: flex;
    align-items: center;
    font-size: 14px;
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
}

.profile-card .form-group i {
    margin-right: 10px;
    color: #02b1f4;
    width: 20px;
    text-align: center;
}

.profile-card .form-group input {
    width: 100%;
    padding: 10px !important;
    font-size: 15px;
    font-family: "Poppins", sans-serif;
    border: 1px solid #ced4da;
    border-radius: 8px;
    box-sizing: border-box;
}

.profile-card .form-group small {
    font-size: 12px;
    color: #777;
    margin-top: 5px;
}

.profile-card .btn-submit {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
    font-size: 15px;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(45deg, #02b1f4, #00c2ff);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.profile-card .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3);
}

/* --- ESTILO PARA CAMPOS DE SOLO LECTURA --- */
.profile-card .form-group input[readonly] {
    background-color: #e9ecef; /* Un gris claro */
    cursor: not-allowed;
    opacity: 0.8;
}

/* --- AJUSTE DE ALTURA ESPECÍFICO PARA CAMPOS DE CONTRASEÑA --- */
#nueva_contrasena,
#confirmar_nueva_contrasena {
    padding-top: 9px !important;    /* Reduce el relleno superior */
    padding-bottom: 9px !important; /* Reduce el relleno inferior */
}

/* --- LÍNEA DIVISORIA ELEGANTE PARA LA SECCIÓN DE PERFIL --- */
#vista-perfil .profile-grid {
    padding-bottom: 0px; /* Espacio entre las tarjetas y la línea */
    margin-bottom: 40px;  /* Espacio debajo de la línea */
    border-bottom: 1px solid #e9ecef; /* La línea divisoria sutil */
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
        <i class="fa-solid fa-chart-line"></i> Panel de Control
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('admin-personal', event)">
    <i class="fa-solid fa-users-cog"></i> <span>Añadir Personal</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('admin-contenido', event)"><i class="fa-solid fa-file-pen"></i> <span>Editar Contenido</span></a>
    <a href="#" class="nav-link" onclick="mostrarVista('perfil', event)">
        <i class="fa-solid fa-user-gear"></i> <span>Mi Perfil Personal</span>
    </a>
    
<?php endif; ?>
                <?php if ($rol_usuario == 'psicologo' || $rol_usuario == 'psiquiatra'): ?>
    <a href="#" id="nav-dashboard" class="nav-link active" onclick="mostrarVista('dashboard', event)">
        <i class="fa-solid fa-chart-line"></i> <span>Panel de Control</span>
    </a>
    <a href="#" id="nav-pacientes" class="nav-link" onclick="mostrarVista('pacientes', event)">
        <i class="fa-solid fa-users"></i> <span>Pacientes Clinicos</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('proximas-citas', event)">
        <i class="fa-solid fa-hourglass-half"></i> <span> Mis Próximas Citas</span>
    </a>
    
    <a href="#" id="nav-citas" class="nav-link" onclick="mostrarVista('citas', event)">
        <i class="fa-solid fa-inbox"></i> <span>Solicitudes de Cita</span>
    </a>
    
    
    <a href="#" class="nav-link" onclick="mostrarVista('historial-citas', event)">
        <i class="fa-solid fa-clipboard-list"></i> <span>Historial de Citas</span>
    </a>

    
    <a href="#" class="nav-link" onclick="mostrarVista('notas-sesion', event)">
    <i class="fa-solid fa-notes-medical"></i> <span>Notas de Sesión</span>
    </a>
    
    <a href="#" id="nav-agenda" class="nav-link" onclick="mostrarVista('agenda', event)">
        <i class="fa-solid fa-calendar-days"></i> <span>Agenda Personal</span>
    </a>
    
    <a href="#" class="nav-link" onclick="mostrarVista('perfil', event)">
        <i class="fa-solid fa-user-gear"></i> <span> Mi Perfil Personal</span>
    </a>
    
    
    
<?php endif; ?>
                
                <?php if ($rol_usuario == 'paciente'): ?>
    <a href="#" class="nav-link active" onclick="mostrarVista('paciente-dashboard', event)">
        <i class="fa-solid fa-house"></i> <span>Panel de Control</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('miscitas', event)">
        <i class="fa-solid fa-calendar-check"></i> <span>Total de Mis Citas</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('solicitar', event)">
        <i class="fa-solid fa-file-circle-plus"></i> <span>Solicitar nueva cita</span>
    </a>
    <!-- ENLACE MODIFICADO -->
    <a href="#" class="nav-link" onclick="mostrarVista('psicologos', event)">
        <i class="fa-solid fa-user-doctor"></i> <span>Psicólogos Activos</span>
    </a>
    <!-- ENLACE NUEVO -->
    <a href="#" class="nav-link" onclick="mostrarVista('psiquiatras', event)">
        <i class="fa-solid fa-brain"></i> <span>Psiquiatras Activos</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('faq', event)">
        <i class="fa-solid fa-circle-question"></i> <span>Preguntas Sobre:</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('ayuda', event)">
        <i class="fa-solid fa-circle-question"></i> <span>Centro de Ayuda</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('perfil', event)">
        <i class="fa-solid fa-user-gear"></i> <span>Mi Perfil personal</span>
    </a>
<?php endif; ?>

                <!-- ENLACES PARA SECRETARIA -->
<?php if ($rol_usuario == 'secretaria'): ?>
    <a href="#" class="nav-link active" onclick="mostrarVista('solicitudes-generales', event)">
        <i class="fa-solid fa-inbox"></i> <span>Solicitudes de Citas</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('historial-citas-general', event)">
        <i class="fa-solid fa-clipboard-list"></i> <span>Historial de Citas del Personal</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('agenda-general', event)">
        <i class="fa-solid fa-calendar-week"></i> <span>Agenda de personal</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('gestion-pacientes', event)">
        <i class="fa-solid fa-address-book"></i> <span>Gestión de Pacientes</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('directorio', event)">
        <i class="fa-solid fa-user-doctor"></i> <span>Directorio Profesional</span>
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
        <h2>Mi Perfil</h2>
        <p>Este es tu espacio personal. En la sección de la izquierda puedes revisar tu información de contacto. En la sección de la derecha, puedes actualizar tu contraseña para mantener tu cuenta segura. Te recomendamos usar una combinación de letras, números y símbolos.</p>

        <?php
        if (isset($_GET['status']) && $_GET['status'] == 'perfil_actualizado') {
            echo '<div class="alert-box success"><span><strong>¡Éxito!</strong> Tu contraseña ha sido actualizada.</span></div>';
        }
        if (isset($_GET['error'])) {
            $error_msg = 'Ocurrió un error. Inténtalo de nuevo.';
            if ($_GET['error'] == 'pass_no_coincide') {
                $error_msg = 'La nueva contraseña y su confirmación no coinciden.';
            } elseif ($_GET['error'] == 'pass_no_segura') {
                $error_msg = 'La nueva contraseña no cumple con los requisitos de seguridad.';
            }
            echo '<div class="alert-box error"><span><strong>Error:</strong> ' . htmlspecialchars($error_msg) . '</span></div>';
        }
        ?>

        <div class="profile-grid">
            <div class="profile-card">
                <h4>Información de Contacto</h4>
                <div class="form-group">
                    <label for="nombre_completo_perfil"><i class="fa-solid fa-user"></i> Nombre Completo:</label>
                    <input type="text" id="nombre_completo_perfil" value="<?php echo htmlspecialchars($nombre_usuario); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="correo_perfil"><i class="fa-solid fa-envelope"></i> Correo Electrónico:</label>
                    <input type="email" id="correo_perfil" value="<?php echo htmlspecialchars($_SESSION['correo']); ?>" readonly>
                </div>
            </div>

            <div class="profile-card">
                <form action="actualizar_perfil.php" method="POST" id="form-cambiar-pass">
                    <input type="hidden" name="accion" value="cambiar_contrasena">
                    <h4>Cambiar Contraseña</h4>
                    <div class="form-group">
                        <label for="nueva_contrasena"><i class="fa-solid fa-lock"></i> Nueva Contraseña:</label>
                        <input type="password" name="nueva_contrasena" id="nueva_contrasena" required 
                               minlength="8" 
                               pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}" 
                               title="Mínimo 8 caracteres, una mayúscula y un símbolo.">
                        <small></small>
                    </div>
                    <div class="form-group">
                        <label for="confirmar_nueva_contrasena"><i class="fa-solid fa-lock"></i> Confirmar Nueva Contraseña:</label>
                        <input type="password" name="confirmar_nueva_contrasena" id="confirmar_nueva_contrasena" required>
                    </div>
                    <button type="submit" class="btn-submit">Actualizar Contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>

            <?php if ($rol_usuario == 'administrador'): ?>

                <!-- VISTA 3: GESTIÓN DE CONTENIDO WEB (AHORA EN SU LUGAR CORRECTO) -->
    <div id="vista-admin-contenido" class="panel-vista">
    <div class="panel-seccion">
        <h2>Gestión de Contenido Web</h2>
        
        <div class="content-management-grid">
            <a href="gestionar_terapias.php" class="content-card">
                <div class="content-card-icon" style="background-color: #02b1f4;"><i class="fa-solid fa-hand-holding-heart"></i></div>
                <h3>Gestionar Terapias</h3>
                <p>Añade, edita o elimina las terapias.</p>
                <span class="card-action">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="gestionar_farmacos.php" class="content-card">
                <div class="content-card-icon" style="background-color: #17a2b8;"><i class="fa-solid fa-pills"></i></div>
                <h3>Gestionar Fármacos</h3>
                <p>Administra la lista de fármacos.</p>
                <span class="card-action">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="gestionar_faq.php" class="content-card">
                <div class="content-card-icon" style="background-color: #6f42c1;"><i class="fa-solid fa-circle-question"></i></div>
                <h3>Gestionar Preguntas</h3>
                <p>Edita la sección de Preguntas Frecuentes.</p>
                <span class="card-action">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="gestionar_textos.php" class="content-card">
                <div class="content-card-icon" style="background-color: #28a745;"><i class="fa-solid fa-file-lines"></i></div>
                <h3>Editar "Nosotros"</h3>
                <p>Modifica los textos de Misión, Visión y Valores.</p>
                <span class="card-action">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
            </a>
        </div>
    </div>

        <!-- NUEVA SECCIÓN: ACCESOS DIRECTOS DE CONTENIDO -->
<div class="panel-seccion shortcut-panel">
    <h2>Accesos Directos al Contenido</h2>
    <ul class="shortcut-list">
        <li class="shortcut-item">
            <div class="shortcut-info">
                <i class="fa-solid fa-file-lines"></i>
                <span>Sección "Nosotros"</span>
            </div>
            <div class="shortcut-actions">
                <a href="index.php#nosotros" target="_blank" class="btn-view">Ver Página</a>
                <a href="gestionar_textos.php" class="btn-manage">Gestionar</a>
            </div>
        </li>
        <li class="shortcut-item">
            <div class="shortcut-info">
                <i class="fa-solid fa-hand-holding-heart"></i>
                <span>Terapias</span>
            </div>
            <div class="shortcut-actions">
                <a href="terapias.php" target="_blank" class="btn-view">Ver Página</a>
                <a href="gestionar_terapias.php" class="btn-manage">Gestionar</a>
            </div>
        </li>
        <li class="shortcut-item">
            <div class="shortcut-info">
                <i class="fa-solid fa-pills"></i>
                <span>Fármacos</span>
            </div>
            <div class="shortcut-actions">
                <a href="farmacologia.php" target="_blank" class="btn-view">Ver Página</a>
                <a href="gestionar_farmacos.php" class="btn-manage">Gestionar</a>
            </div>
        </li>
        <li class="shortcut-item">
            <div class="shortcut-info">
                <i class="fa-solid fa-circle-question"></i>
                <span>Preguntas Frecuentes</span>
            </div>
            <div class="shortcut-actions">
                <a href="index.php#faq" target="_blank" class="btn-view">Ver Página</a> <!-- Asumiendo que tendrás una sección #faq en el index -->
                <a href="gestionar_faq.php" class="btn-manage">Gestionar</a>
            </div>
        </li>
    </ul>
</div>
    </div>

    <!-- VISTA 1: DASHBOARD (Contiene las tarjetas Y los gráficos) -->
    <div id="vista-admin-dashboard" class="panel-vista active">
        
        <!-- Fila de Tarjetas de Estadísticas -->
        <div class="stats-grid">

            <!-- 1. Usuarios Totales -->
            <a href="ver_usuarios.php?filtro=aprobados" class="stat-card-link">
                <div class="stat-card">
                    <div class="icon"><i class="fa-solid fa-users"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['aprobados']; ?></div>
                        <div class="label">Usuarios Totales</div>
                    </div>
                </div>
            </a>

            <!-- 2. Pacientes Activos -->
            <a href="ver_usuarios.php?filtro=pacientes" class="stat-card-link">
                <div class="stat-card">
                    <div class="icon"><i class="fa-solid fa-hospital-user"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['pacientes_activos']; ?></div>
                        <div class="label">Pacientes Activos</div>
                    </div>
                </div>
            </a>

            <!-- 3. Personal Activo -->
            <a href="ver_usuarios.php?filtro=personal" class="stat-card-link">
                <div class="stat-card">
                    <div class="icon"><i class="fa-solid fa-user-tie"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['personal']; ?></div>
                        <div class="label">Personal Activo</div>
                    </div>
                </div>
            </a>

            <!-- 4. Citas Registradas -->
            <a href="ver_citas_admin.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['total_citas']; ?></div>
                        <div class="label">Citas Registradas</div>
                    </div>
                </div>
            </a>

        <!-- Cierre de la Fila de Tarjetas de Estadísticas -->
        </div>

        <!-- Rejilla de Gráficos -->
    <div class="dashboard-widgets-grid-full">
        <!-- Fila Superior -->
        <div class="panel-seccion">
            <h3>Distribución de Pacientes</h3>
            <div class="chart-container"><canvas id="patientAgeChart"></canvas></div>
        </div>
        <div class="panel-seccion">
            <h3>Carga de Trabajo por Profesional</h3>
            <div class="chart-container"><canvas id="workloadChart"></canvas></div>
        </div>
        
        <!-- Fila Inferior -->
        <div class="panel-seccion">
            <h3>Crecimiento de Usuarios</h3>
            <div class="chart-container"><canvas id="userGrowthChart"></canvas></div>
        </div>
        <div class="panel-seccion">
            <h3>Primeras Consultas vs. Seguimiento</h3>
            <div class="chart-container"><canvas id="appointmentTypesChart"></canvas></div>
        </div>
            <div class="panel-seccion">
            <h3>Citas Completadas esta semana. Ultimos 7 dias</h3>
            <div class="chart-container"><canvas id="dailyAppointmentsChart"></canvas></div>
        </div>
        <div class="panel-seccion">
            <h3>Citas Confirmadas vs. Reprogramadas</h3>
            <div class="chart-container"><canvas id="confirmedReprogrammedChart"></canvas></div>
        </div>
    </div>

    </div> <!-- <-- El div del dashboard ahora se cierra aquí, después de los gráficos -->

    <!-- VISTA 2: AÑADIR PERSONAL (DISEÑO MEJORADO Y CORREGIDO) -->
<div id="vista-admin-personal" class="panel-vista">
    <div class="panel-seccion">
        <h2>Añadir Nuevo Usuario al Sistema</h2>
        
        <div class="user-creation-grid">
            <!-- Tarjeta para Registrar Psicólogo -->
            <a href="crear_usuario_admin.php?rol=psicologo" class="creation-card">
                <div class="card-icon psicologo-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <h3>Registrar Psicólogo</h3>
                <p>Crear una cuenta para un nuevo terapeuta.</p>
                <span class="card-action">Crear Perfil <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <!-- Tarjeta para Registrar Psiquiatra -->
            <a href="crear_usuario_admin.php?rol=psiquiatra" class="creation-card">
                <div class="card-icon psiquiatra-icon"><i class="fa-solid fa-brain"></i></div>
                <h3>Registrar Psiquiatra</h3>
                <p>Crear una cuenta para un médico especialista.</p>
                <span class="card-action">Crear Perfil <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <!-- Tarjeta para Registrar Secretaria -->
            <a href="crear_usuario_admin.php?rol=secretaria" class="creation-card">
                <div class="card-icon secretaria-icon"><i class="fa-solid fa-clipboard-user"></i></div>
                <h3>Registrar Secretaria</h3>
                <p>Crear una cuenta para asistente administrativo.</p>
                <span class="card-action">Crear Perfil <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <!-- Tarjeta para Registrar Paciente -->
            <a href="crear_paciente.php" class="creation-card">
                <div class="card-icon paciente-icon"><i class="fa-solid fa-user-plus"></i></div>
                <h3>Registrar Paciente</h3>
                <p>Crear una cuenta para un nuevo paciente.</p>
                <span class="card-action">Crear Perfil <i class="fa-solid fa-arrow-right"></i></span>
            </a>
        </div>
    </div>


    <!-- Sección para la lista de Psicólogos -->
    <div class="panel-seccion">
        <h2><i class="fa-solid fa-user-doctor"></i> Psicólogos Activos</h2>
        <div class="personal-grid">
            <?php
            $consulta_psicologos = $conex->query("SELECT id, nombre_completo, correo FROM usuarios WHERE rol = 'psicologo' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
            if ($consulta_psicologos->num_rows > 0) {
                while($profesional = $consulta_psicologos->fetch_assoc()) {
                    echo '<a href="ver_perfil_personal.php?id=' . $profesional['id'] . '" class="personal-card-link">';
                    echo '  <div class="personal-card psicologo">';
                    echo '      <h3>' . htmlspecialchars($profesional['nombre_completo']) . '</h3>';
                    echo '      <p><i class="fa-solid fa-envelope"></i> ' . htmlspecialchars($profesional['correo']) . '</p>';
                    echo '  </div>';
                    echo '</a>';
                }
            } else {
                echo "<p>No hay psicólogos registrados.</p>";
            }
            ?>
        </div>
    </div>

    <!-- Sección para la lista de Psiquiatras -->
    <div class="panel-seccion">
        <h2><i class="fa-solid fa-brain"></i> Psiquiatras Activos</h2>
        <div class="personal-grid">
            <?php
            $consulta_psiquiatras = $conex->query("SELECT id, nombre_completo, correo FROM usuarios WHERE rol = 'psiquiatra' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
            if ($consulta_psiquiatras->num_rows > 0) {
                while($profesional = $consulta_psiquiatras->fetch_assoc()) {
                    echo '<a href="ver_perfil_personal.php?id=' . $profesional['id'] . '" class="personal-card-link">';
                    echo '  <div class="personal-card psiquiatra">';
                    echo '      <h3>' . htmlspecialchars($profesional['nombre_completo']) . '</h3>';
                    echo '      <p><i class="fa-solid fa-envelope"></i> ' . htmlspecialchars($profesional['correo']) . '</p>';
                    echo '  </div>';
                    echo '</a>';
                }
            } else {
                echo "<p>No hay psiquiatras registrados.</p>";
            }
            ?>
        </div>
    </div>

    <!-- Sección para la lista de Secretarias -->
    <div class="panel-seccion">
        <h2><i class="fa-solid fa-user-tie"></i> Secretarias Activas</h2>
        <div class="personal-grid">
             <?php
            $consulta_secretarias = $conex->query("SELECT id, nombre_completo, correo FROM usuarios WHERE rol = 'secretaria' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
            if ($consulta_secretarias->num_rows > 0) {
                while($profesional = $consulta_secretarias->fetch_assoc()) {
                    echo '<a href="ver_perfil_personal.php?id=' . $profesional['id'] . '" class="personal-card-link">';
                    echo '  <div class="personal-card secretaria">';
                    echo '      <h3>' . htmlspecialchars($profesional['nombre_completo']) . '</h3>';
                    echo '      <p><i class="fa-solid fa-envelope"></i> ' . htmlspecialchars($profesional['correo']) . '</p>';
                    echo '  </div>';
                    echo '</a>';
                }
            } else {
                echo "<p>No hay secretarias registradas.</p>";
            }
            ?>
        </div>
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
                        <div class="appointment-details">
                            <span class="patient-name"><?php echo htmlspecialchars($cita['nombre_completo']); ?></span>
                            <span class="patient-cedula">(C.I: <?php echo htmlspecialchars($cita['cedula']); ?>)</span>
                            <span class="appointment-motive">- <?php echo htmlspecialchars(substr($cita['motivo_consulta'], 0, 50)) . '...'; ?></span>
                        </div>
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

    <!-- VISTA 2: MIS PACIENTES (CORREGIDA) -->
<div id="vista-pacientes" class="panel-vista">
    <div class="panel-seccion">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Mis Pacientes</h2>

         <!-- BOTON PARA AÑADIR PACIENTE -->
            <button id="btn-abrir-modal-paciente" class="btn-outline-primary">
             <i class="fa-solid fa-plus"></i> Añadir Paciente
            </button>
        </div>
        
        <!-- BARRA DE BÚSQUEDA -->
        <div class="search-container">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="buscador-pacientes" placeholder="Buscar paciente por nombre o cédula...">
        </div>

        <!-- 👇 TEXTO DESCRIPTIVO EN SU LUGAR CORRECTO 👇 -->
        <p style="margin-top: 15px; margin-bottom: 20px; color: #777; font-size: 16px;">
            Aquí aparecen los pacientes que has añadido manualmente o aquellos a los que les has confirmado una cita.
        </p>

        <!-- El contenido de esta tabla se cargará aquí con JavaScript -->
        <div id="tabla-pacientes-container">
            <p>Cargando pacientes...</p>
        </div>
    </div>
</div>

    <div id="vista-citas" class="panel-vista">
        <div class="panel-seccion">
            <h2>Solicitudes de Cita Pendientes</h2>
             <?php
            // Consulta para obtener solicitudes pendientes
            $consulta_pendientes = $conex->prepare("
                SELECT c.id, c.motivo_consulta, c.fecha_cita, u.nombre_completo as paciente_nombre 
                FROM citas c 
                JOIN usuarios u ON c.paciente_id = u.id 
                WHERE c.estado = 'pendiente' AND c.psicologo_id = ?
                ORDER BY c.fecha_solicitud ASC
            ");
            $consulta_pendientes->bind_param("i", $usuario_id);
            $consulta_pendientes->execute();
            $resultado_pendientes_citas = $consulta_pendientes->get_result();

            if ($resultado_pendientes_citas->num_rows > 0) {
                // Añadimos la columna "Motivo" a la cabecera
                echo "<table class='approvals-table'><thead><tr><th>Paciente</th><th>Motivo</th><th>Fecha Propuesta</th><th>Acciones</th></tr></thead><tbody>";
                while($solicitud = $resultado_pendientes_citas->fetch_assoc()) {
                    $nombre_paciente_js = htmlspecialchars($solicitud['paciente_nombre'], ENT_QUOTES);
                    echo "<tr data-cita-id='" . $solicitud['id'] . "'>";
                    echo "<td>" . htmlspecialchars($solicitud['paciente_nombre']) . "</td>";
                    echo "<td>" . htmlspecialchars(substr($solicitud['motivo_consulta'], 0, 54)) . '...' . "</td>";
                    echo "<td>" . htmlspecialchars(date('d/m/Y h:i A', strtotime($solicitud['fecha_cita']))) . "</td>";
                    echo "<td class='action-links'>
                            <!-- BOTÓN MODIFICADO -->
                            <button class='approve' onclick='intentarConfirmarCita(" . $solicitud['id'] . ")'>Confirmar</button>
                            <button class='btn-secondary' onclick='abrirModalProponerFecha(" . $solicitud['id'] . ", \"" . $nombre_paciente_js . "\")'>Posponer</button>
                          </td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
            } else { 
                echo "<p>No tienes solicitudes de cita pendientes.</p>"; 
            }
            $consulta_pendientes->close();
            ?>
        </div>
    </div>
    
    <div id="vista-agenda" class="panel-vista">
        <div class="panel-seccion">
            <h2>Mi Agenda de Citas</h2>
            <div id="calendario"></div>
        </div>
    </div>

    <!-- VISTA 5: HISTORIAL DE CITAS (PSICÓLOGO) - CORREGIDO PARA CARGA DINÁMICA -->
<div id="vista-historial-citas" class="panel-vista">
    <div class="panel-seccion">
        <h2>Historial de Todas Mis Citas</h2>
        <p>Aquí puedes ver un registro completo de tus citas. Haz clic en una fila para ver los detalles.</p>

        <!-- Barra de búsqueda para el historial -->
        <div class="search-container">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="buscador-historial-citas" placeholder="Buscar por paciente o cédula...">
        </div>

        <!-- Contenedor donde se cargará la tabla con JavaScript -->
        <div id="tabla-historial-citas-container">
            <!-- La tabla aparecerá aquí -->
        </div>
    </div>
</div>


<!-- VISTA DE PRÓXIMAS CITAS (DISEÑO PREMIUM) -->
<div id="vista-proximas-citas" class="panel-vista">
    <div class="panel-seccion">
        <h2>Próximas Citas Confirmadas</h2>
        <p>Esta es la lista de todas tus citas programadas para el futuro, ordenadas por fecha.</p>
        
        <div class="appointments-list-premium">
            <?php
            $psicologo_id_proximas = $_SESSION['usuario_id'];
            $consulta_proximas_stmt = $conex->prepare(
                "SELECT c.id, c.fecha_cita, u.id as paciente_id, u.nombre_completo as paciente_nombre, u.cedula as paciente_cedula, u.correo as paciente_correo
                 FROM citas c 
                 JOIN usuarios u ON c.paciente_id = u.id 
                 WHERE c.psicologo_id = ? AND c.estado IN ('confirmada', 'reprogramada') AND c.fecha_cita >= NOW()
                 ORDER BY c.fecha_cita ASC"
            );
            $consulta_proximas_stmt->bind_param("i", $psicologo_id_proximas);
            $consulta_proximas_stmt->execute();
            $resultado_proximas = $consulta_proximas_stmt->get_result();
            
            if ($resultado_proximas->num_rows > 0) {
                while($cita = $resultado_proximas->fetch_assoc()) {
                    $nombre_paciente_js = htmlspecialchars($cita['paciente_nombre'], ENT_QUOTES);
                    
                    // --- TARJETA MODIFICADA ---
                    // Añadimos un atributo 'data-cita-id' a la tarjeta para identificarla
                    echo '<div class="appointment-card-pro" data-cita-id="' . $cita['id'] . '">';
                    echo '  <div class="patient-info-pro">';
                    echo '      <h4>' . htmlspecialchars($cita['paciente_nombre']) . '</h4>';
                    echo '      <span><i class="fa-solid fa-id-card"></i> C.I: ' . htmlspecialchars($cita['paciente_cedula']) . '</span>';
                    echo '      <span><i class="fa-solid fa-envelope"></i> ' . htmlspecialchars($cita['paciente_correo']) . '</span>';
                    echo '  </div>';
                    echo '  <div class="date-info-pro">';
                    echo '      <p><i class="fa-solid fa-calendar-day"></i> ' . htmlspecialchars(date('d/m/Y', strtotime($cita['fecha_cita']))) . '</p>';
                    echo '      <p><i class="fa-solid fa-clock"></i> ' . htmlspecialchars(date('h:i A', strtotime($cita['fecha_cita']))) . '</p>';
                    echo '  </div>';
                    echo '  <div class="actions-pro action-links">';
                    echo "      <button class='approve' onclick='abrirModalGestionarPaciente(" . $cita['paciente_id'] . ")'>Gestionar</button>";
                    echo "      <button class='btn-secondary' onclick='abrirModalReprogramarCita(" . $cita['id'] . ", \"" . $nombre_paciente_js . "\")'>Reprogramar</button>";
                    echo '  </div>';
                    echo '</div>';
                }
            } else {
                echo "<p>No tienes citas programadas en el futuro.</p>";
            }
            $consulta_proximas_stmt->close();
            ?>
        </div>
    </div>
</div>

<!-- VISTA 6: NOTAS DE SESIÓN (CON TABLA MEJORADA) -->
<div id="vista-notas-sesion" class="panel-vista">
    <div class="panel-seccion">
        <h2>Notas de Sesión</h2>
        <p>Selecciona un paciente para ver su historial de notas de evolución o para añadir una nueva.</p>
        
        <div id="tabla-notas-container">
            <?php
            // Consulta actualizada para incluir correo y fecha de registro
            $psicologo_id_notas = $_SESSION['usuario_id'];
            $sql_pacientes_notas = "SELECT DISTINCT u.id, u.nombre_completo, u.cedula, u.correo, u.fecha_registro 
                                    FROM usuarios u
                                    LEFT JOIN citas c ON u.id = c.paciente_id
                                    WHERE u.rol = 'paciente' AND u.estado = 'aprobado'
                                    AND (u.creado_por_psicologo_id = ? OR c.psicologo_id = ?)";
            $stmt_pacientes_notas = $conex->prepare($sql_pacientes_notas);
            $stmt_pacientes_notas->bind_param("ii", $psicologo_id_notas, $psicologo_id_notas);
            $stmt_pacientes_notas->execute();
            $pacientes_notas = $stmt_pacientes_notas->get_result();

            if ($pacientes_notas->num_rows > 0):
                // Cabecera de la tabla con las nuevas columnas ordenables
                echo "<table class='approvals-table'>
                        <thead>
                            <tr>
                                <th class='sortable-header'>Paciente</th>
                                <th class='sortable-header'>Cédula</th>
                                <th class='sortable-header'>Correo</th>
                                <th class='sortable-header'>Fecha de Ingreso</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>";
                while($paciente = $pacientes_notas->fetch_assoc()):
                    echo "<tr>
                            <td>" . htmlspecialchars($paciente['nombre_completo']) . "</td>
                            <td>" . htmlspecialchars($paciente['cedula']) . "</td>
                            <td>" . htmlspecialchars($paciente['correo']) . "</td>
                            <td>" . htmlspecialchars(date('d/m/Y', strtotime($paciente['fecha_registro']))) . "</td>
                            <td class='action-links'>
                                <button class='approve' onclick='abrirModalGestionarNotas(" . $paciente['id'] . ")'>Ver/Añadir Notas</button>
                            </td>
                          </tr>";
                endwhile;
                echo "</tbody></table>";
            else:
                echo "<p>No tienes pacientes asignados.</p>";
            endif;
            $stmt_pacientes_notas->close();
            ?>
        </div>
    </div>
</div>

<?php endif; ?>


    <?php if ($rol_usuario == 'paciente'): ?>

    <?php
    // --- LÓGICA PARA EL DASHBOARD DEL PACIENTE ---
    $paciente_id = $_SESSION['usuario_id'];
    $dashboard_data = [
        'proxima_cita' => null,
        'profesional_principal' => 'No asignado',
        'citas_totales' => 0
    ];

    // 1. Buscar la próxima cita confirmada
    $stmt_proxima = $conex->prepare("
        SELECT c.fecha_cita, u.nombre_completo as profesional_nombre
        FROM citas c
        JOIN usuarios u ON c.psicologo_id = u.id
        WHERE c.paciente_id = ? AND c.estado IN ('confirmada', 'reprogramada') AND c.fecha_cita >= NOW()
        ORDER BY c.fecha_cita ASC
        LIMIT 1
    ");
    $stmt_proxima->bind_param("i", $paciente_id);
    $stmt_proxima->execute();
    $result_proxima = $stmt_proxima->get_result();
    if ($result_proxima->num_rows > 0) {
        $dashboard_data['proxima_cita'] = $result_proxima->fetch_assoc();
    }
    $stmt_proxima->close();

    // 2. Contar citas totales (completadas)
    $stmt_totales = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE paciente_id = ? AND estado = 'completada'");
    $stmt_totales->bind_param("i", $paciente_id);
    $stmt_totales->execute();
    $dashboard_data['citas_totales'] = $stmt_totales->get_result()->fetch_assoc()['total'];
    $stmt_totales->close();

    // 3. Encontrar al profesional principal (con más citas)
    $stmt_profesional = $conex->prepare("
        SELECT u.nombre_completo
        FROM citas c
        JOIN usuarios u ON c.psicologo_id = u.id
        WHERE c.paciente_id = ?
        GROUP BY c.psicologo_id
        ORDER BY COUNT(c.id) DESC
        LIMIT 1
    ");
    $stmt_profesional->bind_param("i", $paciente_id);
    $stmt_profesional->execute();
    $result_profesional = $stmt_profesional->get_result();
    if ($result_profesional->num_rows > 0) {
        $dashboard_data['profesional_principal'] = $result_profesional->fetch_assoc()['nombre_completo'];
    }
    $stmt_profesional->close();
    
    // --- (El resto del código del panel del paciente continúa aquí) ---
    ?>

    <!-- VISTA 0: DASHBOARD DEL PACIENTE -->
    <div id="vista-paciente-dashboard" class="panel-vista active">
        <div class="panel-seccion">
            <h2>Bienvenido/a, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?></h2>
            <p>Este es tu espacio personal. Aquí tienes un resumen de tu actividad.</p>
        </div>

        <div class="patient-dashboard-grid">
            <!-- Tarjeta de Próxima Cita (con Ícono) -->
            <div class="dashboard-card primary-card">
                <div class="card-icon"><i class="fa-solid fa-calendar-star"></i></div>
                <h4>Próxima Cita</h4>
                <?php if ($dashboard_data['proxima_cita']): ?>
                    <p class="main-data"><?php echo date('d \d\e F, Y', strtotime($dashboard_data['proxima_cita']['fecha_cita'])); ?></p>
                    <p class="sub-data"><?php echo date('h:i A', strtotime($dashboard_data['proxima_cita']['fecha_cita'])); ?> con <?php echo htmlspecialchars($dashboard_data['proxima_cita']['profesional_nombre']); ?></p>
                <?php else: ?>
                    <p class="main-data">Sin citas próximas</p>
                    <p class="sub-data">Puedes solicitar una nueva cuando quieras.</p>
                <?php endif; ?>
            </div>

            <!-- Tarjetas de Información -->
            <div class="dashboard-card">
                <div class="card-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <h4>Profesional Principal</h4>
                <p class="main-data"><?php echo htmlspecialchars($dashboard_data['profesional_principal']); ?></p>
            </div>
            <div class="dashboard-card">
                <div class="card-icon"><i class="fa-solid fa-check-double"></i></div>
                <h4>Citas Completadas</h4>
                <p class="main-data"><?php echo $dashboard_data['citas_totales']; ?></p>
            </div>

            <!-- Tarjeta de Acción -->
            <div class="dashboard-card action-card" onclick="mostrarVista('solicitar', event)">
                <i class="fa-solid fa-plus"></i>
                <h3>Solicitar Nueva Cita</h3>
            </div>
        </div>

        <!-- Widget del Gráfico de Frecuencia de Citas -->
        <div class="panel-seccion" style="margin-top: 25px;">
          <h3>Frecuencia de Citas (Últimos 8 meses)</h3>
           <div class="chart-container" style="height: 250px;">
            <canvas id="patientCitasChart"></canvas>
        </div>
</div>
    </div>

    <!-- VISTA 1: MIS CITAS (DISEÑO CORREGIDO CON BLOQUE INTERNO) -->
<div id="vista-miscitas" class="panel-vista active">
    <div class="panel-seccion">
        
        <h2>Mis Citas</h2>
        <p>Aquí aparecerán todas las citas que tengas programadas.</p>

        <!-- Bloque blanco interno para la tabla y notificaciones -->
        <div class="content-block">
            <?php
            // --- CÓDIGO PARA BUSCAR Y MOSTRAR NOTIFICACIONES ---
            $stmt_notif = $conex->prepare("SELECT id, notificacion_paciente FROM citas WHERE paciente_id = ? AND notificacion_paciente IS NOT NULL");
            $stmt_notif->bind_param("i", $usuario_id);
            $stmt_notif->execute();
            $notificaciones = $stmt_notif->get_result();
            
            if ($notificaciones->num_rows > 0) {
                while ($notif = $notificaciones->fetch_assoc()) {
                    echo '<div class="alert-box info">';
                    echo '  <span><strong><i class="fa-solid fa-bell"></i> Notificación:</strong> ' . $notif['notificacion_paciente'] . '</span>';
                    echo '  <span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span>';
                    echo '</div>';
                    // Una vez mostrada, limpiamos la notificación
                    $stmt_clear = $conex->prepare("UPDATE citas SET notificacion_paciente = NULL WHERE id = ?");
                    $stmt_clear->bind_param("i", $notif['id']);
                    $stmt_clear->execute();
                    $stmt_clear->close();
                }
            }
            $stmt_notif->close();
            ?>

            <?php
            // Consulta actualizada para obtener los campos necesarios para la negociación
            $consulta_citas = $conex->prepare("
                SELECT c.id, c.fecha_cita, c.fecha_propuesta, c.estado, p.nombre_completo as psicologo_nombre, p.rol as profesional_rol
                FROM citas c 
                LEFT JOIN usuarios p ON c.psicologo_id = p.id 
                WHERE c.paciente_id = ? 
                ORDER BY c.fecha_solicitud DESC
            ");
            $consulta_citas->bind_param("i", $usuario_id);
            $consulta_citas->execute();
            $resultado_citas = $consulta_citas->get_result();

            if ($resultado_citas->num_rows > 0) {
                echo "<table class='approvals-table'><thead><tr><th>Fecha Programada</th><th>Profesional</th><th>Especialidad</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>";
                while($cita = $resultado_citas->fetch_assoc()) {
                    // --- LÓGICA AÑADIDA PARA TRADUCIR EL ESTADO ---
                    $estado_actual = $cita['estado'];
                    $texto_estado = ucfirst($estado_actual); // Por defecto: "Pendiente", "Confirmada", etc.
                    
                    if ($estado_actual == 'pendiente_paciente') {
                        $texto_estado = 'Pospuesta'; // Aquí hacemos el cambio de texto
                    }
                    // --- FIN DE LA LÓGICA ---

                    echo "<tr>";
                    
                    // Columna 1: Fecha
                    echo "<td>";
                    if ($cita['estado'] == 'pendiente_paciente' && !empty($cita['fecha_propuesta'])) {
                        echo 'FECHA: <strong>' . htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_propuesta']))) . '</strong>';
                    } elseif (!empty($cita['fecha_cita'])) {
                        echo htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita'])));
                    } else {
                        echo 'Por confirmar';
                    }
                    echo "</td>";

                    // Columna 2: Profesional
                    echo "<td>" . htmlspecialchars($cita['psicologo_nombre'] ?? 'No asignado') . "</td>";

                    // --- ORDEN CORREGIDO ---
                    // Columna 3: Especialidad
                    echo "<td>" . htmlspecialchars(ucfirst($cita['profesional_rol'])) . "</td>";
                    
                    // Columna 4: Estado
                    echo "<td><span class='status-badge status-" . htmlspecialchars($estado_actual) . "'>" . htmlspecialchars($texto_estado) . "</span></td>";
                    
                    // Columna 5: Acciones
                    echo "<td class='action-links'>";
                    echo "<button class='approve' onclick='abrirModalDetalleCitaPaciente(" . $cita['id'] . ")'>Ver Detalles</button>";
                    echo "</td>";

                    echo "</tr>";
                }
                echo "</tbody></table>";
            } else { 
                echo "<p style='text-align: center; padding: 20px; color: #777;'>No tienes ninguna cita solicitada o programada.</p>"; 
            }
            $consulta_citas->close();
            ?>
        </div>
    </div>
</div>



    <!-- VISTA 2: SOLICITAR NUEVA CITA (DISEÑO PREMIUM MULTI-PASO) -->
<div id="vista-solicitar" class="panel-vista">
    <div class="panel-seccion">
                <div class="section-header">
            <h2>Solicitar Nueva Cita</h2>
            <p>Sigue los pasos para encontrar un horario y enviar tu solicitud.</p>
               </div>

        <div class="appointment-form-container">
            <form action="solicitar_cita_directa.php" method="POST" id="form-solicitar-cita">
                
                <!-- PASO 1: TIPO DE CITA -->
                <div class="form-step">
                    <div class="step-header">
                        <span class="step-number">1</span>
                        <label class="step-label">Detalles de la Consulta</label>
                    </div>
                    <div class="form-grid-appointment">
                        <div class="form-group">
                            <label for="tipo_cita">Tipo de Cita</label>
                            <select name="tipo_cita" id="tipo_cita" required>
                                <option value="primera_consulta">Primera Consulta</option>
                                <option value="seguimiento">Seguimiento</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modalidad">Modalidad</label>
                            <select name="modalidad" id="modalidad" required>
                                <option value="presencial">Presencial</option>
                                <option value="virtual">Virtual</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="motivo_principal">Motivo Principal</label>
                            <select name="motivo_principal" id="motivo_principal">
                                <option value="">Selecciona un motivo</option>
                                <option value="Ansiedad">Ansiedad</option>
                                <option value="Depresión">Depresión</option>
                                <option value="Terapia de Pareja">Terapia de Pareja</option>
                                <option value="Evaluación Psicológica">Evaluación Psicológica</option>
                                <option value="Otro">Otro (especificar abajo)</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="motivo_consulta">Describe tu motivo con más detalle</label>
                            <textarea name="motivo_consulta" id="motivo_consulta" rows="3" required placeholder="Ej: Últimamente me he sentido muy ansioso/a en el trabajo y me gustaría hablar de ello."></textarea>                        </div>
                    </div>
                </div>

                <!-- PASO 2: SELECCIÓN DE PROFESIONAL Y HORARIO -->
                <div class="form-step">
                    <div class="step-header">
                        <span class="step-number">2</span>
                        <label class="step-label">Selecciona Profesional y Horario</label>
                    </div>
                    <div class="form-grid-appointment">
                        <div class="form-group">
                            <label for="especialidad_selector">Especialidad Requerida</label>
                            <select id="especialidad_selector" required>
                                <option value="">Elige una especialidad</option>
                                <option value="psicologo">Psicología</option>
                                <option value="psiquiatra">Psiquiatría</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="psicologo_selector">Profesional Disponible</label>
                            <select name="psicologo_id" id="psicologo_selector" required disabled>
                                <option value="">Primero elige especialidad</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="date-picker-group" style="display:none;">
                        <label for="calendario-paciente">Fechas disponibles:</label>
                        <input type="text" id="calendario-paciente" name="fecha_seleccionada" placeholder="Selecciona una fecha..." readonly>
                    </div>
                    <div class="form-group" id="time-slots-group" style="display:none;">
                        <label>Horas disponibles:</label>
                        <div id="time-slots-container" class="time-slots-grid"></div>
                        <input type="hidden" name="hora_seleccionada" id="hora_seleccionada_input">
                    </div>
                </div>

                <!-- PASO 3: PREFERENCIAS -->
                <div class="form-step">
                    <div class="step-header">
                        <span class="step-number">3</span>
                        <label class="step-label">Preferencias Adicionales (Opcional)</label>
                    </div>
                    <div class="form-group">
                        <textarea name="notas_paciente" id="notas_paciente" rows="3" placeholder="Ej: Prefiero sesiones por la tarde, cualquier información relevante..."></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn" id="btn-enviar-solicitud" disabled>Completa los pasos anteriores</button>
            </form>
        </div>
    </div>
</div>

    <!-- VISTA 3: PSICÓLOGOS (DISEÑO MINIMALISTA) -->
    <div id="vista-psicologos" class="panel-vista">
        <div class="panel-seccion">
            <h2>Nuestros Psicólogos</h2>
            <p>Conoce a los profesionales dedicados a la terapia y el acompañamiento emocional.</p>
            <div class="professionals-list">
                <?php
                $psicologos_result = $conex->query("
                    SELECT u.id, u.nombre_completo, u.especialidades, 
                           (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c WHERE c.psicologo_id = u.id AND c.estado IN ('confirmada', 'completada')) as pacientes_atendidos
                    FROM usuarios u 
                    WHERE u.rol = 'psicologo' AND u.estado = 'aprobado'
                ");
                if ($psicologos_result->num_rows > 0) {
                    while($profesional = $psicologos_result->fetch_assoc()) {
                        echo '<button type="button" class="professional-list-item" onclick="abrirModalProfesionalDetalle(' . $profesional['id'] . ')">';
                        echo '  <div class="item-avatar psicologo"><i class="fa-solid fa-user-doctor"></i></div>';
                        echo '  <div class="item-info">';
                        echo '      <h3>' . htmlspecialchars($profesional['nombre_completo']) . '</h3>';
                        echo '      <p>' . htmlspecialchars($profesional['especialidades'] ?? 'Especialidad no definida') . '</p>';
                        echo '  </div>';
                        echo '  <div class="item-stat">';
                        echo '      <i class="fa-solid fa-users"></i> ' . $profesional['pacientes_atendidos'] . ' pacientes';
                        echo '  </div>';
                        echo '  <div class="item-action">Ver Perfil <i class="fa-solid fa-arrow-right"></i></div>';
                        echo '</button>';
                    }
                } else { echo "<p>No hay psicólogos disponibles.</p>"; }
                ?>
            </div>
        </div>
    </div>

    <!-- VISTA 4: PSIQUIATRAS (DISEÑO MINIMALISTA) -->
    <div id="vista-psiquiatras" class="panel-vista">
        <div class="panel-seccion">
            <h2>Nuestros Psiquiatras</h2>
            <p>Conoce a los médicos especialistas en el diagnóstico y tratamiento de trastornos mentales.</p>
            <div class="professionals-list">
                <?php
                 $psiquiatras_result = $conex->query("
                    SELECT u.id, u.nombre_completo, u.especialidades, 
                           (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c WHERE c.psicologo_id = u.id AND c.estado IN ('confirmada', 'completada')) as pacientes_atendidos
                    FROM usuarios u 
                    WHERE u.rol = 'psiquiatra' AND u.estado = 'aprobado'
                ");
                 if ($psiquiatras_result->num_rows > 0) {
                    while($profesional = $psiquiatras_result->fetch_assoc()) {
                        echo '<button type="button" class="professional-list-item" onclick="abrirModalProfesionalDetalle(' . $profesional['id'] . ')">';
                        echo '  <div class="item-avatar psiquiatra"><i class="fa-solid fa-brain"></i></div>';
                        echo '  <div class="item-info">';
                        echo '      <h3>' . htmlspecialchars($profesional['nombre_completo']) . '</h3>';
                        echo '      <p>' . htmlspecialchars($profesional['especialidades'] ?? 'Especialidad no definida') . '</p>';
                        echo '  </div>';
                        echo '  <div class="item-stat">';
                        echo '      <i class="fa-solid fa-users"></i> ' . $profesional['pacientes_atendidos'] . ' pacientes';
                        echo '  </div>';
                        echo '  <div class="item-action">Ver Perfil <i class="fa-solid fa-arrow-right"></i></div>';
                        echo '</button>';
                    }
                } else { echo "<p>No hay psiquiatras disponibles.</p>"; }
                ?>
            </div>
        </div>
    </div>

    <!-- VISTA 4: PSIQUIATRAS (DISEÑO MEJORADO) -->
    <div id="vista-psiquiatras" class="panel-vista">
        <div class="panel-seccion">
            <h2>Nuestros Psiquiatras</h2>
            <p>Conoce a los médicos especialistas en el diagnóstico y tratamiento de trastornos mentales.</p>
            <div class="professionals-grid">
                <?php
                 $psiquiatras_result = $conex->query("
                    SELECT u.id, u.nombre_completo, u.especialidades, 
                           (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c WHERE c.psicologo_id = u.id AND c.estado IN ('confirmada', 'completada')) as pacientes_atendidos
                    FROM usuarios u 
                    WHERE u.rol = 'psiquiatra' AND u.estado = 'aprobado'
                ");
                 if ($psiquiatras_result->num_rows > 0) {
                    while($profesional = $psiquiatras_result->fetch_assoc()) {
                    echo '<button type="button" class="professional-card" onclick="abrirModalProfesionalDetalle(' . $profesional['id'] . ')">';
                    echo '  <div class="card-header-pro" style="background: linear-gradient(45deg, #17a2b8, #bae3eaff);"><i class="fa-solid fa-brain"></i></div>';
                    echo '  <div class="card-body-pro">';
                    echo '      <h3>' . htmlspecialchars($profesional['nombre_completo']) . '</h3>';
                    echo '      <p class="specialties">' . htmlspecialchars($profesional['especialidades'] ?? 'Especialidad no definida') . '</p>';
                    echo '      <div class="pro-stats"><i class="fa-solid fa-users"></i> ' . $profesional['pacientes_atendidos'] . ' pacientes atendidos</div>';
                    echo '  </div>';
                    echo '</button>';
                }
                } else { echo "<p>No hay psiquiatras disponibles.</p>"; }
                ?>
            </div>
        </div>
    </div>

    <!-- MODAL PARA DETALLES DE LA CITA -->
<div id="modal-detalle-cita" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <span class="modal-close" onclick="cerrarModal()">&times;</span>
        <h2>Detalles de la Cita</h2>
        <div id="modal-body">
            <!-- El contenido se cargará aquí con JavaScript -->
        </div>
    </div>
</div>

<!-- VISTA DE AYUDA (DISEÑO MEJORADO Y CORREGIDO) -->
<div id="vista-ayuda" class="panel-vista">
    <div class="panel-seccion">
        <h2>Centro de Ayuda</h2>
        <p>Si tienes alguna duda o problema, aquí tienes cómo contactarnos.</p>

        <div class="ayuda-grid">
            <!-- Columna 1: Formulario de Contacto -->
            <div class="ayuda-form-container">
                <h4>Enviar un Mensaje Directo</h4>
                <form action="enviar_ayuda.php" method="POST">
                    <div class="form-group">
                        <label for="asunto_ayuda">Asunto:</label>
                        <input type="text" name="asunto" id="asunto_ayuda" placeholder="Ej: Duda sobre mi próxima cita" required>
                    </div>
                    <!-- 👇 AÑADE LA CLASE 'message-group' AQUÍ 👇 -->
                    <div class="form-group message-group">
                        <label for="mensaje_ayuda">Mensaje:</label>
                        <textarea name="mensaje" id="mensaje_ayuda" rows="6.9" required placeholder="Escribe aquí tu consulta..."></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Enviar Mensaje</button>
                </form>
            </div>

            <!-- Columna 2: Información de Contacto y Emergencia -->
            <div class="ayuda-info-container">
                <h4>Información de Contacto</h4>
                <div class="contact-info-panel">
                    <p><i class="fa-solid fa-phone"></i> <strong>Teléfono:</strong> +58 123 456 7890</p>
                    <p><i class="fa-solid fa-envelope"></i> <strong>Correo:</strong> contacto@webpsy.com</p>
                    <p><i class="fa-solid fa-map-marker-alt"></i> <strong>Dirección:</strong> Valencia, Edo. Carabobo</p>
                </div>

                <div class="emergency-info">
                    <h4><i class="fa-solid fa-triangle-exclamation"></i> En caso de Emergencia</h4>
                    <p>Si te encuentras en una crisis de salud mental, sientes que tu vida o la de alguien más está en riesgo, o necesitas ayuda inmediata. Por favor, contacta a los servicios de emergencia locales o envía un mensaje directo desde aquí.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- VISTA 5: PREGUNTAS FRECUENTES (FAQ) -->
<div id="vista-faq" class="panel-vista">
    <div class="panel-seccion">
        <h2>Preguntas Frecuentes</h2>
        <p>Encuentra respuestas a las dudas más comunes sobre nuestros servicios y procesos.</p>
        
        <div class="faq-container">
            <?php
            // Consulta para obtener todas las preguntas y respuestas
            $faqs_result = $conex->query("SELECT pregunta, respuesta FROM faqs ORDER BY orden ASC, id ASC");
            if ($faqs_result->num_rows > 0) {
                while($faq = $faqs_result->fetch_assoc()) {
                    echo '<div class="faq-item">';
                    echo '  <button class="faq-question">';
                    echo '      <span>' . htmlspecialchars($faq['pregunta']) . '</span>';
                    echo '      <i class="fa-solid fa-chevron-down"></i>';
                    echo '  </button>';
                    echo '  <div class="faq-answer">';
                    echo '      <p>' . nl2br(htmlspecialchars($faq['respuesta'])) . '</p>';
                    echo '  </div>';
                    echo '</div>';
                }
            } else {
                echo "<p>No hay preguntas frecuentes disponibles en este momento.</p>";
            }
            ?>
        </div>
    </div>
</div>



<?php endif; ?>

<!-- ================== VISTAS PARA SECRETARIA ================== -->
<?php if ($rol_usuario == 'secretaria'): ?>

    <!-- VISTA 1: SOLICITUDES DE CITAS GENERALES (DISEÑO MEJORADO) -->
<div id="vista-solicitudes-generales" class="panel-vista active">
    <div class="panel-seccion">
        <h2>Solicitudes de Cita Pendientes</h2>
        <p>Aquí se muestran todas las solicitudes de citas pendientes de asignar.</p>
        
        <div class="solicitudes-list">
            <?php
            // CONSULTA ACTUALIZADA para incluir correo y edad
            $consulta_pendientes_gral = $conex->query("
                SELECT 
                    c.id, 
                    c.motivo_consulta, 
                    u.nombre_completo as paciente_nombre, 
                    u.cedula as paciente_cedula,
                    u.correo as paciente_correo,
                    u.edad as paciente_edad
                FROM citas c 
                JOIN usuarios u ON c.paciente_id = u.id 
                WHERE c.estado = 'pendiente'
            ");
            if ($consulta_pendientes_gral->num_rows > 0) {
                while($solicitud = $consulta_pendientes_gral->fetch_assoc()) {
                    echo '<div class="solicitud-card">';
                    echo '  <div class="solicitud-info">';
                    echo '      <h4>' . htmlspecialchars($solicitud['paciente_nombre']) . '</h4>';
                    // --- NUEVA SECCIÓN DE DETALLES ---
                    echo '      <div class="solicitud-details">';
                    echo '          <span><strong>C.I:</strong> ' . htmlspecialchars($solicitud['paciente_cedula']) . '</span>';
                    echo '          <span><strong>Edad:</strong> ' . htmlspecialchars($solicitud['paciente_edad']) . ' años</span>';
                    echo '          <span><strong>Correo:</strong> ' . htmlspecialchars($solicitud['paciente_correo']) . '</span>';
                    echo '      </div>';
                    echo '      <p class="solicitud-motivo"><strong>Motivo:</strong> ' . htmlspecialchars(substr($solicitud['motivo_consulta'], 0, 80)) . '...</p>';
                    echo '  </div>';
                    echo '  <div class="solicitud-actions">';
                    echo '      <button class="btn-view" onclick="abrirModalAsignarCita(' . $solicitud['id'] . ')">Asignar y Programar</button>';
                    echo '  </div>';
                    echo '</div>';
                }
            } else { echo "<p>No hay solicitudes de cita pendientes.</p>"; }
            ?>
        </div>
    </div>
</div>
    
    <!-- VISTA 2: AGENDA GENERAL DEL CONSULTORIO -->
    <div id="vista-agenda-general" class="panel-vista">
        <div class="panel-seccion">
            <h2>Agenda General del Consultorio</h2>
            <p>Calendario con las citas de todos los profesionales.</p>
            <div id="calendario-general" style="height: 70vh;"></div>
        </div>
    </div>

    <!-- VISTA 3: GESTIÓN DE PACIENTES -->
    <div id="vista-gestion-pacientes" class="panel-vista">
        <div class="panel-seccion">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Gestión de Pacientes</h2>
                <a href="crear_paciente.php" class="action-links approve" style="text-decoration: none;">
                    <i class="fa-solid fa-user-plus"></i> Añadir Nuevo Paciente
                </a>
            </div>
            <p>Busca, consulta o añade nuevos pacientes al sistema.</p>
            <div class="search-container">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="buscador-pacientes-secretaria" placeholder="Buscar paciente por nombre o cédula...">
            </div>
            <div id="tabla-pacientes-secretaria-container">
                <?php
                $todos_los_pacientes = $conex->query("SELECT id, nombre_completo, cedula, correo FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
                if ($todos_los_pacientes->num_rows > 0) {
                    echo "<table class='approvals-table'><thead><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Acciones</th></tr></thead><tbody>";
                    while($paciente = $todos_los_pacientes->fetch_assoc()) {
                        echo "<tr><td>" . htmlspecialchars($paciente['nombre_completo']) . "</td><td>" . htmlspecialchars($paciente['cedula']) . "</td><td>" . htmlspecialchars($paciente['correo']) . "</td><td class='action-links'><a href='gestionar_paciente.php?paciente_id=" . $paciente['id'] . "' class='approve'>Gestionar</a></td></tr>";
                    }
                    echo "</tbody></table>";
                } else { echo "<p>No hay pacientes registrados en el sistema.</p>"; }
                ?>
            </div>
        </div>
    </div>

    <!-- VISTA 4: DIRECTORIO PROFESIONAL (CORREGIDO) -->
<div id="vista-directorio" class="panel-vista">
    
    <!-- Sección para Psicólogos -->
    <div class="panel-seccion">
        <h2><i class="fa-solid fa-user-doctor"></i> Psicólogos</h2>
        <p>Lista de psicólogos activos en el sistema.</p>
        <?php
        // Consulta para obtener solo los psicólogos
        $consulta_psicologos = $conex->query("SELECT nombre_completo, correo FROM usuarios WHERE rol = 'psicologo' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
        
        if ($consulta_psicologos->num_rows > 0) {
            echo "<table class='approvals-table'>";
            echo "<thead><tr><th>Nombre Completo</th><th>Correo</th></tr></thead>";
            echo "<tbody>";
            while($profesional = $consulta_psicologos->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($profesional['nombre_completo']) . "</td>";
                echo "<td>" . htmlspecialchars($profesional['correo']) . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No hay psicólogos registrados en el sistema.</p>";
        }
        ?>
    </div>

    <!-- Sección para Psiquiatras -->
    <div class="panel-seccion">
        <h2><i class="fa-solid fa-brain"></i> Psiquiatras</h2>
        <p>Lista de psiquiatras activos en el sistema.</p>
        <?php
        // Consulta para obtener solo los psiquiatras
        $consulta_psiquiatras = $conex->query("SELECT nombre_completo, correo FROM usuarios WHERE rol = 'psiquiatra' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
        
        if ($consulta_psiquiatras->num_rows > 0) {
            echo "<table class='approvals-table'>";
            echo "<thead><tr><th>Nombre Completo</th><th>Correo</th></tr></thead>";
            echo "<tbody>";
            while($profesional = $consulta_psiquiatras->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($profesional['nombre_completo']) . "</td>";
                echo "<td>" . htmlspecialchars($profesional['correo']) . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No hay psiquiatras registrados en el sistema.</p>";
        }
        ?>
    </div>
</div>

<!-- VISTA PARA HISTORIAL DE CITAS GENERAL (SECRETARIA) -->
<div id="vista-historial-citas-general" class="panel-vista">
    <div class="panel-seccion">
        <h2>Historial Completo de Citas</h2>
        
        <!-- BARRA DE BÚSQUEDA AÑADIDA -->
        <div class="search-container">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="buscador-historial-secretaria" placeholder="Buscar por paciente, profesional o cédula...">
        </div>

        <!-- Contenedor para la tabla de resultados -->
        <div id="tabla-historial-secretaria-container">
            <p>Cargando historial de citas...</p>
        </div>
    </div>
</div>

<?php endif; ?>
        </main>
    </div>

    <!-- ================== MODAL PARA AÑADIR NUEVO PACIENTE (DISEÑO PREMIUM) ================== -->
<div id="modal-crear-paciente" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel">
            <div class="info-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h3>Nuevo Paciente</h3>
            <p>Rellena los datos para crear un nuevo perfil de paciente en el sistema.</p>
            <p class="info-footer">Se generará una contraseña temporal que deberás proporcionar al paciente para su primer acceso.</p>
        </div>

        <!-- Panel Derecho (Formulario) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalCrearPaciente()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Datos del Paciente</h4>
            <form action="guardar_paciente.php" method="POST" id="form-crear-paciente">
                <!-- Div para mostrar mensajes de error -->
                <div id="modal-paciente-error" class="alert-box error" style="display: none; margin-bottom: 20px;"></div>
                
                <div class="form-grid">
                <div class="input-group full-width">
                    <label for="nombre_completo_modal">Nombre Completo:</label>
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="nombre_completo" id="nombre_completo_modal" placeholder="Nombre y apellido" required>
                </div>

                <!-- CAMPO DE FECHA DE NACIMIENTO ACTUALIZADO PARA FLATPICKR -->
                <div class="input-group">
                    <label for="fecha_nacimiento_modal">Fecha de Nacimiento:</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-calendar-day"></i>
                        <input type="text" name="fecha_nacimiento" id="fecha_nacimiento_modal" placeholder="Selecciona una fecha..." required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="cedula_numero_modal">Identificacion:</label>
                    <div class="cedula-input-group">
                        <select name="cedula_tipo" id="cedula_tipo_modal">
                            <option value="V-">V</option>
                            <option value="E-">E</option>
                            <option value="P-">P</option>
                        </select>
                        <input type="number" name="cedula_numero" id="cedula_numero_modal" placeholder="De 7 a 8 dígitos" required minlength="7" maxlength="8">
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="correo_modal">Correo Electrónico:</label>
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="correo" id="correo_modal" placeholder="ejemplo@gmail.com" required>
                </div>
            </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalCrearPaciente()">Cancelar</button>
                    <button type="submit" class="btn-submit">Crear Paciente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA PROGRAMAR CITA DIRECTA (DISEÑO PREMIUM) ================== -->
<div id="modal-programar-cita" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel">
            <div class="info-icon">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <h3>Nueva Cita</h3>
            <p>Estás agendando una nueva consulta para:</p>
            <strong id="modal-paciente-nombre-display"></strong>
            <p class="info-footer">Asegúrate de que la fecha y el motivo sean correctos antes de guardar.</p>
        </div>

        <!-- Panel Derecho (Formulario) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalProgramarCita()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Detalles de la Cita</h4>
            <form action="guardar_cita_directa.php" method="POST" id="form-programar-cita">
                <input type="hidden" name="paciente_id" id="modal-paciente-id">
                
                <div class="form-group">
                    <label for="calendario-programar">Fecha y Hora</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-calendar-alt"></i>
                        <input type="text" id="calendario-programar" name="fecha_cita" placeholder="Selecciona una fecha..." required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="motivo_consulta_modal">Motivo de la consulta</label>
                    <textarea name="motivo_consulta" id="motivo_consulta_modal" rows="5" required placeholder="Ej: Cita de seguimiento..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalProgramarCita()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA REPROGRAMAR CITA (DISEÑO PREMIUM) ================== -->
<div id="modal-reprogramar-cita" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium">
        <!-- Panel Izquierdo (Informativo) con estilo de advertencia -->
        <div class="modal-info-panel info-panel-warning">
            <div class="info-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <h3>Reprogramar Cita</h3>
            <p>Paciente:</p>
            <strong id="reprogramar-paciente-nombre"></strong>
            <p class="info-footer">El paciente recibirá una notificación con la nueva fecha y el motivo del cambio.</p>
        </div>

        <!-- Panel Derecho (Formulario) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalReprogramarCita()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Nuevos Detalles de la Cita</h4>
            <form action="actualizar_cita.php" method="POST" id="form-reprogramar-cita">
                <input type="hidden" name="cita_id" id="reprogramar-cita-id">
                
                <div class="form-group">
                    <label for="calendario-reprogramar">Seleccionar Nueva Fecha y Hora:</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-calendar-alt"></i>
                        <input type="text" id="calendario-reprogramar" name="nueva_fecha_cita" placeholder="Haz clic para seleccionar..." required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="motivo_reprogramacion_modal">Motivo de la reprogramación:</label>
                    <textarea name="motivo_reprogramacion" id="motivo_reprogramacion_modal" rows="4" required placeholder="Ej: Conflicto de horario imprevisto..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalReprogramarCita()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar y Notificar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA PROPONER NUEVA FECHA (DISEÑO PREMIUM) ================== -->
<div id="modal-proponer-fecha" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel info-panel-warning">
            <div class="info-icon">
                <i class="fa-solid fa-calendar-plus"></i>
            </div>
            <h3>Proponer Nueva Fecha</h3>
            <p>Paciente:</p>
            <strong id="proponer-paciente-nombre"></strong>
            <p class="info-footer">El paciente recibirá una notificación con tu propuesta y deberá aceptarla o rechazarla.</p>
        </div>

        <!-- Panel Derecho (Formulario) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalProponerFecha()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Detalles de la Propuesta</h4>
            <form action="guardar_propuesta.php" method="POST" id="form-proponer-fecha">
                <input type="hidden" name="cita_id" id="proponer-cita-id">
                
                <div class="form-group">
                    <label for="calendario-proponer">Sugerir nueva fecha y hora:</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-calendar-alt"></i>
                        <input type="text" id="calendario-proponer" name="fecha_propuesta" placeholder="Haz clic para seleccionar..." required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="motivo_reprogramacion_propuesta">Motivo (se notificará al paciente):</label>
                    <textarea name="motivo_reprogramacion" id="motivo_reprogramacion_propuesta" rows="4" required placeholder="Ej: No tengo disponibilidad en el horario solicitado..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalProponerFecha()">Cancelar</button>
                    <button type="submit" class="btn-submit">Enviar Propuesta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA GESTIONAR PACIENTE (DISEÑO PREMIUM) ================== -->
<div id="modal-gestionar-paciente" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium" style="max-width: 900px;">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel">
            <div class="info-icon"><i class="fa-solid fa-user-gear"></i></div>
            <h3>Gestionar Paciente</h3>
            <p>Acciones rápidas para:</p>
            <strong id="gestion-paciente-nombre"></strong>
            <p id="gestion-paciente-edad" class="info-panel-age"></p>
            <p class="info-footer">Desde aquí puedes acceder a la historia clínica y a los informes del paciente.</p>
        </div>

        <!-- Panel Derecho (Contenido y Acciones) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalGestionarPaciente()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Información y Acciones</h4>
            <div id="gestion-modal-body">
                <!-- El contenido se cargará aquí con JavaScript -->
                <p>Cargando datos del paciente...</p>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA GESTIONAR NOTAS DE SESIÓN (LAYOUT CORREGIDO) ================== -->
<div id="modal-gestionar-notas" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium" style="max-width: 950px;">
        
                <!-- Panel Derecho (Historial de Notas) -->
        <div class="modal-info-panel info-panel-history">
            <div class="history-header">
                <div class="info-icon">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div>
                    <h3>Historial de Notas</h3>
                    <p id="notas-paciente-nombre"></p>
                </div>
                <!-- BOTÓN AÑADIDO -->
                <button id="btn-limpiar-notas" class="btn-clear-notes" title="Limpiar todo el historial de notas">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
            <div id="historial-notas-container" class="history-list">
                <!-- Las notas se cargarán aquí con JavaScript -->
                <p>Cargando historial...</p>
            </div>
        </div>

        <!-- Panel Derecho (Ahora es el Formulario para Añadir) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalGestionarNotas()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Añadir Nueva Nota de Sesión</h4>
            <form action="guardar_nota.php" method="POST" id="form-guardar-nota">
                <input type="hidden" name="paciente_id" id="notas-paciente-id">
                <div class="form-group">
    <label for="fecha_sesion_modal">Fecha de la Sesión:</label>
    <div class="input-wrapper">
        <i class="fa-solid fa-calendar-alt"></i>
        <input type="text" name="fecha_sesion" id="fecha_sesion_modal" placeholder="Selecciona fecha y hora..." required>
    </div>
</div>
                <div class="form-group">
                    <label for="nota_modal">Nota de Evolución:</label>
                    <textarea name="nota" id="nota_modal" rows="8" required placeholder="Escribe aquí tus observaciones sobre la sesión..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Guardar Nota</button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- ================== MODAL PARA SELECCIONAR TIPO DE HISTORIA (DISEÑO PREMIUM) ================== -->
<div id="modal-seleccionar-historia" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-select">
        <div class="modal-header-select">
            <h2>Seleccionar Tipo de Expediente</h2>
            <p>Elige el formato de historia clínica adecuado para el paciente.</p>
            <button type="button" class="modal-close-btn" onclick="cerrarModalSeleccionarHistoria()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body-select">
            <div class="selection-grid-premium">
                 <div class="selection-card-premium" id="btn-seleccionar-adulto">
                    <div class="card-icon"><i class="fa-solid fa-user"></i></div>
                    <div class="card-text">
                        <h3>Historia de Adulto</h3>
                        <p>Formulario completo para pacientes mayores de 18 años.</p>
                    </div>
                    <div class="card-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <div class="selection-card-premium" id="btn-seleccionar-infantil">
                    <div class="card-icon"><i class="fa-solid fa-child"></i></div>
                    <div class="card-text">
                        <h3>Historia Infantil</h3>
                        <p>Formulario detallado para niños y adolescentes.</p>
                    </div>
                    <div class="card-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA CREAR HISTORIA CLÍNICA (ADULTO - DISEÑO DE ENCABEZADO) ================== -->
<div id="modal-crear-historia" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-medical"></i>
                <div>
                    <h2>Crear Historia Clínica de Adulto</h2>
                    <p id="historia-paciente-nombre-display-header"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalCrearHistoria()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo del Formulario con Íconos junto a las Etiquetas -->
        <div class="modal-body-premium">
            <form action="guardar_historia.php" method="POST" id="form-crear-historia">
                <input type="hidden" name="tipo_historia" value="adulto">
                <input type="hidden" name="paciente_id" id="historia-paciente-id" value="123">
                
                <h3>Datos Generales</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-hashtag"></i> N° de Historia:</label><input type="text" name="numero_historia" id="historia-numero-adulto" class="validate-numeric" readonly required></div>
                    <div class="form-group"><label><i class="fa-solid fa-hospital"></i> Centro de Salud:</label><input type="text" name="centro_salud" value="WebPSY Consultorio" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-calendar-day"></i> Fecha:</label><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required readonly></div>
                </div>
                <h3>Datos Personales</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-id-card"></i> Identificacion:</label><input type="text" name="ci_paciente" id="historia-paciente-cedula" readonly required></div>
                    <div class="form-group"><label><i class="fa-solid fa-venus-mars"></i> Sexo:</label><select name="sexo" required><option value="" disabled selected>Seleccione</option><option value="Masculino">Masculino</option><option value="Femenino">Femenino</option></select></div>
                        <div class="form-group">
                        <label><i class="fa-solid fa-phone"></i> Teléfono:</label>
                        <div class="phone-input-group">
                            <select name="telefono_tipo" required>
                                <option value="Móvil" selected>Móvil</option>
                                <option value="Fijo">Fijo</option>
                            </select>
                            <select name="telefono_codigo_pais" required>
                                <option value="+58" selected>(+58)</option>
                                <option value="+57">(+57)</option>
                                <option value="+1">(+1)</option>
                                <option value="+34">(+34)</option>
                                <option value="+51">(+51)</option>
                                <option value="+54">(+54)</option>
                                <option value="+55">(+55)</option>
                                <option value="+56">(+56)</option>
                                <option value="+593">(+593)</option>
                                <option value="+52">(+52)</option>
                                <option value="+507">(+507)</option>
                                <option value="+39">(+39)</option>
                                <option value="+44">(+44)</option>
                                <!-- Puedes añadir más países aquí -->
                            </select>
                            <input type="text" name="telefono_numero" placeholder="EJ: 4141234567" required class="validate-numeric" maxlength="10">
                        </div>
                    </div>
                    <div class="form-group"><label><i class="fa-solid fa-ring"></i> Edo. Civil:</label><select name="estado_civil" required><option value="" disabled selected>Seleccione</option><option value="Soltero(a)">Soltero(a)</option><option value="Casado(a)">Casado(a)</option><option value="Divorciado(a)">Divorciado(a)</option><option value="Viudo(a)">Viudo(a)</option><option value="Unión Libre">Unión Libre</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-flag"></i> Nacionalidad:</label><select name="nacionalidad" required><option value="" disabled selected>Seleccione</option><option value="Venezolana">Venezolana</option><option value="Otra">Otra</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-children"></i> ¿Tiene Hijos?:</label><select name="hijos" required><option value="" disabled selected>Seleccione</option><option value="No">No</option><option value="Sí">Sí</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-cross"></i> Religión:</label><input type="text" name="religion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label><select name="grado_instruccion" required><option value="" disabled selected>Seleccione</option><option value="Sin instrucción">Sin instrucción</option><option value="Primaria">Primaria</option><option value="Secundaria">Secundaria</option><option value="Bachiller">Bachiller</option><option value="Técnico Superior">Técnico Superior</option><option value="Universitario">Universitario</option><option value="Postgrado">Postgrado</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-briefcase"></i> Ocupación:</label><input type="text" name="ocupacion" class="validate-text-only" required></div>
                    <div class="form-group full-width"><label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label><textarea name="direccion" required></textarea></div>
                </div>
                <h3>Motivo y Antecedentes</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-comment-medical"></i> Motivo de Consulta:</label><textarea name="motivo_consulta" rows="4" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-user-pen"></i> Antecedentes Personales:</label><textarea name="antecedentes_personales" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-users"></i> Antecedentes Familiares:</label><textarea name="antecedentes_familiares" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-brain"></i> Antecedentes Psiquiátricos:</label><textarea name="antecedentes_psiquiatricos" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-notes-medical"></i> Antecedentes Médicos:</label><textarea name="antecedentes_medicos" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-heart"></i> Antecedentes de Pareja:</label><textarea name="antecedentes_pareja" rows="3" required></textarea></div>
                <h3>Diagnóstico</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-file-waveform"></i> Impresión Diagnóstica:</label><textarea name="impresion_diagnostica" rows="5" required></textarea></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalCrearHistoria()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar Historia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA CREAR HISTORIA CLÍNICA (INFANTIL - DISEÑO PREMIUM) ================== -->
<div id="modal-crear-historia-infantil" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-child"></i>
                <div>
                    <h2>Crear Historia Clínica Infantil</h2>
                    <p id="historia-paciente-nombre-display-infantil-header"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalCrearHistoriaInfantil()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
         <!-- Cuerpo del Formulario con Íconos junto a las Etiquetas -->
        <div class="modal-body-premium">
            <form action="guardar_historia.php" method="POST" id="form-crear-historia-infantil">
                <input type="hidden" name="tipo_historia" value="infantil">
                <input type="hidden" name="paciente_id" id="historia-paciente-id-infantil" value="123">
                
                <h3>Datos Generales</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-hashtag"></i> N° de Historia:</label><input type="text" name="numero_historia" id="historia-numero-infantil" class="validate-numeric" readonly required></div>
                    <div class="form-group"><label><i class="fa-solid fa-hospital"></i> Centro de Salud:</label><input type="text" name="centro_salud" value="WebPSY Consultorio" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-calendar-day"></i> Fecha:</label><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required readonly></div>
                </div>

                <h3>Datos Personales del Infante</h3>
                <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="form-group"><label><i class="fa-solid fa-map-pin"></i> Lugar de Nacimiento:</label><input type="text" name="lugar_nacimiento" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-id-card"></i> Identificación:</label><input type="text" name="ci_infante" id="ci_infante_modal" class="validate-numeric" readonly></div>
                    <div class="form-group"><label><i class="fa-solid fa-school"></i> Institución Escolar:</label><input type="text" name="institucion_escolar" required></div>
                </div>
                
                <h3>Datos del Padre</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-user"></i> Nombre y Apellido:</label><input type="text" name="padre_nombre" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-birthday-cake"></i> Edad:</label><input type="text" name="padre_edad" class="validate-numeric" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-id-card"></i> C.I.:</label><input type="text" name="padre_ci" class="validate-numeric" required minlength="7" maxlength="8" placeholder="7 a 8 dígitos"></div>
                    <div class="form-group"><label><i class="fa-solid fa-flag"></i> Nacionalidad:</label><select name="padre_nacionalidad" required><option value="" disabled selected>Seleccione</option><option value="Venezolana">Venezolana</option><option value="Otra">Otra</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-cross"></i> Religión:</label><input type="text" name="padre_religion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label><select name="padre_instruccion" required><option value="" disabled selected>Seleccione</option><option value="Sin instrucción">Sin instrucción</option><option value="Primaria">Primaria</option><option value="Secundaria">Secundaria</option><option value="Bachiller">Bachiller</option><option value="Técnico Superior">Técnico Superior</option><option value="Universitario">Universitario</option><option value="Postgrado">Postgrado</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-briefcase"></i> Ocupación:</label><input type="text" name="padre_ocupacion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-phone"></i> Teléfono:</label><input type="text" name="padre_telefono" class="validate-numeric" required maxlength="11"></div>
                    <div class="form-group full-width"><label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label><textarea name="padre_direccion" rows="2" required></textarea></div>
                </div>

                <h3>Datos de la Madre</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-user"></i> Nombre y Apellido:</label><input type="text" name="madre_nombre" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-birthday-cake"></i> Edad:</label><input type="text" name="madre_edad" class="validate-numeric" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-id-card"></i> C.I.:</label><input type="text" name="madre_ci" class="validate-numeric" required minlength="7" maxlength="8" placeholder="7 a 8 dígitos"></div>
                    <div class="form-group"><label><i class="fa-solid fa-flag"></i> Nacionalidad:</label><select name="madre_nacionalidad" required><option value="" disabled selected>Seleccione</option><option value="Venezolana">Venezolana</option><option value="Otra">Otra</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-cross"></i> Religión:</label><input type="text" name="madre_religion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label><select name="madre_instruccion" required><option value="" disabled selected>Seleccione</option><option value="Sin instrucción">Sin instrucción</option><option value="Primaria">Primaria</option><option value="Secundaria">Secundaria</option><option value="Bachiller">Bachiller</option><option value="Técnico Superior">Técnico Superior</option><option value="Universitario">Universitario</option><option value="Postgrado">Postgrado</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-briefcase"></i> Ocupación:</label><input type="text" name="madre_ocupacion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-phone"></i> Teléfono:</label><input type="text" name="madre_telefono" class="validate-numeric" required maxlength="11"></div>
                    <div class="form-group full-width"><label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label><textarea name="madre_direccion" rows="2" required></textarea></div>
                </div>

                <h3>Dinámica Familiar</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-house-user"></i> ¿Padres viven juntos?:</label><select name="padres_viven_juntos" required><option value="" disabled selected>Seleccione</option><option value="Sí">Sí</option><option value="No">No</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-ring"></i> ¿Están casados?:</label><select name="estan_casados" required><option value="" disabled selected>Seleccione</option><option value="Sí">Sí</option><option value="No">No</option></select></div>
                    <div class="form-group full-width"><label><i class="fa-solid fa-comment-slash"></i> Motivo de separación (si aplica):</label><textarea name="motivo_separacion" rows="2"></textarea></div>
                </div>
                <div class="form-group full-width">
                    <label><i class="fa-solid fa-users"></i> Hermanos</label>
                    <div id="hermanos-container-modal"></div>
                    <button type="button" id="add-hermano-btn-modal" class="btn-outline-primary" style="width: auto; margin-top: 10px;"><i class="fa-solid fa-plus"></i> Añadir Hermano</button>
                </div>

                <h3>Motivos y Antecedentes</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-comment-medical"></i> Motivo de Consulta:</label><textarea name="motivo_consulta" rows="4" required></textarea></div>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-baby-carriage"></i> Tipo de Embarazo:</label><input type="text" name="antecedentes_embarazo" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-hospital"></i> Parto (Lugar):</label><input type="text" name="antecedentes_parto" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-baby"></i> Estado del niño/a al nacer:</label><input type="text" name="estado_nino_nacer" required></div>
                </div>
                <div class="form-group full-width"><label><i class="fa-solid fa-person-walking"></i> Desarrollo Psicomotor:</label><textarea name="desarrollo_psicomotor" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-hand-sparkles"></i> Hábitos de Independencia:</label><textarea name="habitos_independencia" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-heartbeat"></i> Condiciones Generales de Salud:</label><textarea name="condiciones_salud" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-users"></i> Vida Social:</label><textarea name="vida_social" rows="3" required></textarea></div>
                
                <h3>Plan Terapéutico</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-clipboard-list"></i> Plan Psicoterapéutico:</label><textarea name="plan_psicoterapeutico" rows="5" required></textarea></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalCrearHistoriaInfantil()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar Historia Infantil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER INFORMES (DISEÑO PREMIUM) ================== -->
<div id="modal-ver-informes" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium" style="max-width: 700px;">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel" style="background: linear-gradient(160deg, #6f42c1, #5a32a3);">
            <div class="info-icon"><i class="fa-solid fa-folder-open"></i></div>
            <h3>Historial de Informes</h3>
            <p>Paciente:</p>
            <strong id="informes-paciente-nombre"></strong>
            <p id="informes-paciente-edad" class="info-panel-age"></p>
            <p class="info-footer">Aquí se listan todos los informes psicológicos generados para este paciente.</p>
        </div>

        <!-- Panel Derecho (Lista de Informes) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalVerInformes()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Informes Registrados</h4>
            <div id="historial-informes-container" class="history-list">
                <!-- Los informes se cargarán aquí con JavaScript -->
                <p>Cargando historial...</p>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER DETALLE DE INFORME (DISEÑO PREMIUM) ================== -->
<div id="modal-informe-detalle" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header" style="max-width: 800px;">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-waveform"></i>
                <div>
                    <h2>Informe Psicológico</h2>
                    <p id="informe-detalle-paciente-nombre"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalInformeDetalle()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo del Informe con Scroll -->
        <div class="modal-body-premium" id="informe-detalle-body">
            <!-- El contenido del informe se cargará aquí con JavaScript -->
            <p>Cargando informe...</p>
        </div>
    </div>
</div>


<!-- ================== NUEVA MODAL PARA CREAR INFORME (DISEÑO PREMIUM) ================== -->
    <div id="modal-crear-informe" class="modal-overlay" style="display: none;">
        <div class="modal-content-premium-header">
            <!-- Encabezado -->
            <div class="modal-header-premium" style="background: linear-gradient(160deg, #17a2b8, #107586);">
                <div class="header-content">
                    <i class="fa-solid fa-file-pen"></i>
                    <div>
                        <h2>Crear Nuevo Informe</h2>
                        <p id="informe-paciente-nombre-display"></p>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="cerrarModalCrearInforme()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <!-- Cuerpo del Formulario con Scroll, Íconos y Validación -->
        <div class="modal-body-premium">
            <form action="guardar_informe.php" method="POST" id="form-crear-informe">
                <input type="hidden" name="paciente_id" id="informe-paciente-id">
                
                <h3>Datos de Referencia</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fa-solid fa-hashtag"></i> N° de Historia:</label>
                        <input type="text" name="numero_historia" id="informe-numero-historia" class="validate-numeric" placeholder="Cargando..." readonly required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar-day"></i> Fecha de Evaluación:</label>
                        <input type="date" name="fecha_evaluacion" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group full-width">
                        <label><i class="fa-solid fa-user-md"></i> Referido por:</label>
                        <input type="text" name="referido_por" placeholder="Ej: Dr. Juan Pérez" required>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label><i class="fa-solid fa-comment-medical"></i> Motivo de la Referencia:</label>
                    <textarea name="motivo_referencia" rows="3" placeholder="Descripción del motivo..." required></textarea>
                </div>
                <div class="form-group full-width">
                    <label><i class="fa-solid fa-user-check"></i> Actitud ante la Evaluación:</label>
                    <textarea name="actitud_ante_evaluacion" rows="3" placeholder="Describe la actitud y comportamiento del paciente..." required></textarea>
                </div>

                <h3>Resultados de la Evaluación</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-eye"></i> Área Visomotriz:</label><textarea name="area_visomotriz" rows="4" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-brain"></i> Área Intelectual:</label><textarea name="area_intelectual" rows="4" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-heart-pulse"></i> Área Emocional:</label><textarea name="area_emocional" rows="4" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-clipboard-list"></i> Otros Resultados Relevantes:</label><textarea name="resultados_adicionales" rows="4" required></textarea></div>
                
                <h3>Recomendaciones</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-prescription"></i> Recomendaciones:</label><textarea name="recomendaciones" rows="6" required></textarea></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalCrearInforme()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar Informe</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- ================== MODAL PARA VER HISTORIA CLÍNICA (DISEÑO PREMIUM) ================== -->
<div id="modal-ver-historia" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-medical"></i>
                <div>
                    <h2 id="ver-historia-titulo">Historia Clínica</h2>
                    <p id="ver-historia-paciente-nombre"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalVerHistoria()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo de la Historia con Scroll -->
        <div class="modal-body-premium" id="ver-historia-body">
            <!-- El contenido de la historia se cargará aquí con JavaScript -->
            <p>Cargando historial...</p>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA ASIGNAR Y PROGRAMAR CITA (DISEÑO PREMIUM) ================== -->
<div id="modal-asignar-cita" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel">
            <div class="info-icon"><i class="fa-solid fa-calendar-plus"></i></div>
            <h3>Asignar Cita</h3>
            <p>Paciente:</p>
            <strong id="asignar-paciente-nombre"></strong>
            <p class="info-footer">Selecciona un profesional y una fecha para confirmar la cita.</p>
        </div>

        <!-- Panel Derecho (Formulario) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalAsignarCita()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Detalles de la Programación</h4>
            <form action="guardar_cita.php" method="POST" id="form-asignar-cita">
                <input type="hidden" name="cita_id" id="asignar-cita-id">
                
                <div class="form-group">
                    <label>Motivo de la Consulta:</label>
                    <p id="asignar-motivo-consulta" class="info-text-box"></p>
                </div>

                <!-- CAMPO AÑADIDO PARA MOSTRAR PROFESIONAL ASIGNADO -->
                <div class="form-group inline-info">
                <label>Profesional Asignado:</label>
                <span id="asignar-profesional-solicitado"></span>
                </div>

                <div class="form-group">
                    <label for="asignar-psicologo-id">Asignar a Profesional:</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user-doctor"></i>
                        <select name="psicologo_id" id="asignar-psicologo-id" required>
                            <option value="">-- Seleccione un profesional --</option>
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
                </div>

                <div class="form-group">
                    <label for="calendario-asignar">Seleccionar Fecha y Hora:</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-calendar-alt"></i>
                        <input type="text" id="calendario-asignar" name="fecha_cita" placeholder="Haz clic para seleccionar..." required>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalAsignarCita()">Cancelar</button>
                    <button type="submit" class="btn-submit">Confirmar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER DETALLES DE CITA (PACIENTE) ================== -->
<div id="modal-detalle-cita-paciente" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header" style="max-width: 700px;">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-invoice"></i>
                <div>
                    <h2>Detalles de la Cita</h2>
                    <p id="detalle-cita-fecha"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalDetalleCitaPaciente()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo de la Cita con Scroll -->
        <div class="modal-body-premium" id="detalle-cita-body">
            <!-- El contenido se cargará aquí con JavaScript -->
            <p>Cargando detalles...</p>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER DETALLES DEL PROFESIONAL ================== -->
<div id="modal-profesional-detalle" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-user-doctor"></i>
                <div>
                    <h2 id="profesional-detalle-nombre"></h2>
                    <p id="profesional-detalle-rol"></p>
                </div>
            </div>
            
        </div>
        
        <!-- Cuerpo de la Información -->
        <div class="modal-body-premium" id="profesional-detalle-body">
            <!-- El contenido se cargará aquí con JavaScript -->
            <p>Cargando detalles...</p>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA MOSTRAR CONTRASEÑA TEMPORAL ================== -->
<div id="modal-exito-paciente" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 500px; text-align: center;">
        <div class="modal-icon success-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <h3>¡Paciente Creado con Éxito!</h3>
        <p>La cuenta para <strong id="exito-paciente-nombre"></strong> ha sido creada. Su contraseña temporal es:</p>
        <div class="temp-password-box">
            <span id="exito-paciente-password"></span>
        </div>
        <p style="font-size: 14px; color: #777; margin-top: 15px;">Por favor, anota esta contraseña y entrégasela al paciente.</p>
        <button class="btn-submit" style="width: auto; padding: 10px 30px;" onclick="cerrarModalExitoPaciente()">Entendido</button>
    </div>
</div>

<!-- ================== MODAL PARA VER DETALLES DE SOLICITUD (PSICÓLOGO) ================== -->
<div id="modal-solicitud-detalle" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header" style="max-width: 700px;">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-import"></i>
                <div>
                    <h2>Detalles de la Solicitud</h2>
                    <p id="solicitud-paciente-nombre"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalSolicitudDetalle()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo de la Solicitud -->
        <div class="modal-body-premium" id="solicitud-detalle-body">
            <!-- El contenido se cargará aquí con JavaScript -->
        </div>
    </div>
</div>

<!-- ================== MODAL PARA CONFLICTO DE CITA ================== -->
<div id="modal-conflicto-cita" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 500px; text-align: center;">
        <div class="modal-icon" style="color: #ffc107; font-size: 50px; margin-bottom: 15px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>Conflicto de Horario</h3>
        <p>Ya tienes otra cita confirmada para este día a esta misma hora. Por favor, asigna una nueva fecha para este paciente.</p>
        <div class="modal-actions" style="justify-content: center; gap: 15px;">
            <button type="button" class="btn-secondary" onclick="cerrarModalConflicto()">Cancelar</button>
            <a href="#" id="btn-proponer-fecha-conflicto" class="btn-submit">Proponer Nueva Fecha</a>
        </div>
    </div>
</div>




<!-- ================== MODAL PARA DETALLES DE CITA DEL HISTORIAL (PSICÓLOGO) ================== -->
<div id="modal-historial-detalle" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header" style="max-width: 750px;">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-medical"></i>
                <div>
                    <h2 id="historial-detalle-titulo">Detalles de la Cita</h2>
                    <p id="historial-detalle-paciente-nombre">Paciente: Cargando...</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalHistorialDetalle()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo del Modal -->
        <div class="modal-body-premium" id="historial-detalle-body">
            <!-- El contenido se cargará aquí con JavaScript -->
            <p>Cargando detalles...</p>
        </div>
    </div>
</div>


    <!-- PASO 1: Cargar la librería principal de Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- PASO 2: Cargar el paquete de idioma español para Flatpickr -->
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>

    <script>
    // ==================================================================
    // VARIABLES GLOBALES Y FUNCIONES DE CONTROL
    // ==================================================================
    // --- VARIABLES Y FUNCIONES PARA LAS NUEVAS MODALES ---
    const modalSeleccionarHistoria = document.getElementById('modal-seleccionar-historia');
    const modalCrearHistoria = document.getElementById('modal-crear-historia');
    const modalCrearHistoriaInfantil = document.getElementById('modal-crear-historia-infantil');
    const modalVerInformes = document.getElementById('modal-ver-informes');
    const modalInformeDetalle = document.getElementById('modal-informe-detalle');
    const modalCrearInforme = document.getElementById('modal-crear-informe');
    const modalVerHistoria = document.getElementById('modal-ver-historia');
    // Variables para las ventanas modales
    const modalCrearPaciente = document.getElementById('modal-crear-paciente');
    const modalProgramarCita = document.getElementById('modal-programar-cita');
    const modalGestionarPaciente = document.getElementById('modal-gestionar-paciente');
    const modalSolicitudDetalle = document.getElementById('modal-solicitud-detalle');
    const modalGestionarNotas = document.getElementById('modal-gestionar-notas');
    // Variables globales para que las funciones puedan acceder a ellas
    let calendar;
    let isCalendarRendered = false;
    let generalCalendar;
    let isGeneralCalendarRendered = false;
    let citasChart = null;
    let newPatientsChart = null;
    let pacienteCalendar; // Calendario de disponibilidad para el paciente
    let currentManagedPatientId = null;
    

    // --- FUNCIÓN ÚNICA Y CORRECTA PARA CAMBIAR DE VISTA (PESTAÑAS) ---
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

        // Renderizar el calendario del psicólogo solo la primera vez que se muestra
        if (vista === 'agenda' && !isCalendarRendered) {
            if (calendar) {
                calendar.render();
                isCalendarRendered = true;
            }
        }

        // Renderizar el calendario general de la secretaria
        if (vista === 'agenda-general' && !isGeneralCalendarRendered) {
            if (generalCalendar) {
                generalCalendar.render();
                isGeneralCalendarRendered = true;
            }
        }
    }

    // --- FUNCIÓN REUTILIZABLE Y ROBUSTA PARA ORDENAR TABLAS ---
    const makeTableSortable = (container) => {
        if (!container) return;

        // Función para convertir la fecha de 'dd/mm/yyyy' a un formato comparable
        const parseDate = (dateStr) => {
            const parts = dateStr.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (!parts) return new Date(0); // Devuelve una fecha muy antigua si el formato no coincide
            // Formato: año, mes (0-11), día
            return new Date(parts[3], parts[2] - 1, parts[1]);
        };

        const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;

        const comparer = (idx, asc) => (a, b) => {
            const vA = getCellValue(asc ? a : b, idx);
            const vB = getCellValue(asc ? b : a, idx);

            // Comprobar si el encabezado es de tipo fecha
            const header = container.querySelector(`th:nth-child(${idx + 1})`);
            const isDateColumn = header && header.textContent.toLowerCase().includes('fecha');

            if (isDateColumn) {
                return parseDate(vA) - parseDate(vB);
            }

            // Ordenamiento normal para texto y números
            return vA.toString().localeCompare(vB, 'es', { numeric: true, sensitivity: 'base' });
        };

        container.addEventListener('click', function(e) {
            const th = e.target.closest('.sortable-header');
            if (!th) return;

            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const headers = table.querySelectorAll('.sortable-header');
            const columnIndex = Array.from(th.parentNode.children).indexOf(th);
            const isAsc = !th.classList.contains('sort-asc');

            headers.forEach(header => header.classList.remove('sort-asc', 'sort-desc'));
            th.classList.toggle('sort-asc', isAsc);
            th.classList.toggle('sort-desc', !isAsc);

            Array.from(tbody.querySelectorAll('tr'))
                .sort(comparer(columnIndex, isAsc))
                .forEach(tr => tbody.appendChild(tr));
        });
    };


    // --- FUNCIONES PARA LA MODAL DE CREAR PACIENTE ---
    function abrirModalCrearPaciente() {
        if (modalCrearPaciente) modalCrearPaciente.style.display = 'flex';
    }
    function cerrarModalCrearPaciente() {
        if (modalCrearPaciente) {
            modalCrearPaciente.style.display = 'none';

            // --- LÍNEAS AÑADIDAS ---
            // Buscamos el div del mensaje de error
            const modalErrorDiv = document.getElementById('modal-paciente-error');
            if (modalErrorDiv) {
                modalErrorDiv.style.display = 'none'; // Ocultamos el mensaje
                modalErrorDiv.textContent = '';       // Borramos su contenido
            }
            // --- FIN DE LÍNEAS AÑADIDAS ---
        }
    }

    function abrirModalProgramarCita(pacienteId, pacienteNombre) {
    if (modalProgramarCita) {
        // Rellenar los datos del paciente en la modal
        document.getElementById('modal-paciente-id').value = pacienteId;
        document.getElementById('modal-paciente-nombre-display').textContent = pacienteNombre; // Usamos el nuevo elemento
        modalProgramarCita.style.display = 'flex';
    }
    }
    function cerrarModalProgramarCita() {
        if (modalProgramarCita) {
            modalProgramarCita.style.display = 'none';
            document.getElementById('form-programar-cita').reset();
        }
    }

    // --- FUNCIÓN PARA MOSTRAR MENSAJE DE ÉXITO (CREAR PACIENTE) ---
    function mostrarMensajeExito(nombre, password) {
        const mainContent = document.querySelector('.main-content');
        const alertaVieja = document.getElementById('alerta-paciente-creado');
        if (alertaVieja) alertaVieja.remove();

        const alertDiv = document.createElement('div');
        alertDiv.id = 'alerta-paciente-creado';
        alertDiv.className = 'alert-box success';
        alertDiv.innerHTML = `
            <span><strong>¡Éxito!</strong> Paciente <strong>${nombre}</strong> creado. Su contraseña temporal es: <strong class="temp-pass">${password}</strong></span>
            <span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
        `;
        // Insertar el mensaje al principio de la vista de pacientes
        const vistaPacientes = document.getElementById('vista-pacientes');
        if (vistaPacientes) {
             vistaPacientes.prepend(alertDiv);
        }
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE REPROGRAMAR CITA ---
    const modalReprogramarCita = document.getElementById('modal-reprogramar-cita');

    function abrirModalReprogramarCita(citaId, pacienteNombre) {
        if (modalReprogramarCita) {
            document.getElementById('reprogramar-cita-id').value = citaId;
            document.getElementById('reprogramar-paciente-nombre').textContent = pacienteNombre;
            modalReprogramarCita.style.display = 'flex';
        }
    }
    function cerrarModalReprogramarCita() {
        if (modalReprogramarCita) {
            modalReprogramarCita.style.display = 'none';
            document.getElementById('form-reprogramar-cita').reset();
        }
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE PROPONER FECHA ---
    const modalProponerFecha = document.getElementById('modal-proponer-fecha');

    function abrirModalProponerFecha(citaId, pacienteNombre) {
        if (modalProponerFecha) {
            document.getElementById('proponer-cita-id').value = citaId;
            document.getElementById('proponer-paciente-nombre').textContent = pacienteNombre;
            modalProponerFecha.style.display = 'flex';
        }
    }
    function cerrarModalProponerFecha() {
        if (modalProponerFecha) {
            modalProponerFecha.style.display = 'none';
            document.getElementById('form-proponer-fecha').reset();
        }
    }
    
    // --- NUEVAS FUNCIONES PARA LA MODAL DE GESTIONAR PACIENTE ---
    // --- NUEVA FUNCIÓN PARA NAVEGAR CON ANIMACIÓN ---
    function navigateWithAnimation(url) {
        event.preventDefault(); // Previene la redirección instantánea
        document.body.classList.add('fade-out'); // Añade la clase que activa la animación
        // Espera a que termine la animación (400ms) antes de cambiar de página
        setTimeout(() => {
            window.location.href = url;
        }, 400); 
    }

    // --- FUNCIÓN MODIFICADA PARA GESTIONAR PACIENTE (ESTRUCTURA CORREGIDA) ---
    function abrirModalGestionarPaciente(pacienteId) {
        currentManagedPatientId = pacienteId; // Guardamos el ID del paciente que estamos gestionando
        if (modalGestionarPaciente) {
            const modalBody = document.getElementById('gestion-modal-body');
            const pacienteNombreDisplay = document.getElementById('gestion-paciente-nombre');
            const pacienteEdadDisplay = document.getElementById('gestion-paciente-edad');
            
            modalBody.innerHTML = '<p>Cargando datos del paciente...</p>';
            pacienteNombreDisplay.textContent = '...';
            modalGestionarPaciente.style.display = 'flex';

            fetch(`get_patient_details.php?id=${pacienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    pacienteNombreDisplay.textContent = data.paciente.nombre_completo;
                    if (data.paciente.edad) { // Nuevo
                        pacienteEdadDisplay.textContent = `${data.paciente.edad} años`;
                    } else {
                        pacienteEdadDisplay.textContent = '';
                    }
                    const nombreEscapado = data.paciente.nombre_completo.replace(/'/g, "\\'");
                    const pacienteEdad = data.paciente.edad; // Obtenemos la edad

                    const historiaBoton = data.tiene_historia
                        ? `<button type="button" class="action-card" onclick='abrirModalVerHistoria(${pacienteId}, ${pacienteEdad})'>
                               <div class="icon-wrapper" style="background-color: #02b1f4;"><i class="fa-solid fa-file-medical"></i></div>
                               <div><h3>Ver Historia Clínica</h3><p>Consulta el expediente completo.</p></div>
                           </button>`
                        : `<button type="button" class="action-card" onclick='abrirModalSeleccionarHistoria(${pacienteId}, "${data.paciente.cedula}", "${nombreEscapado}", ${data.paciente.edad})'>
                               <div class="icon-wrapper" style="background-color: #02b1f4;"><i class="fa-solid fa-file-circle-plus"></i></div>
                               <div><h3>Crear Historia Clínica</h3><p>Inicia un nuevo expediente.</p></div>
                           </button>`;

                    const informeBoton = data.tiene_historia
                        ? `<button type="button" class="action-card" onclick='abrirModalCrearInforme(${pacienteId}, ${JSON.stringify(data.paciente.nombre_completo)}, "${data.paciente.cedula}", ${pacienteEdad})'>
                               <div class="icon-wrapper" style="background-color: #17a2b8;"><i class="fa-solid fa-file-pen"></i></div>
                               <div><h3>Crear Nuevo Informe</h3><p>Redacta un nuevo informe.</p></div>
                           </button>`
                        : `<div class="action-card disabled-card">
                               <div class="icon-wrapper"><i class="fa-solid fa-file-pen"></i></div>
                               <div><h3>Crear Nuevo Informe</h3><p>Requiere historia clínica.</p></div>
                           </div>`;
                    
                    // --- TARJETA DE NOTAS DE SESIÓN CON NUEVO COLOR ---
                    const notasBoton = `
                        <button type="button" class="action-card" onclick='abrirModalGestionarNotas(${pacienteId})'>
                            <div class="icon-wrapper" style="background-color: #29bcd2ff;"><i class="fa-solid fa-notes-medical"></i></div>
                            <div>
                                <h3>Notas de Sesión (${data.total_notas})</h3>
                                <p>Ver y añadir notas de evolución del paciente.</p>
                            </div>
                        </button>
                    `;

                    // --- ORDEN CORREGIDO DE LAS TARJETAS ---
                    modalBody.innerHTML = `
                        <div class="action-grid">
                            ${historiaBoton}
                            <button type="button" class="action-card" onclick="abrirModalVerInformes(${pacienteId}, ${pacienteEdad})">
                                <div class="icon-wrapper" style="background-color: #6f42c1;"><i class="fa-solid fa-folder-open"></i></div>
                                <div><h3>Ver Informes (${data.total_informes})</h3><p>Accede al historial de informes.</p></div>
                            </button>
                            ${notasBoton}
                            ${informeBoton}
                        </div>
                    `;
                })
                .catch(error => {
                    modalBody.innerHTML = `<p style="color: red;">No se pudo cargar la información.</p>`;
                });
        }
    }
    function cerrarModalGestionarPaciente() { if (modalGestionarPaciente) modalGestionarPaciente.style.display = 'none'; }



    // --- NUEVAS FUNCIONES PARA LA MODAL DE NOTAS DE SESIÓN ---
    function abrirModalGestionarNotas(pacienteId) {
        if (modalGestionarNotas) {
            const pacienteNombreDisplay = document.getElementById('notas-paciente-nombre');
            const historialContainer = document.getElementById('historial-notas-container');
            const pacienteIdInput = document.getElementById('notas-paciente-id');

            pacienteIdInput.value = pacienteId;
            pacienteNombreDisplay.textContent = 'Cargando...';
            historialContainer.innerHTML = '<p>Cargando historial...</p>';
            modalGestionarNotas.style.display = 'flex';

            fetch(`get_notas_paciente.php?paciente_id=${pacienteId}`)
                .then(response => response.json())
                .then(data => {
                    pacienteNombreDisplay.textContent = data.paciente_nombre;
                    let notasHtml = '';
                    if (data.notas && data.notas.length > 0) {
                        data.notas.forEach(nota => {
                            notasHtml += `<div class="note-item"><div class="note-header">${nota.fecha_formateada}</div><div class="note-content">${nota.nota.replace(/\n/g, '<br>')}</div></div>`;
                        });
                    } else {
                        notasHtml = '<p>Este paciente no tiene notas de sesión.</p>';
                    }
                    historialContainer.innerHTML = notasHtml;
                })
                .catch(error => {
                    console.error('Error al cargar notas:', error);
                    historialContainer.innerHTML = '<p style="color: red;">Error al cargar el historial.</p>';
                });
        }
    }
    function cerrarModalGestionarNotas() {
        if (modalGestionarNotas) {
            modalGestionarNotas.style.display = 'none';
            document.getElementById('form-guardar-nota').reset();
        }
    }

    // Abrir modal para seleccionar historia clínica
    function abrirModalSeleccionarHistoria(pacienteId, pacienteCedula, pacienteNombre, pacienteEdad) {
        if (modalSeleccionarHistoria) {
            const btnAdulto = document.getElementById('btn-seleccionar-adulto');
            const btnInfantil = document.getElementById('btn-seleccionar-infantil');
            btnAdulto.dataset.pacienteId = pacienteId;
            btnAdulto.dataset.pacienteCedula = pacienteCedula;
            btnAdulto.dataset.pacienteNombre = pacienteNombre;
            btnAdulto.dataset.pacienteEdad = pacienteEdad; // Guardamos la edad


            btnInfantil.dataset.pacienteId = pacienteId;
            btnInfantil.dataset.pacienteCedula = pacienteCedula;
            btnInfantil.dataset.pacienteNombre = pacienteNombre;
            btnInfantil.dataset.pacienteEdad = pacienteEdad; // Guardamos la edad
            cerrarModalGestionarPaciente();
            modalSeleccionarHistoria.style.display = 'flex';
        }
    }
    function cerrarModalSeleccionarHistoria() {
        if (modalSeleccionarHistoria) {
            modalSeleccionarHistoria.style.display = 'none';
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // --- FUNCIONES PARA LA MODAL DE CREAR HISTORIA CLÍNICA ADULTO ---
    function abrirModalCrearHistoria(pacienteId, pacienteCedula, pacienteNombre, pacienteEdad) {
    if (modalCrearHistoria) {
        document.getElementById('historia-paciente-id').value = pacienteId;
        // Asignar cédula sólo si existe y no es '0'
        const cedulaAdulto = (typeof pacienteCedula !== 'undefined' && pacienteCedula !== null) ? String(pacienteCedula) : '';
        if (cedulaAdulto && cedulaAdulto !== '0') {
            document.getElementById('historia-paciente-cedula').value = cedulaAdulto;
            document.getElementById('historia-numero-adulto').value = cedulaAdulto;
        } else {
            document.getElementById('historia-paciente-cedula').value = '';
            document.getElementById('historia-numero-adulto').value = '';
        }
        
        let displayText = `Paciente: ${pacienteNombre}`;
        if (pacienteEdad) {
            displayText += ` (${pacienteEdad} años)`;
        }
        document.getElementById('historia-paciente-nombre-display-header').textContent = displayText;
        
        cerrarModalSeleccionarHistoria();
        modalCrearHistoria.style.display = 'flex';
    }
    }
    function cerrarModalCrearHistoria() {
        if (modalCrearHistoria) {
            modalCrearHistoria.style.display = 'none';
            document.getElementById('form-crear-historia').reset();
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }


    // --- FUNCIONES PARA LA MODAL DE CREAR HISTORIA INFANTIL ---

    function abrirModalCrearHistoriaInfantil(pacienteId, pacienteCedula, pacienteNombre, pacienteEdad) {
    if (modalCrearHistoriaInfantil) {
        document.getElementById('historia-paciente-id-infantil').value = pacienteId;
        // Asignar cédula sólo si existe y no es '0'
        const cedulaInfantil = (typeof pacienteCedula !== 'undefined' && pacienteCedula !== null) ? String(pacienteCedula) : '';
        if (cedulaInfantil && cedulaInfantil !== '0') {
            document.getElementById('ci_infante_modal').value = cedulaInfantil;
            document.getElementById('historia-numero-infantil').value = cedulaInfantil;
        } else {
            document.getElementById('ci_infante_modal').value = '';
            document.getElementById('historia-numero-infantil').value = '';
        }
        
        let displayText = `Paciente: ${pacienteNombre}`;
        if (pacienteEdad) {
            displayText += ` (${pacienteEdad} años)`;
        }
        document.getElementById('historia-paciente-nombre-display-infantil-header').textContent = displayText;
        
        cerrarModalSeleccionarHistoria();
        modalCrearHistoriaInfantil.style.display = 'flex';
    }
    }

    function cerrarModalCrearHistoriaInfantil() {
        if (modalCrearHistoriaInfantil) {
            modalCrearHistoriaInfantil.style.display = 'none';
            document.getElementById('form-crear-historia-infantil').reset();
            document.getElementById('hermanos-container-modal').innerHTML = '';

            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // --- FUNCIONES PARA LA MODAL DE VER INFORMES ---
    // --- FUNCIÓN 2: MODIFICADA PARA RECIBIR Y PASAR LA EDAD ---
    function abrirModalVerInformes(pacienteId, pacienteEdad) {
        if (modalVerInformes) {
            const pacienteNombreDisplay = document.getElementById('informes-paciente-nombre');
            const pacienteEdadDisplay = document.getElementById('informes-paciente-edad');
            const historialContainer = document.getElementById('historial-informes-container');

            pacienteNombreDisplay.textContent = 'Cargando...';
            historialContainer.innerHTML = '<p>Cargando historial...</p>';
            cerrarModalGestionarPaciente();
            modalVerInformes.style.display = 'flex';

            fetch(`get_informes_paciente.php?paciente_id=${pacienteId}`)
                .then(response => response.json())
                .then(data => {
                    let displayText = `Paciente: ${data.paciente_nombre}`;
                    pacienteNombreDisplay.textContent = data.paciente_nombre;
                    if (pacienteEdad) { // Nuevo
                        pacienteEdadDisplay.textContent = `${pacienteEdad} años`;
                    } else {
                        pacienteEdadDisplay.textContent = '';
                    }

                    let informesHtml = '';
                    if (data.informes && data.informes.length > 0) {
                        data.informes.forEach(informe => {
                            informesHtml += `
                                <div class="informe-list-item">
                                    <div class="item-icon"><i class="fa-solid fa-file-alt"></i></div>
                                    <div class="item-info">
                                        <h4>Informe del ${informe.fecha_formateada}</h4>
                                        <p>Motivo: ${informe.motivo}</p>
                                    </div>
                                    <div class="item-actions">
                                        <button class="btn-view-details" onclick='abrirModalInformeDetalle(${informe.id}, ${pacienteEdad})'>Ver Detalles</button>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        informesHtml = '<p>Este paciente no tiene informes registrados.</p>';
                    }
                    historialContainer.innerHTML = informesHtml;
                });
        }
    }

    function cerrarModalVerInformes() {
        if (modalVerInformes) {
            modalVerInformes.style.display = 'none';
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE DETALLE DE INFORME ---
    function abrirModalInformeDetalle(informeId, pacienteEdad) {
        if (modalInformeDetalle) {
            const modalBody = document.getElementById('informe-detalle-body');
            const pacienteNombreDisplay = document.getElementById('informe-detalle-paciente-nombre');
            const modalHeader = modalInformeDetalle.querySelector('.modal-header-premium');

            // Limpiar cualquier botón de acción anterior para evitar duplicados
            const oldActions = modalHeader.querySelector('.modal-header-actions');
            if (oldActions) oldActions.remove();

            modalBody.innerHTML = '<p>Cargando informe...</p>';
            pacienteNombreDisplay.textContent = '';
            modalInformeDetalle.style.display = 'flex';

            fetch(`get_informe_detalle.php?informe_id=${informeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    // --- LÓGICA PARA AÑADIR LOS BOTONES DE ACCIÓN ---
                    const headerActions = document.createElement('div');
                    headerActions.className = 'modal-header-actions';

                    // 1. Botón de Imprimir
                    const printButton = document.createElement('button');
                    printButton.className = 'btn-print-informe';
                    printButton.innerHTML = '<i class="fa-solid fa-print"></i> Imprimir';
                    printButton.onclick = function() {
                        window.print(); // Llama a la función de impresión del navegador
                    };
                    headerActions.appendChild(printButton);

                    // 2. Botón de Borrar
                    const deleteButton = document.createElement('a');
                    deleteButton.className = 'btn-delete-historia';
                    deleteButton.href = `borrar_informe.php?id=${informeId}`;
                    deleteButton.innerHTML = '<i class="fa-solid fa-trash-can"></i> Borrar';
                    deleteButton.onclick = function(event) {
                        if (!confirm('¿Estás seguro de que quieres borrar este informe? Esta acción es irreversible.')) {
                            event.preventDefault();
                        }
                    };
                    headerActions.appendChild(deleteButton);

                    // Insertamos el contenedor de botones en el encabezado
                    modalHeader.appendChild(headerActions);

                    

                    // Función auxiliar para mostrar los datos de forma segura
                    const mostrar = (valor) => valor ? htmlspecialchars(valor) : 'No especificado';
                    const mostrarLargo = (valor) => valor ? nl2br(htmlspecialchars(valor)) : 'No especificado';

                    function htmlspecialchars(str) {
                        if (str === null || typeof str === 'undefined') return '';
                        return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                    }
                    function nl2br(str) {
                        if (str === null || typeof str === 'undefined') return '';
                        return str.replace(/\r\n|\r|\n/g, '<br>');
                    }

                    // Rellenamos el encabezado
                    let displayText = `Paciente: ${data.paciente_nombre}`;
                    if (pacienteEdad) {
                        displayText += ` (${pacienteEdad} años)`;
                    }
                    pacienteNombreDisplay.textContent = displayText;
                    
                    // --- CONSTRUIMOS EL HTML CON TODOS LOS DETALLES, SIN OMITIR NADA ---
                    let informeHtml = `
                        <h3>Datos de Referencia</h3>
                        <div class="dato-item"><strong>N° de Historia:</strong> <p>${mostrar(data.numero_historia)}</p></div>
                        <div class="dato-item"><strong>Fecha de Evaluación:</strong> <p>${mostrar(data.fecha_evaluacion_formateada)}</p></div>
                        <div class="dato-item"><strong>Referido por:</strong> <p>${mostrar(data.referido_por)}</p></div>

                        <h3>Evaluación</h3>
                        <div class="dato-item"><strong>Motivo de la Referencia:</strong> <p>${mostrarLargo(data.motivo_referencia)}</p></div>
                        <div class="dato-item"><strong>Actitud ante la Evaluación:</strong> <p>${mostrarLargo(data.actitud_ante_evaluacion)}</p></div>
                        
                        <h3>Resultados</h3>
                        <div class="dato-item"><strong>Área Visomotriz:</strong> <p>${mostrarLargo(data.area_visomotriz)}</p></div>
                        <div class="dato-item"><strong>Área Intelectual:</strong> <p>${mostrarLargo(data.area_intelectual)}</p></div>
                        <div class="dato-item"><strong>Área Emocional:</strong> <p>${mostrarLargo(data.area_emocional)}</p></div>
                        <div class="dato-item"><strong>Otros Resultados Relevantes:</strong> <p>${mostrarLargo(data.resultados_adicionales)}</p></div>
                        
                        <h3>Recomendaciones</h3>
                        <div class="dato-item"><strong>Recomendaciones:</strong> <p>${mostrarLargo(data.recomendaciones)}</p></div>
                    `;
                    modalBody.innerHTML = informeHtml;
                });
        }
    }
    function cerrarModalInformeDetalle() {
        if (modalInformeDetalle) {
            modalInformeDetalle.style.display = 'none';
        }
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE CREAR INFORME ---
    function abrirModalCrearInforme(pacienteId, pacienteNombre, pacienteCedula, pacienteEdad) {
        if (modalCrearInforme) {
            document.getElementById('informe-paciente-id').value = pacienteId;
            
            // Construimos el texto del encabezado, añadiendo la edad solo si existe
            let displayText = `Paciente: ${pacienteNombre}`;
            if (pacienteEdad) {
                displayText += ` (${pacienteEdad} años)`;
            }
            document.getElementById('informe-paciente-nombre-display').textContent = displayText;
            document.getElementById('informe-numero-historia').value = pacienteCedula;

            
            
            cerrarModalGestionarPaciente(); // Cierra la modal anterior
            modalCrearInforme.style.display = 'flex';
        }
    }

    function cerrarModalCrearInforme() {
        if (modalCrearInforme) {
            modalCrearInforme.style.display = 'none';
            document.getElementById('form-crear-informe').reset();
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE VER HISTORIA CLÍNICA ---
    function abrirModalVerHistoria(pacienteId, pacienteEdad) {
        if (modalVerHistoria) {
            const modalBody = document.getElementById('ver-historia-body');
            const modalTitulo = document.getElementById('ver-historia-titulo');
            const pacienteNombreDisplay = document.getElementById('ver-historia-paciente-nombre');
            const modalHeader = modalVerHistoria.querySelector('.modal-header-premium');

            // Limpiar cualquier botón de acción anterior (borrar/editar) para evitar duplicados
            const oldActionBtn = document.getElementById('btn-borrar-historia') || document.getElementById('btn-editar-historia');
            if (oldActionBtn) oldActionBtn.remove();

            modalBody.innerHTML = '<p>Cargando historial...</p>';
            modalTitulo.textContent = 'Historia Clínica';
            pacienteNombreDisplay.textContent = '...';
            cerrarModalGestionarPaciente();
            modalVerHistoria.style.display = 'flex';

            fetch(`get_historia_clinica.php?paciente_id=${pacienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.tipo || !data.datos) {
                        modalBody.innerHTML = '<p>Este paciente no tiene una historia clínica registrada.</p>';
                        return;
                    }
                    
                    // --- LÓGICA PARA AÑADIR EL BOTÓN DE EDITAR ---
                    const editButton = document.createElement('a');
                    editButton.id = 'btn-editar-historia';
                    editButton.className = 'btn-edit-historia';
                    editButton.href = `editar_historia.php?historia_id=${data.datos.id}&tipo=${data.tipo}&paciente_id=${pacienteId}`;
                    editButton.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Editar Historia';
                    // Insertamos el botón en el encabezado
                    modalHeader.appendChild(editButton);
                    
                    const nombrePaciente = document.getElementById('gestion-paciente-nombre').textContent;
                    // Construimos el texto del encabezado, añadiendo la edad solo si existe
                    let displayText = `Paciente: ${nombrePaciente}`;
                    if (pacienteEdad) {
                        displayText += ` (${pacienteEdad} años)`;
                    }
                    pacienteNombreDisplay.textContent = displayText;
                    
                    let historiaHtml = '';
                    const datos = data.datos;

                    // Función auxiliar para mostrar los datos de forma segura
                    const mostrar = (valor) => valor ? htmlspecialchars(valor) : 'No especificado';
                    const mostrarLargo = (valor) => valor ? nl2br(htmlspecialchars(valor)) : 'No especificado';

                    function htmlspecialchars(str) {
                        return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                    }
                    function nl2br(str) {
                        return str.replace(/\r\n|\r|\n/g, '<br>');
                    }

                    if (data.tipo === 'adulto') {
                        modalTitulo.textContent = 'Historia Clínica de Adulto';
                        historiaHtml = `
                            <h3>Datos Generales</h3>
                            <div class="dato-item"><strong>N° de Historia:</strong> <p>${mostrar(datos.numero_historia)}</p></div>
                            <div class="dato-item"><strong>Centro de Salud:</strong> <p>${mostrar(datos.centro_salud)}</p></div>
                            <div class="dato-item"><strong>Fecha:</strong> <p>${new Date(datos.fecha).toLocaleDateString('es-ES')}</p></div>
                            <h3>Datos Personales</h3>
                            <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(datos.ci_paciente)}</p></div>
                            <div class="dato-item"><strong>Sexo:</strong> <p>${mostrar(datos.sexo)}</p></div>
                            <div class="dato-item"><strong>Teléfono:</strong> <p>${mostrar(datos.telefono)}</p></div>
                            <div class="dato-item"><strong>Edo. Civil:</strong> <p>${mostrar(datos.estado_civil)}</p></div>
                            <div class="dato-item"><strong>Nacionalidad:</strong> <p>${mostrar(datos.nacionalidad)}</p></div>
                            <div class="dato-item"><strong>Hijos:</strong> <p>${mostrar(datos.hijos)}</p></div>
                            <div class="dato-item"><strong>Religión:</strong> <p>${mostrar(datos.religion)}</p></div>
                            <div class="dato-item"><strong>Grado de Instrucción:</strong> <p>${mostrar(datos.grado_instruccion)}</p></div>
                            <div class="dato-item"><strong>Ocupación:</strong> <p>${mostrar(datos.ocupacion)}</p></div>
                            <div class="dato-item"><strong>Dirección:</strong> <p>${mostrarLargo(datos.direccion)}</p></div>
                            <h3>Motivo y Antecedentes</h3>
                            <div class="dato-item"><strong>Motivo de Consulta:</strong> <p>${mostrarLargo(datos.motivo_consulta)}</p></div>
                            <div class="dato-item"><strong>Antecedentes Personales:</strong> <p>${mostrarLargo(datos.antecedentes_personales)}</p></div>
                            <div class="dato-item"><strong>Antecedentes Familiares:</strong> <p>${mostrarLargo(datos.antecedentes_familiares)}</p></div>
                            <div class="dato-item"><strong>Antecedentes Psiquiátricos:</strong> <p>${mostrarLargo(datos.antecedentes_psiquiatricos)}</p></div>
                            <div class="dato-item"><strong>Antecedentes Médicos:</strong> <p>${mostrarLargo(datos.antecedentes_medicos)}</p></div>
                            <div class="dato-item"><strong>Antecedentes de Pareja:</strong> <p>${mostrarLargo(datos.antecedentes_pareja)}</p></div>
                            <h3>Diagnóstico</h3>
                            <div class="dato-item"><strong>Impresión Diagnóstica:</strong> <p>${mostrarLargo(datos.impresion_diagnostica)}</p></div>
                        `;
                    } else if (data.tipo === 'infantil') {
                        modalTitulo.textContent = 'Historia Clínica Infantil';
                        let hermanosHtml = '<p>No se registraron hermanos.</p>';
                        if (datos.hermanos) {
                            try {
                                const hermanos = JSON.parse(datos.hermanos);
                                if (hermanos && hermanos.length > 0) {
                                    hermanosHtml = '<ul>';
                                    hermanos.forEach(h => {
                                        hermanosHtml += `<li>${mostrar(h.nombre)} (${mostrar(h.edad)} años) - ${mostrar(h.sexo)} - ${mostrar(h.ocupacion)}</li>`;
                                    });
                                    hermanosHtml += '</ul>';
                                }
                            } catch (e) { /* Mantener el mensaje por defecto si el JSON es inválido */ }
                        }
                        historiaHtml = `
                            <h3>Datos Generales</h3>
                            <div class="dato-item"><strong>N° de Historia:</strong> <p>${mostrar(datos.numero_historia)}</p></div>
                            <div class="dato-item"><strong>Centro de Salud:</strong> <p>${mostrar(datos.centro_salud)}</p></div>
                            <div class="dato-item"><strong>Fecha:</strong> <p>${new Date(datos.fecha).toLocaleDateString('es-ES')}</p></div>
                            <h3>Datos Personales del Infante</h3>
                            <div class="dato-item"><strong>Lugar de Nacimiento:</strong> <p>${mostrar(datos.lugar_nacimiento)}</p></div>
                            <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(datos.ci_infante)}</p></div>
                            <div class="dato-item"><strong>Institución Escolar:</strong> <p>${mostrar(datos.institucion_escolar)}</p></div>
                            <h3>Datos del Padre</h3>
                            <div class="dato-item"><strong>Nombre:</strong> <p>${mostrar(datos.padre_nombre)}</p></div>
                            <div class="dato-item"><strong>Edad:</strong> <p>${mostrar(datos.padre_edad)}</p></div>
                            <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(datos.padre_ci)}</p></div>
                            <div class="dato-item"><strong>Nacionalidad:</strong> <p>${mostrar(datos.padre_nacionalidad)}</p></div>
                            <div class="dato-item"><strong>Religión:</strong> <p>${mostrar(datos.padre_religion)}</p></div>
                            <div class="dato-item"><strong>Grado de Instrucción:</strong> <p>${mostrar(datos.padre_instruccion)}</p></div>
                            <div class="dato-item"><strong>Ocupación:</strong> <p>${mostrar(datos.padre_ocupacion)}</p></div>
                            <div class="dato-item"><strong>Teléfono:</strong> <p>${mostrar(datos.padre_telefono)}</p></div>
                            <div class="dato-item"><strong>Dirección:</strong> <p>${mostrarLargo(datos.padre_direccion)}</p></div>
                            <h3>Datos de la Madre</h3>
                            <div class="dato-item"><strong>Nombre:</strong> <p>${mostrar(datos.madre_nombre)}</p></div>
                            <div class="dato-item"><strong>Edad:</strong> <p>${mostrar(datos.madre_edad)}</p></div>
                            <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(datos.madre_ci)}</p></div>
                            <div class="dato-item"><strong>Nacionalidad:</strong> <p>${mostrar(datos.madre_nacionalidad)}</p></div>
                            <div class="dato-item"><strong>Religión:</strong> <p>${mostrar(datos.madre_religion)}</p></div>
                            <div class="dato-item"><strong>Grado de Instrucción:</strong> <p>${mostrar(datos.madre_instruccion)}</p></div>
                            <div class="dato-item"><strong>Ocupación:</strong> <p>${mostrar(datos.madre_ocupacion)}</p></div>
                            <div class="dato-item"><strong>Teléfono:</strong> <p>${mostrar(datos.madre_telefono)}</p></div>
                            <div class="dato-item"><strong>Dirección:</strong> <p>${mostrarLargo(datos.madre_direccion)}</p></div>
                            <h3>Dinámica Familiar</h3>
                            <div class="dato-item"><strong>¿Padres viven juntos?:</strong> <p>${mostrar(datos.padres_viven_juntos)}</p></div>
                            <div class="dato-item"><strong>¿Están casados?:</strong> <p>${mostrar(datos.estan_casados)}</p></div>
                            <div class="dato-item"><strong>Motivo de separación:</strong> <p>${mostrarLargo(datos.motivo_separacion)}</p></div>
                            <div class="dato-item"><strong>Hermanos:</strong> ${hermanosHtml}</div>
                            <h3>Motivos y Antecedentes</h3>
                            <div class="dato-item"><strong>Motivo de Consulta:</strong> <p>${mostrarLargo(datos.motivo_consulta)}</p></div>
                            <div class="dato-item"><strong>Tipo de Embarazo:</strong> <p>${mostrar(datos.antecedentes_embarazo)}</p></div>
                            <div class="dato-item"><strong>Parto (Lugar):</strong> <p>${mostrar(datos.antecedentes_parto)}</p></div>
                            <div class="dato-item"><strong>Estado del niño/a al nacer:</strong> <p>${mostrar(datos.estado_nino_nacer)}</p></div>
                            <div class="dato-item"><strong>Desarrollo Psicomotor:</strong> <p>${mostrarLargo(datos.desarrollo_psicomotor)}</p></div>
                            <div class="dato-item"><strong>Hábitos de Independencia:</strong> <p>${mostrarLargo(datos.habitos_independencia)}</p></div>
                            <div class="dato-item"><strong>Condiciones de Salud:</strong> <p>${mostrarLargo(datos.condiciones_salud)}</p></div>
                            <div class="dato-item"><strong>Vida Social:</strong> <p>${mostrarLargo(datos.vida_social)}</p></div>
                            <h3>Plan Terapéutico</h3>
                            <div class="dato-item"><strong>Plan Psicoterapéutico:</strong> <p>${mostrarLargo(datos.plan_psicoterapeutico)}</p></div>
                        `;
                    }
                    modalBody.innerHTML = historiaHtml;
                });
        }
    }
    function cerrarModalVerHistoria() {
        if (modalVerHistoria) {
            modalVerHistoria.style.display = 'none';
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE ASIGNAR CITA ---
    const modalAsignarCita = document.getElementById('modal-asignar-cita');

    function abrirModalAsignarCita(citaId) {
        if (modalAsignarCita) {
            const pacienteNombreDisplay = document.getElementById('asignar-paciente-nombre');
            const motivoConsultaDisplay = document.getElementById('asignar-motivo-consulta');
            const citaIdInput = document.getElementById('asignar-cita-id');
            const profesionalSolicitadoDisplay = document.getElementById('asignar-profesional-solicitado'); // Nuevo
            const psicologoSelector = document.getElementById('asignar-psicologo-id'); // Nuevo

            pacienteNombreDisplay.textContent = 'Cargando...';
            motivoConsultaDisplay.textContent = '...';
            profesionalSolicitadoDisplay.textContent = '...'; // Nuevo
            citaIdInput.value = citaId;
            modalAsignarCita.style.display = 'flex';

            fetch(`get_cita_details_secretaria.php?cita_id=${citaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        pacienteNombreDisplay.textContent = 'Error';
                        motivoConsultaDisplay.textContent = data.error;
                        return;
                    }
                    pacienteNombreDisplay.textContent = data.paciente_nombre;
                    motivoConsultaDisplay.textContent = data.motivo_consulta;
                    
                    // --- LÓGICA AÑADIDA ---
                    // Mostramos el profesional que el paciente solicitó
                    profesionalSolicitadoDisplay.textContent = data.profesional_solicitado_nombre || 'No especificado';
                    // Pre-seleccionamos a ese profesional en el combo box
                    psicologoSelector.value = data.profesional_solicitado_id;
                });
        }
    }
    function cerrarModalAsignarCita() {
        if (modalAsignarCita) {
            modalAsignarCita.style.display = 'none';
            document.getElementById('form-asignar-cita').reset();
        }
    }


    // --- NUEVAS FUNCIONES PARA LA MODAL DE DETALLES DE CITA ---
    const modalDetalleCitaPaciente = document.getElementById('modal-detalle-cita-paciente');

    function abrirModalDetalleCitaPaciente(citaId) {
        if (modalDetalleCitaPaciente) {
            const modalBody = document.getElementById('detalle-cita-body');
            const fechaDisplay = document.getElementById('detalle-cita-fecha');

            modalBody.innerHTML = '<p>Cargando detalles...</p>';
            fechaDisplay.textContent = '...';
            modalDetalleCitaPaciente.style.display = 'flex';

            fetch(`get_cita_details_paciente.php?id=${citaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    fechaDisplay.textContent = `Cita para el ${data.fecha_cita_formateada}`;
                    
                    const mostrar = (valor) => valor || 'No especificado';
                    const mostrarLargo = (valor) => valor ? valor.replace(/\n/g, '<br>') : 'No especificado';

                    let detalleHtml = '';

                    // --- LÓGICA CORREGIDA Y DEFINITIVA PARA CITAS POSPUESTAS ---
                    // Se muestra si el estado es 'pendiente_paciente' O 'reprogramada'
                    if (data.estado === 'pendiente_paciente' || data.estado === 'reprogramada') {
                        detalleHtml += `
                            <div class="reprogramacion-info">
                                <h4>¡Atención! El profesional ha propuesto una nueva fecha</h4>
                                <p><strong>Nueva Fecha Sugerida:</strong> ${data.fecha_propuesta_formateada}</p>
                                <p><strong>Motivo:</strong> <em>"${mostrarLargo(data.reprogramacion_motivo)}"</em></p>
                            </div>
                            <div class="modal-actions-propuesta">
                                <a href="gestionar_propuesta.php?cita_id=${data.id}&accion=rechazar" class="btn-secondary">Rechazar Propuesta</a>
                                <a href="gestionar_propuesta.php?cita_id=${data.id}&accion=aceptar" class="btn-submit">Aceptar Nueva Fecha</a>
                            </div>
                        `;
                    }

                    detalleHtml += `
                        <h3>Detalles de la Consulta</h3>
                        <div class="dato-item"><strong>Tipo de Cita:</strong> <p>${mostrar(data.tipo_cita)}</p></div>
                        <div class="dato-item"><strong>Modalidad:</strong> <p>${mostrar(data.modalidad)}</p></div>
                        <div class="dato-item"><strong>Motivo Principal:</strong> <p>${mostrar(data.motivo_principal)}</p></div>
                        <div class="dato-item"><strong>Descripción Adicional:</strong> <p>${mostrarLargo(data.motivo_consulta)}</p></div>

                        <h3>Profesional y Preferencias</h3>
                        <div class="dato-item"><strong>Especialidad Requerida:</strong> <p>${mostrar(data.profesional_rol)}</p></div>
                        <div class="dato-item"><strong>Profesional Asignado:</strong> <p>${mostrar(data.profesional_nombre)}</p></div>
                        <div class="dato-item"><strong>Notas Adicionales:</strong> <p>${mostrar(data.notas_paciente)}</p></div>
                    `;

                    modalBody.innerHTML = detalleHtml;
                });
        }
    }
    function cerrarModalDetalleCitaPaciente() {
        if (modalDetalleCitaPaciente) {
            modalDetalleCitaPaciente.style.display = 'none';
        }
    }


    // --- NUEVAS FUNCIONES PARA LA MODAL DE DETALLES DE PROFESIONAL ---
    const modalProfesionalDetalle = document.getElementById('modal-profesional-detalle');

    function abrirModalProfesionalDetalle(profesionalId) {
        if (modalProfesionalDetalle) {
            const modalBody = document.getElementById('profesional-detalle-body');
            const nombreDisplay = document.getElementById('profesional-detalle-nombre');
            const rolDisplay = document.getElementById('profesional-detalle-rol');

            modalBody.innerHTML = '<p>Cargando...</p>';
            nombreDisplay.textContent = '...';
            rolDisplay.textContent = '...';
            modalProfesionalDetalle.style.display = 'flex';

            fetch(`get_professional_details.php?id=${profesionalId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    nombreDisplay.textContent = data.nombre_completo;
                    rolDisplay.textContent = data.rol_formateado;
                    
                    const mostrar = (valor) => valor || 'No especificado';

                    let detalleHtml = `
                        <div class="dato-item"><strong>Nombre Completo:</strong> <p>${mostrar(data.nombre_completo)}</p></div>
                        <div class="dato-item"><strong>Cédula:</strong> <p>${mostrar(data.cedula)}</p></div>
                        <div class="dato-item"><strong>Correo Electrónico:</strong> <p>${mostrar(data.correo)}</p></div>
                        <div class="dato-item"><strong>Especialidades:</strong> <p>${mostrar(data.especialidades)}</p></div>
                        <div class="dato-item"><strong>Estado de la Cuenta:</strong> <p>${mostrar(data.estado_formateado)}</p></div>
                        <div class="dato-item"><strong>Miembro desde:</strong> <p>${mostrar(data.fecha_registro_formateada)}</p></div>
                    `;
                    modalBody.innerHTML = detalleHtml;
                });
        }
    }
    function cerrarModalProfesionalDetalle() {
        if (modalProfesionalDetalle) {
            modalProfesionalDetalle.style.display = 'none';
        }
    }


    // --- NUEVAS FUNCIONES PARA LA MODAL DE ÉXITO ---
    const modalExitoPaciente = document.getElementById('modal-exito-paciente');

    function abrirModalExitoPaciente(nombre, password) {
        if (modalExitoPaciente) {
            document.getElementById('exito-paciente-nombre').textContent = nombre;
            document.getElementById('exito-paciente-password').textContent = password;
            modalExitoPaciente.style.display = 'flex';
        }
    }
    function cerrarModalExitoPaciente() {
        if (modalExitoPaciente) {
            modalExitoPaciente.style.display = 'none';
        }
    }


    // --- FUNCIONES PARA LAS MODALES (ABRIR/CERRAR) ---
    // (Aquí van todas tus funciones para abrir y cerrar las modales que ya funcionan)
    function abrirModalSolicitudDetalle(citaId) {
        if (modalSolicitudDetalle) {
            const modalBody = document.getElementById('solicitud-detalle-body');
            const pacienteNombreDisplay = document.getElementById('solicitud-paciente-nombre');

            modalBody.innerHTML = '<p>Cargando...</p>';
            pacienteNombreDisplay.textContent = '...';
            modalSolicitudDetalle.style.display = 'flex';

            fetch(`get_solicitud_details.php?id=${citaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    pacienteNombreDisplay.textContent = 'Paciente: ' + data.paciente_nombre;
                    const mostrar = (valor) => valor || 'No especificado';

                    // --- HTML ACTUALIZADO CON LOS NUEVOS CAMPOS ---
                    let detalleHtml = `
                        <h3>Datos del Paciente</h3>
                        <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(data.paciente_cedula)}</p></div>
                        <div class="dato-item"><strong>Edad:</strong> <p>${mostrar(data.paciente_edad)} años</p></div>

                        <h3>Detalles de la Consulta</h3>
                        <div class="dato-item"><strong>Tipo de Cita:</strong> <p>${mostrar(data.tipo_cita_formateado)}</p></div>
                        <div class="dato-item"><strong>Modalidad:</strong> <p>${mostrar(data.modalidad_formateada)}</p></div>
                        <div class="dato-item"><strong>Motivo Principal:</strong> <p>${mostrar(data.motivo_principal)}</p></div>
                        <div class="dato-item"><strong>Descripción Adicional:</strong> <p>${mostrar(data.motivo_consulta)}</p></div>
                        <div class="dato-item"><strong>Notas del Paciente:</strong> <p>${mostrar(data.notas_paciente)}</p></div>
                        <div class="dato-item"><strong>Fecha y Hora Solicitada:</strong> <p>${data.fecha_solicitada_formateada}</p></div>
                    `;
                    modalBody.innerHTML = detalleHtml;
                });
        }
    }
    function cerrarModalSolicitudDetalle() {
        if (modalSolicitudDetalle) {
            modalSolicitudDetalle.style.display = 'none';
        }
    }





    // --- FUNCIONES PARA LA MODAL DE CONFLICTO ---
    const modalConflictoCita = document.getElementById('modal-conflicto-cita');

    function abrirModalConflicto(citaId, pacienteNombre) {
        if (modalConflictoCita) {
            const proponerBtn = document.getElementById('btn-proponer-fecha-conflicto');
            
            // Quitamos el enlace y añadimos un evento de clic
            proponerBtn.removeAttribute('href');
            proponerBtn.onclick = function() {
                cerrarModalConflicto();
                abrirModalProponerFecha(citaId, pacienteNombre);
            };

            modalConflictoCita.style.display = 'flex';
        }
    }
    function cerrarModalConflicto() {
        if (modalConflictoCita) {
            modalConflictoCita.style.display = 'none';
        }
    }

    // --- FUNCIÓN MODIFICADA PARA VERIFICAR ANTES DE CONFIRMAR ---
    function intentarConfirmarCita(citaId) {
        fetch(`check_conflict.php?cita_id=${citaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.conflict) {
                    // Si hay conflicto, abrimos la modal de advertencia y le pasamos el nombre
                    abrirModalConflicto(citaId, data.paciente_nombre);
                } else {
                    // Si no hay conflicto, procedemos a confirmar la cita
                    window.location.href = `confirmar_cita.php?cita_id=${citaId}`;
                }
            })
            .catch(error => console.error('Error al verificar conflicto:', error));
    }



    // --- MODAL DETALLES DE HISTORIAL (PSICÓLOGO) ---
    const modalHistorialDetalle = document.getElementById('modal-historial-detalle');
    function abrirModalHistorialDetalle(citaId) {
        if (modalHistorialDetalle) {
            const modalBody = document.getElementById('historial-detalle-body');
            const pacienteNombreDisplay = document.getElementById('historial-detalle-paciente-nombre');
            
            pacienteNombreDisplay.textContent = 'Cargando...';
            modalBody.innerHTML = '<p>Cargando detalles...</p>';
            modalHistorialDetalle.style.display = 'flex';

            fetch(`get_cita_details_psicologo.php?cita_id=${citaId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const cita = result.data;
                        pacienteNombreDisplay.textContent = cita.paciente_nombre;

                        const fechaFormateada = new Date(cita.fecha_cita).toLocaleString('es-VE', { dateStyle: 'long', timeStyle: 'short' });
                        let estado_texto = cita.estado.replace('_', ' ');

                        let contenidoHTML = `
                            <div class="detalle-grid">
                                <div class="detalle-item"><strong>Paciente:</strong><span>${cita.paciente_nombre}</span></div>
                                <div class="detalle-item"><strong>Cédula:</strong><span>${cita.paciente_cedula}</span></div>
                                <div class="detalle-item"><strong>Fecha y Hora:</strong><span>${fechaFormateada}</span></div>
                                <div class="detalle-item"><strong>Estado Actual:</strong><span><span class="status-badge status-${cita.estado}">${estado_texto}</span></span></div>
                            </div>
                            <div class="detalle-item-full"><strong>Motivo de Consulta:</strong><p>${cita.motivo_consulta}</p></div>
                        `;
                        
                        const fechaCita = new Date(cita.fecha_cita);
                        const ahora = new Date();

                        if (fechaCita < ahora && (cita.estado === 'confirmada' || cita.estado === 'reprogramada')) {
                            contenidoHTML += `
                                <div class="modal-actions" style="justify-content: center;">
                                    <button class="btn-submit" onclick="marcarCitaComoCompletada(${cita.id})">
                                        <i class="fa-solid fa-check"></i> Marcar como Completada
                                    </button>
                                </div>
                            `;
                        }
                        
                        modalBody.innerHTML = contenidoHTML;
                    } else {
                        modalBody.innerHTML = `<p style="color:red;">${result.message}</p>`;
                    }
                });
        }
    }

    function cerrarModalHistorialDetalle() {
        if (modalHistorialDetalle) {
            modalHistorialDetalle.style.display = 'none';
        }
    }

    function marcarCitaComoCompletada(citaId) {
        if (confirm('¿Estás seguro de que quieres marcar esta cita como completada?')) {
            fetch(`marcar_completada.php?cita_id=${citaId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        cerrarModalHistorialDetalle();
                        const buscador = document.getElementById('buscador-historial-citas');
                        if (buscador) {
                            buscarHistorialPsicologo(buscador.value); // Recargar la tabla
                        }
                    } else {
                        alert('Error: No se pudo completar la cita.');
                    }
                });
        }
    }

    

    // --- CÓDIGO QUE SE EJECUTA UNA SOLA VEZ CUANDO LA PÁGINA CARGA ---
    document.addEventListener('DOMContentLoaded', function() {

        // --- NUEVA LÓGICA PARA LA VALIDACIÓN DE CONTRASEÑA EN EL PERFIL ---
        const nuevaPassword = document.getElementById("nueva_contrasena");
        const confirmarNuevaPassword = document.getElementById("confirmar_nueva_contrasena");

        function validateNewPassword(){
          if(nuevaPassword.value !== confirmarNuevaPassword.value) {
            confirmarNuevaPassword.setCustomValidity("Las contraseñas no coinciden.");
          } else {
            confirmarNuevaPassword.setCustomValidity('');
          }
        }
        
        if (nuevaPassword && confirmarNuevaPassword) {
            nuevaPassword.onchange = validateNewPassword;
            confirmarNuevaPassword.onkeyup = validateNewPassword;
        }

        // --- LÓGICA DE BÚSQUEDA Y CARGA DE TABLAS DINÁMICAS ---
        const buscadorHistorial = document.getElementById('buscador-historial-citas');
        const tablaHistorialContainer = document.getElementById('tabla-historial-citas-container');
        
        window.buscarHistorialPsicologo = function(query) {
             if (!tablaHistorialContainer) return;
             fetch('buscar_historial_citas.php', {
                 method: 'POST',
                 headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                 body: 'query=' + encodeURIComponent(query)
             })
             .then(response => response.text())
             .then(data => { tablaHistorialContainer.innerHTML = data; });
        }
        
        if (buscadorHistorial) {
            buscarHistorialPsicologo(''); // Carga inicial
            buscadorHistorial.addEventListener('keyup', function() {
                buscarHistorialPsicologo(this.value);
            });
        }

        // --- LÓGICA PARA HACER CLICABLE LA TABLA DE HISTORIAL DE CITAS ---
        if (tablaHistorialContainer) {
            tablaHistorialContainer.addEventListener('click', function(event) {
                const row = event.target.closest('tr.clickable-row');
                if (row && row.dataset.citaId) {
                    const citaId = row.dataset.citaId;
                    abrirModalHistorialDetalle(citaId);
                }
            });
        }







        

        // --- NUEVA LÓGICA PARA HACER CLICABLE LAS TARJETAS DE PRÓXIMAS CITAS ---
        const proximasCitasContainer = document.querySelector('#vista-proximas-citas .appointments-list-premium');
        if (proximasCitasContainer) {
            proximasCitasContainer.addEventListener('click', function(event) {
                // Si el clic fue directamente en un botón de acción, no hacemos nada.
                if (event.target.closest('.actions-pro')) {
                    return;
                }

                // Si el clic fue en cualquier otra parte de la tarjeta, abrimos la modal de detalles.
                const card = event.target.closest('.appointment-card-pro');
                if (card && card.dataset.citaId) {
                    const citaId = card.dataset.citaId;
                    abrirModalSolicitudDetalle(citaId);
                }
            });
        }

        // --- LÓGICA PARA HACER CLICABLE LA TABLA DE SOLICITUDES ---
        const tablaSolicitudesContainer = document.getElementById('vista-citas');
        if (tablaSolicitudesContainer) {
            tablaSolicitudesContainer.addEventListener('click', function(event) {
                // Si el clic fue directamente en un botón de acción, no hacemos nada.
                if (event.target.closest('.action-links')) {
                    return;
                }

                // Si el clic fue en cualquier otra parte de la fila, abrimos la modal.
                const row = event.target.closest('tr');
                if (row && row.dataset.citaId) {
                    const citaId = row.dataset.citaId;
                    abrirModalSolicitudDetalle(citaId);
                }
            });
        }

        

        // --- NUEVA LÓGICA PARA EL CAMPO DE TELÉFONO COMPUESTO ---
        const phoneTypeSelect = document.getElementById('telefono_tipo_modal');
        const phoneCodeSelect = document.getElementById('telefono_codigo_modal');
        const phoneInput = document.getElementById('telefono_numero_modal');

        const mobileCodes = ['+58 412', '+58 414', '+58 416', '+58 424', '+58 426'];
        const landlineCodes = ['+58 212', '+58 241', '+58 243', '+58 251', '+58 261']; // Caracas, Valencia, Maracay, Bqto, etc.

        function updatePhoneCodes() {
            const selectedType = phoneTypeSelect.value;
            phoneCodeSelect.innerHTML = ''; // Limpiar opciones anteriores

            const codes = (selectedType === 'fijo') ? landlineCodes : mobileCodes;
            
            // Cambiamos el maxlength para el número
            phoneInput.maxLength = 7; // Tanto fijos como móviles en Vzla tienen 7 dígitos

            codes.forEach(code => {
                const option = document.createElement('option');
                option.value = code;
                option.textContent = code;
                phoneCodeSelect.appendChild(option);
            });
        }

        if (phoneTypeSelect) {
            phoneTypeSelect.addEventListener('change', updatePhoneCodes);
            // Carga inicial de los códigos
            updatePhoneCodes();
        }
        

        // --- LÓGICA PARA EL ACORDEÓN DE PREGUNTAS FRECUENTES ---
        const faqQuestions = document.querySelectorAll('.faq-question');
        faqQuestions.forEach(button => {
            button.addEventListener('click', () => {
                const answer = button.nextElementSibling;
                button.classList.toggle('active');

                if (button.classList.contains('active')) {
                    answer.style.maxHeight = answer.scrollHeight + "px";
                    answer.style.paddingTop = "20px";
                } else {
                    answer.style.maxHeight = 0;
                    answer.style.paddingTop = "0";
                }
            });
        });

        // --- LÓGICA DEL GRÁFICO DE FRECUENCIA DE CITAS (PACIENTE) ---
        const patientChartCanvas = document.getElementById('patientCitasChart');
        if (patientChartCanvas) {
            fetch('get_patient_chart_data.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(patientChartCanvas, {
                        type: 'line', // Tipo de gráfico: línea
                        data: {
                            labels: chartData.labels, // ['Mar', 'Abr', ...]
                            datasets: [{
                                // --- LÍNEA CORREGIDA ---
                                label: 'Citas Confirmadas',
                                data: chartData.data, // [2, 4, 3, ...]
                                fill: true,
                                backgroundColor: 'rgba(2, 177, 244, 0.1)',
                                borderColor: '#02b1f4',
                                tension: 0.4, // Hace la línea curva
                                pointBackgroundColor: '#02b1f4',
                                pointRadius: 5
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
                .catch(error => console.error('Error al cargar datos del gráfico del paciente:', error));
        }


        // --- LÓGICA PARA EL FORMULARIO MODAL DE ASIGNAR CITA ---
        const formAsignarCita = document.getElementById('form-asignar-cita');
        if(formAsignarCita) {
            flatpickr("#calendario-asignar", {
                enableTime: true, dateFormat: "Y-m-d H:i", altInput: true,
                altFormat: "d/m/Y h:i K", locale: "es", minuteIncrement: 15,
            });

            formAsignarCita.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_cita.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalAsignarCita();
                        alert('¡Cita programada con éxito!');
                        window.location.reload(); // Recargamos para actualizar la lista
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo programar la cita.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Confirmar Cita';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA DEL BUSCADOR DE HISTORIAL DE CITAS (SECRETARIA) ---
        const buscadorHistorialSecretaria = document.getElementById('buscador-historial-secretaria');
        const contenedorTablaHistorialSecretaria = document.getElementById('tabla-historial-secretaria-container');

        function buscarHistorialGeneral(query) {
            if (!contenedorTablaHistorialSecretaria) return;
            fetch('buscar_citas_secretaria.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'query=' + encodeURIComponent(query)
            })
            .then(response => response.text())
            .then(data => {
                contenedorTablaHistorialSecretaria.innerHTML = data;
            })
            .catch(error => console.error('Error en la búsqueda del historial:', error));
        }

        if (buscadorHistorialSecretaria) {
            // Carga inicial de la tabla
            buscarHistorialGeneral('');
            
            // Búsqueda en tiempo real al escribir
            buscadorHistorialSecretaria.addEventListener('keyup', function() {
                buscarHistorialGeneral(this.value);
            });
        }


                // --- VALIDACIÓN DE CAMPOS EN TIEMPO REAL PARA MODAL DE HISTORIA INFANTIL ---
        const formHistoriaInfantil = document.getElementById('form-crear-historia-infantil');
        if (formHistoriaInfantil) {
            formHistoriaInfantil.addEventListener('keydown', function(event) {
                const target = event.target;
                
                // Permitir teclas de control como Backspace, Tab, Flechas, etc.
                if (event.key.length > 1) {
                    return;
                }

                // Validación para campos que solo aceptan números
                if (target.classList.contains('validate-numeric')) {
                    if (!/^[0-9]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }

                // Validación para campos que solo aceptan texto (letras y espacios)
                if (target.classList.contains('validate-text-only')) {
                    if (!/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }
            });
        }

        // --- VALIDACIÓN DE CAMPOS EN TIEMPO REAL PARA MODAL DE HISTORIA DE ADULTO ---
        const formHistoriaAdulto = document.getElementById('form-crear-historia');
        if (formHistoriaAdulto) {
            formHistoriaAdulto.addEventListener('keydown', function(event) {
                const target = event.target;
                
                // Permitir teclas de control como Backspace, Tab, Flechas, etc.
                if (event.key.length > 1) {
                    return;
                }

                // Validación para campos que solo aceptan números
                if (target.classList.contains('validate-numeric')) {
                    if (!/^[0-9]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }

                // Validación para campos que solo aceptan texto (letras y espacios)
                if (target.classList.contains('validate-text-only')) {
                    if (!/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }
            });
        }


        // --- NUEVA LÓGICA PARA LOS BOTONES DE SELECCIÓN DE HISTORIA ---
        const btnSeleccionarAdulto = document.getElementById('btn-seleccionar-adulto');
        const btnSeleccionarInfantil = document.getElementById('btn-seleccionar-infantil');


        // --- LÓGICA PARA LOS BOTONES DE SELECCIÓN DE HISTORIA ---


        // Botón de seleccionar historia adulta
        if (btnSeleccionarAdulto) {
            btnSeleccionarAdulto.addEventListener('click', function() {
                const edad = parseInt(this.dataset.pacienteEdad, 10);
                if (edad < 18) {
                    alert('Error: Este paciente es menor de edad. Debes crear una historia clínica infantil.');
                    return;
                }
                
                const pacienteId = this.dataset.pacienteId;
                const pacienteCedula = this.dataset.pacienteCedula;
                const pacienteNombre = this.dataset.pacienteNombre;
                const pacienteEdad = this.dataset.pacienteEdad;
                
                abrirModalCrearHistoria(pacienteId, pacienteCedula, pacienteNombre, pacienteEdad);
            });
        }

        // Botón de seleccionar historia infantil
        if (btnSeleccionarInfantil) {
            btnSeleccionarInfantil.addEventListener('click', function() {
                const edad = parseInt(this.dataset.pacienteEdad, 10);
                if (edad >= 18) {
                    alert('Error: Este paciente es mayor de edad. Debes crear una historia clínica de adulto.');
                    return;
                }
                
                const pacienteId = this.dataset.pacienteId;
                const pacienteCedula = this.dataset.pacienteCedula;
                const pacienteNombre = this.dataset.pacienteNombre;
                const pacienteEdad = this.dataset.pacienteEdad;
                
                abrirModalCrearHistoriaInfantil(pacienteId, pacienteCedula, pacienteNombre, pacienteEdad);
            });
        }

// --- LÓGICA PARA EL FORMULARIO MODAL DE CREAR HISTORIA (ADULTO) ---
const formCrearHistoria = document.getElementById('form-crear-historia');
if (formCrearHistoria) {
    formCrearHistoria.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        
        // 1. CAPTURAMOS EL ID DEL PACIENTE AQUÍ (igual que en el infantil)
        const pacienteId = formData.get('paciente_id'); 
        
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.textContent = 'Guardando...';
        submitButton.disabled = true;

        // Verificar que paciente_id tiene valor
        if (!pacienteId) {
            alert('Error: No se encontró el ID del paciente.');
            // Reactivar botón si hay error
            submitButton.textContent = 'Guardar Historia';
            submitButton.disabled = false;
            return; 
        }

        console.log('Enviando paciente_id (Adulto):', pacienteId);

        fetch('guardar_historia.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            console.log(data);
            if (data.success) {
                // 2. LLAMAMOS A LAS MISMAS FUNCIONES QUE EN EL INFANTIL

                // Asumo que tienes una función para cerrar el modal de adulto.
                // Si no, puedes usar: $('#tu-modal-de-adulto').modal('hide');
                cerrarModalCrearHistoria(); // O el nombre que tenga tu función para cerrar este modal.

                alert('¡Historia clínica de adulto guardada con éxito!');
                
                // Usamos la misma función para abrir el modal de gestión, pasándole el ID.
                abrirModalGestionarPaciente(pacienteId);

            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => console.error('Error en fetch:', err))
        .finally(() => {
            submitButton.textContent = 'Guardar Historia';
            submitButton.disabled = false;
        });
    });
}


        // --- LÓGICA PARA LOS FORMULARIO INFANTIL ---
        const formCrearHistoriaInfantil = document.getElementById('form-crear-historia-infantil');
        if (formCrearHistoriaInfantil) {
            formCrearHistoriaInfantil.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const pacienteId = formData.get('paciente_id');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_historia.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalCrearHistoriaInfantil();
                        alert('¡Historia clínica infantil guardada con éxito!');
                        abrirModalGestionarPaciente(pacienteId);
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo guardar la historia.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Historia Infantil';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA PARA AÑADIR HERMANOS EN LA MODAL ---
        const addHermanoBtnModal = document.getElementById('add-hermano-btn-modal');
        const hermanosContainerModal = document.getElementById('hermanos-container-modal');
        if (addHermanoBtnModal) {
            addHermanoBtnModal.addEventListener('click', function() {
                const hermanoDiv = document.createElement('div');
                hermanoDiv.className = 'hermano-entry form-grid';
                hermanoDiv.style.cssText = 'margin-bottom: 15px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; position: relative;';
                hermanoDiv.innerHTML = `
                    <div class="form-group"><label>Nombre:</label><input type="text" name="hermano_nombre[]"></div>
                    <div class="form-group"><label>Edad:</label><input type="number" name="hermano_edad[]"></div>
                    <div class="form-group"><label>Sexo:</label><input type="text" name="hermano_sexo[]"></div>
                    <div class="form-group"><label>Ocupación:</label><input type="text" name="hermano_ocupacion[]"></div>
                    <div class="form-group">
    <label>¿Vive en casa?:</label>
    <select name="hermano_vive_hogar[]" required>
        <option value="Sí">Sí</option>
        <option value="No">No</option>
    </select>
</div>
                    <button type="button" class="remove-hermano-btn" onclick="this.closest('.hermano-entry').remove()"><i class="fa-solid fa-trash-can"></i></button>
                `;
                hermanosContainerModal.appendChild(hermanoDiv);
            });
        }


        // --- LÓGICA PARA LA MODAL DE CREAR PACIENTE ---
        const btnAbrirModal = document.getElementById('btn-abrir-modal-paciente');
        const btnCerrarModal = document.querySelector('#modal-crear-paciente .modal-close');
        const formCrearPaciente = document.getElementById('form-crear-paciente');
        const modalErrorDiv = document.getElementById('modal-paciente-error');

        if (btnAbrirModal) {
            btnAbrirModal.addEventListener('click', abrirModalCrearPaciente);
        }
        if (btnCerrarModal) {
            btnCerrarModal.addEventListener('click', cerrarModalCrearPaciente);
        }
        if (formCrearPaciente) {
            // Inicializar Flatpickr para el campo de fecha de nacimiento
            flatpickr("#fecha_nacimiento_modal", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d/m/Y",
                locale: "es",
                maxDate: "today",

                // --- LÓGICA AÑADIDA PARA CENTRAR EL CALENDARIO ---
                position: function(self, dom) {
                    // Hacemos que el calendario sea 'fixed' para posicionarlo en la pantalla
                    self.calendarContainer.style.position = 'fixed';
                    
                    // Calculamos el centro de la pantalla
                    const topPosition = (window.innerHeight - self.calendarContainer.offsetHeight) / 2;
                    const leftPosition = (window.innerWidth - self.calendarContainer.offsetWidth) / 2;
                    
                    // Asignamos las posiciones
                    self.calendarContainer.style.top = `${topPosition}px`;
                    self.calendarContainer.style.left = `${leftPosition}px`;
                }
                // --- FIN DE LA LÓGICA AÑADIDA ---
            });
            formCrearPaciente.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;
                if(modalErrorDiv) modalErrorDiv.style.display = 'none';

                fetch('guardar_paciente.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalCrearPaciente();
                        formCrearPaciente.reset(); // Limpiar el formulario
                        // Llamamos a la nueva función para abrir la modal de éxito
                        abrirModalExitoPaciente(data.nombre, data.password);
                        if (window.buscarMisPacientes) {
                            window.buscarMisPacientes(''); // Refrescar la lista de pacientes
                        }
                    } else {
                        if(modalErrorDiv) {
                            modalErrorDiv.textContent = data.message;
                            modalErrorDiv.style.display = 'block';
                        }
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Paciente';
                    submitButton.disabled = false;
                });
            });
        }

        // Lógica para el botón de cierre de la modal infantil
        const btnCerrarModalInfantil = document.querySelector('#modal-crear-historia-infantil .modal-close-btn');
        if (btnCerrarModalInfantil) {
            btnCerrarModalInfantil.addEventListener('click', cerrarModalCrearHistoriaInfantil);
        }

        // --- LÓGICA PARA EL FORMULARIO MODAL DE CREAR INFORME (UNIFICADA) ---
        const formCrearInforme = document.getElementById('form-crear-informe');
        if (formCrearInforme) {

            // 1. Lógica para el envío del formulario (AJAX)
            formCrearInforme.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const pacienteId = formData.get('paciente_id');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_informe.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalCrearInforme();
                        alert('¡Informe guardado con éxito!');
                        abrirModalGestionarPaciente(pacienteId);
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo guardar el informe.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Informe';
                    submitButton.disabled = false;
                });
            });

            // 2. Lógica para la validación de campos en tiempo real
            formCrearInforme.addEventListener('keydown', function(event) {
                const target = event.target;
                
                // Permitir teclas de control
                if (event.key.length > 1) {
                    return;
                }

                // Validación para campos numéricos
                if (target.classList.contains('validate-numeric')) {
                    if (!/^[0-9]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }
            });
        }

        
        // Cerrar modales al hacer clic fuera
        window.addEventListener('click', function(event) {
            if (event.target == modalCrearPaciente) cerrarModalCrearPaciente();
            if (event.target == modalProgramarCita) cerrarModalProgramarCita();
            if (event.target == modalProponerFecha) cerrarModalProponerFecha();
            if (event.target == modalReprogramarCita) cerrarModalReprogramarCita();
            if (event.target == modalGestionarPaciente) cerrarModalGestionarPaciente();
            if (event.target == modalGestionarNotas) cerrarModalGestionarNotas();
            if (event.target == modalSeleccionarHistoria) cerrarModalSeleccionarHistoria();
            if (event.target == modalProfesionalDetalle) cerrarModalProfesionalDetalle();
            if (event.target == modalConflictoCita) cerrarModalConflicto();
        });

        // --- LÓGICA PARA EL FORMULARIO MODAL DE GUARDAR NOTA ---
        const formGuardarNota = document.getElementById('form-guardar-nota');
        if (formGuardarNota) {
            formGuardarNota.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const pacienteId = formData.get('paciente_id');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_nota.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        formGuardarNota.reset();
                        document.getElementById('fecha_sesion_modal').value = new Date().toISOString().slice(0, 16);
                        abrirModalGestionarNotas(pacienteId); // Recarga los datos de la modal
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo guardar la nota.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Nota';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA PARA EL BOTÓN DE LIMPIAR NOTAS (CORREGIDA) ---
        const btnLimpiarNotas = document.getElementById('btn-limpiar-notas');
        if (btnLimpiarNotas) {
            btnLimpiarNotas.addEventListener('click', function() {
                const pacienteId = document.getElementById('notas-paciente-id').value;
                if (pacienteId && confirm('¿Estás seguro de que quieres borrar TODAS las notas de este paciente? Esta acción es irreversible.')) {
                    const formData = new FormData();
                    formData.append('paciente_id', pacienteId);

                    fetch('limpiar_notas.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Si tuvo éxito, recargamos las notas para ver la lista vacía
                            abrirModalGestionarNotas(pacienteId);
                        } else {
                            alert('Error: ' + (data.message || 'No se pudieron eliminar las notas.'));
                        }
                    })
                    .catch(error => console.error('Error al limpiar notas:', error));
                }
            });
        }

                // --- LÓGICA PARA EL CALENDARIO FLATPICKR EN LA MODAL DE NOTAS ---
        const fechaSesionInput = document.getElementById('fecha_sesion_modal');
        if (fechaSesionInput) {
            flatpickr(fechaSesionInput, {
                enableTime: true,
                dateFormat: "Y-m-d H:i", // Formato para la base de datos
                altInput: true,
                altFormat: "d/m/Y h:i K", // Formato visible para el usuario
                locale: "es",
                defaultDate: new Date() // Pone la fecha y hora actual por defecto
            });
        }

        

        // --- LÓGICA PARA EL FORMULARIO MODAL DE PROGRAMAR CITA ---
        const formProgramarCita = document.getElementById('form-programar-cita');
        const btnCerrarModalProgramar = document.querySelector('#modal-programar-cita .modal-close');

        if (btnCerrarModalProgramar) btnCerrarModalProgramar.addEventListener('click', cerrarModalProgramarCita);

        if(formProgramarCita) {
            flatpickr("#calendario-programar", {
                enableTime: true, dateFormat: "Y-m-d H:i", altInput: true,
                altFormat: "d/m/Y h:i K", locale: "es", minuteIncrement: 15,
            });

            formProgramarCita.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_cita_directa.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalProgramarCita();
                        alert('¡Cita programada con éxito!');
                        // Opcional: Refrescar la tabla de historial de citas si está visible
                        if(document.getElementById('vista-historial-citas') && document.getElementById('vista-historial-citas').classList.contains('active')){
                           // Aquí se podría recargar la tabla del historial si fuera necesario
                        }
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo programar la cita.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Cita';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA PARA EL FORMULARIO MODAL DE REPROGRAMAR CITA ---
        const formReprogramarCita = document.getElementById('form-reprogramar-cita');
        if(formReprogramarCita) {
            flatpickr("#calendario-reprogramar", {
                enableTime: true, dateFormat: "Y-m-d H:i", altInput: true,
                altFormat: "d/m/Y h:i K", locale: "es", minuteIncrement: 15,
            });

            formReprogramarCita.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('actualizar_cita.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalReprogramarCita();
                        alert('¡Cita reprogramada con éxito!');
                        // Para ver el cambio, recargamos la página. Es la forma más simple.
                        window.location.href = 'panel.php?vista=proximas-citas';
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo reprogramar la cita.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Cita';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA PARA EL FORMULARIO MODAL DE PROPONER FECHA ---
        const formProponerFecha = document.getElementById('form-proponer-fecha');
        if(formProponerFecha) {
            flatpickr("#calendario-proponer", {
                enableTime: true, dateFormat: "Y-m-d H:i", altInput: true,
                altFormat: "d/m/Y h:i K", locale: "es", minuteIncrement: 15,
            });

            formProponerFecha.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Enviando...';
                submitButton.disabled = true;

                fetch('guardar_propuesta.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalProponerFecha();
                        alert('¡Propuesta enviada con éxito!');
                        window.location.href = 'panel.php?vista=citas'; // Recarga para actualizar la lista
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo enviar la propuesta.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Enviar Propuesta';
                    submitButton.disabled = false;
                });
            });
        }

        
        // --- ACTIVAR ORDENAMIENTO EN LAS TABLAS ---
        makeTableSortable(document.getElementById('tabla-pacientes-container'));
        makeTableSortable(document.getElementById('tabla-historial-container'));
        makeTableSortable(tablaHistorialContainer); // Usamos la variable que ya declaramos
        makeTableSortable(document.getElementById('tabla-notas-container'));
        makeTableSortable(document.getElementById('tabla-historial-secretaria-container'));

        // --- LÓGICA PARA EL FORMULARIO DE CITAS DEL PACIENTE ---
        const especialidadSelector = document.getElementById('especialidad_selector');
        const psicologoSelector = document.getElementById('psicologo_selector');
        const datePickerGroup = document.getElementById('date-picker-group');
        const calendarInput = document.getElementById('calendario-paciente');
        const timeSlotsGroup = document.getElementById('time-slots-group');
        const timeSlotsContainer = document.getElementById('time-slots-container');
        const btnEnviarSolicitud = document.getElementById('btn-enviar-solicitud');
        const horaSeleccionadaInput = document.getElementById('hora_seleccionada_input');
        let fp;

        if (especialidadSelector) {
            especialidadSelector.addEventListener('change', function() {
                const rol = this.value;
                psicologoSelector.innerHTML = '<option value="">Cargando...</option>';
                psicologoSelector.disabled = true;
                datePickerGroup.style.display = 'none';
                timeSlotsGroup.style.display = 'none';
                if (fp) fp.destroy();

                if (rol) {
                    fetch(`get_professionals_by_specialty.php?rol=${rol}`)
                    .then(response => response.json())
                    .then(profesionales => {
                        psicologoSelector.innerHTML = '<option value="">Elige un profesional</option>';
                        if (profesionales.length > 0) {
                            profesionales.forEach(prof => {
                                psicologoSelector.innerHTML += `<option value="${prof.id}">${prof.nombre_completo}</option>`;
                            });
                            psicologoSelector.disabled = false;
                        } else {
                            psicologoSelector.innerHTML = '<option value="">-- No hay profesionales disponibles --</option>';
                        }
                    });
                }
            });
        }

        if (psicologoSelector) {
            psicologoSelector.addEventListener('change', function() {
                const psicologoId = this.value;
                datePickerGroup.style.display = 'none';
                timeSlotsGroup.style.display = 'none';
                if (fp) fp.destroy();

                if (psicologoId) {
                    fetch(`get_available_dates.php?psicologo_id=${psicologoId}`)
                    .then(response => response.json())
                    .then(availableDates => {
                        datePickerGroup.style.display = 'block';
                        fp = flatpickr(calendarInput, {
                            locale: "es",
                            dateFormat: "Y-m-d",
                            minDate: "today",
                            enable: availableDates, // Solo habilita las fechas que vienen del servidor
                            
                            // --- LÓGICA AÑADIDA PARA CENTRAR EL CALENDARIO ---
                            position: function(self, dom) {
                                // Hacemos que el calendario sea 'fixed' para posicionarlo en la pantalla
                                self.calendarContainer.style.position = 'fixed';
                                
                                // Calculamos el centro de la pantalla
                                const topPosition = (window.innerHeight - self.calendarContainer.offsetHeight) / 2;
                                const leftPosition = (window.innerWidth - self.calendarContainer.offsetWidth) / 2;
                                
                                // Asignamos las posiciones
                                self.calendarContainer.style.top = `${topPosition}px`;
                                self.calendarContainer.style.left = `${leftPosition}px`;
                            },
                            onChange: function(selectedDates, dateStr) {
                                timeSlotsContainer.innerHTML = 'Cargando...';
                                timeSlotsGroup.style.display = 'block';
                                fetch(`get_available_times.php?psicologo_id=${psicologoId}&fecha=${dateStr}`)
                                .then(res => res.json())
                                .then(times => {
                                    timeSlotsContainer.innerHTML = '';
                                    if (times.length > 0) {
                                        times.forEach(time => {
                                            const timeButton = document.createElement('button');
                                            timeButton.type = 'button';
                                            timeButton.className = 'time-slot-btn';
                                            timeButton.textContent = new Date(`1970-01-01T${time}`).toLocaleTimeString('es-VE', {hour: 'numeric', minute: '2-digit', hour12: true});
                                            timeButton.onclick = () => {
                                                document.querySelectorAll('.time-slot-btn').forEach(btn => btn.classList.remove('selected'));
                                                timeButton.classList.add('selected');
                                                horaSeleccionadaInput.value = time;
                                                btnEnviarSolicitud.disabled = false;
                                                btnEnviarSolicitud.textContent = 'Enviar Solicitud de Cita';
                                            };
                                            timeSlotsContainer.appendChild(timeButton);
                                        });
                                    } else {
                                        timeSlotsContainer.innerHTML = '<p>No hay horarios disponibles para este día.</p>';
                                    }
                                });
                            }
                        });
                    });
                }
            });
        }

        // LÓGICA DEL CALENDARIO DEL PSICÓLOGO (FullCalendar)
        const calendarEl = document.getElementById('calendario');
        if (calendarEl) {
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'es',
                headerToolbar: {
                    left: 'prev,next today manageAvailabilityButton', // <-- Botón movido aquí
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
                allDayText: 'Hora',
                height: 522,
                events: 'get_citas.php',

                // Botón personalizado para gestionar la disponibilidad
                customButtons: {
                    manageAvailabilityButton: {
                     text: 'Mi Disponibilidad', // Texto más corto para el botón
                        click: function() {
                          window.location.href = 'gestionar_disponibilidad.php';
                        }
                    }
                }, // <-- La coma aquí es importante si hay más opciones después

                // Formato de 12 horas
                slotLabelFormat: {
                    hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true
                },
                eventTimeFormat: {
                    hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true
                },
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    const pacienteId = info.event.extendedProps.paciente_id;
                    if (pacienteId) {
                        window.location.href = 'gestionar_paciente.php?paciente_id=' + pacienteId;
                    }
                },
                
            });
            
        }

        

        // LÓGICA DEL CALENDARIO GENERAL (SECRETARIA)
        const generalCalendarEl = document.getElementById('calendario-general');
        if (generalCalendarEl) {
            generalCalendar = new FullCalendar.Calendar(generalCalendarEl, {
                initialView: 'dayGridMonth', // <-- LÍNEA CORREGIDA
                locale: 'es',
                allDayText: 'Hora',
                height: 480,
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
                events: 'get_all_citas.php',
                slotLabelFormat: {
                    hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true
                },
                eventTimeFormat: {
                    hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true
                },
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

        // LÓGICA DEL BUSCADOR DE PACIENTES (SECRETARIA)
        const buscadorSecretaria = document.getElementById('buscador-pacientes-secretaria');
        const contenedorTablaSecretaria = document.getElementById('tabla-pacientes-secretaria-container');
        if (buscadorSecretaria) {
            buscadorSecretaria.addEventListener('keyup', function() {
                const query = this.value;
                fetch('buscar_pacientes_secretaria.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'query=' + encodeURIComponent(query)
                })
                .then(response => response.text())
                .then(data => {
                    contenedorTablaSecretaria.innerHTML = data;
                })
                .catch(error => console.error('Error en la búsqueda:', error));
            });
        }

        // --- LÓGICA DEL GRÁFICO DE EDAD DE PACIENTES (DISEÑO PREMIUM) ---
        const patientAgeCanvas = document.getElementById('patientAgeChart');
        if (patientAgeCanvas) {
            fetch('get_patient_age_distribution.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(patientAgeCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Pacientes',
                                data: chartData.data,
                                // --- NUEVA PALETA DE COLORES AZULADOS ---
                                backgroundColor: [
                                '#02b1f4', // Azul Principal
                                '#17a2b8', // Turquesa
                                '#5bc0de', // Azul Claro
                                '#6c757d'  // Gris Frío
                                ],
                                borderColor: '#ffffff',
                                borderWidth: 4,
                                hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '65%',
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        font: { family: "'Poppins', sans-serif", size: 13 },
                                        boxWidth: 15,
                                        padding: 15
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#333',
                                    titleFont: { size: 14, weight: 'bold', family: "'Poppins', sans-serif" },
                                    bodyFont: { size: 13, family: "'Poppins', sans-serif" },
                                    padding: 10,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) { label += ': '; }
                                            if (context.parsed !== null) { label += context.parsed; }
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico de edad:', error));
        }

         // --- LÓGICA DEL GRÁFICO DE CARGA DE TRABAJO (DISEÑO PREMIUM) ---
        const workloadCanvas = document.getElementById('workloadChart');
        if (workloadCanvas) {
            fetch('get_workload_data.php')
                .then(response => response.json())
                .then(chartData => {
                    const ctx = workloadCanvas.getContext('2d');
                    const gradient = ctx.createLinearGradient(0, 0, workloadCanvas.width, 0);
                    gradient.addColorStop(0, '#00c2ff');
                    gradient.addColorStop(1, '#0361b3ff');

                    new Chart(workloadCanvas, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Citas Asignadas',
                                data: chartData.data,
                                backgroundColor: gradient,
                                borderRadius: 8, // Bordes redondeados
                                borderWidth: 0
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#333',
                                    titleFont: { size: 14, weight: 'bold', family: "'Poppins', sans-serif" },
                                    bodyFont: { size: 13, family: "'Poppins', sans-serif" },
                                    padding: 10,
                                    cornerRadius: 8
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false // Quita las líneas verticales
                                    },
                                    ticks: {
                                        stepSize: 1,
                                        font: { family: "'Poppins', sans-serif" }
                                    }
                                },
                                y: {
                                    grid: {
                                        color: '#f0f0f0' // Líneas horizontales más sutiles
                                    },
                                    ticks: {
                                        font: { family: "'Poppins', sans-serif" }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico de carga de trabajo:', error));
        }

        // LÓGICA DEL GRÁFICO DE CRECIMIENTO DE USUARIOS (Line Chart)
        const growthChartCanvas = document.getElementById('userGrowthChart');
        if (growthChartCanvas) {
            fetch('get_user_growth_data.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(growthChartCanvas, {
                        type: 'line', // Tipo de gráfico: línea
                        data: {
                            labels: chartData.labels, // ['Febrero', 'Marzo', ...]
                            datasets: [{
                                label: 'Nuevos Usuarios',
                                data: chartData.data, // [5, 8, 12, ...]
                                fill: true,
                                backgroundColor: 'rgba(2, 177, 244, 0.2)',
                                borderColor: 'rgba(2, 177, 244, 1)',
                                tension: 0.3 // Hace la línea un poco curva
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico de crecimiento:', error));
        }


        // --- LÓGICA CORREGIDA PARA LOS NUEVOS GRÁFICOS ---
        
        // 1. Gráfico de Tipos de Cita
        const appointmentTypesCanvas = document.getElementById('appointmentTypesChart');
        if (appointmentTypesCanvas) {
            fetch('get_appointment_types_data.php')
            .then(response => response.json())
            .then(chartData => {
                new Chart(appointmentTypesCanvas, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: chartData.datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } },
                        plugins: { legend: { position: 'top' } }
                    }
                });
            });
        }

        // --- LÓGICA PARA EL GRÁFICO DE CITAS DIARIAS (ADMINISTRADOR) ---
        const dailyAppointmentsCanvas = document.getElementById('dailyAppointmentsChart');
        if (dailyAppointmentsCanvas) {
            fetch('get_daily_appointments_data.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(dailyAppointmentsCanvas, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Citas Atendidas',
                                data: chartData.data,
                                // --- COLORES CAMBIADOS ---
                                backgroundColor: '#02b1f4', // Azul principal
                                borderColor: '#028ac7',   // Azul más oscuro
                                borderWidth: 1,
                                borderRadius: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico diario:', error));
        }

        // 3. Gráfico de Confirmadas vs. Reprogramadas
        const confirmedReprogrammedCanvas = document.getElementById('confirmedReprogrammedChart');
        if (confirmedReprogrammedCanvas) {
            fetch('get_confirmed_reprogrammed_data.php')
            .then(response => response.json())
            .then(chartData => {
                new Chart(confirmedReprogrammedCanvas, {
                    type: 'pie',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            data: chartData.data,
                            backgroundColor: ['#17a2b8', '#ffc107'],
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } }
                    }
                });
            });
        }


    // --- LÓGICA DEL BUSCADOR DE PACIENTES (PSICÓLOGO) ---
        const buscadorPacientes = document.getElementById('buscador-pacientes');
        const tablaPacientesContainer = document.getElementById('tabla-pacientes-container');
        
        function buscarMisPacientes(query) {
            if (!tablaPacientesContainer) return;
            fetch('buscar_pacientes.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'query=' + encodeURIComponent(query)
            })
            .then(response => response.text())
            .then(data => { tablaPacientesContainer.innerHTML = data; })
            .catch(error => console.error('Error en la búsqueda:', error));
        }

        if (buscadorPacientes) {
            // Carga inicial de la tabla
            buscarMisPacientes('');
            // Búsqueda en tiempo real al escribir
            buscadorPacientes.addEventListener('keyup', function() {
                buscarMisPacientes(this.value);
            });
        }

           

        

        


</script>





</body>
</html>