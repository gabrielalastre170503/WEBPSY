<?php
/**
 * Modales shell: detalle y reprogramacion de cita (ecografista).
 *
 * IDs: eco-modal-detalle-cita-eco, eco-modal-reprogramar-cita-eco
 * JS: assets/js/ecografista-modals.js
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
#eco-modal-reprogramar-cita-eco .eco-field label i { color:var(--accent); margin-right:5px; }
#eco-modal-reprogramar-cita-eco .eco-modal__title i { color:var(--accent); margin-right:7px; }
</style>
<div id="eco-modal-detalle-cita-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-detalle-cita-title">
    <div class="eco-modal__dialog eco-modal__dialog--compact" style="max-width:540px;">
        <div class="eco-modal__main" style="padding-top:24px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <h4 class="eco-modal__title" id="eco-detalle-cita-title">Detalle de cita</h4>
            <div id="eco-detalle-cita-body">
                <p class="eco-modal__body-text">Cargando detalle...</p>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-reprogramar-cita-eco" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-reprogramar-title">
    <div class="eco-modal__dialog" style="max-width:540px;">
        <div class="eco-modal__main" style="padding-top:24px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <h4 class="eco-modal__title" id="eco-reprogramar-title"><i class="fa-solid fa-calendar-pen"></i>Reprogramar cita</h4>
            <p class="eco-modal__body-text" style="margin-top:-6px;">La nueva fecha quedará pendiente de confirmación por el paciente.</p>

            <div class="rep-card">
                <div class="rep-card__head">
                    <strong class="rep-name" id="eco-reprogramar-paciente">-</strong>
                    <span class="badge" id="eco-rep-estado">—</span>
                </div>
                <div class="rep-card__grid">
                    <div class="rep-li"><i class="fa-solid fa-id-card"></i><span id="eco-rep-sub">—</span></div>
                    <div class="rep-li"><i class="fa-solid fa-wave-square"></i><span id="eco-rep-estudio">—</span></div>
                    <div class="rep-li"><i class="fa-solid fa-location-dot"></i><span id="eco-rep-modalidad">—</span></div>
                    <div class="rep-li rep-current"><i class="fa-regular fa-clock"></i><span><strong>Actual:</strong> <span id="eco-reprogramar-fecha-actual">-</span></span></div>
                </div>
                <div class="rep-motivo" id="eco-rep-motivo-box" style="display:none;">
                    <i class="fa-solid fa-quote-left"></i><span id="eco-rep-motivo-text">—</span>
                </div>
            </div>

            <div id="eco-reprogramar-error" style="display:none;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);color:#b91c1c;" role="alert"></div>

            <form id="eco-form-reprogramar-cita" novalidate>
                <input type="hidden" name="cita_id" id="eco-reprogramar-cita-id">
                <div class="eco-field">
                    <label for="eco-reprogramar-calendario"><i class="fa-solid fa-calendar-day"></i> Nueva fecha y hora</label>
                    <input type="text" name="nueva_fecha_cita" id="eco-reprogramar-calendario" required autocomplete="off" placeholder="Seleccionar...">
                </div>
                <div class="eco-field">
                    <label for="eco-reprogramar-motivo"><i class="fa-solid fa-comment-dots"></i> Motivo de la reprogramación</label>
                    <textarea name="motivo_reprogramacion" id="eco-reprogramar-motivo" rows="3" required placeholder="Se notificará al paciente."></textarea>
                </div>
                <div class="eco-modal__footer">
                    <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                    <button type="submit" class="btn-primary" id="eco-reprogramar-submit"><i class="fa-solid fa-paper-plane"></i> Enviar propuesta</button>
                </div>
            </form>
        </div>
    </div>
</div>
