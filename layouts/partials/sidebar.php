<?php
/**
 * Sidebar lateral — TODAS las secciones del sistema agrupadas y filtradas por rol.
 *
 * Variables esperadas:
 *   $active_section  (string)  id de la sección activa
 *   $_SESSION['rol'] (string)  administrador | ecografista | recepcionista | paciente
 *   $_SESSION['nombre_completo'] (string)
 */
$rol = $_SESSION['rol'] ?? '';
$active_section = $active_section ?? '';

/* ---------------------------------------------------------------
 * MENÚ por ROL
 * Para cada item: id (= active_section), label, icon, href, roles
 * Los items se agrupan en bloques (heading). Si una página ya está
 * migrada al nuevo shell, su href apunta al archivo nuevo;
 * si no, apunta a panel.php?vista=slug (deep-link existente).
 * --------------------------------------------------------------- */

$menu = [
    /* ─── PRINCIPAL ─── */
    'Principal' => [
        /* === ADMINISTRADOR === */
        [
            'id' => 'dashboard',
            'label' => 'Panel de Control',
            'icon' => 'fa-solid fa-gauge-high',
            'href' => 'dashboard_v2.php',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'admin-personal',
            'label' => 'Añadir Personal',
            'icon' => 'fa-solid fa-users-cog',
            'href' => 'admin_personal.php',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'ver-usuarios',
            'label' => 'Usuarios',
            'icon' => 'fa-solid fa-users',
            'href' => 'ver_usuarios.php?filtro=aprobados',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'admin-especialidades',
            'label' => 'Especialidades',
            'icon' => 'fa-solid fa-stethoscope',
            'href' => 'admin_especialidades.php',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'agenda-general',
            'label' => 'Agenda General',
            'icon' => 'fa-solid fa-calendar-week',
            'href' => 'agenda_general.php',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'reportes',
            'label' => 'Reportes',
            'icon' => 'fa-solid fa-chart-line',
            'href' => 'reportes.php',
            'roles' => ['administrador', 'recepcionista'],
        ],

        /* === ECOGRAFISTA === */
        [
            'id' => 'dashboard',
            'label' => 'Panel de Control',
            'icon' => 'fa-solid fa-gauge-high',
            'href' => 'dashboard_v2.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'pacientes',
            'label' => 'Pacientes Clínicos',
            'icon' => 'fa-solid fa-users',
            'href' => 'mis_pacientes.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'proximas-citas',
            'label' => 'Próximas Citas',
            'icon' => 'fa-solid fa-hourglass-half',
            'href' => 'mis_proximas_citas.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'citas',
            'label' => 'Solicitudes de Cita',
            'icon' => 'fa-solid fa-inbox',
            'href' => 'mis_solicitudes.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'agenda',
            'label' => 'Mi Agenda',
            'icon' => 'fa-solid fa-calendar-days',
            'href' => 'mi_agenda.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'disponibilidad',
            'label' => 'Mi Disponibilidad',
            'icon' => 'fa-solid fa-clock',
            'href' => 'gestionar_disponibilidad.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'facturacion',
            'label' => 'Facturación',
            'icon' => 'fa-solid fa-cash-register',
            'href' => 'facturacion.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'estadisticas',
            'label' => 'Estadísticas',
            'icon' => 'fa-solid fa-chart-pie',
            'href' => 'estadisticas_ecografista.php',
            'roles' => ['ecografista'],
        ],

        /* === RECEPCIONISTA === */
        [
            'id' => 'dashboard',
            'label' => 'Panel de Control',
            'icon' => 'fa-solid fa-gauge-high',
            'href' => 'dashboard_v2.php',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'agenda-general',
            'label' => 'Agenda General',
            'icon' => 'fa-solid fa-calendar-week',
            'href' => 'agenda_general.php',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'solicitudes-generales',
            'label' => 'Citas Pendientes',
            'icon' => 'fa-solid fa-inbox',
            'href' => 'recepcion_citas_pendientes.php',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'gestion-pacientes',
            'label' => 'Gestión Pacientes',
            'icon' => 'fa-solid fa-address-book',
            'href' => 'recepcion_gestion_pacientes.php',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'facturacion',
            'label' => 'Facturación',
            'icon' => 'fa-solid fa-cash-register',
            'href' => 'facturacion.php',
            'roles' => ['recepcionista'],
        ],

        /* === PACIENTE === */
        [
            'id' => 'paciente-dashboard',
            'label' => 'Panel de Control',
            'icon' => 'fa-solid fa-house',
            'href' => 'dashboard_v2.php',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'miscitas',
            'label' => 'Mis Citas',
            'icon' => 'fa-solid fa-calendar-check',
            'href' => 'mis_citas_paciente.php',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'solicitar',
            'label' => 'Solicitar Nueva Cita',
            'icon' => 'fa-solid fa-file-circle-plus',
            'href' => 'solicitar_cita_paciente.php',
            'roles' => ['paciente'],
        ],
    ],

    /* ─── HISTORIAL / REGISTROS ─── */
    'Registros' => [
        [
            'id' => 'historial-citas',
            'label' => 'Historial de Citas',
            'icon' => 'fa-solid fa-clipboard-list',
            'href' => 'mi_historial_citas.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'historial-citas-general',
            'label' => 'Historial de Citas',
            'icon' => 'fa-solid fa-clipboard-list',
            'href' => 'recepcion_historial_citas.php',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'notas-sesion',
            'label' => 'Notas de Sesión',
            'icon' => 'fa-solid fa-notes-medical',
            'href' => 'mis_notas_sesion.php',
            'roles' => ['ecografista'],
        ],
        [
            'id' => 'directorio',
            'label' => 'Directorio Clínico',
            'icon' => 'fa-solid fa-user-doctor',
            'href' => 'recepcion_directorio.php',
            'roles' => ['recepcionista'],
        ],
        [
            'id' => 'admin-documentos',
            'label' => 'Repositorio Documentos',
            'icon' => 'fa-solid fa-folder-open',
            'href' => 'admin_documentos.php',
            'roles' => ['administrador'],
        ],
    ],

    /* ─── INFORMACIÓN (para pacientes) ─── */
    'Información' => [
        [
            'id' => 'mis-informes',
            'label' => 'Mis Informes',
            'icon' => 'fa-solid fa-file-medical',
            'href' => 'mis_informes_paciente.php',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'psicologos',
            'label' => 'Ecografistas Activos',
            'icon' => 'fa-solid fa-user-doctor',
            'href' => 'ecografistas_paciente.php',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'preparacion',
            'label' => 'Preparación de Estudios',
            'icon' => 'fa-solid fa-clipboard-list',
            'href' => 'preparacion_estudios_paciente.php',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'precios',
            'label' => 'Precios de Ecografías',
            'icon' => 'fa-solid fa-tag',
            'href' => 'precios_ecografias_paciente.php',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'faq',
            'label' => 'Preguntas Frecuentes',
            'icon' => 'fa-solid fa-circle-question',
            'href' => 'paciente_faq.php',
            'roles' => ['paciente'],
        ],
        [
            'id' => 'ayuda',
            'label' => 'Centro de Ayuda',
            'icon' => 'fa-solid fa-life-ring',
            'href' => 'paciente_ayuda.php',
            'roles' => ['paciente'],
        ],
    ],

    /* ─── CONFIGURACIÓN / SISTEMA ─── */
    'Sistema' => [
        [
            'id' => 'admin-contenido',
            'label' => 'Contenido Web',
            'icon' => 'fa-solid fa-file-pen',
            'href' => 'admin_contenido.php',
            'roles' => ['administrador'],
        ],
        [
            'id' => 'notas-rapidas',
            'label' => 'Notas Rápidas',
            'icon' => 'fa-solid fa-note-sticky',
            'href' => 'notas_rapidas.php',
            'roles' => ['administrador', 'recepcionista'],
        ],
        [
            'id' => 'perfil',
            'label' => 'Mi Perfil',
            'icon' => 'fa-solid fa-user-gear',
            'href' => 'perfil.php',
            'roles' => ['administrador', 'ecografista', 'recepcionista', 'paciente'],
        ],
    ],
];

