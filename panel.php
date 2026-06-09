<?php
date_default_timezone_set('America/Caracas'); // <-- AÑADE ESTA LÍNEA
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/admin_data.php';

if (!function_exists('formatearBytes')) {
    function formatearBytes($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        $power = min($power, count($units));
        $value = $bytes / pow(1024, $power);
        $unit = $units[$power - 1] ?? 'KB';
        return number_format($value, $value >= 10 ? 1 : 2) . ' ' . $unit;
    }
}

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
$especialidades_panel_data = [
    'profesionales' => [],
    'resumen' => [],
    'catalogo' => [],
    'unique_total' => 0,
    'with_specialty' => 0,
    'without_specialty' => 0
];
if ($rol_usuario == 'administrador') {
    $result_pendientes = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE estado = 'pendiente'");
    $stats['pendientes'] = $result_pendientes->fetch_assoc()['total'];

    $result_aprobados = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE estado = 'aprobado'");
    $stats['aprobados'] = $result_aprobados->fetch_assoc()['total'];
    
    // Contar todo el personal activo (psicólogos, psiquiatras, secretarias)
    $result_personal = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE rol IN ('ecografista', 'recepcionista') AND estado = 'aprobado'");
    $stats['personal'] = $result_personal->fetch_assoc()['total'];

    // Contar todas las citas
    $result_citas = $conex->query("SELECT COUNT(id) as total FROM citas");
    $stats['total_citas'] = $result_citas->fetch_assoc()['total'];

    // Pacientes activos
    $result_pacientes = $conex->query("SELECT COUNT(id) as total FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'");
    $stats['pacientes_activos'] = $result_pacientes->fetch_assoc()['total'];

    $especialidades_panel_data = [
        'profesionales' => [],
        'resumen' => [],
        'catalogo' => [],
        'unique_total' => 0,
        'with_specialty' => 0,
        'without_specialty' => 0
    ];

    $documentos_data = [
        'items' => [],
        'stats' => [
            'total_archivos' => 0,
            'tamano_total' => 0,
            'tamano_total_legible' => '0 B',
            'por_categoria' => []
        ],
        'carpeta_disponible' => true,
        'base_url' => 'documentos/',
        'feedback' => null
    ];

    if (isset($_SESSION['documentos_feedback'])) {
        $documentos_data['feedback'] = $_SESSION['documentos_feedback'];
        unset($_SESSION['documentos_feedback']);
    }

    $especialidades_panel_data = eco_admin_build_especialidades_panel($conex);

    $documentos_base_path = __DIR__ . DIRECTORY_SEPARATOR . 'documentos';
    if (!is_dir($documentos_base_path)) {
        @mkdir($documentos_base_path, 0777, true);
    }
    $documentos_data['carpeta_disponible'] = is_dir($documentos_base_path) && is_writable($documentos_base_path);

    $extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar'];
    $mapa_categorias = [
        'pdf' => 'PDF y Manuales',
        'doc' => 'Documentos Word',
        'docx' => 'Documentos Word',
        'xls' => 'Hojas de Cálculo',
        'xlsx' => 'Hojas de Cálculo',
        'ppt' => 'Presentaciones',
        'pptx' => 'Presentaciones',
        'txt' => 'Notas y Texto Plano',
        'csv' => 'Registros CSV',
        'zip' => 'Archivos Comprimidos',
        'rar' => 'Archivos Comprimidos'
    ];
    $peso_maximo_bytes = 10 * 1024 * 1024; // 10 MB

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['documento_action'])) {
        $accionDocumento = $_POST['documento_action'];
        $redirectUrl = 'panel.php?vista=admin-documentos';

        if ($accionDocumento === 'upload') {
            if (!$documentos_data['carpeta_disponible']) {
                $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se puede escribir en la carpeta de documentos. Verifica permisos.'];
                header('Location: ' . $redirectUrl);
                exit();
            }

            if (!isset($_FILES['documento_archivo']) || $_FILES['documento_archivo']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se recibió el archivo o se produjo un error durante la subida.'];
                header('Location: ' . $redirectUrl);
                exit();
            }

            $archivoSubido = $_FILES['documento_archivo'];
            if ($archivoSubido['size'] > $peso_maximo_bytes) {
                $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'El archivo supera el tamaño máximo permitido (10 MB).'];
                header('Location: ' . $redirectUrl);
                exit();
            }

            $nombreOriginal = $archivoSubido['name'];
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            if (!in_array($extension, $extensiones_permitidas, true)) {
                $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'Tipo de archivo no permitido.'];
                header('Location: ' . $redirectUrl);
                exit();
            }

            $nombreBase = pathinfo($nombreOriginal, PATHINFO_FILENAME);
            $nombreSanitizado = preg_replace('/[^a-zA-Z0-9-_]+/', '-', $nombreBase);
            $nombreSanitizado = trim($nombreSanitizado, '-_');
            if ($nombreSanitizado === '') {
                $nombreSanitizado = 'documento';
            }
            $nombreDestino = $nombreSanitizado . '-' . date('Ymd-His') . '.' . $extension;
            $rutaDestino = $documentos_base_path . DIRECTORY_SEPARATOR . $nombreDestino;

            if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaDestino)) {
                $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se pudo guardar el archivo en el servidor.'];
                header('Location: ' . $redirectUrl);
                exit();
            }

            $_SESSION['documentos_feedback'] = ['type' => 'success', 'message' => 'Documento cargado correctamente.'];
            header('Location: ' . $redirectUrl);
            exit();
        }

        if ($accionDocumento === 'delete') {
            $archivoEliminar = isset($_POST['documento_nombre']) ? basename($_POST['documento_nombre']) : '';
            $rutaEliminar = $archivoEliminar ? realpath($documentos_base_path . DIRECTORY_SEPARATOR . $archivoEliminar) : false;
            $carpetaReal = realpath($documentos_base_path);

            if (!$archivoEliminar || !$rutaEliminar || strpos($rutaEliminar, $carpetaReal) !== 0 || !is_file($rutaEliminar)) {
                $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se encontró el archivo solicitado.'];
                header('Location: ' . $redirectUrl);
                exit();
            }

            if (!@unlink($rutaEliminar)) {
                $_SESSION['documentos_feedback'] = ['type' => 'error', 'message' => 'No se pudo eliminar el archivo.'];
                header('Location: ' . $redirectUrl);
                exit();
            }

            $_SESSION['documentos_feedback'] = ['type' => 'success', 'message' => 'Documento eliminado correctamente.'];
            header('Location: ' . $redirectUrl);
            exit();
        }

        header('Location: ' . $redirectUrl);
        exit();
    }

    if (is_dir($documentos_base_path)) {
        $archivos = scandir($documentos_base_path);
        $carpetaReal = realpath($documentos_base_path);
        foreach ($archivos as $archivo) {
            if ($archivo === '.' || $archivo === '..') {
                continue;
            }

            $rutaArchivo = $documentos_base_path . DIRECTORY_SEPARATOR . $archivo;
            if (!is_file($rutaArchivo)) {
                continue;
            }

            $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
            $categoria = $mapa_categorias[$extension] ?? 'Otros Documentos';
            $tamano = filesize($rutaArchivo);
            $documentos_data['stats']['total_archivos']++;
            $documentos_data['stats']['tamano_total'] += $tamano;

            if (!isset($documentos_data['stats']['por_categoria'][$categoria])) {
                $documentos_data['stats']['por_categoria'][$categoria] = [
                    'nombre' => $categoria,
                    'total' => 0,
                    'tamano' => 0
                ];
            }
            $documentos_data['stats']['por_categoria'][$categoria]['total']++;
            $documentos_data['stats']['por_categoria'][$categoria]['tamano'] += $tamano;

            $documentos_data['items'][] = [
                'nombre' => $archivo,
                'extension' => $extension,
                'categoria' => $categoria,
                'tamano' => $tamano,
                'tamano_legible' => formatearBytes($tamano),
                'modificado' => filemtime($rutaArchivo),
                'modificado_legible' => date('d/m/Y H:i', filemtime($rutaArchivo)),
                'search_text' => strtolower($archivo . ' ' . $categoria . ' ' . ($mapa_categorias[$extension] ?? $extension))
            ];
        }

        usort($documentos_data['items'], function ($a, $b) {
            return $b['modificado'] <=> $a['modificado'];
        });

        $documentos_data['stats']['tamano_total_legible'] = formatearBytes($documentos_data['stats']['tamano_total']);
        $documentos_data['stats']['por_categoria'] = array_values(array_map(function ($categoria) {
            $categoria['tamano_legible'] = formatearBytes($categoria['tamano']);
            return $categoria;
        }, $documentos_data['stats']['por_categoria']));

        usort($documentos_data['stats']['por_categoria'], function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
    }

} elseif ($rol_usuario == 'ecografista' || $rol_usuario == 'ecografista') {
    $ecografista_id = $_SESSION['usuario_id'];
    
    // Citas para hoy
    $hoy_inicio = date('Y-m-d 00:00:00');
    $hoy_fin = date('Y-m-d 23:59:59');
    $stmt_citas_hoy = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE ecografista_id = ? AND estado = 'confirmada' AND fecha_cita BETWEEN ? AND ?");
    $stmt_citas_hoy->bind_param("iss", $ecografista_id, $hoy_inicio, $hoy_fin);
    $stmt_citas_hoy->execute();
    $stats['citas_hoy'] = $stmt_citas_hoy->get_result()->fetch_assoc()['total'];
    $stmt_citas_hoy->close();

    // Solicitudes pendientes
    $stmt_pendientes = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE ecografista_id = ? AND estado = 'pendiente'");
    $stmt_pendientes->bind_param("i", $ecografista_id);
    $stmt_pendientes->execute();
    $stats['pendientes'] = $stmt_pendientes->get_result()->fetch_assoc()['total'];
    $stmt_pendientes->close();

    // Pacientes activos
    $stmt_pacientes_activos = $conex->prepare("SELECT COUNT(DISTINCT u.id) as total FROM usuarios u LEFT JOIN citas c ON u.id = c.paciente_id WHERE u.rol = 'paciente' AND u.estado = 'aprobado' AND (u.creado_por_id = ? OR c.ecografista_id = ?)");
    $stmt_pacientes_activos->bind_param("ii", $ecografista_id, $ecografista_id);
    $stmt_pacientes_activos->execute();
    $stats['pacientes_activos'] = $stmt_pacientes_activos->get_result()->fetch_assoc()['total'];
    $stmt_pacientes_activos->close();

    // --- AÑADE ESTE NUEVO BLOQUE DE CÓDIGO ---
         // 4. Obtener las próximas 5 citas (con ID y más detalles)
    $proximas_citas_stmt = $conex->prepare("
        SELECT c.id, c.fecha_cita, c.motivo_consulta, u.nombre_completo, u.cedula 
        FROM citas c 
        JOIN usuarios u ON c.paciente_id = u.id 
        WHERE c.ecografista_id = ? 
        AND c.estado IN ('confirmada', 'reprogramada')
        AND c.fecha_cita >= NOW() 
        ORDER BY c.fecha_cita ASC 
        LIMIT 5
    ");
    $proximas_citas_stmt->bind_param("i", $ecografista_id);
    $proximas_citas_stmt->execute();
    $proximas_citas = $proximas_citas_stmt->get_result();
}

// ── Tipos de ecografía para la modal de selección (disponibles para todos los roles) ──
// Excluimos sub-tipos (Musculoesqueletica_Sub, Obstetrica_Sub, Partes_Blandas_Sub) — se muestran en sub-modales
$tipos_panel = [];
$res_tipos_panel = $conex->query("SELECT id, codigo, nombre, categoria, descripcion, icono FROM tipos_ecografias WHERE activo = 1 AND (categoria IS NULL OR categoria NOT IN ('Musculoesqueletica_Sub', 'Obstetrica_Sub', 'Partes_Blandas_Sub')) ORDER BY posicion, nombre");
if ($res_tipos_panel) {
    while ($row = $res_tipos_panel->fetch_assoc()) {
        $tipos_panel[] = $row;
    }
}

// Sub-tipos musculoesqueléticos (Hombro, Codo, Muñeca, Cadera, Rodilla, Tobillo)
$tipos_musculo = [];
$res_musc = $conex->query("SELECT id, codigo, nombre, descripcion, icono FROM tipos_ecografias WHERE activo = 1 AND categoria = 'Musculoesqueletica_Sub' ORDER BY posicion, nombre");
if ($res_musc) {
    while ($row = $res_musc->fetch_assoc()) {
        $tipos_musculo[] = $row;
    }
}

// Sub-tipos obstétricos (I Trimestre, II y III Trimestre)
$tipos_obstetrica = [];
$res_obs = $conex->query("SELECT id, codigo, nombre, descripcion, icono FROM tipos_ecografias WHERE activo = 1 AND categoria = 'Obstetrica_Sub' ORDER BY posicion, nombre");
if ($res_obs) {
    while ($row = $res_obs->fetch_assoc()) {
        $tipos_obstetrica[] = $row;
    }
}

// Sub-tipos partes blandas (General, Cuello, Inguinal)
$tipos_partes_blandas = [];
$res_pbl = $conex->query("SELECT id, codigo, nombre, descripcion, icono FROM tipos_ecografias WHERE activo = 1 AND categoria = 'Partes_Blandas_Sub' ORDER BY posicion, nombre");
if ($res_pbl) {
    while ($row = $res_pbl->fetch_assoc()) {
        $tipos_partes_blandas[] = $row;
    }
}

// Mapa de colores por categoría
$eco_colores = [
    'Abdominal'         => ['bg' => 'linear-gradient(135deg,#02b1f4,#38bdf8)', 'badge' => '#e0f5fe', 'text' => '#0284c7'],
    'Renal'             => ['bg' => 'linear-gradient(135deg,#0ea5e9,#7dd3fc)', 'badge' => '#e0f2fe', 'text' => '#0369a1'],
    'Obstetrica'        => ['bg' => 'linear-gradient(135deg,#ec4899,#f9a8d4)', 'badge' => '#fce7f3', 'text' => '#be185d'],
    'Cervical'          => ['bg' => 'linear-gradient(135deg,#14b8a6,#5eead4)', 'badge' => '#ccfbf1', 'text' => '#0f766e'],
    'Pelvica'           => ['bg' => 'linear-gradient(135deg,#8b5cf6,#c4b5fd)', 'badge' => '#ede9fe', 'text' => '#6d28d9'],
    'Musculoesqueletica'=> ['bg' => 'linear-gradient(135deg,#22c55e,#86efac)', 'badge' => '#dcfce7', 'text' => '#15803d'],
    'Prostatica'        => ['bg' => 'linear-gradient(135deg,#3b82f6,#93c5fd)', 'badge' => '#dbeafe', 'text' => '#1d4ed8'],
    'Mamaria'           => ['bg' => 'linear-gradient(135deg,#f43f5e,#fda4af)', 'badge' => '#ffe4e6', 'text' => '#be123c'],
    'Partes Blandas'    => ['bg' => 'linear-gradient(135deg,#f59e0b,#fcd34d)', 'badge' => '#fef3c7', 'text' => '#b45309'],
    'Testicular'        => ['bg' => 'linear-gradient(135deg,#6366f1,#a5b4fc)', 'badge' => '#e0e7ff', 'text' => '#4338ca'],
    'Pulmonar'          => ['bg' => 'linear-gradient(135deg,#0891b2,#22d3ee)', 'badge' => '#cffafe', 'text' => '#0e7490'],
];
$eco_color_default = ['bg' => 'linear-gradient(135deg,#64748b,#94a3b8)', 'badge' => '#f1f5f9', 'text' => '#475569'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control - EcoMadelleine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/shell.css">
    <link rel="stylesheet" href="assets/css/shell-modals.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
<link rel="stylesheet" href="assets/css/panel.css?v=<?= @filemtime(__DIR__ . '/assets/css/panel.css') ?>">
</head>
<body>
    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="panel.php" class="logo">EcoMadelleine</a>
            </div>

            <nav class="sidebar-nav">
                <?php if ($rol_usuario == 'administrador'): ?>
    <a href="#" class="nav-link active" onclick="mostrarVista('admin-dashboard', event)">
        <i class="fa-solid fa-chart-line"></i> Panel de Control
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('admin-personal', event)">
    <i class="fa-solid fa-users-cog"></i> <span>Añadir Personal</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('admin-especialidades', event)">
        <i class="fa-solid fa-stethoscope"></i> <span>Especialidades</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('agenda-general', event)">
        <i class="fa-solid fa-calendar-week"></i> <span>Agenda General</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('notas-rapidas', event)">
        <i class="fa-solid fa-note-sticky"></i> <span>Tareas Rápidas</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('admin-documentos', event)">
        <i class="fa-solid fa-folder-open"></i> <span>Rv Documentos</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('admin-contenido', event)"><i class="fa-solid fa-file-pen"></i> <span>Editar Contenido</span></a>
    <a href="#" class="nav-link" onclick="mostrarVista('perfil', event)">
        <i class="fa-solid fa-user-gear"></i> <span>Mi Perfil Personal</span>
    </a>
    
<?php endif; ?>
                <?php if ($rol_usuario == 'ecografista' || $rol_usuario == 'ecografista'): ?>
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
        <i class="fa-solid fa-user-doctor"></i> <span>Ecografistas Activos</span>
    </a>
    <!-- ENLACE NUEVO -->
    <a href="#" class="nav-link" onclick="mostrarVista('psiquiatras', event)">
        <i class="fa-solid fa-brain"></i> <span>Ecografistas Senior</span>
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
<?php if ($rol_usuario == 'recepcionista'): ?>
    <a href="#" class="nav-link active" onclick="mostrarVista('secretaria-dashboard', event)">
        <i class="fa-solid fa-chart-line"></i> <span>Panel de Control</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('solicitudes-generales', event)">
        <i class="fa-solid fa-inbox"></i> <span>Citas Pendientes</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('historial-citas-general', event)">
        <i class="fa-solid fa-clipboard-list"></i> <span>Historial de Citas</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('agenda-general', event)">
        <i class="fa-solid fa-calendar-week"></i> <span>Agenda Personal</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('gestion-pacientes', event)">
        <i class="fa-solid fa-address-book"></i> <span>Gestión Pacientes</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('directorio', event)">
        <i class="fa-solid fa-user-doctor"></i> <span>Directorio Clinico</span>
    </a>
    <a href="#" class="nav-link" onclick="mostrarVista('notas-rapidas', event)">
        <i class="fa-solid fa-note-sticky"></i> <span>Notas Personales</span>
    </a>
    
    <a href="#" class="nav-link" onclick="mostrarVista('perfil', event)">
        <i class="fa-solid fa-user-gear"></i> <span>Mi Perfil Personal</span>
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
        <?php
            $correoUsuario = $_SESSION['correo'] ?? '';
            $telefonoUsuario = $_SESSION['telefono'] ?? null;
            $fechaRegistro = $_SESSION['fecha_registro'] ?? null;
            if (empty($fechaRegistro) && isset($_SESSION['usuario_id'])) {
                $usuarioIdPerfil = (int)$_SESSION['usuario_id'];
                if ($usuarioIdPerfil > 0 && isset($conex) && $conex instanceof mysqli) {
                    if ($stmtFechaPerfil = $conex->prepare("SELECT fecha_registro FROM usuarios WHERE id = ? LIMIT 1")) {
                        $stmtFechaPerfil->bind_param("i", $usuarioIdPerfil);
                        if ($stmtFechaPerfil->execute()) {
                            $stmtFechaPerfil->bind_result($fechaRegistroDb);
                            if ($stmtFechaPerfil->fetch()) {
                                $fechaRegistro = $fechaRegistroDb;
                                if (!empty($fechaRegistroDb)) {
                                    $_SESSION['fecha_registro'] = $fechaRegistroDb;
                                }
                            }
                        }
                        $stmtFechaPerfil->close();
                    }
                }
            }
            $fechaRegistroTexto = '—';
            if (!empty($fechaRegistro)) {
                $timestampRegistro = strtotime($fechaRegistro);
                if ($timestampRegistro !== false && $timestampRegistro > 0) {
                    $fechaRegistroTexto = date('d/m/Y', $timestampRegistro);
                } else {
                    $fechaRegistroTexto = (string)$fechaRegistro;
                }
            }

            $ultimaActividad = $_SESSION['ultima_actividad'] ?? null;
            $ultimaActividadTexto = 'Registro reciente verificado';
            if (!empty($ultimaActividad)) {
                $timestampActividad = strtotime($ultimaActividad);
                if ($timestampActividad !== false && $timestampActividad > 0) {
                    $ultimaActividadTexto = date('d/m/Y H:i', $timestampActividad);
                } else {
                    $ultimaActividadTexto = (string)$ultimaActividad;
                }
            }
            $nombreUsuarioPlano = (string)($nombre_usuario ?? '');
            $avatarInicial = $nombreUsuarioPlano !== '' ? strtoupper(substr($nombreUsuarioPlano, 0, 1)) : '?';
        ?>

        <?php
            if (isset($_GET['status']) && $_GET['status'] === 'perfil_actualizado') {
                echo '<div class="alert-box success"><span><strong>¡Éxito!</strong> Tu contraseña ha sido actualizada.</span></div>';
            } elseif (isset($_GET['error'])) {
                $error_msg = 'Ocurrió un error. Inténtalo de nuevo.';
                if ($_GET['error'] === 'pass_no_coincide') {
                    $error_msg = 'La nueva contraseña y su confirmación no coinciden.';
                } elseif ($_GET['error'] === 'pass_no_segura') {
                    $error_msg = 'La nueva contraseña no cumple con los requisitos de seguridad.';
                }
                echo '<div class="alert-box error"><span><strong>Error:</strong> ' . htmlspecialchars($error_msg) . '</span></div>';
            }
        ?>

        <div class="perfil-hero">
            <div class="perfil-hero-icon">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div class="perfil-hero-texto">
                <h2>Hola, <?php echo htmlspecialchars($nombre_usuario); ?></h2>
                <p>Gestiona tu información personal, refuerza la seguridad de tu cuenta y mantente al día con tus datos principales.</p>
            </div>
            <div class="perfil-hero-estado">
                <span class="perfil-estado-badge"><i class="fa-solid fa-circle-check"></i> Perfil activo</span>
                <span>Rol: <?php echo htmlspecialchars(ucfirst($rol_usuario)); ?></span>
                <span>Miembro desde: <?php echo htmlspecialchars($fechaRegistroTexto); ?></span>
            </div>
        </div>

        <div class="perfil-detalle">
            <div class="perfil-summary">
                <div class="perfil-avatar">
                    <span><?php echo htmlspecialchars($avatarInicial); ?></span>
                </div>
                <div class="perfil-summary-text">
                    <h3><?php echo htmlspecialchars($nombre_usuario); ?></h3>
                    <p><?php echo htmlspecialchars($correoUsuario); ?></p>
                </div>
                <div class="perfil-summary-meta">
                    <div>
                        <span class="meta-label">Rol</span>
                        <span class="meta-value"><?php echo htmlspecialchars(ucfirst($rol_usuario)); ?></span>
                    </div>
                    <div>
                        <span class="meta-label">Miembro desde</span>
                        <span class="meta-value"><?php echo htmlspecialchars($fechaRegistroTexto); ?></span>
                    </div>
                    <div>
                        <span class="meta-label">Estado</span>
                        <span class="meta-badge activo"><i class="fa-solid fa-circle-check"></i> Activo</span>
                    </div>
                </div>
            </div>

            <div class="profile-grid">
                <div class="profile-card">
                    <h4><i class="fa-solid fa-address-card"></i> Información de contacto</h4>
                    <div class="form-group">
                        <label><i class="fa-solid fa-user"></i> Nombre completo</label>
                        <input type="text" value="<?php echo htmlspecialchars($nombre_usuario); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-envelope"></i> Correo electrónico</label>
                        <input type="email" value="<?php echo htmlspecialchars($correoUsuario); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-phone"></i> Teléfono registrado</label>
                        <input type="text" value="<?php echo htmlspecialchars($telefonoUsuario ?? 'No registrado'); ?>" readonly>
                    </div>
                </div>

                <div class="profile-card">
                    <h4><i class="fa-solid fa-lock"></i> Seguridad de la cuenta</h4>
                    <ul class="perfil-checklist">
                        <li><i class="fa-solid fa-check-circle"></i> Evita compartir tus datos.</li>
                    </ul>

                    <form action="actualizar_perfil.php" method="POST" class="perfil-form">
                        <input type="hidden" name="accion" value="cambiar_contrasena">
                        <div class="form-group">
                            <label for="nueva_contrasena_perfil"><i class="fa-solid fa-shield-halved"></i> Nueva contraseña</label>
                            <input type="password" name="nueva_contrasena" id="nueva_contrasena_perfil" required minlength="8" pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}" oninvalid="this.setCustomValidity('Requiere mínimo 8 caracteres, una mayúscula y un símbolo.')" oninput="this.setCustomValidity('')">
                        </div>
                        <div class="form-group">
                            <label for="confirmar_contrasena_perfil"><i class="fa-solid fa-shield-heart"></i> Confirmar nueva contraseña</label>
                            <input type="password" name="confirmar_nueva_contrasena" id="confirmar_contrasena_perfil" required>
                        </div>
                        <button type="submit" class="btn-submit">Actualizar contraseña</button>
                    </form>
                </div>

                <div class="profile-card perfil-actividad">
                    <h4><i class="fa-solid fa-timeline"></i> Actividad reciente</h4>
                    <ul>
                        <li><i class="fa-solid fa-clock-rotate-left"></i> Último acceso: <?php echo htmlspecialchars($ultimaActividadTexto); ?></li>
                        <li><i class="fa-solid fa-envelope-circle-check"></i> Correo verificado.</li>
                        <li><i class="fa-solid fa-shield"></i> Autenticación base reforzada.</li>
                    </ul>
                    <a href="#" class="perfil-action-link"><i class="fa-solid fa-download"></i> Descargar historial</a>
                </div>
            </div>

            <div class="perfil-consejos">
                <div>
                    <h4><i class="fa-solid fa-lightbulb"></i> Buenas prácticas</h4>
                    <p>Actualiza tu contraseña periódicamente y evita reutilizarla en otros sistemas.</p>
                </div>
                <div>
                    <h4><i class="fa-solid fa-headset"></i> Soporte</h4>
                    <p>¿Necesitas ayuda? Escríbenos a <a href="mailto:soporte@ecomadelleine.com">soporte@ecomadelleine.com</a>.</p>
                </div>
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
            <a href="#" class="content-card">
                <div class="content-card-icon" style="background-color: #02b1f4;"><i class="fa-solid fa-hand-holding-heart"></i></div>
                <h3>Gestionar Terapias</h3>
                <p>Añade, edita o elimina las terapias.</p>
                <span class="card-action">Gestionar <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <a href="#" class="content-card">
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
                <a href="index.php#nosotros" target="_blank" class="btn-view">Ver página</a>
                <a href="gestionar_textos.php" class="btn-manage">Gestionar</a>
            </div>
        </li>
        <li class="shortcut-item">
            <div class="shortcut-info">
                <i class="fa-solid fa-circle-question"></i>
                <span>Preguntas Frecuentes</span>
            </div>
            <div class="shortcut-actions">
                <a href="index.php#faq" target="_blank" class="btn-view">Ver página</a>
                <a href="gestionar_faq.php" class="btn-manage">Gestionar</a>
            </div>
        </li>
        <li class="shortcut-item">
            <div class="shortcut-info">
                <i class="fa-solid fa-hand-holding-heart"></i>
                <span>Terapias</span>
            </div>
            <div class="shortcut-actions">
                <a href="#" target="_blank" class="btn-view">Ver página</a>
                <a href="#" class="btn-manage">Gestionar</a>
            </div>
        </li>
        <li class="shortcut-item">
            <div class="shortcut-info">
                <i class="fa-solid fa-pills"></i>
                <span>Fármacos</span>
            </div>
            <div class="shortcut-actions">
                <a href="#" target="_blank" class="btn-view">Ver página</a>
                <a href="#" class="btn-manage">Gestionar</a>
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
                        <div class="label">Personales Activo</div>
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
            <!-- Tarjeta para Registrar Ecografista -->
            <a href="crear_usuario_admin.php?rol=ecografista" class="creation-card">
                <div class="card-icon psicologo-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <h3>Registrar Ecografista</h3>
                <p>Crear una cuenta para un nuevo terapeuta.</p>
                <span class="card-action">Crear Perfil <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <!-- Tarjeta para Registrar Especialista -->
            <a href="crear_usuario_admin.php?rol=ecografista" class="creation-card">
                <div class="card-icon psiquiatra-icon"><i class="fa-solid fa-brain"></i></div>
                <h3>Registrar Especialista</h3>
                <p>Crear una cuenta para un médico especialista.</p>
                <span class="card-action">Crear Perfil <i class="fa-solid fa-arrow-right"></i></span>
            </a>

            <!-- Tarjeta para Registrar Secretaria -->
            <a href="crear_usuario_admin.php?rol=recepcionista" class="creation-card">
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
        <h2><i class="fa-solid fa-user-doctor"></i> Ecografistas Activos</h2>
        <div class="personal-grid">
            <?php
            $consulta_psicologos = $conex->query("SELECT id, nombre_completo, correo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
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
        <h2><i class="fa-solid fa-brain"></i> Ecografistas Senior</h2>
        <div class="personal-grid">
            <?php
            $consulta_psiquiatras = $conex->query("SELECT id, nombre_completo, correo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
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
            $consulta_secretarias = $conex->query("SELECT id, nombre_completo, correo FROM usuarios WHERE rol = 'recepcionista' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
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
                echo "<p>No hay recepcionistas registradas.</p>";
            }
            ?>
        </div>
    </div>
</div>

    <div id="vista-admin-especialidades" class="panel-vista">
        <div class="panel-seccion">
            <h2>Gestión de Especialidades</h2>
            <p>Centraliza las áreas de experiencia de tu equipo profesional para mantener actualizado el catálogo clínico.</p>

            <?php if (isset($_GET['esp']) && $_GET['esp'] === 'success'): ?>
                <div class="alert-box success"><span><strong>¡Cambios guardados!</strong> Las especialidades fueron actualizadas correctamente.</span></div>
            <?php elseif (isset($_GET['esp']) && $_GET['esp'] === 'error'): ?>
                <div class="alert-box error"><span><strong>No se pudo guardar.</strong> Inténtalo nuevamente o verifica los datos enviados.</span></div>
            <?php endif; ?>

            <?php $totalProfesionalesEspecialidad = count($especialidades_panel_data['profesionales']); ?>
            <div class="specialty-overview-grid">
                <div class="specialty-overview-card">
                    <span class="metric-label">Especialidades únicas</span>
                    <span class="metric-value"><?php echo $especialidades_panel_data['unique_total']; ?></span>
                    <span class="metric-hint">Áreas distintas registradas.</span>
                </div>
                <div class="specialty-overview-card">
                    <span class="metric-label">Profesionales con especialidad</span>
                    <span class="metric-value"><?php echo $especialidades_panel_data['with_specialty']; ?></span>
                    <span class="metric-hint">de <?php echo $totalProfesionalesEspecialidad; ?> profesionales activos.</span>
                </div>
                <div class="specialty-overview-card">
                    <span class="metric-label">Profesionales por asignar</span>
                    <span class="metric-value"><?php echo $especialidades_panel_data['without_specialty']; ?></span>
                    <span class="metric-hint">Pendientes de definir áreas.</span>
                </div>
            </div>
        </div>

        <div class="panel-seccion">
            <h3>Mapa de Especialidades</h3>
            <p>Consulta cuántos profesionales cubren cada área para detectar oportunidades de contratación o formación.</p>

            <?php if (!empty($especialidades_panel_data['resumen'])): ?>
                <table class="specialty-summary-table">
                    <thead>
                        <tr>
                            <th>Especialidad</th>
                            <th>Profesionales</th>
                            <th>Equipo de referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($especialidades_panel_data['resumen'] as $resumen): ?>
                            <?php $profPreview = array_slice($resumen['profesionales'], 0, 3); ?>
                            <?php $restantes = max($resumen['total'] - count($profPreview), 0); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($resumen['nombre']); ?></td>
                                <td><?php echo (int)$resumen['total']; ?></td>
                                <td>
                                    <div class="specialty-professionals-list">
                                        <?php foreach ($profPreview as $nombreProfesional): ?>
                                            <span class="specialty-badge"><?php echo htmlspecialchars($nombreProfesional); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($restantes > 0): ?>
                                            <span class="specialty-badge">+<?php echo $restantes; ?> más</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="specialty-empty-state">
                    <p>No hay especialidades registradas aún. Asigna al menos una a cada profesional para iniciar el catálogo.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-seccion">
            <h3>Asignar o editar especialidades</h3>
            <p>Define las áreas de experiencia de cada profesional. Usa comas para separar múltiples especialidades.</p>

            <div class="specialty-search-bar">
                <input type="search" id="specialty-search-input" placeholder="Buscar por nombre, rol o especialidad...">
                <span class="specialty-empty-text">Los cambios se guardan de forma individual.</span>
            </div>

            <table class="specialty-management-table">
                <thead>
                    <tr>
                        <th>Profesional</th>
                        <th>Rol</th>
                        <th>Especialidades actuales</th>
                        <th>Estado</th>
                        <th>Actualizar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($especialidades_panel_data['profesionales'])): ?>
                        <?php foreach ($especialidades_panel_data['profesionales'] as $profesional): ?>
                            <?php $estadoActual = strtolower($profesional['estado'] ?? ''); ?>
                            <tr class="specialty-row" data-search="<?php echo htmlspecialchars($profesional['search_text']); ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($profesional['nombre_completo']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($profesional['correo']); ?></small>
                                </td>
                                <td>
                                    <span class="specialty-role-badge"><?php echo htmlspecialchars($profesional['rol']); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($profesional['especialidades_lista'])): ?>
                                        <div class="specialty-professionals-list">
                                            <?php foreach ($profesional['especialidades_lista'] as $especialidadActual): ?>
                                                <span class="specialty-badge"><?php echo htmlspecialchars($especialidadActual); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="specialty-empty-text">Sin especialidades asignadas.</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="specialty-status-badge <?php echo htmlspecialchars($estadoActual); ?>"><?php echo htmlspecialchars($profesional['estado'] ? ucfirst($profesional['estado']) : 'Sin estado'); ?></span>
                                </td>
                                <td>
                                    <form action="#" method="POST" class="specialty-form">
                                        <input type="hidden" name="usuario_id" value="<?php echo (int)$profesional['id']; ?>">
                                        <input type="text" name="especialidades" value="<?php echo htmlspecialchars($profesional['especialidades_texto']); ?>" placeholder="Ej. Terapia Familiar, Mindfulness" list="catalogo-especialidades">
                                        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="specialty-empty-state">
                                    <p>No hay profesionales disponibles para gestionar.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <datalist id="catalogo-especialidades">
                <?php foreach ($especialidades_panel_data['catalogo'] as $especialidadCatalogo): ?>
                    <option value="<?php echo htmlspecialchars($especialidadCatalogo); ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
    </div>


    <div id="vista-admin-documentos" class="panel-vista">
        <div class="panel-seccion">
            <h2>Repositorio de Documentos</h2>
            <p>Administra contratos, reglamentos y manuales internos desde un espacio centralizado. Mantén al equipo sincronizado con la información más reciente.</p>

            <?php
                $documentFeedback = isset($documentos_data['feedback']) && is_array($documentos_data['feedback']) ? $documentos_data['feedback'] : null;
                $documentStats = isset($documentos_data['stats']) && is_array($documentos_data['stats']) ? $documentos_data['stats'] : [];
                $documentCategories = isset($documentStats['por_categoria']) && is_array($documentStats['por_categoria']) ? $documentStats['por_categoria'] : [];
                $documentItems = isset($documentos_data['items']) && is_array($documentos_data['items']) ? $documentos_data['items'] : [];
                $documentBaseUrl = isset($documentos_data['base_url']) ? $documentos_data['base_url'] : '';
                $carpetaDisponibleDocs = (bool)($documentos_data['carpeta_disponible'] ?? true);
                $totalCategoriasDoc = count($documentCategories);
            ?>

            <?php if ($documentFeedback): ?>
                <?php $feedbackClass = ($documentFeedback['type'] ?? '') === 'success' ? 'success' : 'error'; ?>
                <div class="alert-box <?php echo $feedbackClass; ?>">
                    <span><strong><?php echo ($documentFeedback['type'] ?? '') === 'success' ? 'Listo' : 'Ups'; ?>:</strong> <?php echo htmlspecialchars($documentFeedback['message'] ?? ''); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$carpetaDisponibleDocs): ?>
                <div class="alert-box error" style="margin-bottom: 18px;">
                    <span><strong>Permisos insuficientes:</strong> No se puede escribir en la carpeta <code>documentos/</code>. Ajusta los permisos para habilitar la subida de archivos.</span>
                </div>
            <?php endif; ?>
            <div class="document-stats-grid">
                <div class="document-stat-card">
                    <span class="metric-label">Documentos disponibles</span>
                    <span class="metric-value"><?php echo (int)($documentStats['total_archivos'] ?? 0); ?></span>
                    <span class="metric-hint">Archivos almacenados en el repositorio.</span>
                </div>
                <div class="document-stat-card">
                    <span class="metric-label">Espacio ocupado</span>
                    <span class="metric-value"><?php echo htmlspecialchars($documentStats['tamano_total_legible'] ?? '0 B'); ?></span>
                    <span class="metric-hint">Uso total de almacenamiento.</span>
                </div>
                <div class="document-stat-card">
                    <span class="metric-label">Categorías activas</span>
                    <span class="metric-value"><?php echo $totalCategoriasDoc; ?></span>
                    <span class="metric-hint">Grupos de documentos identificados.</span>
                </div>
            </div>

            <?php if ($totalCategoriasDoc > 0): ?>
                <div class="document-category-pills">
                    <?php foreach ($documentCategories as $categoriaResumen): ?>
                        <span class="document-category-pill">
                            <?php echo htmlspecialchars($categoriaResumen['nombre'] ?? ''); ?> · <?php echo (int)($categoriaResumen['total'] ?? 0); ?> docs
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel-seccion">
            <h3>Subir nuevo documento</h3>
            <div class="document-upload-card">
                <p>Arrastra tu archivo al botón o selecciónalo manualmente. Formatos admitidos: PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP y RAR (máx. 10 MB).</p>
                <form method="POST" enctype="multipart/form-data" class="document-upload-form">
                    <input type="hidden" name="documento_action" value="upload">
                    <input type="file" name="documento_archivo" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar" <?php echo $carpetaDisponibleDocs ? '' : 'disabled'; ?> required>
                    <button type="submit" <?php echo $carpetaDisponibleDocs ? '' : 'disabled'; ?>><i class="fa-solid fa-cloud-arrow-up"></i> Subir documento</button>
                </form>
                <small style="color: #64748b;">Consejo: utiliza nombres descriptivos (p. ej. <em>Contrato-clínica-2025.pdf</em>) para encontrarlos rápido.</small>
            </div>
        </div>

        <div class="panel-seccion">
            <h3>Documentos disponibles</h3>
            <p>Mantén tus archivos organizados y accesibles para el equipo administrativo. Filtra por nombre, tipo o categoría.</p>

            <div class="document-search-bar">
                <input type="search" id="document-search-input" placeholder="Buscar por nombre, extensión o categoría...">
                <span class="specialty-empty-text">Los enlaces se abren en nueva pestaña.</span>
            </div>

            <?php if (!empty($documentItems)): ?>
                <table class="document-list-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Tamaño</th>
                            <th>Actualizado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentItems as $documento): ?>
                            <?php
                                $docNombre = $documento['nombre'] ?? '';
                                $docUrl = $documentBaseUrl . rawurlencode($docNombre);
                            ?>
                            <tr class="document-row" data-search="<?php echo htmlspecialchars($documento['search_text'] ?? ''); ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($docNombre); ?></strong><br>
                                    <small>.<?php echo htmlspecialchars($documento['extension'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($documento['categoria'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($documento['tamano_legible'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($documento['modificado_legible'] ?? ''); ?></td>
                                <td>
                                    <div class="document-actions">
                                        <a class="download-link" href="<?php echo htmlspecialchars($docUrl); ?>" target="_blank" rel="noopener">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir
                                        </a>
                                        <button type="button" class="copy-link document-copy-link" data-url="<?php echo htmlspecialchars($docUrl); ?>">
                                            <i class="fa-solid fa-link"></i> Copiar enlace
                                        </button>
                                        <form method="POST" onsubmit="return confirm('¿Eliminar este documento?');">
                                            <input type="hidden" name="documento_action" value="delete">
                                            <input type="hidden" name="documento_nombre" value="<?php echo htmlspecialchars($docNombre); ?>">
                                            <button type="submit" class="delete-link"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="document-empty-state">
                    <p>Aún no has subido documentos. Utiliza el formulario superior para comenzar y mantener un repositorio siempre disponible.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>


<?php endif; ?>

<?php if ($rol_usuario == 'administrador' || $rol_usuario == 'recepcionista'): ?>
<div id="vista-agenda-general" class="panel-vista">
    <div class="panel-seccion">
        <h2>Agenda General del Consultorio</h2>
        <p>Calendario con las citas de todos los profesionales.</p>
        <div id="calendario-general" style="height: 70vh;"></div>
    </div>
</div>

<div id="vista-notas-rapidas" class="panel-vista">
    <div class="panel-seccion">
        <h2><i class="fa-solid fa-note-sticky"></i> Notas rápidas</h2>
        <p>Guarda recordatorios internos sobre tareas pendientes, seguimientos o mensajes importantes para el día a día.</p>

        <div class="quick-notes-container">
            <div class="quick-notes-header">
                <div>
                    <span class="quick-notes-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> Organiza tu día</span>
                    <h3>Panel de notas rápidas</h3>
                    <p>Centraliza pendientes internos, recuerda seguimientos clave y mantén a la mano lo que no puede olvidarse.</p>
                </div>
                <div class="quick-notes-metrics">
                    <div class="quick-notes-stat">
                        <span class="stat-label">Notas totales</span>
                        <span class="stat-value" id="quick-notes-total">0</span>
                    </div>
                    <div class="quick-notes-stat">
                        <span class="stat-label">Pendientes</span>
                        <span class="stat-value" id="quick-notes-pending">0</span>
                    </div>
                    <div class="quick-notes-stat">
                        <span class="stat-label">Completadas</span>
                        <span class="stat-value" id="quick-notes-completed">0</span>
                    </div>
                </div>
            </div>

            <div class="quick-notes-grid">
                <div class="quick-notes-column">
                    <div class="quick-notes-form-card">
                        <form id="nota-rapida-form" class="quick-note-form">
                            <label for="nota-rapida-texto">Nueva nota</label>
                            <textarea id="nota-rapida-texto" class="quick-note-textarea" rows="3" placeholder="Ejemplo: Llamar a paciente X mañana a primera hora" required></textarea>
                            <div class="quick-note-actions">
                                <button type="submit" class="quick-note-add-btn"><i class="fa-solid fa-plus"></i> Guardar nota</button>
                            </div>
                        </form>
                    </div>
                    <div class="quick-note-hint">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Consejo rápido</strong>
                            <span>Prioriza tus recordatorios con verbos de acción y marca las notas como completadas para tener claridad al final del día.</span>
                        </div>
                    </div>
                </div>
                <div class="quick-notes-column">
                    <div class="quick-notes-controls">
                        <div class="quick-notes-tabs">
                            <button type="button" class="quick-notes-tab is-active" data-quick-notes-filter="all">Todas</button>
                            <button type="button" class="quick-notes-tab" data-quick-notes-filter="pending">Pendientes</button>
                            <button type="button" class="quick-notes-tab" data-quick-notes-filter="completed">Completadas</button>
                        </div>
                    </div>

                    <div id="estado-notas-vacio" class="quick-notes-empty">
                        <i class="fa-solid fa-inbox"></i>
                        <div>
                            <strong id="quick-notes-empty-title">No tienes notas todavía</strong>
                            <p id="quick-notes-empty-message">Agrega un recordatorio y aparecerá aquí.</p>
                        </div>
                    </div>

                    <ul id="lista-notas-rapidas" class="quick-notes-list"></ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
            <?php if ($rol_usuario == 'ecografista' || $rol_usuario == 'ecografista'): ?>

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
        $ecografista_id_stats = $_SESSION['usuario_id'];
        
        // Citas para hoy
        $hoy_inicio = date('Y-m-d 00:00:00');
        $hoy_fin = date('Y-m-d 23:59:59');
        $stmt_citas_hoy = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE ecografista_id = ? AND estado = 'confirmada' AND fecha_cita BETWEEN ? AND ?");
        $stmt_citas_hoy->bind_param("iss", $ecografista_id_stats, $hoy_inicio, $hoy_fin);
        $stmt_citas_hoy->execute();
        $citas_hoy = $stmt_citas_hoy->get_result()->fetch_assoc()['total'];
        $stmt_citas_hoy->close();

        // Solicitudes pendientes
        $stmt_pendientes = $conex->prepare("SELECT COUNT(id) as total FROM citas WHERE ecografista_id = ? AND estado = 'pendiente'");
        $stmt_pendientes->bind_param("i", $ecografista_id_stats);
        $stmt_pendientes->execute();
        $solicitudes_pendientes = $stmt_pendientes->get_result()->fetch_assoc()['total'];
        $stmt_pendientes->close();

        // Pacientes activos
        $stmt_pacientes_activos = $conex->prepare("SELECT COUNT(DISTINCT u.id) as total FROM usuarios u LEFT JOIN citas c ON u.id = c.paciente_id WHERE u.rol = 'paciente' AND u.estado = 'aprobado' AND (u.creado_por_id = ? OR c.ecografista_id = ?)");
        $stmt_pacientes_activos->bind_param("ii", $ecografista_id_stats, $ecografista_id_stats);
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
                WHERE c.estado = 'pendiente' AND c.ecografista_id = ?
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
            $ecografista_id_proximas = $_SESSION['usuario_id'];
            $consulta_proximas_stmt = $conex->prepare(
                "SELECT c.id, c.fecha_cita, u.id as paciente_id, u.nombre_completo as paciente_nombre, u.cedula as paciente_cedula, u.correo as paciente_correo
                 FROM citas c 
                 JOIN usuarios u ON c.paciente_id = u.id 
                 WHERE c.ecografista_id = ? AND c.estado IN ('confirmada', 'reprogramada') AND c.fecha_cita >= NOW()
                 ORDER BY c.fecha_cita ASC"
            );
            $consulta_proximas_stmt->bind_param("i", $ecografista_id_proximas);
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
            $ecografista_id_notas = $_SESSION['usuario_id'];
            $sql_pacientes_notas = "SELECT DISTINCT u.id, u.nombre_completo, u.cedula, u.correo, u.fecha_registro 
                                    FROM usuarios u
                                    LEFT JOIN citas c ON u.id = c.paciente_id
                                    WHERE u.rol = 'paciente' AND u.estado = 'aprobado'
                                    AND (u.creado_por_id = ? OR c.ecografista_id = ?)";
            $stmt_pacientes_notas = $conex->prepare($sql_pacientes_notas);
            $stmt_pacientes_notas->bind_param("ii", $ecografista_id_notas, $ecografista_id_notas);
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
        JOIN usuarios u ON c.ecografista_id = u.id
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
        JOIN usuarios u ON c.ecografista_id = u.id
        WHERE c.paciente_id = ?
        GROUP BY c.ecografista_id
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
                <?php $nextAppointment = is_array($dashboard_data['proxima_cita']) ? $dashboard_data['proxima_cita'] : null; ?>
                <?php if ($nextAppointment): ?>
                    <?php $nextDate = isset($nextAppointment['fecha_cita']) ? strtotime($nextAppointment['fecha_cita']) : null; ?>
                    <p class="main-data"><?php echo $nextDate ? date('d \d\e F, Y', $nextDate) : 'Fecha por confirmar'; ?></p>
                    <p class="sub-data">
                        <?php echo $nextDate ? date('h:i A', $nextDate) : '--:--'; ?>
                        <?php if (!empty($nextAppointment['profesional_nombre'])): ?>
                            con <?php echo htmlspecialchars($nextAppointment['profesional_nombre']); ?>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="main-data">Sin citas próximas</p>
                    <p class="sub-data">Puedes solicitar una nueva cuando quieras.</p>
                <?php endif; ?>
            </div>

            <!-- Tarjetas de Información -->
            <div class="dashboard-card">
                <div class="card-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <h4>Profesional Principal</h4>
                <p class="main-data"><?php echo htmlspecialchars($dashboard_data['profesional_principal'] ?? 'No asignado'); ?></p>
            </div>
            <div class="dashboard-card">
                <div class="card-icon"><i class="fa-solid fa-check-double"></i></div>
                <h4>Citas Completadas</h4>
                <p class="main-data"><?php echo (int)($dashboard_data['citas_totales'] ?? 0); ?></p>
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
                LEFT JOIN usuarios p ON c.ecografista_id = p.id 
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
                                <option value="ecografista">Ecografista</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="psicologo_selector">Profesional Disponible</label>
                            <select name="ecografista_id" id="psicologo_selector" required disabled>
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
            <h2>Nuestros Ecografistas</h2>
            <p>Conoce a los profesionales dedicados a la terapia y el acompañamiento emocional.</p>
            <div class="professionals-list">
                <?php
                $ecografistas_result = $conex->query("
                    SELECT u.id, u.nombre_completo, (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', ') FROM usuario_especialidades ue JOIN especialidades e ON e.id = ue.especialidad_id WHERE ue.usuario_id = u.id) AS especialidades, 
                           (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c WHERE c.ecografista_id = u.id AND c.estado IN ('confirmada', 'completada')) as pacientes_atendidos
                    FROM usuarios u 
                    WHERE u.rol = 'ecografista' AND u.estado = 'aprobado'
                ");
                if ($ecografistas_result->num_rows > 0) {
                    while($profesional = $ecografistas_result->fetch_assoc()) {
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
            <h2>Nuestros Especialistas</h2>
            <p>Conoce a los médicos especialistas en el diagnóstico y tratamiento de trastornos mentales.</p>
            <div class="professionals-list">
                <?php
                 $psiquiatras_result = $conex->query("
                    SELECT u.id, u.nombre_completo, (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', ') FROM usuario_especialidades ue JOIN especialidades e ON e.id = ue.especialidad_id WHERE ue.usuario_id = u.id) AS especialidades, 
                           (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c WHERE c.ecografista_id = u.id AND c.estado IN ('confirmada', 'completada')) as pacientes_atendidos
                    FROM usuarios u 
                    WHERE u.rol = 'ecografista' AND u.estado = 'aprobado'
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
            <h2>Nuestros Especialistas</h2>
            <p>Conoce a los médicos especialistas en el diagnóstico y tratamiento de trastornos mentales.</p>
            <div class="professionals-grid">
                <?php
                 $psiquiatras_result = $conex->query("
                    SELECT u.id, u.nombre_completo, (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', ') FROM usuario_especialidades ue JOIN especialidades e ON e.id = ue.especialidad_id WHERE ue.usuario_id = u.id) AS especialidades, 
                           (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c WHERE c.ecografista_id = u.id AND c.estado IN ('confirmada', 'completada')) as pacientes_atendidos
                    FROM usuarios u 
                    WHERE u.rol = 'ecografista' AND u.estado = 'aprobado'
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
<?php if ($rol_usuario == 'recepcionista'): ?>

    <!-- VISTA 0: DASHBOARD GENERAL PARA SECRETARÍA -->
<div id="vista-secretaria-dashboard" class="panel-vista active">
    <div class="panel-seccion">
        <h2>Resumen General</h2>
        <?php
            $totalPendientesSecretaria = 0;
            $citasConfirmadasHoy = 0;
            $pacientesActivosSecretaria = 0;
            $profesionalesActivosSecretaria = 0;
            $nuevasSolicitudesSecretaria = 0;

            if ($resultadoTemp = $conex->query("SELECT COUNT(*) AS total FROM citas WHERE estado = 'pendiente'")) {
                $fila = $resultadoTemp->fetch_assoc();
                $totalPendientesSecretaria = (int)($fila['total'] ?? 0);
                $resultadoTemp->free();
            }

            if ($resultadoTemp = $conex->query("SELECT COUNT(*) AS total FROM citas WHERE estado IN ('confirmada','reprogramada') AND DATE(fecha_cita) = CURDATE()")) {
                $fila = $resultadoTemp->fetch_assoc();
                $citasConfirmadasHoy = (int)($fila['total'] ?? 0);
                $resultadoTemp->free();
            }

            if ($resultadoTemp = $conex->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado'")) {
                $fila = $resultadoTemp->fetch_assoc();
                $pacientesActivosSecretaria = (int)($fila['total'] ?? 0);
                $resultadoTemp->free();
            }

            if ($resultadoTemp = $conex->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado'")) {
                $fila = $resultadoTemp->fetch_assoc();
                $profesionalesActivosSecretaria = (int)($fila['total'] ?? 0);
                $resultadoTemp->free();
            }

            if ($resultadoTemp = $conex->query("SELECT COUNT(*) AS total FROM citas WHERE estado = 'pendiente' AND fecha_solicitud >= (NOW() - INTERVAL 1 DAY)")) {
                $fila = $resultadoTemp->fetch_assoc();
                $nuevasSolicitudesSecretaria = (int)($fila['total'] ?? 0);
                $resultadoTemp->free();
            }

            $agendaHoySecretaria = [];
            if ($stmtAgendaHoySecretaria = $conex->prepare("SELECT c.fecha_cita, c.motivo_consulta, u.nombre_completo AS paciente_nombre, prof.nombre_completo AS profesional_nombre FROM citas c JOIN usuarios u ON c.paciente_id = u.id LEFT JOIN usuarios prof ON c.ecografista_id = prof.id WHERE c.estado IN ('confirmada','reprogramada') AND DATE(c.fecha_cita) = CURDATE() ORDER BY c.fecha_cita ASC LIMIT 5")) {
                $stmtAgendaHoySecretaria->execute();
                $resultadoAgenda = $stmtAgendaHoySecretaria->get_result();
                while ($filaAgenda = $resultadoAgenda->fetch_assoc()) {
                    $agendaHoySecretaria[] = $filaAgenda;
                }
                $stmtAgendaHoySecretaria->close();
            }

            $solicitudesRecientesSecretaria = [];
            if ($stmtSolicitudesRecientesSecretaria = $conex->prepare("SELECT c.id, c.fecha_solicitud, u.nombre_completo AS paciente_nombre, u.correo FROM citas c JOIN usuarios u ON c.paciente_id = u.id WHERE c.estado = 'pendiente' ORDER BY c.fecha_solicitud DESC LIMIT 5")) {
                $stmtSolicitudesRecientesSecretaria->execute();
                $resultadoSolicitudes = $stmtSolicitudesRecientesSecretaria->get_result();
                while ($filaSolicitud = $resultadoSolicitudes->fetch_assoc()) {
                    $solicitudesRecientesSecretaria[] = $filaSolicitud;
                }
                $stmtSolicitudesRecientesSecretaria->close();
            }

            $nuevosPacientesSecretaria = [];
            if ($resultadoTemp = $conex->query("SELECT nombre_completo, fecha_registro FROM usuarios WHERE rol = 'paciente' AND estado = 'aprobado' ORDER BY fecha_registro DESC LIMIT 3")) {
                while ($filaPaciente = $resultadoTemp->fetch_assoc()) {
                    $nuevosPacientesSecretaria[] = $filaPaciente;
                }
                $resultadoTemp->free();
            }

            $agendaHoyTotal = count($agendaHoySecretaria);
            $solicitudesRecientesTotal = count($solicitudesRecientesSecretaria);
            $nuevosPacientesTotal = count($nuevosPacientesSecretaria);
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon" style="background-color: rgba(2, 177, 244, 0.15); color: #02b1f4;"><i class="fa-solid fa-inbox"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($totalPendientesSecretaria); ?></div>
                    <div class="label">Citas pendientes</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon" style="background-color: rgba(34, 197, 94, 0.15); color: #15803d;"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($citasConfirmadasHoy); ?></div>
                    <div class="label">Citas programadas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon" style="background-color: rgba(99, 102, 241, 0.15); color: #4338ca;"><i class="fa-solid fa-users"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($pacientesActivosSecretaria); ?></div>
                    <div class="label">Pacientes activos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon" style="background-color: rgba(249, 115, 22, 0.15); color: #c2410c;"><i class="fa-solid fa-user-doctor"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($profesionalesActivosSecretaria); ?></div>
                    <div class="label">Profesionales disponibles</div>
                </div>
            </div>
        </div>

        <div class="secretary-dashboard-grid">
            <div class="secretary-dashboard-card agenda-card">
                <div class="card-header">
                    <span class="secretary-card-icon icon-blue"><i class="fa-solid fa-calendar-check"></i></span>
                    <div class="card-title-group">
                        <h3>Mi Agenda de hoy</h3>
                        <span class="card-subtitle">Citas confirmadas</span>
                    </div>
                    <span class="card-highlight"><?php echo number_format($agendaHoyTotal); ?> <?php echo $agendaHoyTotal === 1 ? 'cita programada' : 'citas programadas'; ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($agendaHoySecretaria)): ?>
                        <ul class="dashboard-list">
                            <?php foreach ($agendaHoySecretaria as $citaHoy): ?>
                                <?php
                                    $horaCita = !empty($citaHoy['fecha_cita']) ? date('h:i A', strtotime($citaHoy['fecha_cita'])) : 'Por definir';
                                    $motivoBruto = isset($citaHoy['motivo_consulta']) ? trim((string)$citaHoy['motivo_consulta']) : '';
                                    if ($motivoBruto !== '' && strlen($motivoBruto) > 80) {
                                        $motivoBruto = substr($motivoBruto, 0, 77) . '...';
                                    }
                                    $motivoTexto = $motivoBruto !== '' ? $motivoBruto : 'Sin motivo registrado';
                                    $profesionalTexto = $citaHoy['profesional_nombre'] ?? 'Por asignar';
                                ?>
                                <li class="dashboard-list-item">
                                    <span class="time-badge"><?php echo htmlspecialchars($horaCita); ?></span>
                                    <div class="list-content">
                                        <strong><?php echo htmlspecialchars($citaHoy['paciente_nombre'] ?? 'Paciente'); ?></strong>
                                        <p><?php echo htmlspecialchars($motivoTexto); ?></p>
                                        <div class="list-meta">
                                            <span class="mini-status"><i class="fa-solid fa-user-doctor"></i> <?php echo htmlspecialchars($profesionalTexto); ?></span>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="card-empty">
                            <i class="fa-solid fa-calendar-minus"></i>
                            <div>
                                <strong>Sin agenda confirmada.</strong>
                                <p>Cuando se programen citas para hoy aparecerán en este resumen.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="secretary-dashboard-card solicitudes-card">
                <div class="card-header">
                    <span class="secretary-card-icon icon-indigo"><i class="fa-solid fa-paper-plane"></i></span>
                    <div class="card-title-group">
                        <h3>Solicitudes recientes</h3>
                        <span class="card-subtitle">Últimas peticiones</span>
                    </div>
                    <span class="card-highlight"><?php echo number_format($solicitudesRecientesTotal); ?> <?php echo $solicitudesRecientesTotal === 1 ? 'solicitud abierta' : 'solicitudes abiertas'; ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($solicitudesRecientesSecretaria)): ?>
                        <ul class="dashboard-list">
                            <?php foreach ($solicitudesRecientesSecretaria as $solicitud): ?>
                                <?php
                                    $fechaSolicitud = !empty($solicitud['fecha_solicitud']) ? date('d/m H:i', strtotime($solicitud['fecha_solicitud'])) : 'Sin fecha';
                                    $correoSolicitud = $solicitud['correo'] ?? 'Sin correo';
                                ?>
                                <li class="dashboard-list-item">
                                    <span class="time-badge indigo"><?php echo htmlspecialchars($fechaSolicitud); ?></span>
                                    <div class="list-content">
                                        <strong><?php echo htmlspecialchars($solicitud['paciente_nombre'] ?? 'Paciente'); ?></strong>
                                        <p><?php echo htmlspecialchars($correoSolicitud); ?></p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                                <div id="vista-admin-tareas" class="panel-vista">
                                    <div class="panel-seccion">
                                        <h2>Tareas Rápidas Administrativas</h2>
                                        <p>Organiza los pendientes del día a día, asigna responsables y marca avances sin salir del panel.</p>
                                        <div class="admin-task-grid">
                                            <div class="admin-task-panel">
                                                <h3>Nueva tarea</h3>
                                                <form id="admin-task-form" class="admin-task-form">
                                                    <input type="text" id="admin-task-input" placeholder="Ej. Actualizar contratos de proveedores" maxlength="160" required>
                                                    <button type="submit"><i class="fa-solid fa-plus"></i> Añadir tarea</button>
                                                </form>
                                                <div class="admin-task-meta">
                                                    <span id="admin-task-counter">0 pendientes · 0 completadas</span>
                                                    <div class="admin-task-filters">
                                                        <button type="button" data-filter="all" class="active">Todas</button>
                                                        <button type="button" data-filter="pending">Pendientes</button>
                                                        <button type="button" data-filter="done">Completadas</button>
                                                    </div>
                                                </div>
                                                <ul id="admin-task-list" class="admin-task-list"></ul>
                                                <div id="admin-task-empty" class="admin-task-empty" style="display: none;">
                                                    <i class="fa-solid fa-clipboard-list"></i><br>
                                                    Aún no has añadido tareas. Empieza con la prioridad más alta.
                                                </div>
                                                <div style="display: flex; justify-content: flex-end;">
                                                    <button type="button" id="admin-task-clear" class="btn-secondary" style="background: #fee2e2; color: #b91c1c; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                                        <i class="fa-solid fa-broom"></i> Borrar completadas
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="admin-task-panel">
                                                <h3>Atajos de coordinación</h3>
                                                <ul class="admin-task-list" style="gap: 16px;">
                                                    <li class="admin-task-item" style="background: #f1f5f9; border-color: #e2e8f0;">
                                                        <div class="admin-task-text" style="font-weight: 600; color: #0f172a;">Actualiza esta lista al inicio y cierre de jornada.</div>
                                                    </li>
                                                    <li class="admin-task-item" style="background: #f1f5f9; border-color: #e2e8f0;">
                                                        <div class="admin-task-text">Prioriza vencimientos próximos (pagos, contratos, reportes regulatorios).</div>
                                                    </li>
                                                    <li class="admin-task-item" style="background: #f1f5f9; border-color: #e2e8f0;">
                                                        <div class="admin-task-text">Comparte enlaces relevantes pegándolos directamente en la descripción.</div>
                                                    </li>
                                                    <li class="admin-task-item" style="background: #f1f5f9; border-color: #e2e8f0;">
                                                        <div class="admin-task-text">Usa la sección Documentos para adjuntar soportes antes de marcar como finalizado.</div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                    <?php else: ?>
                        <div class="card-empty">
                            <i class="fa-solid fa-inbox"></i>
                            <div>
                                <strong>No hay solicitudes recientes.</strong>
                                <p>Las nuevas peticiones aparecerán automáticamente cuando lleguen.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="mini-status" style="align-self: flex-start; background: rgba(2, 177, 244, 0.12); color: #0369a1;">
                        <i class="fa-solid fa-bolt"></i> <?php echo number_format($nuevasSolicitudesSecretaria); ?> nuevas hoy
                    </div>
                </div>
            </div>

            <div class="secretary-dashboard-card">
                <div class="card-header">
                    <span class="secretary-card-icon icon-emerald"><i class="fa-solid fa-user-plus"></i></span>
                    <div class="card-title-group">
                        <h3>Pacientes recientes</h3>
                        <span class="card-subtitle">Registros aprobados</span>
                    </div>
                    <span class="card-highlight"><?php echo number_format($nuevosPacientesTotal); ?> <?php echo $nuevosPacientesTotal === 1 ? 'paciente nuevo' : 'pacientes nuevos'; ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($nuevosPacientesSecretaria)): ?>
                        <ul class="dashboard-list">
                            <?php foreach ($nuevosPacientesSecretaria as $pacienteNuevo): ?>
                                <?php $fechaRegistro = !empty($pacienteNuevo['fecha_registro']) ? date('d/m', strtotime($pacienteNuevo['fecha_registro'])) : '--'; ?>
                                <li class="dashboard-list-item">
                                    <span class="time-badge emerald"><?php echo htmlspecialchars($fechaRegistro); ?></span>
                                    <div class="list-content">
                                        <strong><?php echo htmlspecialchars($pacienteNuevo['nombre_completo'] ?? 'Paciente'); ?></strong>
                                        <p>Registrado recientemente</p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="card-empty">
                            <i class="fa-solid fa-user-clock"></i>
                            <div>
                                <strong>Sin nuevos pacientes.</strong>
                                <p>Cuando se aprueben nuevos registros aparecerán en este listado.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- VISTA 1: SOLICITUDES DE CITAS GENERALES (DISEÑO MEJORADO) -->
<div id="vista-solicitudes-generales" class="panel-vista">
    <div class="panel-seccion">
        <h2>Solicitudes de Cita Pendientes</h2>
        <p>Aquí se muestran todas las solicitudes de citas pendientes de asignar.</p>
        
        <div class="solicitudes-list">
            <?php
            $consulta_pendientes_gral = $conex->query("
                SELECT 
                    c.id,
                    c.motivo_consulta,
                    c.fecha_solicitud,
                    u.nombre_completo AS paciente_nombre,
                    u.cedula AS paciente_cedula,
                    u.correo AS paciente_correo,
                    TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS paciente_edad
                FROM citas c
                JOIN usuarios u ON c.paciente_id = u.id
                WHERE c.estado = 'pendiente'
                ORDER BY c.fecha_solicitud DESC
            ");

            if ($consulta_pendientes_gral && $consulta_pendientes_gral->num_rows > 0) {
                while($solicitud = $consulta_pendientes_gral->fetch_assoc()) {
                    $fechaSolicitudTimestamp = !empty($solicitud['fecha_solicitud']) ? strtotime($solicitud['fecha_solicitud']) : false;
                    $fechaSolicitudTexto = $fechaSolicitudTimestamp ? 'Recibida el ' . date('d/m H:i', $fechaSolicitudTimestamp) : 'Fecha no registrada';

                    $motivoBruto = trim((string)($solicitud['motivo_consulta'] ?? ''));
                    if ($motivoBruto !== '' && strlen($motivoBruto) > 160) {
                        $motivoBruto = substr($motivoBruto, 0, 157) . '...';
                    }
                    $motivoTexto = $motivoBruto !== '' ? $motivoBruto : 'Sin motivo registrado';

                    $correoTexto = !empty($solicitud['paciente_correo']) ? $solicitud['paciente_correo'] : 'Sin correo registrado';

                    $badges = [];
                    if (!empty($solicitud['paciente_cedula'])) {
                        $badges[] = '<span class="solicitud-badge"><i class="fa-solid fa-id-card"></i> C.I ' . htmlspecialchars($solicitud['paciente_cedula']) . '</span>';
                    }
                    if (!empty($solicitud['paciente_edad'])) {
                        $badges[] = '<span class="solicitud-badge"><i class="fa-solid fa-cake-candles"></i> ' . htmlspecialchars($solicitud['paciente_edad']) . ' años</span>';
                    }

                    echo '<div class="solicitud-card">';
                    echo '  <div class="solicitud-card-header">';
                    echo '      <span class="solicitud-card-icon"><i class="fa-solid fa-user-clock"></i></span>';
                    echo '      <div class="solicitud-card-title">';
                    echo '          <h4>' . htmlspecialchars($solicitud['paciente_nombre']) . '</h4>';
                    echo '          <span class="solicitud-card-subtitle">' . htmlspecialchars($fechaSolicitudTexto) . '</span>';
                    echo '      </div>';
                    echo '  </div>';

                    if (!empty($badges)) {
                        echo '  <div class="solicitud-card-badges">' . implode('', $badges) . '</div>';
                    }

                    echo '  <div class="solicitud-meta">';
                    echo '      <span><i class="fa-solid fa-envelope"></i> ' . htmlspecialchars($correoTexto) . '</span>';
                    echo '  </div>';

                    echo '  <div class="solicitud-motivo-box"><strong>Motivo:</strong> ' . htmlspecialchars($motivoTexto) . '</div>';

                    echo '  <div class="solicitud-actions">';
                    echo '      <button type="button" class="solicitud-action-primary" onclick="abrirModalAsignarCita(' . (int)$solicitud['id'] . ')"><i class="fa-solid fa-calendar-check"></i> Asignar y Programar</button>';
                    echo '  </div>';
                    echo '</div>';
                }
            } else {
                echo '<p>No hay solicitudes de cita pendientes.</p>';
            }
            ?>
        </div>
    </div>
</div>
    
    <!-- VISTA 3: GESTIÓN DE PACIENTES -->
    <div id="vista-gestion-pacientes" class="panel-vista">
        <div class="panel-seccion">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Gestión de Pacientes</h2>
                <button class="btn-outline-primary" onclick="abrirModalCrearPaciente()">
                    <i class="fa-solid fa-plus"></i> Añadir Paciente
                </button>
            </div>

            <div class="search-container">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="buscador-pacientes-secretaria" placeholder="Buscar paciente por nombre o cédula...">
            </div>

            <p style="margin-top: 15px; margin-bottom: 20px; color: #777; font-size: 16px;">
                Gestiona el directorio completo de pacientes para programar o asignar citas rápidamente.
            </p>

            <div id="tabla-pacientes-secretaria-container">
                <p>Cargando pacientes...</p>
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
        $consulta_psicologos = $conex->query("SELECT u.id, u.nombre_completo, u.correo, u.cedula, (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', ') FROM usuario_especialidades ue JOIN especialidades e ON e.id = ue.especialidad_id WHERE ue.usuario_id = u.id) AS especialidades, u.fecha_registro,
            (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c WHERE c.ecografista_id = u.id AND c.estado IN ('confirmada','completada')) AS pacientes_atendidos
            FROM usuarios u WHERE u.rol = 'ecografista' AND u.estado = 'aprobado' ORDER BY u.nombre_completo ASC");
        
        if ($consulta_psicologos->num_rows > 0) {
            echo "<table class='approvals-table'>";
            echo "<thead><tr><th>Nombre</th><th>Especialidad</th><th>Correo</th><th>Cédula</th><th>Pacientes Atendidos</th><th>Miembro Desde</th></tr></thead>";
            echo "<tbody>";
            while($profesional = $consulta_psicologos->fetch_assoc()) {
                $especialidad = !empty($profesional['especialidades']) ? $profesional['especialidades'] : 'No especificada';
                $fechaRegistro = !empty($profesional['fecha_registro']) ? date('d/m/Y', strtotime($profesional['fecha_registro'])) : 'Sin registro';
                $pacientesAtendidos = isset($profesional['pacientes_atendidos']) ? (int)$profesional['pacientes_atendidos'] : 0;
                echo "<tr>";
                echo "<td>" . htmlspecialchars($profesional['nombre_completo']) . "</td>";
                echo "<td>" . htmlspecialchars($especialidad) . "</td>";
                echo "<td>" . htmlspecialchars($profesional['correo']) . "</td>";
                echo "<td>" . htmlspecialchars($profesional['cedula'] ?? 'Sin dato') . "</td>";
                echo "<td>" . $pacientesAtendidos . "</td>";
                echo "<td>" . htmlspecialchars($fechaRegistro) . "</td>";
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
        $consulta_psiquiatras = $conex->query("SELECT u.id, u.nombre_completo, u.correo, u.cedula, (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', ') FROM usuario_especialidades ue JOIN especialidades e ON e.id = ue.especialidad_id WHERE ue.usuario_id = u.id) AS especialidades, u.fecha_registro,
            (SELECT COUNT(DISTINCT c.paciente_id) FROM citas c WHERE c.ecografista_id = u.id AND c.estado IN ('confirmada','completada')) AS pacientes_atendidos
            FROM usuarios u WHERE u.rol = 'ecografista' AND u.estado = 'aprobado' ORDER BY u.nombre_completo ASC");
        
        if ($consulta_psiquiatras->num_rows > 0) {
            echo "<table class='approvals-table'>";
            echo "<thead><tr><th>Nombre</th><th>Especialidad</th><th>Correo</th><th>Cédula</th><th>Pacientes Atendidos</th><th>Miembro Desde</th></tr></thead>";
            echo "<tbody>";
            while($profesional = $consulta_psiquiatras->fetch_assoc()) {
                $especialidad = !empty($profesional['especialidades']) ? $profesional['especialidades'] : 'No especificada';
                $fechaRegistro = !empty($profesional['fecha_registro']) ? date('d/m/Y', strtotime($profesional['fecha_registro'])) : 'Sin registro';
                $pacientesAtendidos = isset($profesional['pacientes_atendidos']) ? (int)$profesional['pacientes_atendidos'] : 0;
                echo "<tr>";
                echo "<td>" . htmlspecialchars($profesional['nombre_completo']) . "</td>";
                echo "<td>" . htmlspecialchars($especialidad) . "</td>";
                echo "<td>" . htmlspecialchars($profesional['correo']) . "</td>";
                echo "<td>" . htmlspecialchars($profesional['cedula'] ?? 'Sin dato') . "</td>";
                echo "<td>" . $pacientesAtendidos . "</td>";
                echo "<td>" . htmlspecialchars($fechaRegistro) . "</td>";
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

    <?php include __DIR__ . '/layouts/partials/panel_modales.php'; ?>


    <!-- PASO 1: Cargar la librería principal de Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script src="assets/js/shell-modals.js"></script>

    <script>window.ECO_PANEL = { csrf: '<?= csrf_token() ?>', usuarioId: <?= (int)$usuario_id ?> };</script>
    <script src="assets/js/panel.js?v=<?= @filemtime(__DIR__ . '/assets/js/panel.js') ?>"></script>





</body>
</html>