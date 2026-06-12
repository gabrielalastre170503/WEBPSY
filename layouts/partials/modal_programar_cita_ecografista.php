<?php
/**
 * Modal shell: programar cita directa (ecografista).
 * IDs: eco-modal-programar-cita-eco — JS: assets/js/panel/ecografista-modals.js
 * Backend: guardar_cita_directa.php (requiere tipo_ecografia_id + fecha_cita).
 */
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    return;
}
if (!isset($conex) || !($conex instanceof mysqli)) {
    return;
}
require_once __DIR__ . '/../../lib/informes/catalogo.php';
$eco_prog_tipos_rows = eco_catalogo_tipos_activos($conex);
?>
<div id="eco-modal-programar-cita-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-prog-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <h3 id="eco-prog-aside-title">Nueva cita</h3>
                <p>Agendar estudio ecográfico para:</p>
                <strong id="eco-prog-paciente-nombre">—</strong>
                <p class="eco-modal__hint"><i class="fa-solid fa-circle-info" style="margin-right:4px;"></i> La cita queda confirmada y el paciente recibe notificación.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" onclick="window.cerrarProgramarCitaEco && cerrarProgramarCitaEco()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Detalle de la cita</h4>
                <div id="eco-prog-cita-error" style="display:none;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);color:#b91c1c;" role="alert"></div>
                <form id="eco-form-programar-cita-eco" action="<?= eco_url('api/guardar_cita_directa.php') ?>" method="post" novalidate>
                    <input type="hidden" name="paciente_id" id="eco-prog-paciente-id" value="">
                    <div class="eco-field">
                        <label for="eco-prog-tipo-eco">Tipo de ecografía</label>
                        <select name="tipo_ecografia_id" id="eco-prog-tipo-eco" required>
                            <option value="">Selecciona el estudio…</option>
                            <?php foreach ($eco_prog_tipos_rows as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars(($t['categoria'] ? $t['categoria'] . ' — ' : '') . $t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="eco-field">
                        <label for="eco-prog-fecha-eco">Fecha y hora</label>
                        <input type="text" name="fecha_cita" id="eco-prog-fecha-eco" required placeholder="Seleccionar…" autocomplete="off">
                    </div>
                    <div class="eco-field">
                        <label for="eco-prog-motivo-eco">Motivo / indicación <span style="font-weight:400;color:var(--text-muted);">(opcional)</span></label>
                        <textarea name="motivo_consulta" id="eco-prog-motivo-eco" rows="4" placeholder="Ej.: control evolutivo, dolor abdominal…"></textarea>
                    </div>
                    <div class="eco-modal__footer">
                        <button type="button" class="btn-secondary" onclick="window.cerrarProgramarCitaEco && cerrarProgramarCitaEco()">Cancelar</button>
                        <button type="submit" class="btn-primary" id="eco-prog-submit"><i class="fa-solid fa-check"></i> Guardar cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
