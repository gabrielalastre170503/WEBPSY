<?php
/**
 * routes.php — Tabla de rutas limpias → handlers existentes (alias aditivo).
 *
 * Cada ruta limpia incluye el .php existente; ese handler corre sus propios
 * chequeos de sesión/rol y su lógica, sin cambios. Las URLs `.php?query` viejas
 * siguen funcionando en paralelo. Migración de referencias = gradual, después.
 *
 * @var EcoRouter $r  (definido en router.php)
 */

/* ── Públicas ── */
$r->get('/login',       'login.php');
$r->any('/registro',    'registro.php');
$r->any('/recuperar',   'recuperar.php');
$r->get('/privacidad',  'privacidad.php');
$r->get('/logout',      'logout.php');
$r->any('/verificar-2fa', 'verificar_2fa.php');

/* ── Panel / dashboard ── */
$r->get('/dashboard',   'dashboard_v2.php');
$r->get('/perfil',      'perfil.php');

/* ── Informes ── */
$r->get('/informe/{informe_id}', 'ver_informe_estudio.php');
$r->get('/mis-informes',         'mis_informes_paciente.php');

/* ── Citas / agenda ── */
$r->get('/mi-agenda',        'mi_agenda.php');
$r->get('/mis-citas',        'mis_proximas_citas.php');
$r->get('/historial-citas',  'mi_historial_citas.php');
$r->get('/agenda',           'agenda_general.php');

/* ── Pacientes ── */
$r->get('/mis-pacientes',    'mis_pacientes.php');
$r->get('/notas-sesion',     'mis_notas_sesion.php');

/* ── Reportes / cumplimiento ── */
$r->get('/reportes',   'reportes.php');
$r->get('/auditoria',  'auditoria.php');
