<?php
/**
 * routes.php — Tabla de rutas limpias → handlers existentes (alias aditivo).
 *
 * Cada ruta incluye el .php existente; ese handler corre sus propios chequeos
 * de sesión/rol y su lógica, sin cambios. Las URLs `.php?query` viejas siguen
 * funcionando en paralelo (fallback). Migración de referencias = gradual.
 *
 * @var EcoRouter $r  (definido en router.php)
 */

/* ── Públicas / identidad ── */
$r->get('/login',         'login.php');
$r->any('/registro',      'registro.php');
$r->any('/recuperar',     'recuperar.php');
$r->any('/verificar-2fa', 'verificar_2fa.php');
$r->get('/privacidad',    'privacidad.php');
$r->get('/logout',        'logout.php');

/* ── Comunes ── */
$r->get('/dashboard', 'dashboard_v2.php');
$r->get('/perfil',    'perfil.php');
$r->get('/reportes',  'reportes.php');
$r->get('/agenda',    'agenda_general.php');
$r->get('/facturacion', 'facturacion.php');
$r->any('/consentimiento', 'consentimiento.php');

/* ── Informes ── */
$r->get('/informe/{informe_id}', 'ver_informe_estudio.php');
$r->get('/mis-informes',         'mis_informes_paciente.php');

/* ── Administrador ── */
$r->get('/personal',      'admin/admin_personal.php');
$r->get('/usuarios',      'ver_usuarios.php');
$r->any('/especialidades', 'admin/admin_especialidades.php');
$r->any('/repositorio',   'admin/admin_documentos.php');
$r->get('/contenido',     'admin/admin_contenido.php');
$r->get('/auditoria',     'auditoria.php');
$r->get('/notas-rapidas', 'notas_rapidas.php');

/* ── Ecografista ── */
$r->get('/mis-pacientes',  'ecografista/mis_pacientes.php');
$r->get('/proximas-citas', 'ecografista/mis_proximas_citas.php');
$r->get('/solicitudes',    'ecografista/mis_solicitudes.php');
$r->get('/mi-agenda',      'ecografista/mi_agenda.php');
$r->get('/disponibilidad', 'ecografista/gestionar_disponibilidad.php');
$r->get('/historial-citas', 'ecografista/mi_historial_citas.php');
$r->get('/notas-sesion',   'ecografista/mis_notas_sesion.php');

/* ── Recepcionista ── */
$r->get('/citas-pendientes',    'recepcion/recepcion_citas_pendientes.php');
$r->get('/gestion-pacientes',   'recepcion/recepcion_gestion_pacientes.php');
$r->get('/historial-recepcion', 'recepcion/recepcion_historial_citas.php');
$r->get('/directorio',          'recepcion/recepcion_directorio.php');
$r->get('/ficha-paciente',      'recepcion/recepcion_ficha_paciente.php');

/* ── Paciente ── */
$r->get('/mis-citas',       'mis_citas_paciente.php');
$r->get('/solicitar-cita',  'solicitar_cita_paciente.php');
$r->get('/ecografistas',    'ecografistas_paciente.php');
$r->get('/preparacion',     'preparacion_estudios_paciente.php');
$r->get('/precios',         'precios_ecografias_paciente.php');
$r->get('/faq',             'paciente_faq.php');
$r->get('/ayuda',           'paciente_ayuda.php');
