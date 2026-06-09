<?php
/**
 * Partial reutilizable: modal "Recordatorios de cita" (Fase 4B).
 * Lo usan agenda_general.php (admin/recep) y mi_agenda.php (ecografista).
 * Requiere un boton con id="agenda-btn-recordatorios" y assets/js/recordatorios-ui.js.
 */
?>
<div id="eco-recd-modal" class="eco-recd-modal" hidden>
    <div class="eco-recd-dialog">
        <div class="eco-recd-head">
            <strong><i class="fa-solid fa-bell"></i> Recordatorios de cita</strong>
            <button type="button" class="eco-recd-x" id="eco-recd-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="eco-recd-body">
            <p class="eco-recd-intro">Envía recordatorio por <strong>email + notificación</strong> a los pacientes con cita en las próximas <strong>24 horas</strong> que aún no lo recibieron. WhatsApp se ofrece como enlace para enviar a mano.</p>
            <div class="eco-recd-actions">
                <button type="button" class="btn-secondary" id="eco-recd-dry"><i class="fa-solid fa-eye"></i> Previsualizar</button>
                <button type="button" class="btn-primary" id="eco-recd-run"><i class="fa-solid fa-paper-plane"></i> Enviar ahora</button>
            </div>
            <div id="eco-recd-result"></div>
        </div>
    </div>
</div>
<style>
.eco-recd-modal{position:fixed;inset:0;background:rgba(10,18,30,.55);display:flex;align-items:center;justify-content:center;z-index:9999;padding:20px;}
.eco-recd-modal[hidden]{display:none;}
.eco-recd-dialog{background:var(--bg-surface);border:1px solid var(--border);border-radius:14px;width:560px;max-width:96vw;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;}
.eco-recd-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-soft,var(--border));}
.eco-recd-head strong{font-size:15px;color:var(--text-primary);display:flex;align-items:center;gap:8px;}
.eco-recd-head i{color:var(--accent);}
.eco-recd-x{background:none;border:none;font-size:24px;line-height:1;color:var(--text-muted);cursor:pointer;}
.eco-recd-body{padding:18px 20px;overflow-y:auto;}
.eco-recd-intro{font-size:13px;color:var(--text-secondary);line-height:1.55;margin:0 0 14px;}
.eco-recd-actions{display:flex;gap:10px;margin-bottom:8px;}
.eco-recd-summary{margin:14px 0 10px;font-size:13px;color:var(--text-primary);font-weight:600;}
.eco-recd-item{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border-soft,var(--border));font-size:13px;}
.eco-recd-item:last-child{border-bottom:none;}
.eco-recd-item .nm{font-weight:600;color:var(--text-primary);}
.eco-recd-item .fc{color:var(--text-muted);font-size:12px;}
.eco-recd-item .sp{margin-left:auto;display:flex;align-items:center;gap:8px;}
.eco-recd-tag{font-size:11px;font-weight:600;border-radius:10px;padding:2px 8px;}
.eco-recd-tag.ok{background:rgba(34,197,94,.14);color:#15803d;}
.eco-recd-tag.no{background:rgba(239,68,68,.12);color:#b91c1c;}
.eco-recd-tag.dry{background:rgba(245,158,11,.14);color:#b45309;}
.eco-recd-wa{display:inline-flex;align-items:center;gap:5px;background:#25d366;color:#fff;border-radius:8px;padding:4px 9px;font-size:11.5px;font-weight:600;text-decoration:none;}
.eco-recd-empty{padding:18px 0;text-align:center;color:var(--text-muted);font-size:13px;}
</style>
