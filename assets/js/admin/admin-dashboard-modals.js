/* Dashboard administrador — modales KPI */
(function () {
    'use strict';

    var KPI_CONFIG = {
        usuarios: {
            title: 'Usuarios totales',
            sub: 'Cuentas aprobadas en el sistema',
            icon: 'fa-solid fa-users',
            iconClass: '',
            href: 'ver_usuarios.php?filtro=aprobados',
            placeholder: 'Buscar por nombre, cédula o correo…'
        },
        pacientes: {
            title: 'Pacientes activos',
            sub: 'Pacientes con cuenta aprobada',
            icon: 'fa-solid fa-hospital-user',
            iconClass: 'icon--green',
            href: 'ver_usuarios.php?filtro=pacientes',
            placeholder: 'Buscar paciente…'
        },
        personal: {
            title: 'Personal activo',
            sub: 'Ecografistas y recepcionistas aprobados',
            icon: 'fa-solid fa-user-tie',
            iconClass: 'icon--amber',
            href: 'ver_usuarios.php?filtro=personal',
            placeholder: 'Buscar personal…'
        },
        citas: {
            title: 'Citas registradas',
            sub: 'Historial completo de citas en la clínica',
            icon: 'fa-solid fa-calendar-check',
            iconClass: 'icon--violet',
            href: 'ver_citas_admin.php',
            placeholder: 'Buscar por paciente, ecografista o estudio…'
        }
    };

    var currentTipo = null;
    var searchTimer = null;

    function byId(id) {
        return document.getElementById(id);
    }

    function cargarListado(tipo, query) {
        var body = byId('admin-kpi-body');
        if (!body) return;
        body.innerHTML = '<p class="admin-kpi-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p>';

        var fd = new FormData();
        fd.append('tipo', tipo);
        fd.append('query', query || '');

        fetch('dashboard_buscar_kpi.php', { method: 'POST', body: fd })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                body.innerHTML = html || '<p class="admin-kpi-empty">Sin resultados.</p>';
            })
            .catch(function () {
                body.innerHTML = '<p class="admin-kpi-empty" style="color:#b91c1c;">No se pudo cargar el listado.</p>';
            });
    }

    window.adminAbrirKpiModal = function (tipo, count) {
        var cfg = KPI_CONFIG[tipo];
        if (!cfg || !window.EcoModal) return;

        currentTipo = tipo;

        var titleEl = byId('admin-kpi-title');
        var subEl = byId('admin-kpi-sub');
        var iconWrap = byId('admin-kpi-icon');
        var countEl = byId('admin-kpi-count');
        var queryInp = byId('admin-kpi-query');
        var verTodo = byId('admin-kpi-ver-todo');

        if (titleEl) titleEl.textContent = cfg.title;
        if (subEl) subEl.textContent = cfg.sub;
        if (iconWrap) {
            iconWrap.className = 'admin-kpi-modal-head__icon' + (cfg.iconClass ? ' ' + cfg.iconClass : '');
            iconWrap.innerHTML = '<i class="' + cfg.icon + '"></i>';
        }
        if (countEl) countEl.textContent = typeof count === 'number' || typeof count === 'string' ? String(count) : '—';
        if (queryInp) {
            queryInp.value = '';
            queryInp.placeholder = cfg.placeholder;
        }
        if (verTodo) verTodo.href = cfg.href;

        EcoModal.open('eco-modal-admin-kpi');
        cargarListado(tipo, '');
        if (queryInp) setTimeout(function () { queryInp.focus(); }, 120);
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-admin-kpi]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tipo = btn.getAttribute('data-admin-kpi');
                var count = parseInt(btn.getAttribute('data-admin-kpi-count'), 10);
                if (isNaN(count)) count = btn.getAttribute('data-admin-kpi-count') || '';
                window.adminAbrirKpiModal(tipo, count);
            });
        });

        var queryInp = byId('admin-kpi-query');
        if (queryInp) {
            queryInp.addEventListener('input', function () {
                if (!currentTipo) return;
                var q = this.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    cargarListado(currentTipo, q);
                }, 280);
            });
        }
    });
})();
