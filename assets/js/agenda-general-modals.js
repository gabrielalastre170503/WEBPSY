/* Agenda general — modales lista de citas y nueva cita */
(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    function esc(s) {
        if (s === null || typeof s === 'undefined') return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function iniciales(nombre) {
        var parts = String(nombre || '').trim().split(/\s+/);
        var ini = '';
        for (var i = 0; i < parts.length && ini.length < 2; i++) {
            if (parts[i]) ini += parts[i][0].toUpperCase();
        }
        return ini || '?';
    }

    function setErr(msg) {
        var el = byId('agenda-nueva-error');
        if (!el) return;
        el.textContent = msg || '';
        el.style.display = msg ? 'block' : 'none';
    }

    function destroyFp(inp) {
        if (inp && inp._flatpickr) inp._flatpickr.destroy();
    }

    var agendaFp = null;
    var pacienteTimer = null;
    var pacienteAbort = null;

    function initAgendaFp() {
        var fecha = byId('agenda-fecha');
        if (!fecha || typeof flatpickr === 'undefined') return;
        destroyFp(fecha);
        agendaFp = null;
        fecha.value = '';
        agendaFp = flatpickr(fecha, {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'd/m/Y h:i K',
            locale: flatpickr.l10ns && flatpickr.l10ns.es ? flatpickr.l10ns.es : 'es',
            minuteIncrement: 15
        });
    }

    function refetchCalendar() {
        if (window.agendaGeneralCalendar && typeof window.agendaGeneralCalendar.refetchEvents === 'function') {
            window.agendaGeneralCalendar.refetchEvents();
        }
    }

    function buscarCitasLista(q) {
        var box = byId('agenda-lista-body');
        if (!box) return;
        box.innerHTML = '<p class="agenda-modal-empty"><i class="fa-solid fa-spinner fa-spin"></i> Buscando…</p>';
        fetch('buscar_citas_secretaria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'query=' + encodeURIComponent(q || '')
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                box.innerHTML = html || '<p class="agenda-modal-empty">Sin resultados.</p>';
            })
            .catch(function () {
                box.innerHTML = '<p class="agenda-modal-empty" style="color:#b91c1c;">No se pudo cargar el listado.</p>';
            });
    }

    window.agendaAbrirLista = function () {
        if (!window.EcoModal) return;
        EcoModal.open('eco-modal-agenda-lista');
        var inp = byId('agenda-lista-query');
        buscarCitasLista(inp ? inp.value : '');
        if (inp) setTimeout(function () { inp.focus(); }, 120);
    };

    function clearPaciente() {
        var pid = byId('agenda-paciente-id');
        var chip = byId('agenda-paciente-chip');
        var q = byId('agenda-paciente-q');
        var res = byId('agenda-paciente-results');
        if (pid) pid.value = '';
        if (chip) chip.hidden = true;
        if (q) {
            q.value = '';
            q.disabled = false;
        }
        if (res) {
            res.hidden = true;
            res.innerHTML = '';
        }
    }

    function selectPaciente(id, nombre, cedula) {
        var pid = byId('agenda-paciente-id');
        var chip = byId('agenda-paciente-chip');
        var q = byId('agenda-paciente-q');
        var res = byId('agenda-paciente-results');
        var ini = byId('agenda-paciente-chip-ini');
        var nom = byId('agenda-paciente-chip-nom');
        var ci = byId('agenda-paciente-chip-ci');
        if (!pid || !chip) return;

        pid.value = id;
        if (ini) ini.textContent = iniciales(nombre);
        if (nom) nom.textContent = nombre || '—';
        if (ci) ci.textContent = cedula ? 'CI ' + cedula : '';
        chip.hidden = false;
        if (q) {
            q.value = '';
            q.disabled = true;
        }
        if (res) {
            res.hidden = true;
            res.innerHTML = '';
        }
    }

    function renderPacienteResults(list) {
        var res = byId('agenda-paciente-results');
        if (!res) return;
        if (!list || !list.length) {
            res.innerHTML = '<p class="agenda-modal-empty" style="padding:14px;">Sin coincidencias.</p>';
            res.hidden = false;
            return;
        }
        var html = '';
        list.forEach(function (p) {
            html += '<button type="button" class="agenda-paciente-result" data-id="' + esc(p.id) + '" data-nom="' + esc(p.nombre) + '" data-ci="' + esc(p.cedula || '') + '">';
            html += '<span class="agenda-paciente-result__ini">' + esc(iniciales(p.nombre)) + '</span>';
            html += '<span class="agenda-paciente-result__meta"><strong>' + esc(p.nombre) + '</strong>';
            if (p.cedula) html += '<small>CI ' + esc(p.cedula) + '</small>';
            html += '</span></button>';
        });
        res.innerHTML = html;
        res.hidden = false;
    }

    function buscarPacientes(q) {
        if (pacienteAbort) pacienteAbort.abort();
        if (q.length < 2) {
            var res = byId('agenda-paciente-results');
            if (res) {
                res.hidden = true;
                res.innerHTML = '';
            }
            return;
        }
        pacienteAbort = new AbortController();
        fetch('agenda_buscar_pacientes.php?q=' + encodeURIComponent(q), { signal: pacienteAbort.signal })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderPacienteResults(data.pacientes || []);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
            });
    }

    window.agendaAbrirNuevaCita = function () {
        if (!window.EcoModal) return;
        var form = byId('agenda-form-nueva-cita');
        setErr('');
        if (form) form.reset();
        clearPaciente();
        destroyFp(byId('agenda-fecha'));
        EcoModal.open('eco-modal-agenda-nueva-cita');
        setTimeout(initAgendaFp, 0);
        var q = byId('agenda-paciente-q');
        if (q) setTimeout(function () { q.focus(); }, 150);
    };

    document.addEventListener('DOMContentLoaded', function () {
        var btnLista = byId('agenda-btn-lista');
        var btnNueva = byId('agenda-btn-nueva');
        if (btnLista) btnLista.addEventListener('click', window.agendaAbrirLista);
        if (btnNueva) btnNueva.addEventListener('click', window.agendaAbrirNuevaCita);

        var inpLista = byId('agenda-lista-query');
        if (inpLista) {
            inpLista.addEventListener('input', function () {
                buscarCitasLista(this.value);
            });
        }

        var inpPac = byId('agenda-paciente-q');
        if (inpPac) {
            inpPac.addEventListener('input', function () {
                var v = this.value.trim();
                clearTimeout(pacienteTimer);
                pacienteTimer = setTimeout(function () { buscarPacientes(v); }, 280);
            });
        }

        var resPac = byId('agenda-paciente-results');
        if (resPac) {
            resPac.addEventListener('click', function (e) {
                var btn = e.target.closest('.agenda-paciente-result');
                if (!btn) return;
                selectPaciente(
                    parseInt(btn.getAttribute('data-id'), 10),
                    btn.getAttribute('data-nom') || '',
                    btn.getAttribute('data-ci') || ''
                );
            });
        }

        var clearBtn = byId('agenda-paciente-clear');
        if (clearBtn) clearBtn.addEventListener('click', clearPaciente);

        var form = byId('agenda-form-nueva-cita');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                setErr('');
                var pid = byId('agenda-paciente-id');
                if (!pid || !pid.value) {
                    setErr('Seleccione un paciente de la lista de búsqueda.');
                    return;
                }
                var eco = byId('agenda-ecografista');
                if (eco && !eco.value) {
                    setErr('Seleccione un ecografista responsable.');
                    return;
                }
                var submit = byId('agenda-nueva-submit');
                if (submit) {
                    submit.disabled = true;
                    submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';
                }
                fetch('guardar_cita_directa.php', { method: 'POST', body: new FormData(form) })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (submit) {
                            submit.disabled = false;
                            submit.innerHTML = '<i class="fa-solid fa-check"></i> Guardar cita';
                        }
                        if (data.success) {
                            EcoModal.close('eco-modal-agenda-nueva-cita');
                            refetchCalendar();
                            if (typeof window.agendaToast === 'function') {
                                window.agendaToast(data.message || 'Cita guardada.');
                            } else {
                                window.alert(data.message || 'Cita guardada.');
                            }
                        } else {
                            setErr(data.message || 'No se pudo guardar la cita.');
                        }
                    })
                    .catch(function () {
                        if (submit) {
                            submit.disabled = false;
                            submit.innerHTML = '<i class="fa-solid fa-check"></i> Guardar cita';
                        }
                        setErr('Error de red. Intente de nuevo.');
                    });
            });
        }
    });
})();
