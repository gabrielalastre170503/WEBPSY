<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if (!in_array($_SESSION['rol'] ?? '', ['administrador', 'recepcionista'], true)) {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

$page_title    = 'Notas Rápidas';
$page_subtitle = 'Tu cuaderno personal de recordatorios y tareas pendientes';
$active_section = 'notas-rapidas';

ob_start();
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3, 1fr);">
    <div class="stat-card">
        <div class="stat-card-icon" style="background:var(--accent-soft);color:var(--accent-text);">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <p class="stat-card-label">Total de notas</p>
        <p class="stat-card-value" id="qn-total">0</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(245,158,11,.12);color:#b45309;">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <p class="stat-card-label">Pendientes</p>
        <p class="stat-card-value warning" id="qn-pending">0</p>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:rgba(34,197,94,.12);color:#15803d;">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <p class="stat-card-label">Completadas</p>
        <p class="stat-card-value success" id="qn-completed">0</p>
    </div>
</div>

<!-- Formulario nueva nota -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-plus-circle" style="margin-right:7px;color:var(--accent);"></i> Nueva Nota</h3>
    </div>
    <form id="qn-form">
        <textarea id="qn-input"
                  placeholder="Escribe un recordatorio, tarea o nota rápida..."
                  rows="3"
                  required maxlength="500"
                  style="width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13.5px;resize:vertical;background:var(--bg-surface);color:var(--text-primary);box-sizing:border-box;"></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
            <small style="color:var(--text-muted);font-size:11.5px;">
                <i class="fa-solid fa-info-circle"></i> Las notas se guardan localmente en tu navegador.
            </small>
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Nota
            </button>
        </div>
    </form>
</div>

<!-- Lista con filtros -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-clipboard-list" style="margin-right:7px;color:var(--accent);"></i> Mis Notas</h3>
        <div class="qn-tabs" style="display:flex;gap:4px;background:var(--bg-muted);padding:3px;border-radius:8px;">
            <button class="qn-tab is-active" data-filter="all">Todas</button>
            <button class="qn-tab" data-filter="pending">Pendientes</button>
            <button class="qn-tab" data-filter="completed">Completadas</button>
        </div>
    </div>

    <ul id="qn-list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px;"></ul>

    <div id="qn-empty" style="text-align:center;padding:50px 20px;color:var(--text-muted);display:none;">
        <i class="fa-solid fa-clipboard" style="font-size:2.8rem;opacity:.3;display:block;margin-bottom:12px;"></i>
        <p style="margin:0;font-size:14px;" id="qn-empty-title">No tienes notas aún</p>
        <p style="margin:5px 0 0;font-size:12.5px;color:var(--text-muted);" id="qn-empty-msg">Agrega tu primer recordatorio arriba.</p>
    </div>
</div>

<style>
.qn-tab {
    background: transparent; border: none;
    padding: 6px 14px; border-radius: 6px;
    font-size: 12px; font-weight: 600; color: var(--text-secondary);
    cursor: pointer; transition: all .2s; font-family: var(--font);
}
.qn-tab:hover { color: var(--text-primary); }
.qn-tab.is-active { background: var(--bg-surface); color: var(--accent-text); box-shadow: var(--shadow-sm); }

.qn-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--border-soft); border-radius: 10px;
    background: var(--bg-surface);
    transition: all .2s;
}
.qn-item:hover { border-color: var(--border); box-shadow: var(--shadow-sm); }
.qn-item.is-done { background: var(--bg-muted); opacity: .7; }
.qn-item.is-done .qn-text { text-decoration: line-through; color: var(--text-secondary); }

.qn-check {
    width: 20px; height: 20px;
    border: 1.5px solid var(--border); border-radius: 6px;
    cursor: pointer; flex-shrink: 0;
    margin-top: 1px;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-surface);
    transition: all .2s;
}
.qn-check:hover { border-color: var(--accent); }
.qn-check.is-checked {
    background: var(--accent); border-color: var(--accent);
    color: #fff;
}
.qn-check.is-checked::before { content: "\f00c"; font-family: "Font Awesome 6 Free"; font-weight: 900; font-size: 11px; }

