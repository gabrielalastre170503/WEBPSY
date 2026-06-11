/**
 * Mi Disponibilidad — horario recurrente + calendario de excepciones
 */
(function () {
    'use strict';

    var TEMPLATES = {
        'lv-9-17':    { days: [1, 2, 3, 4, 5],    inicio: '09:00', fin: '17:00' },
        'ls-8-14':    { days: [1, 2, 3, 4, 5, 6], inicio: '08:00', fin: '14:00' },
        'todos-9-13': { days: [1, 2, 3, 4, 5, 6, 7], inicio: '09:00', fin: '13:00' },
        'limpiar':    { days: [], inicio: null, fin: null }
    };

    function diffHours(inicio, fin) {
        if (!inicio || !fin) return 0;
        var a = inicio.split(':');
        var b = fin.split(':');
        var ini = parseInt(a[0], 10) * 60 + parseInt(a[1], 10);
        var end = parseInt(b[0], 10) * 60 + parseInt(b[1], 10);
        if (end <= ini) return 0;
        return (end - ini) / 60;
    }

    function updateRowHours(row) {
        var toggle = row.querySelector('.disp-day-toggle');
        var inputs = row.querySelectorAll('.input-hora-premium');
        var label  = row.querySelector('[data-hours-label]');
        if (!label) return;

        if (toggle && toggle.checked && inputs.length >= 2) {
            var h = diffHours(inputs[0].value, inputs[1].value);
            label.textContent = h > 0 ? h.toFixed(1) + ' h' : '—';
        } else {
            label.textContent = '—';
        }
    }

    function updateAllStats() {
        var rows = document.querySelectorAll('.disp-day-row');
        var totalHoras = 0;
        var diasActivos = 0;

        rows.forEach(function (row) {
            var toggle = row.querySelector('.disp-day-toggle');
            var inputs = row.querySelectorAll('.input-hora-premium');
            if (toggle && toggle.checked && inputs.length >= 2) {
                var h = diffHours(inputs[0].value, inputs[1].value);
                if (h > 0) {
                    totalHoras += h;
                    diasActivos++;
                }
            }
        });

        var elDias  = document.getElementById('stat-dias-activos');
        var elHoras = document.getElementById('stat-horas-semanales');
        var barDias = document.getElementById('stat-bar-dias');
        var barHoras = document.getElementById('stat-bar-horas');

        if (elDias)  elDias.textContent  = diasActivos;
        if (elHoras) elHoras.textContent = totalHoras.toFixed(1);
        if (barDias) barDias.style.width  = ((diasActivos / 7) * 100) + '%';
        if (barHoras) barHoras.style.width = Math.min(100, (totalHoras / 56) * 100) + '%';
    }

    function syncRow(row) {
        var toggle = row.querySelector('.disp-day-toggle');
        var inputs = row.querySelectorAll('.disp-time-slot .input-hora-premium');
        if (!toggle) return;
        var on = toggle.checked;
        row.classList.toggle('is-active', on);
        inputs.forEach(function (inp) { inp.disabled = !on; });
        updateRowHours(row);
    }

    function initDayToggles() {
        document.querySelectorAll('.disp-day-row').forEach(function (row) {
            var toggle = row.querySelector('.disp-day-toggle');
            var inputs = row.querySelectorAll('.disp-time-slot .input-hora-premium');
            if (!toggle) return;

            toggle.addEventListener('change', function () {
                syncRow(row);
                updateAllStats();
            });

            inputs.forEach(function (inp) {
                inp.addEventListener('input', function () {
                    updateRowHours(row);
                    updateAllStats();
                });
                inp.addEventListener('change', function () {
                    updateRowHours(row);
                    updateAllStats();
                });
            });

            syncRow(row);
        });
        updateAllStats();
    }

    function applyTemplate(templateKey) {
        var tpl = TEMPLATES[templateKey];
        if (!tpl) return;

        document.querySelectorAll('.disp-day-row').forEach(function (row) {
            var day = parseInt(row.getAttribute('data-day'), 10);
            var toggle = row.querySelector('.disp-day-toggle');
            var inputs = row.querySelectorAll('.input-hora-premium');
            if (!toggle) return;

            var shouldBeActive = tpl.days.indexOf(day) !== -1;
            toggle.checked = shouldBeActive;

            if (shouldBeActive && tpl.inicio && tpl.fin && inputs.length >= 2) {
                inputs[0].value = tpl.inicio;
                inputs[1].value = tpl.fin;
            }
            syncRow(row);
        });
        updateAllStats();
    }

    function initQuickActions() {
        document.querySelectorAll('.disp-quick-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-template');
                if (key === 'limpiar') {
                    if (!confirm('¿Limpiar todo el horario semanal? Tendrás que volver a configurar y guardar.')) return;
                }
                applyTemplate(key);
            });
        });
    }

    function parseList(el, attr) {
        var raw = (el.getAttribute(attr) || '').trim();
        return raw ? raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];
    }

    function initCalendar() {
        var el = document.getElementById('disp-calendar');
        if (!el || typeof FullCalendar === 'undefined') return;

        // Datos inyectados por PHP
        var workingDays  = parseList(el, 'data-working-days').map(Number); // 0=Dom … 6=Sáb
        var blockedDates = parseList(el, 'data-blocked-dates');            // 'YYYY-MM-DD'

        var calendar = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            locale: 'es',
            height: 'auto',
            firstDay: 1,
            fixedWeekCount: false,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            buttonText: { today: 'Hoy' },
            // Marcamos cada celda según el horario (azul) o las excepciones (rojo).
            dayCellDidMount: function (arg) {
                if (arg.isOther) return; // días de otro mes: siempre tenues
                var iso = arg.el.getAttribute('data-date');
                if (!iso) return;
                if (blockedDates.indexOf(iso) !== -1) {
                    arg.el.classList.add('disp-cell-blocked');
                } else {
                    var dow = new Date(iso + 'T12:00:00').getDay(); // mediodía: evita desfases
                    if (workingDays.indexOf(dow) !== -1) {
                        arg.el.classList.add('disp-cell-working');
                    }
                }
            },
            // Click en un día: alterna disponible / no disponible (mismo endpoint).
            dateClick: function (info) {
                var fecha = info.dateStr;
                var bloqueado = blockedDates.indexOf(fecha) !== -1;
                var msg = bloqueado
                    ? '¿Reactivar el ' + fecha + ' como día disponible?'
                    : '¿Marcar el ' + fecha + ' como día NO disponible?';
                if (confirm(msg)) {
                    // POST + token CSRF (window.ECO_CSRF lo expone shell.php).
                    var f = document.createElement('form');
                    f.method = 'POST';
                    f.action = 'guardar_disponibilidad.php';
                    f.innerHTML = '<input type="hidden" name="accion" value="alternar_dia_libre">'
                        + '<input type="hidden" name="fecha" value="' + fecha + '">'
                        + '<input type="hidden" name="csrf_token" value="' + (window.ECO_CSRF || '') + '">';
                    document.body.appendChild(f);
                    f.submit();
                }
            }
        });

        calendar.render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDayToggles();
        initQuickActions();
        initCalendar();
    });
})();
