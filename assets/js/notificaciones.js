/* Notificaciones in-app (Fase 4A).
 * Campana del topbar: consulta get_notificaciones.php, pinta badge + dropdown,
 * marca como leidas (marcar_notificacion.php). El wrapper fetch de shell.php
 * agrega la cabecera X-CSRF-Token automaticamente en los POST. */
(function () {
    var root   = document.getElementById('eco-notif');
    if (!root) return;
    var btn    = document.getElementById('eco-notif-btn');
    var panel  = document.getElementById('eco-notif-panel');
    var badge  = document.getElementById('eco-notif-badge');
    var list   = document.getElementById('eco-notif-list');
    var readAll = document.getElementById('eco-notif-readall');
    var cargado = false;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function setBadge(n) {
        n = parseInt(n, 10) || 0;
        if (n > 0) { badge.textContent = n > 99 ? '99+' : n; badge.hidden = false; }
        else { badge.hidden = true; }
    }

    function render(items) {
        if (!items || !items.length) {
            list.innerHTML = '<div class="topbar-notif-empty"><i class="fa-regular fa-bell-slash"></i><span>Sin notificaciones</span></div>';
            return;
        }
        var html = '';
        items.forEach(function (n) {
            html += '<a class="topbar-notif-item' + (n.leida ? '' : ' is-unread') + '"'
                + ' data-id="' + esc(n.id) + '" href="' + (n.url ? esc(n.url) : '#') + '">'
                + '<span class="topbar-notif-ic"><i class="' + esc(n.icono) + '"></i></span>'
                + '<span class="topbar-notif-body">'
                + '<span class="topbar-notif-title">' + esc(n.titulo) + '</span>'
                + (n.mensaje ? '<span class="topbar-notif-msg">' + esc(n.mensaje) + '</span>' : '')
                + '<span class="topbar-notif-time">' + esc(n.hace) + '</span>'
                + '</span></a>';
        });
        list.innerHTML = html;

        list.querySelectorAll('.topbar-notif-item').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var id = a.getAttribute('data-id');
                var href = a.getAttribute('href');
                marcarUna(id);
                if (!href || href === '#') {
                    e.preventDefault();
                    a.classList.remove('is-unread');
                }
                // si hay url, dejamos que navegue (la marca ya se envio)
            });
        });
    }

    function cargar() {
        fetch('get_notificaciones.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) return;
                setBadge(d.no_leidas);
                cargado = true;
                if (!panel.hidden) render(d.items);
                else list.dataset.pending = JSON.stringify(d.items || []);
            })
            .catch(function () {});
    }

    function abrir() {
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        // pinta lo ultimo cargado, o pide de nuevo
        if (list.dataset.pending) { render(JSON.parse(list.dataset.pending)); delete list.dataset.pending; }
        else if (!cargado) { list.innerHTML = '<div class="topbar-notif-loading"><i class="fa-solid fa-spinner fa-spin"></i></div>'; }
        cargar();
    }
    function cerrar() { panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (panel.hidden) abrir(); else cerrar();
    });
    document.addEventListener('click', function (e) {
        if (!panel.hidden && !root.contains(e.target)) cerrar();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });

    function marcarUna(id) {
        var body = new URLSearchParams(); body.set('id', id);
        fetch('marcar_notificacion.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && typeof d.no_leidas !== 'undefined') setBadge(d.no_leidas);
        }).catch(function () {});
    }

    if (readAll) readAll.addEventListener('click', function (e) {
        e.stopPropagation();
        var body = new URLSearchParams(); body.set('todas', '1');
        fetch('marcar_notificacion.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString()
        }).then(function (r) { return r.json(); }).then(function () {
            setBadge(0);
            list.querySelectorAll('.topbar-notif-item.is-unread').forEach(function (a) { a.classList.remove('is-unread'); });
        }).catch(function () {});
    });

    cargar();
    setInterval(cargar, 60000);   // refresco cada 60 s
})();
