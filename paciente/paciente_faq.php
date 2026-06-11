<?php
session_start();
include __DIR__ . '/../conexion.php';
include __DIR__ . '/../data_asistente.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if ($_SESSION['rol'] !== 'paciente') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

/* FAQs visibles en el acordeón */
$faqs = [];
$fr = $conex->query('SELECT pregunta, respuesta FROM faqs WHERE activa = 1 ORDER BY orden ASC, id ASC');
if ($fr) {
    while ($row = $fr->fetch_assoc()) {
        $faqs[] = $row;
    }
    $fr->free();
}
$total_faqs = count($faqs);

/* Base de conocimiento completa del asistente local */
$kb = construir_kb($conex);

$page_title    = 'Preguntas frecuentes';
$page_subtitle = 'Pregúntale al asistente o explora las respuestas más comunes';
$active_section = 'faq';

ob_start();
?>

<style>
/* ── Asistente / chat ── */
.asis-card { margin-bottom:18px; }
.asis-head { display:flex; align-items:center; gap:13px; margin-bottom:6px; }
.asis-head__icon { width:44px; height:44px; border-radius:13px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:19px; color:#fff; background:linear-gradient(135deg,var(--accent),#38bdf8); box-shadow:0 6px 14px rgba(2,177,244,.25); }
.asis-head__t h3 { margin:0; font-size:15.5px; font-weight:700; color:var(--text-primary); }
.asis-head__t p { margin:2px 0 0; font-size:12.5px; color:var(--text-secondary); }
.asis-priv { font-size:11px; color:var(--text-muted); display:inline-flex; align-items:center; gap:6px; margin-top:10px; }

.asis-chat { display:flex; flex-direction:column; gap:12px; max-height:340px; overflow-y:auto; padding:14px 4px; margin:8px 0 12px; }
.asis-msg { display:flex; gap:10px; max-width:92%; }
.asis-msg--bot { align-self:flex-start; }
.asis-msg--user { align-self:flex-end; flex-direction:row-reverse; }
.asis-avatar { width:30px; height:30px; border-radius:9px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:12px; }
.asis-msg--bot .asis-avatar { background:var(--accent-soft); color:var(--accent-text); }
.asis-msg--user .asis-avatar { background:var(--bg-muted); color:var(--text-secondary); }
.asis-bubble { padding:11px 14px; border-radius:14px; font-size:13.5px; line-height:1.55; }
.asis-msg--bot .asis-bubble { background:var(--bg-muted); color:var(--text-primary); border-top-left-radius:4px; }
.asis-msg--user .asis-bubble { background:var(--accent); color:#fff; border-top-right-radius:4px; }
.asis-bubble strong.asis-title { display:block; font-size:12px; color:var(--accent-text); margin-bottom:4px; }
.asis-link { display:inline-flex; align-items:center; gap:7px; margin-top:10px; padding:7px 13px; border-radius:8px; font-size:12.5px; font-weight:600; text-decoration:none; background:var(--accent-soft); color:var(--accent-text); border:1px solid rgba(2,177,244,.25); }
.asis-link:hover { background:var(--accent); color:#fff; }

.asis-typing { display:inline-flex; gap:4px; align-items:center; padding:13px 14px; }
.asis-typing span { width:7px; height:7px; border-radius:50%; background:var(--text-muted); opacity:.5; animation:asisBlink 1.2s infinite; }
.asis-typing span:nth-child(2){ animation-delay:.2s; } .asis-typing span:nth-child(3){ animation-delay:.4s; }
@keyframes asisBlink { 0%,60%,100%{ transform:translateY(0); opacity:.35; } 30%{ transform:translateY(-4px); opacity:1; } }

.asis-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
.asis-chip { padding:7px 13px; border-radius:999px; font-size:12px; font-weight:600; cursor:pointer; background:var(--bg-surface); color:var(--text-secondary); border:1px solid var(--border); transition:all .18s ease; }
.asis-chip:hover { border-color:var(--accent); color:var(--accent-text); background:var(--accent-soft); }

.asis-form { position:relative; display:flex; gap:10px; align-items:center; }
.asis-form i.asis-ico { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:14px; pointer-events:none; }
.asis-input { flex:1; padding:13px 14px 13px 40px; border:1.5px solid var(--border); border-radius:12px; font-family:inherit; font-size:14px; background:var(--bg-surface); color:var(--text-primary); box-sizing:border-box; transition:border-color .18s ease, box-shadow .18s ease; }
.asis-input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(2,177,244,.12); }
.asis-send { flex-shrink:0; display:inline-flex; align-items:center; gap:7px; padding:13px 18px; border-radius:12px; border:none; cursor:pointer; font-family:inherit; font-size:13.5px; font-weight:600; color:#fff; background:var(--accent); transition:filter .18s ease; }
.asis-send:hover { filter:brightness(.95); }
.asis-hint { font-size:11.5px; color:var(--text-muted); margin:9px 0 0; display:flex; align-items:center; gap:7px; }

/* ── Acordeón FAQ ── */
.faq-section-title { font-size:13px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.5px; margin:0 0 12px; display:flex; align-items:center; gap:8px; }
.faq-list { display:flex; flex-direction:column; gap:10px; }
.faq-item { border:1px solid var(--border); border-radius:var(--radius-lg); background:var(--bg-surface); overflow:hidden; transition:border-color .2s ease, box-shadow .2s ease; }
.faq-item.is-open { border-color:rgba(2,177,244,.4); box-shadow:var(--shadow); }
.faq-q { width:100%; display:flex; align-items:center; gap:14px; padding:16px 18px; background:none; border:none; cursor:pointer; font-family:inherit; text-align:left; }
.faq-q__icon { width:34px; height:34px; border-radius:10px; flex-shrink:0; background:var(--accent-soft); color:var(--accent-text); display:flex; align-items:center; justify-content:center; font-size:13px; transition:all .2s ease; }
.faq-item.is-open .faq-q__icon { background:var(--accent); color:#fff; }
.faq-q__text { flex:1; font-size:14px; font-weight:600; color:var(--text-primary); line-height:1.4; }
.faq-q__chevron { color:var(--text-muted); transition:transform .25s ease; flex-shrink:0; font-size:13px; }
.faq-item.is-open .faq-q__chevron { transform:rotate(180deg); color:var(--accent-text); }
.faq-a { max-height:0; overflow:hidden; transition:max-height .3s ease; }
.faq-a__inner { padding:0 18px 18px 66px; font-size:13.5px; color:var(--text-secondary); line-height:1.6; }

.faq-empty { text-align:center; padding:36px 24px; color:var(--text-muted); }
.faq-empty > i { font-size:38px; color:var(--accent); opacity:.5; margin-bottom:12px; display:block; }

.faq-help { margin-top:18px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; background:linear-gradient(135deg,var(--accent-soft),var(--bg-surface)); border:1px solid rgba(2,177,244,.2); }
.faq-help__txt h3 { margin:0 0 3px; font-size:15px; font-weight:700; color:var(--text-primary); }
.faq-help__txt p { margin:0; font-size:13px; color:var(--text-secondary); }
.faq-help__actions { display:flex; gap:10px; flex-wrap:wrap; }
.faq-help__actions .btn-soft { display:inline-flex; align-items:center; gap:7px; padding:10px 16px; border-radius:10px; font-size:13px; font-weight:600; text-decoration:none; background:var(--bg-surface); color:var(--text-secondary); border:1.5px solid var(--border); transition:all .18s ease; }
.faq-help__actions .btn-soft:hover { border-color:var(--accent); color:var(--accent-text); background:var(--accent-soft); }
</style>

<div class="card asis-card">
    <div class="asis-head">
        <div class="asis-head__icon"><i class="fa-solid fa-robot"></i></div>
        <div class="asis-head__t">
            <h3>Asistente EcoMadelleine</h3>
            <p>Escribe tu duda y te respondo al instante sobre citas, estudios y preparación.</p>
        </div>
    </div>

    <div class="asis-chat" id="asis-chat"></div>

    <div class="asis-chips" id="asis-chips">
        <button type="button" class="asis-chip">¿Cómo solicito una cita?</button>
        <button type="button" class="asis-chip">¿Necesito ayuno?</button>
        <button type="button" class="asis-chip">¿Dónde veo mis informes?</button>
        <button type="button" class="asis-chip">¿Cómo me preparo para una ecografía pélvica?</button>
    </div>

    <form class="asis-form" id="asis-form" autocomplete="off">
        <i class="fa-solid fa-magnifying-glass asis-ico"></i>
        <input type="text" class="asis-input" id="asis-input" placeholder="Escribe tu pregunta o busca una palabra…">
        <button type="submit" class="asis-send"><i class="fa-solid fa-paper-plane"></i> Preguntar</button>
    </form>
    <p class="asis-hint"><i class="fa-solid fa-keyboard"></i> Filtra las preguntas mientras escribes · pulsa Enter para preguntarle al asistente</p>
    <p class="asis-priv"><i class="fa-solid fa-lock"></i> Asistente local: tus preguntas no salen de la clínica.</p>
</div>

<?php if ($total_faqs > 0): ?>
    <p class="faq-section-title"><i class="fa-solid fa-circle-question" style="color:var(--accent);"></i> Preguntas frecuentes</p>
    <div class="faq-list" id="faq-list">
        <?php foreach ($faqs as $i => $faq):
            $busca = mb_strtolower(trim($faq['pregunta'] . ' ' . $faq['respuesta']));
        ?>
            <div class="faq-item" data-search="<?= htmlspecialchars($busca, ENT_QUOTES) ?>">
                <button type="button" class="faq-q" aria-expanded="false" aria-controls="faq-a-<?= (int)$i ?>">
                    <span class="faq-q__icon"><i class="fa-solid fa-question"></i></span>
                    <span class="faq-q__text"><?= htmlspecialchars($faq['pregunta']) ?></span>
                    <i class="fa-solid fa-chevron-down faq-q__chevron"></i>
                </button>
                <div class="faq-a" id="faq-a-<?= (int)$i ?>" role="region">
                    <div class="faq-a__inner"><?= nl2br(htmlspecialchars($faq['respuesta'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div id="faq-empty-filter" class="faq-empty" style="display:none;">
            <i class="fa-solid fa-magnifying-glass"></i>
            <p style="margin:0;font-weight:600;color:var(--text-secondary);">Ninguna pregunta coincide con tu búsqueda</p>
            <p style="margin:6px 0 0;font-size:13px;">Pulsa Enter para que el asistente intente responderte.</p>
        </div>
    </div>
<?php endif; ?>

<div class="card faq-help">
    <div class="faq-help__txt">
        <h3><i class="fa-solid fa-headset" style="color:var(--accent);margin-right:7px;"></i> ¿No encontraste tu respuesta?</h3>
        <p>Nuestro equipo está listo para ayudarte con cualquier duda sobre tus citas o estudios.</p>
    </div>
    <div class="faq-help__actions">
        <a href="<?= eco_url('ayuda') ?>" class="btn-primary"><i class="fa-solid fa-life-ring"></i> Centro de Ayuda</a>
        <a href="<?= eco_url('solicitar-cita') ?>" class="btn-soft"><i class="fa-solid fa-file-circle-plus"></i> Solicitar cita</a>
    </div>
</div>

<?php
$page_content = ob_get_clean();

$kb_json = json_encode($kb, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$page_scripts_extra = <<<HTML
<script>
(function () {
    var KB = {$kb_json};

    /* ── Normalización y tokens ── */
    function norm(s) {
        return (s == null ? '' : String(s)).toLowerCase()
            .normalize('NFD').replace(/[\\u0300-\\u036f]/g, '')
            .replace(/[^a-z0-9ñ ]/g, ' ').replace(/\\s+/g, ' ').trim();
    }
    var STOP = new Set('para con por los las una unos unas del que como cual cuales son del este esta estos estas mis tus sus que cuanto cuando donde quien sobre hay tengo puedo necesito mi me te se lo la el en de un y o a'.split(' '));
    function toks(s) { return norm(s).split(' ').filter(function (t) { return t.length > 2 && !STOP.has(t); }); }

    var SYN = {
        precio:['costo','tarifa','valor','cuesta','cobran','pagar'], costo:['precio','tarifa','valor','cuesta'],
        resultado:['informe','reporte'], resultados:['informe','informes','reporte'], informe:['resultado','reporte'],
        cita:['turno','consulta','reserva'], turno:['cita'], agendar:['solicitar','reservar','pedir','programar'],
        doctor:['ecografista','medico','especialista'], medico:['ecografista','doctor'], doctores:['ecografistas'],
        ecografia:['estudio','eco'], estudio:['ecografia','eco'], embarazo:['obstetrica','gestacion','prenatal','embarazada'],
        seguro:['seguros','aseguradora','poliza'], ayuno:['comer','comida','desayunar','ayunas'],
        preparacion:['preparar','prepararme','prepararse','preparo'], cancelar:['anular','cancelacion'],
        reprogramar:['cambiar','mover','reagendar'], confidencial:['privado','privacidad','secreto'],
        vejiga:['orinar','orina','agua'], pelvica:['pelvis','pelvico'], prostata:['prostatica','prostatico'],
        sale:['costo','precio','vale','cuesta'], vale:['costo','precio'], plata:['precio','costo','pagar'],
        examen:['estudio','informe','resultado'], examenes:['estudios','informes','resultados'],
        saco:['sacar','solicitar','agendar'], sacar:['solicitar','agendar','pedir'], conseguir:['solicitar','agendar'],
        regla:['menstruacion','periodo'], menstruacion:['regla','periodo'], inyeccion:['contraste','aguja','pinchazo'],
        placa:['imagen','informe'], placas:['imagenes','informe'], seno:['mama','mamaria'], senos:['mamas','mamaria'],
        mama:['mamaria','seno'], riñon:['renal'], riñones:['renal'], bebe:['embarazo','obstetrica'],
        nene:['niño'], nino:['niño'], hijos:['niños'], celular:['telefono','movil'], movil:['telefono','celular']
    };
    function expand(tokens) {
        var out = tokens.slice();
        tokens.forEach(function (t) { if (SYN[t]) SYN[t].forEach(function (s) { if (out.indexOf(s) === -1) out.push(s); }); });
        return out;
    }

    KB.forEach(function (e) { e._t = toks(e.q); e._b = toks((e.a || '') + ' ' + (e.tags || '')); });

    function score(qtokens, e) {
        var s = 0;
        qtokens.forEach(function (qt) {
            if (e._t.indexOf(qt) !== -1) { s += 3; return; }
            if (e._b.indexOf(qt) !== -1) { s += 1.2; return; }
            var partial = e._t.some(function (t) { return t.indexOf(qt) === 0 || qt.indexOf(t) === 0; });
            if (partial) s += 1.4;
        });
        return s;
    }

    function responder(pregunta) {
        var n = norm(pregunta);
        var base = toks(pregunta);
        if (base.length === 0) return { a: 'Escribe tu pregunta y con gusto te ayudo.' };
        if (/\\bgracias\\b/.test(n)) return { a: '¡Con gusto! ¿Tienes otra duda?' };
        if (/\\b(hola|buenas|buenos|saludos|hey)\\b/.test(n) && base.length <= 2)
            return { a: '¡Hola! Soy el asistente de EcoMadelleine. Pregúntame sobre citas, estudios o cómo prepararte.' };

        var qtokens = expand(base);
        var best = null, bestScore = 0;
        KB.forEach(function (e) { var sc = score(qtokens, e); if (sc > bestScore) { bestScore = sc; best = e; } });

        if (!best || bestScore < 2.6) {
            return { a: 'No estoy seguro de tener esa respuesta. Revisa las preguntas frecuentes de abajo o visita el Centro de Ayuda y con gusto te atendemos.', link: { href: 'paciente_ayuda.php', label: 'Centro de Ayuda' } };
        }
        return { a: best.a, link: best.link, title: best.q };
    }

    /* ── Render del chat ── */
    var chat   = document.getElementById('asis-chat');
    var form   = document.getElementById('asis-form');
    var input  = document.getElementById('asis-input');

    function esc(s) {
        return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function scrollDown() { chat.scrollTop = chat.scrollHeight; }

    function addUser(text) {
        var m = document.createElement('div');
        m.className = 'asis-msg asis-msg--user';
        m.innerHTML = '<div class="asis-avatar"><i class="fa-solid fa-user"></i></div><div class="asis-bubble">' + esc(text).replace(/\\n/g, '<br>') + '</div>';
        chat.appendChild(m); scrollDown();
    }
    function addBot(resp) {
        var m = document.createElement('div');
        m.className = 'asis-msg asis-msg--bot';
        var html = '<div class="asis-avatar"><i class="fa-solid fa-robot"></i></div><div class="asis-bubble">';
        if (resp.title) html += '<strong class="asis-title">' + esc(resp.title) + '</strong>';
        html += esc(resp.a).replace(/\\n/g, '<br>');
        if (resp.link && resp.link.href) html += '<a class="asis-link" href="' + esc(resp.link.href) + '"><i class="fa-solid fa-arrow-right"></i> ' + esc(resp.link.label) + '</a>';
        html += '</div>';
        m.innerHTML = html;
        chat.appendChild(m); scrollDown();
    }
    function addTyping() {
        var m = document.createElement('div');
        m.className = 'asis-msg asis-msg--bot'; m.id = 'asis-typing';
        m.innerHTML = '<div class="asis-avatar"><i class="fa-solid fa-robot"></i></div><div class="asis-bubble asis-typing"><span></span><span></span><span></span></div>';
        chat.appendChild(m); scrollDown();
    }
    function removeTyping() { var t = document.getElementById('asis-typing'); if (t) t.remove(); }

    function preguntar(text) {
        text = (text || '').trim();
        if (!text) return;
        addUser(text);
        addTyping();
        setTimeout(function () {
            removeTyping();
            addBot(responder(text));
        }, 450);
    }

    /* Mensaje de bienvenida */
    addBot({ a: '¡Hola! 👋 Soy tu asistente. Pregúntame sobre tus citas, tus informes o cómo prepararte para un estudio. También puedes usar este buscador para filtrar las preguntas frecuentes.' });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        preguntar(input.value);
        input.value = '';
        filtrarFaqs('');
    });

    document.querySelectorAll('#asis-chips .asis-chip').forEach(function (chip) {
        chip.addEventListener('click', function () { preguntar(chip.textContent); });
    });

    /* ── Filtro en vivo de las FAQ (mismo buscador) ── */
    var items = Array.prototype.slice.call(document.querySelectorAll('.faq-item'));
    var emptyFilter = document.getElementById('faq-empty-filter');

    function cerrar(item) {
        var panel = item.querySelector('.faq-a'); var btn = item.querySelector('.faq-q');
        item.classList.remove('is-open');
        if (panel) panel.style.maxHeight = '0px';
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }
    function abrir(item) {
        var panel = item.querySelector('.faq-a'); var btn = item.querySelector('.faq-q');
        item.classList.add('is-open');
        if (panel) panel.style.maxHeight = panel.scrollHeight + 'px';
        if (btn) btn.setAttribute('aria-expanded', 'true');
    }
    items.forEach(function (item) {
        var btn = item.querySelector('.faq-q'); if (!btn) return;
        btn.addEventListener('click', function () {
            var abierto = item.classList.contains('is-open');
            items.forEach(cerrar);
            if (!abierto) abrir(item);
        });
    });
    function filtrarFaqs(q) {
        q = (q || '').trim().toLowerCase();
        var visibles = 0;
        items.forEach(function (item) {
            var match = !q || (item.getAttribute('data-search') || '').indexOf(q) !== -1;
            item.style.display = match ? '' : 'none';
            if (match) visibles++; else cerrar(item);
        });
        if (emptyFilter) emptyFilter.style.display = (visibles === 0 && items.length > 0) ? '' : 'none';
    }
    input.addEventListener('input', function () { filtrarFaqs(this.value); });
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