/* Render helper */
function eco_render_sidebar_item(array $item, string $active_section, string $rol): string
{
    if (!in_array($rol, $item['roles'], true)) return '';
    $is_active = ($item['id'] === $active_section) ? ' is-active' : '';
    return sprintf(
        '<a href="%s" class="sidebar-link%s" data-tooltip="%s">'
        . '<i class="%s"></i><span>%s</span></a>',
        htmlspecialchars($item['href']),
        $is_active,
        htmlspecialchars($item['label']),
        htmlspecialchars($item['icon']),
        htmlspecialchars($item['label'])
    );
}

$nombre_usuario = $_SESSION['nombre_completo'] ?? 'Usuario';
$iniciales = '';
foreach (explode(' ', trim($nombre_usuario)) as $p) {
    if ($p !== '' && strlen($iniciales) < 2) $iniciales .= strtoupper($p[0]);
}
if ($iniciales === '') $iniciales = 'U';
?>

<aside class="app-sidebar">

    <a href="<?= $rol === 'paciente' ? 'dashboard_v2.php' : 'dashboard_v2.php' ?>" class="sidebar-brand">
        <div class="sidebar-brand-logo">
            <i class="fa-solid fa-wave-square"></i>
        </div>
        <div class="sidebar-brand-text">
            <strong>EcoMadelleine</strong>
            <small>Clínica de Ecografías</small>
        </div>
    </a>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $grupo => $items):
            $items_visibles = array_filter($items, fn($i) => in_array($rol, $i['roles'], true));
            if (empty($items_visibles)) continue;
        ?>
            <div class="sidebar-nav-section"><?= htmlspecialchars($grupo) ?></div>
            <?php foreach ($items_visibles as $item) {
                echo eco_render_sidebar_item($item, $active_section, $rol);
            } ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar"><?= htmlspecialchars($iniciales) ?></div>
        <div class="sidebar-user-info">
            <strong><?= htmlspecialchars($nombre_usuario) ?></strong>
            <small><?= htmlspecialchars($rol ?: 'usuario') ?></small>
        </div>
        <a href="logout.php" class="sidebar-user-logout" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>

</aside>
<div class="sidebar-backdrop"></div>