.qn-body { flex: 1; min-width: 0; }
.qn-text { font-size: 13.5px; color: var(--text-primary); line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; margin: 0; }
.qn-meta { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
.qn-delete {
    background: transparent; border: none;
    color: var(--text-muted);
    padding: 4px 8px; border-radius: 6px;
    transition: all .2s; flex-shrink: 0;
}
.qn-delete:hover { color: var(--danger); background: rgba(239,68,68,.08); }
</style>

<script>
(function() {
    const KEY = 'eco_quick_notes_<?= $usuario_id ?>';
    const LEGACY_KEYS = ['quickNotes_<?= $usuario_id ?>', 'secretariaQuickNotes']; // migración

    const $form  = document.getElementById('qn-form');
    const $input = document.getElementById('qn-input');
    const $list  = document.getElementById('qn-list');
    const $empty = document.getElementById('qn-empty');
    const $emptyTitle = document.getElementById('qn-empty-title');
    const $emptyMsg   = document.getElementById('qn-empty-msg');
    const $tabs  = document.querySelectorAll('.qn-tab');
    const $statTotal = document.getElementById('qn-total');
    const $statPending = document.getElementById('qn-pending');
    const $statCompleted = document.getElementById('qn-completed');

    let notes  = load();
    let filter = 'all';

    function load() {
        try {
            const raw = localStorage.getItem(KEY);
            if (raw) return JSON.parse(raw);
            // Migración desde claves anteriores
            for (const old of LEGACY_KEYS) {
                const legacy = localStorage.getItem(old);
                if (legacy) {
                    const parsed = JSON.parse(legacy);
                    localStorage.setItem(KEY, legacy);
                    return parsed;
                }
            }
        } catch (e) { console.error(e); }
        return [];
    }

    function save() {
        try { localStorage.setItem(KEY, JSON.stringify(notes)); } catch (e) { console.error(e); }
    }

    function formatDate(ts) {
        const d = new Date(ts);
        const hh = String(d.getHours()).padStart(2,'0');
        const mm = String(d.getMinutes()).padStart(2,'0');
        return `${d.getDate()}/${d.getMonth()+1}/${d.getFullYear()} · ${hh}:${mm}`;
    }

    function render() {
        const sorted = [...notes].sort((a, b) => {
            if (a.completed !== b.completed) return a.completed ? 1 : -1;
            return (b.createdAt || 0) - (a.createdAt || 0);
        });
        const filtered = sorted.filter(n => filter === 'all'
            || (filter === 'completed' && n.completed)
            || (filter === 'pending' && !n.completed));

        $statTotal.textContent     = notes.length;
        $statPending.textContent   = notes.filter(n => !n.completed).length;
        $statCompleted.textContent = notes.filter(n =>  n.completed).length;

        $list.innerHTML = '';
        if (filtered.length === 0) {
            $empty.style.display = 'block';
            $emptyTitle.textContent = filter === 'completed' ? 'Sin notas completadas'
                                    : filter === 'pending'   ? '¡Todo al día!'
                                    : 'No tienes notas aún';
            $emptyMsg.textContent   = filter === 'completed' ? 'Marca alguna nota como completada para verla aquí.'
                                    : filter === 'pending'   ? 'No tienes pendientes ahora mismo.'
                                    : 'Agrega tu primer recordatorio arriba.';
            return;
        }
        $empty.style.display = 'none';

        for (const n of filtered) {
            const li = document.createElement('li');
            li.className = 'qn-item' + (n.completed ? ' is-done' : '');
            li.innerHTML = `
                <div class="qn-check ${n.completed ? 'is-checked' : ''}" data-id="${n.id}" data-action="toggle" title="${n.completed ? 'Marcar pendiente' : 'Marcar completada'}"></div>
                <div class="qn-body">
                    <p class="qn-text"></p>
                    <p class="qn-meta"><i class="fa-regular fa-clock"></i> ${formatDate(n.createdAt)}</p>
                </div>
                <button class="qn-delete" data-id="${n.id}" data-action="delete" title="Eliminar">
                    <i class="fa-solid fa-trash"></i>
                </button>`;
            li.querySelector('.qn-text').textContent = n.text;
            $list.appendChild(li);
        }
    }

    $form.addEventListener('submit', e => {
        e.preventDefault();
        const text = $input.value.trim();
        if (!text) return;
        notes.push({ id: Date.now() + Math.random(), text, completed: false, createdAt: Date.now() });
        save(); render();
        $input.value = '';
        $input.focus();
    });

    $list.addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const id = btn.dataset.id;
        const idx = notes.findIndex(n => String(n.id) === String(id));
        if (idx === -1) return;
        if (btn.dataset.action === 'toggle') {
            notes[idx].completed = !notes[idx].completed;
        } else if (btn.dataset.action === 'delete') {
            if (!confirm('¿Eliminar esta nota?')) return;
            notes.splice(idx, 1);
        }
        save(); render();
    });

    $tabs.forEach(t => t.addEventListener('click', () => {
        $tabs.forEach(x => x.classList.remove('is-active'));
        t.classList.add('is-active');
        filter = t.dataset.filter;
        render();
    }));

    render();
})();
</script>

<?php
$page_content = ob_get_clean();
include __DIR__ . '/../layouts/shell.php';
