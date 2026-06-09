<?php
/**
 * Modal: proponer nueva fecha a paciente (solicitud pendiente).
 * POST JSON a guardar_propuesta.php
 *
 * ID: eco-modal-proponer-fecha-eco
 */
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'ecografista') {
    return;
}
?>
<style>
.rep-card { padding:14px 16px; border-radius:10px; background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.35); margin-bottom:16px; }
.rep-card__head { display:flex; align-items:center; gap:10px; margin-bottom:11px; }
.rep-name { font-size:14.5px; font-weight:700; color:var(--text-primary); min-width:0; }
.rep-card__head .badge { margin-left:auto; flex-shrink:0; }
.rep-card__grid { display:grid; grid-template-columns:1fr 1fr; gap:9px 16px; }
.rep-li { display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--text-secondary); min-width:0; }
.rep-li > i { color:#b45309; width:15px; text-align:center; flex-shrink:0; }
.rep-li span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.rep-li.rep-current { grid-column:1/-1; color:var(--text-primary); font-weight:600; }
.rep-li.rep-current strong { color:#b45309; font-weight:700; }
.rep-motivo { margin-top:11px; padding-top:11px; border-top:1px dashed rgba(245,158,11,.4); font-size:12.5px; color:var(--text-secondary); line-height:1.5; }
.rep-motivo > i { color:#b45309; opacity:.6; margin-right:4px; }
#eco-modal-proponer-fecha-eco .eco-field label i { color:var(--accent); margin-right:5px; }
#eco-modal-proponer-fecha-eco .eco-modal__title i { color:var(--accent); margin-right:7px; }
</style>
<div id="eco-modal-proponer-fecha-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-modal-proponer-title">
    <div class="eco-modal__dialog" style="max-width:540px;">
        <div class="eco-modal__main" style="padding-top:24px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <h4 class="eco-modal__title" id="eco-modal-proponer-title"><i class="fa-solid fa-calendar-plus"></i>Proponer nueva fecha</h4>
            <p class="eco-modal__body-text" style="margin-top:-6px;">El paciente recibirá la notificación para confirmar.</p>

            <div class="rep-card">
                <div class="rep-card__head">
                    <strong class="rep-name" id="eco-proponer-paciente-nombre">—</strong>
                    <span class="badge" id="eco-prop-estado">—</span>
                </div>
                <div class="rep-card__grid">
                    <div class="rep-li"><i class="fa-solid fa-id-card"></i><span id="eco-prop-sub">—</span></div>
                    <div class="rep-li"><i class="fa-solid fa-wave-square"></i><span id="eco-prop-estudio">—</span></div>
                    <div class="rep-li"><i class="fa-solid fa-location-dot"></i><span id="eco-prop-modalidad">—</span></div>
                    <div class="rep-li rep-current"><i class="fa-regular fa-clock"></i><span><strong>Solicitó:</strong> <span id="eco-proponer-fecha-actual">—</span></span></div>
                </div>
                <div class="rep-motivo" id="eco-prop-motivo-box" style="display:none;">
                    <i class="fa-solid fa-quote-left"></i><span id="eco-prop-motivo-text">—</span>
                </div>
            </div>

            <div id="eco-proponer-error" style="display:none;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);color:#b91c1c;" role="alert"></div>

            <form id="eco-form-proponer-fecha">
                <input type="hidden" name="cita_id" id="eco-proponer-cita-id" value="">
                <div class="eco-field">
                    <label for="eco-proponer-calendario"><i class="fa-solid fa-calendar-day"></i> Nueva fecha y hora sugerida</label>
                    <input type="text" name="fecha_propuesta" id="eco-proponer-calendario" required autocomplete="off" placeholder="Seleccionar…">
                </div>
                <div class="eco-field">
                    <label for="eco-proponer-motivo"><i class="fa-solid fa-comment-dots"></i> Motivo (visible para el paciente)</label>
                    <textarea name="motivo_reprogramacion" id="eco-proponer-motivo" rows="3" required placeholder="Ej.: No tengo disponibilidad en ese horario; propongo esta alternativa."></textarea>
                </div>
                <div class="eco-modal__footer">
                    <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                    <button type="submit" class="btn-primary" id="eco-proponer-submit"><i class="fa-solid fa-paper-plane"></i> Enviar propuesta</button>
                </div>
            </form>
        </div>
    </div>
</div>
