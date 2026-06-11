/* =====================================================================
   ECOMADELLEINE — SHELL JS
   Toggle de tema (claro/oscuro), colapso de sidebar y reloj en tiempo real
   ===================================================================== */

(function () {
    'use strict';

    /* ── 1. Aplicar tema guardado lo antes posible (evita "flash") ── */
    const THEME_KEY = 'eco_theme';
    const SIDEBAR_KEY = 'eco_sidebar';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        const btn = document.getElementById('btn-toggle-theme');
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            btn.setAttribute('title', theme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        }
    }

    const savedTheme = localStorage.getItem(THEME_KEY) || 'light';
    applyTheme(savedTheme);

    /* ── 2. Inicialización tras DOM listo ── */
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar  = document.querySelector('.app-sidebar');
        const backdrop = document.querySelector('.sidebar-backdrop');

        /* — Estado inicial del sidebar (solo desktop) — */
        if (window.innerWidth > 900) {
            const savedSidebar = localStorage.getItem(SIDEBAR_KEY);
            if (savedSidebar === 'collapsed' && sidebar) {
                sidebar.classList.add('is-collapsed');
            }
        }

        /* — Toggle del sidebar — */
        const btnToggleSidebar = document.getElementById('btn-toggle-sidebar');
        if (btnToggleSidebar && sidebar) {
            btnToggleSidebar.addEventListener('click', function () {
                if (window.innerWidth <= 900) {
                    sidebar.classList.toggle('is-open');
                    if (backdrop) backdrop.classList.toggle('is-open');
                } else {
                    sidebar.classList.toggle('is-collapsed');
                    localStorage.setItem(
                        SIDEBAR_KEY,
                        sidebar.classList.contains('is-collapsed') ? 'collapsed' : 'expanded'
                    );
                }
            });
        }
        if (backdrop && sidebar) {
            backdrop.addEventListener('click', function () {
                sidebar.classList.remove('is-open');
                backdrop.classList.remove('is-open');
            });
        }

        /* — Toggle del tema — */
        const btnToggleTheme = document.getElementById('btn-toggle-theme');
        applyTheme(localStorage.getItem(THEME_KEY) || 'light');
        if (btnToggleTheme) {
            btnToggleTheme.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                const next    = current === 'light' ? 'dark' : 'light';
                applyTheme(next);
                localStorage.setItem(THEME_KEY, next);
            });
        }

        /* — Reloj en tiempo real — */
        const clockEl = document.getElementById('topbar-clock-time');
        const dateEl  = document.getElementById('topbar-clock-date');
        if (clockEl || dateEl) {
            const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                           'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            function tick() {
                const now = new Date();
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                if (clockEl) clockEl.textContent = `${hh}:${mm}`;
                if (dateEl)  dateEl.textContent  =
                    `${dias[now.getDay()]} ${String(now.getDate()).padStart(2,'0')} ${meses[now.getMonth()]}`;
            }
            tick();
            setInterval(tick, 30000);
        }
    });
})();
