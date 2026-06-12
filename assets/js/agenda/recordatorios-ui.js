/* Recordatorios de cita — UI compartida (Fase 4B).
 * Necesita: boton #agenda-btn-recordatorios y el partial modal_recordatorios.php.
 * POST a cron_recordatorios.php (el fetch-wrapper de shell.php agrega el CSRF). */
(function () {
    var openBtn = document.getElementById('agenda-btn-recordatorios');
    var modal   = document.getElementById('eco-recd-modal');
    if (!openBtn || !modal) return;
    var closeBtn = document.getElementById('eco-recd-close');
    var runBtn   = document.getElementById('eco-recd-run');
    var dryBtn   = document.getElementById('eco-recd-dry');
    var result   = document.getElementById('eco-recd-result');

    function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function fmtFecha(s){ if(!s) return ''; var d=new Date(String(s).replace(' ','T')); if(isNaN(d)) return s; return d.toLocaleString('es',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}); }
    function abrir(){ modal.hidden=false; result.innerHTML=''; }
    function cerrar(){ modal.hidden=true; }
    openBtn.addEventListener('click', abrir);
    closeBtn.addEventListener('click', cerrar);
    modal.addEventListener('click', function(e){ if(e.target===modal) cerrar(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') cerrar(); });

    function render(d, dry){
        var html = '<div class="eco-recd-summary">'+(dry?'Previsualización: ':'')+ d.encontradas +' cita(s) en la ventana'+(dry?'':' · '+d.email_ok+' email · '+d.in_app+' notif')+'</div>';
        if(!d.items || !d.items.length){ html+='<div class="eco-recd-empty">No hay recordatorios pendientes ahora mismo.</div>'; result.innerHTML=html; return; }
        d.items.forEach(function(it){
            var tag = dry ? '<span class="eco-recd-tag dry">pendiente</span>'
                : (it.email==='enviado' ? '<span class="eco-recd-tag ok">email enviado</span>'
                : (it.email==='sin_correo' ? '<span class="eco-recd-tag no">sin correo</span>'
                : (it.email==='fallido' ? '<span class="eco-recd-tag no">email falló</span>' : '<span class="eco-recd-tag ok">notificado</span>')));
            var wa = it.wa_link ? '<a class="eco-recd-wa" href="'+esc(it.wa_link)+'" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>' : '';
            html += '<div class="eco-recd-item"><span><span class="nm">'+esc(it.paciente)+'</span><br><span class="fc">'+esc(fmtFecha(it.fecha))+'</span></span><span class="sp">'+tag+wa+'</span></div>';
        });
        result.innerHTML = html;
    }

    function ejecutar(dry, btn){
        var prev = btn.innerHTML; btn.disabled=true; runBtn.disabled=true; dryBtn.disabled=true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando…';
        var body = new URLSearchParams(); if(dry) body.set('dry','1');
        fetch((window.ECO_BASE || '') + 'cli/cron_recordatorios.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
            .then(function(r){return r.json();})
            .then(function(d){
                runBtn.disabled=false; dryBtn.disabled=false; btn.innerHTML=prev;
                if(!d||!d.success){ result.innerHTML='<div class="eco-recd-empty">No se pudo procesar.</div>'; return; }
                render(d, dry);
            })
            .catch(function(){ runBtn.disabled=false; dryBtn.disabled=false; btn.innerHTML=prev; result.innerHTML='<div class="eco-recd-empty">Error de red.</div>'; });
    }

    runBtn.addEventListener('click', function(){ if(confirm('¿Enviar recordatorios por email + notificación a los pacientes con cita en 24 h?')) ejecutar(false, runBtn); });
    dryBtn.addEventListener('click', function(){ ejecutar(true, dryBtn); });
})();
