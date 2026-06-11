    // ==================================================================
    // VARIABLES GLOBALES Y FUNCIONES DE CONTROL
    // ==================================================================
    // --- VARIABLES Y FUNCIONES PARA LAS NUEVAS MODALES ---
    const modalSeleccionarHistoria = document.getElementById('modal-seleccionar-historia');
    const modalSeleccionarEcografia   = document.getElementById('modal-seleccionar-ecografia');
    const modalFormularioEstudio      = document.getElementById('modal-formulario-estudio');
    const modalCrearHistoria = document.getElementById('modal-crear-historia');
    const modalCrearHistoriaInfantil = document.getElementById('modal-crear-historia-infantil');
    const modalVerInformes = document.getElementById('modal-ver-informes');
    const modalInformeDetalle = document.getElementById('modal-informe-detalle');
    const modalCrearInforme = document.getElementById('modal-crear-informe');
    const modalVerHistoria = document.getElementById('modal-ver-historia');
    const modalEditarHistoria = document.getElementById('modal-editar-historia');
    // Variables para las ventanas modales
    const modalCrearPaciente = document.getElementById('modal-crear-paciente');
    const modalProgramarCita = document.getElementById('eco-modal-programar-cita');
    const modalGestionarPaciente = document.getElementById('modal-gestionar-paciente');
    const modalSolicitudDetalle = document.getElementById('modal-solicitud-detalle');
    const modalGestionarNotas = document.getElementById('modal-gestionar-notas');
    // Variables globales para que las funciones puedan acceder a ellas
    let calendar;
    let isCalendarRendered = false;
    let generalCalendar;
    let isGeneralCalendarRendered = false;
    let citasChart = null;
    let newPatientsChart = null;
    let pacienteCalendar; // Calendario de disponibilidad para el paciente
    let currentManagedPatientId = null;
    let currentHistoriaContext = null;
    

    // --- FUNCIÓN ÚNICA Y CORRECTA PARA CAMBIAR DE VISTA (PESTAÑAS) ---
    function mostrarVista(vista, event) {
        if (event) {
            event.preventDefault(); // Evita que el enlace recargue la página
        }

        // Ocultar todas las vistas
        document.querySelectorAll('.panel-vista').forEach(v => v.classList.remove('active'));
        
        // Quitar la clase 'active' de todos los enlaces de la barra lateral
        document.querySelectorAll('.sidebar-nav a').forEach(link => link.classList.remove('active'));
        
        // Mostrar la vista seleccionada
        const vistaAMostrar = document.getElementById('vista-' + vista);
        if (vistaAMostrar) {
            vistaAMostrar.classList.add('active');
        }
        
        // Marcar el enlace correspondiente como activo
        if (event) {
            event.currentTarget.classList.add('active');
        } else {
            const linkActivo = document.querySelector(`.sidebar-nav a[onclick*="'${vista}'"]`);
            if (linkActivo) {
                linkActivo.classList.add('active');
            }
        }

        // Renderizar el calendario del psicólogo solo la primera vez que se muestra
        if (vista === 'agenda' && !isCalendarRendered) {
            if (calendar) {
                calendar.render();
                isCalendarRendered = true;
            }
        }

        // Renderizar el calendario general de la secretaria
        if (vista === 'agenda-general' && !isGeneralCalendarRendered) {
            if (generalCalendar) {
                generalCalendar.render();
                isGeneralCalendarRendered = true;
            }
        }
    }

    // --- FUNCIÓN REUTILIZABLE Y ROBUSTA PARA ORDENAR TABLAS ---
    const makeTableSortable = (container) => {
        if (!container) return;

        // Función para convertir la fecha de 'dd/mm/yyyy' a un formato comparable
        const parseDate = (dateStr) => {
            const parts = dateStr.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (!parts) return new Date(0); // Devuelve una fecha muy antigua si el formato no coincide
            // Formato: año, mes (0-11), día
            return new Date(parts[3], parts[2] - 1, parts[1]);
        };

        const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;

        const comparer = (idx, asc) => (a, b) => {
            const vA = getCellValue(asc ? a : b, idx);
            const vB = getCellValue(asc ? b : a, idx);

            // Comprobar si el encabezado es de tipo fecha
            const header = container.querySelector(`th:nth-child(${idx + 1})`);
            const isDateColumn = header && header.textContent.toLowerCase().includes('fecha');

            if (isDateColumn) {
                return parseDate(vA) - parseDate(vB);
            }

            // Ordenamiento normal para texto y números
            return vA.toString().localeCompare(vB, 'es', { numeric: true, sensitivity: 'base' });
        };

        container.addEventListener('click', function(e) {
            const th = e.target.closest('.sortable-header');
            if (!th) return;

            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const headers = table.querySelectorAll('.sortable-header');
            const columnIndex = Array.from(th.parentNode.children).indexOf(th);
            const isAsc = !th.classList.contains('sort-asc');

            headers.forEach(header => header.classList.remove('sort-asc', 'sort-desc'));
            th.classList.toggle('sort-asc', isAsc);
            th.classList.toggle('sort-desc', !isAsc);

            Array.from(tbody.querySelectorAll('tr'))
                .sort(comparer(columnIndex, isAsc))
                .forEach(tr => tbody.appendChild(tr));
        });
    };


    // --- FUNCIONES PARA LA MODAL DE CREAR PACIENTE ---
    function abrirModalCrearPaciente() {
        if (modalCrearPaciente) modalCrearPaciente.style.display = 'flex';
    }
    function cerrarModalCrearPaciente() {
        if (modalCrearPaciente) {
            modalCrearPaciente.style.display = 'none';

            // --- LÍNEAS AÑADIDAS ---
            // Buscamos el div del mensaje de error
            const modalErrorDiv = document.getElementById('modal-paciente-error');
            if (modalErrorDiv) {
                modalErrorDiv.style.display = 'none'; // Ocultamos el mensaje
                modalErrorDiv.textContent = '';       // Borramos su contenido
            }
            // --- FIN DE LÍNEAS AÑADIDAS ---
        }
    }

    function abrirModalProgramarCita(pacienteId, pacienteNombre) {
        if (!modalProgramarCita) return;
        document.getElementById('modal-paciente-id').value = pacienteId;
        document.getElementById('modal-paciente-nombre-display').textContent = pacienteNombre;
        if (typeof EcoModal !== 'undefined') {
            EcoModal.open('eco-modal-programar-cita');
        }
    }
    function cerrarModalProgramarCita() {
        if (typeof EcoModal !== 'undefined') {
            EcoModal.close('eco-modal-programar-cita');
        }
        var fpForm = document.getElementById('form-programar-cita');
        if (fpForm) fpForm.reset();
    }

    // --- FUNCIÓN PARA MOSTRAR MENSAJE DE ÉXITO (CREAR PACIENTE) ---
    function mostrarMensajeExito(nombre, password) {
        const mainContent = document.querySelector('.main-content');
        const alertaVieja = document.getElementById('alerta-paciente-creado');
        if (alertaVieja) alertaVieja.remove();

        const alertDiv = document.createElement('div');
        alertDiv.id = 'alerta-paciente-creado';
        alertDiv.className = 'alert-box success';
        alertDiv.innerHTML = `
            <span><strong>¡Éxito!</strong> Paciente <strong>${nombre}</strong> creado. Su contraseña temporal es: <strong class="temp-pass">${password}</strong></span>
            <span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
        `;
        // Insertar el mensaje al principio de la vista de pacientes
        const vistaPacientes = document.getElementById('vista-pacientes');
        if (vistaPacientes) {
             vistaPacientes.prepend(alertDiv);
        }
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE REPROGRAMAR CITA ---
    const modalReprogramarCita = document.getElementById('eco-modal-reprogramar-cita');

    function abrirModalReprogramarCita(citaId, pacienteNombre) {
        if (!modalReprogramarCita) return;
        document.getElementById('reprogramar-cita-id').value = citaId;
        document.getElementById('reprogramar-paciente-nombre').textContent = pacienteNombre;
        if (typeof EcoModal !== 'undefined') {
            EcoModal.open('eco-modal-reprogramar-cita');
        }
    }
    function cerrarModalReprogramarCita() {
        if (typeof EcoModal !== 'undefined') {
            EcoModal.close('eco-modal-reprogramar-cita');
        }
        var fr = document.getElementById('form-reprogramar-cita');
        if (fr) fr.reset();
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE PROPONER FECHA ---
    const modalProponerFecha = document.getElementById('eco-modal-proponer-fecha');

    function abrirModalProponerFecha(citaId, pacienteNombre) {
        if (!modalProponerFecha) return;
        document.getElementById('proponer-cita-id').value = citaId;
        document.getElementById('proponer-paciente-nombre').textContent = pacienteNombre;
        if (typeof EcoModal !== 'undefined') {
            EcoModal.open('eco-modal-proponer-fecha');
        }
    }
    function cerrarModalProponerFecha() {
        if (typeof EcoModal !== 'undefined') {
            EcoModal.close('eco-modal-proponer-fecha');
        }
        var fp = document.getElementById('form-proponer-fecha');
        if (fp) fp.reset();
    }
    
    // --- NUEVAS FUNCIONES PARA LA MODAL DE GESTIONAR PACIENTE ---
    // --- NUEVA FUNCIÓN PARA NAVEGAR CON ANIMACIÓN ---
    function navigateWithAnimation(url) {
        event.preventDefault(); // Previene la redirección instantánea
        document.body.classList.add('fade-out'); // Añade la clase que activa la animación
        // Espera a que termine la animación (400ms) antes de cambiar de página
        setTimeout(() => {
            window.location.href = url;
        }, 400); 
    }

    // --- FUNCIÓN MODIFICADA PARA GESTIONAR PACIENTE (ESTRUCTURA CORREGIDA) ---
    function abrirModalGestionarPaciente(pacienteId) {
        currentManagedPatientId = pacienteId; // Guardamos el ID del paciente que estamos gestionando
        if (modalGestionarPaciente) {
            const modalBody = document.getElementById('gestion-modal-body');
            const pacienteNombreDisplay = document.getElementById('gestion-paciente-nombre');
            const pacienteEdadDisplay = document.getElementById('gestion-paciente-edad');
            const pacienteDireccionDisplay = document.getElementById('gestion-paciente-direccion');
            
            modalBody.innerHTML = '<p>Cargando datos del paciente...</p>';
            pacienteNombreDisplay.textContent = '...';
            modalGestionarPaciente.style.display = 'flex';

            fetch(`get_patient_details.php?id=${pacienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    pacienteNombreDisplay.textContent = data.paciente.nombre_completo;
                    if (data.paciente.edad) { // Nuevo
                        pacienteEdadDisplay.textContent = `${data.paciente.edad} años`;
                    } else {
                        pacienteEdadDisplay.textContent = '';
                    }
                    if (pacienteDireccionDisplay) {
                        if (data.paciente.direccion) {
                            pacienteDireccionDisplay.innerHTML = '<i class="fa-solid fa-location-dot" style="margin-right:5px;"></i>';
                            pacienteDireccionDisplay.appendChild(document.createTextNode(data.paciente.direccion));
                        } else {
                            pacienteDireccionDisplay.textContent = '';
                        }
                    }
                    const nombreEscapado = data.paciente.nombre_completo.replace(/'/g, "\\'");
                    const pacienteEdad = data.paciente.edad; // Obtenemos la edad

                    const historiaBoton = data.tiene_historia
                        ? `<button type="button" class="action-card" onclick='abrirModalVerHistoria(${pacienteId}, ${pacienteEdad})'>
                               <div class="icon-wrapper" style="background-color: #02b1f4;"><i class="fa-solid fa-file-medical"></i></div>
                               <div><h3>Ver Historia Clínica</h3><p>Consulta el expediente completo.</p></div>
                           </button>`
                        : `<button type="button" class="action-card" onclick='abrirModalSeleccionarHistoria(${pacienteId}, "${data.paciente.cedula}", "${nombreEscapado}", ${data.paciente.edad})'>
                               <div class="icon-wrapper" style="background-color: #02b1f4;"><i class="fa-solid fa-file-circle-plus"></i></div>
                               <div><h3>Crear Historia Clínica</h3><p>Inicia un nuevo expediente.</p></div>
                           </button>`;

                    const informeBoton = data.tiene_historia
                        ? `<button type="button" class="action-card" onclick='abrirModalCrearInforme(${pacienteId}, ${JSON.stringify(data.paciente.nombre_completo)}, "${data.paciente.cedula}", ${pacienteEdad})'>
                               <div class="icon-wrapper" style="background-color: #17a2b8;"><i class="fa-solid fa-file-pen"></i></div>
                               <div><h3>Crear Nuevo Informe</h3><p>Redacta un nuevo informe.</p></div>
                           </button>`
                        : `<div class="action-card disabled-card">
                               <div class="icon-wrapper"><i class="fa-solid fa-file-pen"></i></div>
                               <div><h3>Crear Nuevo Informe</h3><p>Requiere historia clínica.</p></div>
                           </div>`;
                    
                    // --- TARJETA DE NOTAS DE SESIÓN CON NUEVO COLOR ---
                    const notasBoton = `
                        <button type="button" class="action-card" onclick='abrirModalGestionarNotas(${pacienteId})'>
                            <div class="icon-wrapper" style="background-color: #29bcd2ff;"><i class="fa-solid fa-notes-medical"></i></div>
                            <div>
                                <h3>Notas de Sesión</h3>
                                <p>Ver y añadir notas de evolución del paciente.</p>
                            </div>
                        </button>
                    `;

                    // --- ORDEN CORREGIDO DE LAS TARJETAS ---
                    modalBody.innerHTML = `
                        <div class="action-grid">
                            ${historiaBoton}
                            <button type="button" class="action-card" onclick="abrirModalVerInformes(${pacienteId})">
                                <div class="icon-wrapper" style="background-color: #6f42c1;"><i class="fa-solid fa-folder-open"></i></div>
                                <div><h3>Ver Informes (${data.total_estudios ?? 0})</h3><p>Accede al historial de informes.</p></div>
                            </button>
                            ${notasBoton}
                            ${informeBoton}
                        </div>
                    `;
                })
                .catch(error => {
                    modalBody.innerHTML = `<p style="color: red;">No se pudo cargar la información.</p>`;
                });
        }
    }
    function cerrarModalGestionarPaciente() { if (modalGestionarPaciente) modalGestionarPaciente.style.display = 'none'; }



    // --- NUEVAS FUNCIONES PARA LA MODAL DE NOTAS DE SESIÓN ---
    function abrirModalGestionarNotas(pacienteId) {
        if (modalGestionarNotas) {
            const pacienteNombreDisplay = document.getElementById('notas-paciente-nombre');
            const historialContainer = document.getElementById('historial-notas-container');
            const pacienteIdInput = document.getElementById('notas-paciente-id');

            pacienteIdInput.value = pacienteId;
            pacienteNombreDisplay.textContent = 'Cargando...';
            historialContainer.innerHTML = '<p>Cargando historial...</p>';
            modalGestionarNotas.style.display = 'flex';

            fetch(`get_notas_paciente.php?paciente_id=${pacienteId}`)
                .then(response => response.json())
                .then(data => {
                    pacienteNombreDisplay.textContent = data.paciente_nombre;
                    let notasHtml = '';
                    if (data.notas && data.notas.length > 0) {
                        data.notas.forEach(nota => {
                            notasHtml += `<div class="note-item"><div class="note-header">${nota.fecha_formateada}</div><div class="note-content">${nota.nota.replace(/\n/g, '<br>')}</div></div>`;
                        });
                    } else {
                        notasHtml = '<p>Este paciente no tiene notas de sesión.</p>';
                    }
                    historialContainer.innerHTML = notasHtml;
                })
                .catch(error => {
                    console.error('Error al cargar notas:', error);
                    historialContainer.innerHTML = '<p style="color: red;">Error al cargar el historial.</p>';
                });
        }
    }
    function cerrarModalGestionarNotas() {
        if (modalGestionarNotas) {
            modalGestionarNotas.style.display = 'none';
            document.getElementById('form-guardar-nota').reset();
        }
    }

    // Abrir modal para seleccionar historia clínica
    function abrirModalSeleccionarHistoria(pacienteId, pacienteCedula, pacienteNombre, pacienteEdad) {
        if (modalSeleccionarHistoria) {
            const btnAdulto = document.getElementById('btn-seleccionar-adulto');
            const btnInfantil = document.getElementById('btn-seleccionar-infantil');
            btnAdulto.dataset.pacienteId = pacienteId;
            btnAdulto.dataset.pacienteCedula = pacienteCedula;
            btnAdulto.dataset.pacienteNombre = pacienteNombre;
            btnAdulto.dataset.pacienteEdad = pacienteEdad; // Guardamos la edad


            btnInfantil.dataset.pacienteId = pacienteId;
            btnInfantil.dataset.pacienteCedula = pacienteCedula;
            btnInfantil.dataset.pacienteNombre = pacienteNombre;
            btnInfantil.dataset.pacienteEdad = pacienteEdad; // Guardamos la edad
            cerrarModalGestionarPaciente();
            modalSeleccionarHistoria.style.display = 'flex';
        }
    }
    function cerrarModalSeleccionarHistoria() {
        if (modalSeleccionarHistoria) {
            modalSeleccionarHistoria.style.display = 'none';
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // --- FUNCIONES PARA LA MODAL DE CREAR HISTORIA CLÍNICA ADULTO ---
    function abrirModalCrearHistoria(pacienteId, pacienteCedula, pacienteNombre, pacienteEdad) {
    if (modalCrearHistoria) {
        document.getElementById('historia-paciente-id').value = pacienteId;
        // Asignar cédula sólo si existe y no es '0'
        const cedulaAdulto = (typeof pacienteCedula !== 'undefined' && pacienteCedula !== null) ? String(pacienteCedula) : '';
        if (cedulaAdulto && cedulaAdulto !== '0') {
            document.getElementById('historia-paciente-cedula').value = cedulaAdulto;
            document.getElementById('historia-numero-adulto').value = cedulaAdulto;
        } else {
            document.getElementById('historia-paciente-cedula').value = '';
            document.getElementById('historia-numero-adulto').value = '';
        }
        
        let displayText = `Paciente: ${pacienteNombre}`;
        if (pacienteEdad) {
            displayText += ` (${pacienteEdad} años)`;
        }
        document.getElementById('historia-paciente-nombre-display-header').textContent = displayText;
        
        cerrarModalSeleccionarHistoria();
        modalCrearHistoria.style.display = 'flex';
    }
    }
    function cerrarModalCrearHistoria() {
        if (modalCrearHistoria) {
            modalCrearHistoria.style.display = 'none';
            document.getElementById('form-crear-historia').reset();
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }


    // --- FUNCIONES PARA LA MODAL DE CREAR HISTORIA INFANTIL ---

    function abrirModalCrearHistoriaInfantil(pacienteId, pacienteCedula, pacienteNombre, pacienteEdad) {
    if (modalCrearHistoriaInfantil) {
        document.getElementById('historia-paciente-id-infantil').value = pacienteId;
        // Asignar cédula sólo si existe y no es '0'
        const cedulaInfantil = (typeof pacienteCedula !== 'undefined' && pacienteCedula !== null) ? String(pacienteCedula) : '';
        if (cedulaInfantil && cedulaInfantil !== '0') {
            document.getElementById('ci_infante_modal').value = cedulaInfantil;
            document.getElementById('historia-numero-infantil').value = cedulaInfantil;
        } else {
            document.getElementById('ci_infante_modal').value = '';
            document.getElementById('historia-numero-infantil').value = '';
        }
        
        let displayText = `Paciente: ${pacienteNombre}`;
        if (pacienteEdad) {
            displayText += ` (${pacienteEdad} años)`;
        }
        document.getElementById('historia-paciente-nombre-display-infantil-header').textContent = displayText;
        
        cerrarModalSeleccionarHistoria();
        modalCrearHistoriaInfantil.style.display = 'flex';
    }
    }

    function cerrarModalCrearHistoriaInfantil() {
        if (modalCrearHistoriaInfantil) {
            modalCrearHistoriaInfantil.style.display = 'none';
            document.getElementById('form-crear-historia-infantil').reset();
            document.getElementById('hermanos-container-modal').innerHTML = '';

            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // ── MODAL 2: SELECCIONAR TIPO DE ECOGRAFÍA ──────────────────────────────
    // Variable temporal para guardar el contexto del paciente mientras navega la cadena
    let _ecoCtx = { pacienteId: null, cedula: '', nombre: '', edad: null };

    function abrirModalSeleccionarEcografia(pacienteId, cedula, nombre, edad) {
        if (!modalSeleccionarEcografia) return;
        _ecoCtx = { pacienteId, cedula, nombre, edad };
        const infoEl = document.getElementById('eco-modal-paciente-info');
        if (infoEl) {
            const edadStr = edad ? ` · ${edad} años` : '';
            infoEl.textContent = `Paciente: ${nombre}${edadStr}`;
        }
        // Cerrar la modal 1 sin regresar al gestor de paciente
        if (modalSeleccionarHistoria) modalSeleccionarHistoria.style.display = 'none';
        modalSeleccionarEcografia.style.display = 'flex';
    }

    function cerrarModalSeleccionarEcografia() {
        if (modalSeleccionarEcografia) {
            modalSeleccionarEcografia.style.display = 'none';
            if (currentManagedPatientId) abrirModalGestionarPaciente(currentManagedPatientId);
        }
    }

    function volverAModalHistoria() {
        if (modalSeleccionarEcografia) modalSeleccionarEcografia.style.display = 'none';
        // Volver a abrir la modal 1 con el mismo contexto
        if (modalSeleccionarHistoria && _ecoCtx.pacienteId) {
            modalSeleccionarHistoria.style.display = 'flex';
        }
    }

    /**
     * Llamado al hacer clic en una tarjeta de ecografía.
     * Si es Musculoesquelética (codigo ECO_MUSCU) abre sub-modal con articulaciones.
     * Si es cualquier otra, abre directamente el formulario.
     */
    function seleccionarEcografiaModal(tipoId, tipoNombre, tipoCodigo) {
        if (!_ecoCtx.pacienteId) return;
        tipoCodigo = tipoCodigo || '';

        // Interceptar Musculoesquelética padre → mostrar sub-selector
        if (tipoCodigo === 'ECO_MUSCU') {
            if (modalSeleccionarEcografia) modalSeleccionarEcografia.style.display = 'none';
            abrirModalSubMusculo();
            return;
        }

        // Interceptar Obstétrica padre → mostrar sub-selector (I / II-III Trimestre)
        if (tipoCodigo === 'eco_obstetrica') {
            if (modalSeleccionarEcografia) modalSeleccionarEcografia.style.display = 'none';
            abrirModalSubObstetrica();
            return;
        }

        // Interceptar Partes Blandas padre → mostrar sub-selector (General / Cuello / Inguinal)
        if (tipoCodigo === 'ECO_PBLANCAS') {
            if (modalSeleccionarEcografia) modalSeleccionarEcografia.style.display = 'none';
            abrirModalSubPartesBlandas();
            return;
        }

        // Cerrar Modal 2 sin regresar al gestor de paciente
        if (modalSeleccionarEcografia) modalSeleccionarEcografia.style.display = 'none';
        abrirModalFormularioEstudio(tipoId, tipoNombre);
    }

    // ── MODAL 2.5: SUB-SELECCIÓN MUSCULOESQUELÉTICA ───────────────────────
    const modalSeleccionarMusculo = document.getElementById('modal-seleccionar-musculo');

    function abrirModalSubMusculo() {
        if (!modalSeleccionarMusculo) return;
        const info = document.getElementById('musculo-modal-paciente-info');
        if (info && _ecoCtx.nombre) {
            info.textContent = 'Paciente: ' + _ecoCtx.nombre + ' · Seleccione la articulación a estudiar';
        }
        modalSeleccionarMusculo.style.display = 'flex';
    }

    function cerrarModalMusculo() {
        if (modalSeleccionarMusculo) modalSeleccionarMusculo.style.display = 'none';
    }

    function volverDeModalMusculo() {
        if (modalSeleccionarMusculo) modalSeleccionarMusculo.style.display = 'none';
        if (modalSeleccionarEcografia && _ecoCtx.pacienteId) {
            modalSeleccionarEcografia.style.display = 'flex';
        }
    }

    function seleccionarSubMusculo(tipoId, tipoNombre) {
        if (!_ecoCtx.pacienteId) return;
        _ecoCtx.fromSubMusculo = true;
        if (modalSeleccionarMusculo) modalSeleccionarMusculo.style.display = 'none';
        abrirModalFormularioEstudio(tipoId, tipoNombre);
    }

    // ── MODAL 2.6: SUB-SELECCIÓN OBSTÉTRICA ───────────────────────────────
    const modalSeleccionarObstetrica = document.getElementById('modal-seleccionar-obstetrica');

    function abrirModalSubObstetrica() {
        if (!modalSeleccionarObstetrica) return;
        const info = document.getElementById('obstetrica-modal-paciente-info');
        if (info && _ecoCtx.nombre) {
            info.textContent = 'Paciente: ' + _ecoCtx.nombre + ' · Seleccione el trimestre del estudio';
        }
        modalSeleccionarObstetrica.style.display = 'flex';
    }

    function cerrarModalObstetrica() {
        if (modalSeleccionarObstetrica) modalSeleccionarObstetrica.style.display = 'none';
    }

    function volverDeModalObstetrica() {
        if (modalSeleccionarObstetrica) modalSeleccionarObstetrica.style.display = 'none';
        if (modalSeleccionarEcografia && _ecoCtx.pacienteId) {
            modalSeleccionarEcografia.style.display = 'flex';
        }
    }

    function seleccionarSubObstetrica(tipoId, tipoNombre) {
        if (!_ecoCtx.pacienteId) return;
        _ecoCtx.fromSubObstetrica = true;
        if (modalSeleccionarObstetrica) modalSeleccionarObstetrica.style.display = 'none';
        abrirModalFormularioEstudio(tipoId, tipoNombre);
    }

    // ── MODAL 2.7: SUB-SELECCIÓN PARTES BLANDAS ───────────────────────────
    const modalSeleccionarPartesBlandas = document.getElementById('modal-seleccionar-partes-blandas');

    function abrirModalSubPartesBlandas() {
        if (!modalSeleccionarPartesBlandas) return;
        const info = document.getElementById('pblandas-modal-paciente-info');
        if (info && _ecoCtx.nombre) {
            info.textContent = 'Paciente: ' + _ecoCtx.nombre + ' · Seleccione el tipo de estudio';
        }
        modalSeleccionarPartesBlandas.style.display = 'flex';
    }

    function cerrarModalPartesBlandas() {
        if (modalSeleccionarPartesBlandas) modalSeleccionarPartesBlandas.style.display = 'none';
    }

    function volverDeModalPartesBlandas() {
        if (modalSeleccionarPartesBlandas) modalSeleccionarPartesBlandas.style.display = 'none';
        if (modalSeleccionarEcografia && _ecoCtx.pacienteId) {
            modalSeleccionarEcografia.style.display = 'flex';
        }
    }

    function seleccionarSubPartesBlandas(tipoId, tipoNombre) {
        if (!_ecoCtx.pacienteId) return;
        _ecoCtx.fromSubPartesBlandas = true;
        if (modalSeleccionarPartesBlandas) modalSeleccionarPartesBlandas.style.display = 'none';
        abrirModalFormularioEstudio(tipoId, tipoNombre);
    }

    // ── MODAL 3: FORMULARIO DE ESTUDIO ECOGRÁFICO ─────────────────────────
    function abrirModalFormularioEstudio(tipoId, tipoNombre) {
        if (!modalFormularioEstudio) return;

        const bodyEl     = document.getElementById('modal-form-eco-body');
        const tituloEl   = document.getElementById('modal-form-eco-titulo');
        const pacienteEl = document.getElementById('modal-form-eco-paciente');
        const iconEl     = document.getElementById('modal-form-eco-icon');
        const feedbackEl = document.getElementById('modal-form-eco-feedback');

        // Reset estado
        feedbackEl.style.display = 'none';
        feedbackEl.innerHTML = '';
        tituloEl.textContent = tipoNombre;
        pacienteEl.textContent = 'Paciente: ' + (_ecoCtx.nombre || '—');
        iconEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        bodyEl.innerHTML = '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando formulario…</p></div>';

        modalFormularioEstudio.style.display = 'flex';

        // Carga AJAX del formulario
        fetch(`get_form_ecografia.php?paciente_id=${_ecoCtx.pacienteId}&tipo_id=${tipoId}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    bodyEl.innerHTML = `<p style="color:#c0392b;padding:20px;">${data.error}</p>`;
                    return;
                }

                // Actualizar icono del header
                iconEl.innerHTML = `<i class="${data.tipo.icono || 'fa-solid fa-wave-square'}"></i>`;

                // Inyectar formulario
                bodyEl.innerHTML = `
                    <form id="form-estudio-modal" autocomplete="off">
                        <input type="hidden" name="paciente_id"       value="${data.paciente.id}">
                        <input type="hidden" name="tipo_ecografia_id" value="${data.tipo.id}">
                        <input type="hidden" name="esquema_version"   value="${data.tipo.esquema_version}">
                        ${data.html}
                        <div class="modal-form-eco-actions">
                            <button type="button" class="eco-btn-cancel" onclick="cerrarModalFormularioEstudio()">
                                <i class="fa-solid fa-xmark"></i> Cancelar
                            </button>
                            <button type="submit" class="eco-btn-submit">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar Informe
                            </button>
                            <button type="button" class="eco-btn-imprimir" disabled
                                    title="Disponible después de guardar el informe"
                                    onclick="imprimirInformeModal()">
                                <i class="fa-solid fa-print"></i> Imprimir
                            </button>
                        </div>
                    </form>`;

                const formEl = document.getElementById('form-estudio-modal');
                formEl.addEventListener('submit', _handleFormEstudioSubmit);

                // ── Campos condicionales: mostrar/ocultar según radio SI/NO ──
                formEl.addEventListener('change', function(ev) {
                    const inp = ev.target;
                    if (inp.type !== 'radio') return;
                    formEl.querySelectorAll('.campo-condicional').forEach(function(el) {
                        if (el.dataset.dependeDe === inp.name) {
                            el.style.display = (inp.value === el.dataset.dependeValor) ? '' : 'none';
                        }
                    });
                });
            })
            .catch(err => {
                bodyEl.innerHTML = `<p style="color:#c0392b;padding:20px;">Error de red: ${err.message}</p>`;
                iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            });
    }

    // ID del último informe guardado en este modal (para habilitar Imprimir)
    let _ultimoInformeGuardadoId = null;

    async function _handleFormEstudioSubmit(ev) {
        ev.preventDefault();
        const form      = ev.currentTarget;
        const submitBtn = form.querySelector('.eco-btn-submit');
        const imprimirBtn = form.querySelector('.eco-btn-imprimir');
        const feedbackEl= document.getElementById('modal-form-eco-feedback');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';
        feedbackEl.style.display = 'none';

        try {
            const resp = await fetch('guardar_informe_estudio.php', { method: 'POST', body: new FormData(form) });
            const json = await resp.json();

            feedbackEl.style.display = 'block';
            if (json.success) {
                _ultimoInformeGuardadoId = json.informe_id;
                feedbackEl.innerHTML = `<div class="eco-msg-ok">
                    <i class="fa-solid fa-circle-check"></i> ${json.message}
                    &nbsp;—&nbsp;
                    <a href="ver_informe_estudio.php?informe_id=${json.informe_id}" target="_blank">Ver informe</a>
                </div>`;
                // Marcar como guardado: deshabilitar submit, habilitar imprimir
                submitBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Guardado';
                submitBtn.classList.add('eco-btn-submit--saved');
                if (imprimirBtn) {
                    imprimirBtn.disabled = false;
                    imprimirBtn.removeAttribute('title');
                    imprimirBtn.classList.add('eco-btn-imprimir--ready');
                }
            } else {
                feedbackEl.innerHTML = `<div class="eco-msg-err">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    ${json.message || 'Error al guardar.'}
                </div>`;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Informe';
            }
        } catch (err) {
            feedbackEl.style.display = 'block';
            feedbackEl.innerHTML = `<div class="eco-msg-err">Error de red: ${err.message}</div>`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Informe';
        }
        // Scroll al feedback
        document.getElementById('modal-form-eco-body').scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Imprimir informe en iframe oculto — sin redirección, sin salir de la modal
    function _imprimirInformeEnIframe(informeId) {
        if (!informeId) return;
        var prev = document.getElementById('eco-print-frame');
        if (prev) prev.remove();
        var iframe = document.createElement('iframe');
        iframe.id = 'eco-print-frame';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:8.5in;height:11in;border:0;visibility:hidden;';
        iframe.src = 'ver_informe_estudio.php?informe_id=' + encodeURIComponent(informeId) + '&print=1';
        document.body.appendChild(iframe);
        // Cleanup automático
        setTimeout(function () { try { iframe.remove(); } catch (e) {} }, 60000);
    }

    function imprimirInformeModal() {
        _imprimirInformeEnIframe(_ultimoInformeGuardadoId);
    }

    function cerrarModalFormularioEstudio() {
        if (!modalFormularioEstudio) return;
        modalFormularioEstudio.style.display = 'none';
        document.getElementById('modal-form-eco-body').innerHTML =
            '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando…</p></div>';
        const fb = document.getElementById('modal-form-eco-feedback');
        fb.style.display = 'none';
        fb.innerHTML = '';
        _ultimoInformeGuardadoId = null;
        if (currentManagedPatientId) abrirModalGestionarPaciente(currentManagedPatientId);
    }

    function volverAModalEcoDesdeFormulario() {
        if (modalFormularioEstudio) modalFormularioEstudio.style.display = 'none';
        // Si veníamos del sub-modal musculo, regresamos a él
        if (_ecoCtx.fromSubMusculo && modalSeleccionarMusculo && _ecoCtx.pacienteId) {
            _ecoCtx.fromSubMusculo = false;
            modalSeleccionarMusculo.style.display = 'flex';
            return;
        }
        // Si veníamos del sub-modal obstétrico, regresamos a él
        if (_ecoCtx.fromSubObstetrica && modalSeleccionarObstetrica && _ecoCtx.pacienteId) {
            _ecoCtx.fromSubObstetrica = false;
            modalSeleccionarObstetrica.style.display = 'flex';
            return;
        }
        // Si veníamos del sub-modal partes blandas, regresamos a él
        if (_ecoCtx.fromSubPartesBlandas && modalSeleccionarPartesBlandas && _ecoCtx.pacienteId) {
            _ecoCtx.fromSubPartesBlandas = false;
            modalSeleccionarPartesBlandas.style.display = 'flex';
            return;
        }
        if (modalSeleccionarEcografia && _ecoCtx.pacienteId) {
            modalSeleccionarEcografia.style.display = 'flex';
        }
    }
    // ─────────────────────────────────────────────────────────────────────

    // --- FUNCIONES PARA LA MODAL DE VER INFORMES ---
    function abrirModalVerInformes(pacienteId) {
        if (!modalVerInformes) return;

        const nombreEl    = document.getElementById('informes-paciente-nombre');
        const edadEl      = document.getElementById('informes-paciente-edad');
        const cedulaEl    = document.getElementById('informes-paciente-cedula');
        const tituloEl    = document.getElementById('informes-panel-titulo');
        const container   = document.getElementById('historial-informes-container');

        nombreEl.textContent  = 'Cargando…';
        edadEl.textContent    = '';
        cedulaEl.textContent  = '';
        container.innerHTML   = '<p style="color:#94a3b8;padding:20px 0;">Cargando historial…</p>';

        cerrarModalGestionarPaciente();
        modalVerInformes.style.display = 'flex';

        fetch(`get_informes_paciente.php?paciente_id=${pacienteId}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    container.innerHTML = `<p style="color:#c0392b;">${data.error}</p>`;
                    return;
                }

                nombreEl.textContent  = data.paciente_nombre;
                edadEl.textContent    = data.paciente_edad ? `${data.paciente_edad} años` : '';
                cedulaEl.textContent  = data.paciente_cedula ? `CI: ${data.paciente_cedula}` : '';
                tituloEl.textContent  = `Estudios Registrados (${data.total})`;

                const estadoBadge = (estado, label) => {
                    const colores = {
                        borrador:   '#64748b',
                        finalizado: '#0284c7',
                        firmado:    '#15803d',
                        anulado:    '#b91c1c',
                    };
                    const color = colores[estado] || '#64748b';
                    return `<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;background:${color}20;color:${color};border:1px solid ${color}40;">${label}</span>`;
                };

                if (data.informes && data.informes.length > 0) {
                    container.innerHTML = data.informes.map(inf => `
                        <div class="informe-list-item">
                            <div class="item-icon">
                                <i class="${inf.tipo_icono || 'fa-solid fa-wave-square'}"></i>
                            </div>
                            <div class="item-info">
                                <h4>${inf.tipo_nombre}</h4>
                                    <p>
                                    <i class="fa-regular fa-calendar" style="margin-right:4px;"></i>${inf.fecha_formateada}
                                    &nbsp;·&nbsp;
                                    <i class="fa-solid fa-user-doctor" style="margin-right:4px;"></i>${inf.ecografista}
                                </p>
                            </div>
                            <div class="item-actions">
                                <button class="btn-view-details" onclick="abrirModalInformeDetalle(${inf.id})">
                                    <i class="fa-solid fa-eye" style="margin-right:5px;"></i>Ver
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
                            <i class="fa-solid fa-folder-open" style="font-size:2.5rem;margin-bottom:12px;display:block;opacity:.4;"></i>
                            <p style="margin:0;font-size:14px;">Este paciente no tiene estudios ecográficos registrados.</p>
                        </div>`;
                }
            })
            .catch(err => {
                container.innerHTML = `<p style="color:#c0392b;">Error al cargar: ${err.message}</p>`;
            });
    }

    function cerrarModalVerInformes() {
        if (modalVerInformes) {
            modalVerInformes.style.display = 'none';
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // --- FUNCIONES PARA LA MODAL DE DETALLE DE INFORME ---
    let _currentInformeDetalleId = null;
    function abrirModalInformeDetalle(informeId) {
        if (!modalInformeDetalle) return;
        _currentInformeDetalleId = informeId;

        const bodyEl    = document.getElementById('informe-detalle-body');
        const iconEl    = document.getElementById('inf-det-icon');
        const tituloEl  = document.getElementById('inf-det-titulo');
        const pacienteEl= document.getElementById('inf-det-paciente');
        const printBtn  = document.getElementById('inf-det-print');
        if (printBtn) {
            printBtn.onclick = function () {
                _imprimirInformeEnIframe(_currentInformeDetalleId);
            };
        }

        // Reset
        iconEl.innerHTML    = '<i class="fa-solid fa-spinner fa-spin"></i>';
        tituloEl.textContent = 'Cargando…';
        pacienteEl.textContent = '';
        bodyEl.innerHTML = '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando informe…</p></div>';

        modalInformeDetalle.style.display = 'flex';

        fetch(`get_informe_detalle.php?informe_id=${informeId}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    bodyEl.innerHTML = `<p style="color:#c0392b;padding:20px;">${data.error}</p>`;
                    return;
                }

                // Header
                iconEl.innerHTML     = `<i class="${data.tipo.icono || 'fa-solid fa-wave-square'}"></i>`;
                tituloEl.textContent  = data.tipo.nombre;
                pacienteEl.textContent= `Paciente: ${data.paciente.nombre}  ·  CI: ${data.paciente.cedula}  ·  ${data.paciente.edad} años`;

                // Barra de meta-información
                const metaBar = `
                    <div class="inf-det-meta">
                        <span><i class="fa-solid fa-hashtag"></i> <strong>${data.informe.numero_informe}</strong></span>
                        <span><i class="fa-regular fa-calendar"></i> <strong>${data.informe.fecha_formateada}</strong></span>
                        <span><i class="fa-solid fa-user-doctor"></i> <strong>${data.ecografista}</strong></span>
                    </div>`;

                // Inyectar meta + secciones del formulario en solo lectura
                bodyEl.innerHTML = metaBar + data.html;
            })
            .catch(err => {
                bodyEl.innerHTML = `<p style="color:#c0392b;padding:20px;">Error al cargar: ${err.message}</p>`;
                iconEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            });
    }

    function cerrarModalInformeDetalle() {
        if (!modalInformeDetalle) return;
        modalInformeDetalle.style.display = 'none';
        document.getElementById('informe-detalle-body').innerHTML =
            '<div class="modal-form-eco-loader"><i class="fa-solid fa-spinner fa-spin"></i><p>Cargando…</p></div>';
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE CREAR INFORME ---
    function abrirModalCrearInforme(pacienteId, pacienteNombre, pacienteCedula, pacienteEdad) {
        if (modalCrearInforme) {
            document.getElementById('informe-paciente-id').value = pacienteId;
            
            // Construimos el texto del encabezado, añadiendo la edad solo si existe
            let displayText = `Paciente: ${pacienteNombre}`;
            if (pacienteEdad) {
                displayText += ` (${pacienteEdad} años)`;
            }
            document.getElementById('informe-paciente-nombre-display').textContent = displayText;
            document.getElementById('informe-numero-historia').value = pacienteCedula;

            
            
            cerrarModalGestionarPaciente(); // Cierra la modal anterior
            modalCrearInforme.style.display = 'flex';
        }
    }

    function cerrarModalCrearInforme() {
        if (modalCrearInforme) {
            modalCrearInforme.style.display = 'none';
            document.getElementById('form-crear-informe').reset();
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE VER HISTORIA CLÍNICA ---
    function abrirModalVerHistoria(pacienteId, pacienteEdad) {
        if (modalVerHistoria) {
            const modalBody = document.getElementById('ver-historia-body');
            const modalTitulo = document.getElementById('ver-historia-titulo');
            const pacienteNombreDisplay = document.getElementById('ver-historia-paciente-nombre');
            const modalHeader = modalVerHistoria.querySelector('.modal-header-premium');

            // Limpiar acciones previas para evitar duplicados
            const existingActions = modalHeader.querySelector('.modal-header-actions');
            if (existingActions) {
                existingActions.remove();
            } else {
                const legacyButtons = modalHeader.querySelectorAll('#btn-editar-historia, #btn-imprimir-historia, #btn-borrar-historia');
                legacyButtons.forEach((btn) => btn.remove());
            }

            modalBody.innerHTML = '<p>Cargando historial...</p>';
            modalTitulo.textContent = 'Historia Clínica';
            pacienteNombreDisplay.textContent = '...';
            cerrarModalGestionarPaciente();
            modalVerHistoria.style.display = 'flex';

            fetch(`get_gestionar_paciente.php?paciente_id=${pacienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.tipo || !data.datos) {
                        modalBody.innerHTML = '<p>Este paciente no tiene una historia clínica registrada.</p>';
                        return;
                    }
                    
                    const datos = data.datos;
                    const nombrePaciente = document.getElementById('gestion-paciente-nombre').textContent;
                    const headerActions = document.createElement('div');
                    headerActions.className = 'modal-header-actions';

                    // --- LÓGICA PARA AÑADIR LOS BOTONES DE ACCIÓN ---
                    const profesionalNombre = datos.entrevistador_nombre || data.profesional_nombre || 'No especificado';
                    const printButton = document.createElement('a');
                    printButton.id = 'btn-imprimir-historia';
                    printButton.className = 'btn-edit-historia';
                    printButton.href = '#';
                    printButton.innerHTML = '<i class="fa-solid fa-print"></i> Imprimir';
                    printButton.onclick = function(event) {
                        event.preventDefault();
                        imprimirHistoriaClinica({
                            contenidoHtml: modalBody.innerHTML,
                            titulo: modalTitulo.textContent,
                            pacienteInfo: pacienteNombreDisplay.textContent,
                            profesionalNombre
                        });
                    };
                    headerActions.appendChild(printButton);

                    const editButton = document.createElement('a');
                    editButton.id = 'btn-editar-historia';
                    editButton.className = 'btn-edit-historia';
                    editButton.href = '#';
                    editButton.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Editar Historia';
                    editButton.onclick = function(event) {
                        event.preventDefault();
                        abrirModalEditarHistoria({
                            historiaId: data.datos.id,
                            tipo: data.tipo,
                            pacienteId,
                            pacienteNombre: nombrePaciente,
                            pacienteEdad: pacienteEdad || null
                        });
                    };
                    headerActions.appendChild(editButton);

                    const closeButton = modalHeader.querySelector('.modal-close-btn');
                    if (closeButton) {
                        modalHeader.insertBefore(headerActions, closeButton);
                    } else {
                        modalHeader.appendChild(headerActions);
                    }

                    // Construimos el texto del encabezado, añadiendo la edad solo si existe
                    let displayText = `Paciente: ${nombrePaciente}`;
                    if (pacienteEdad) {
                        displayText += ` (${pacienteEdad} años)`;
                    }
                    pacienteNombreDisplay.textContent = displayText;
                    currentHistoriaContext = {
                        pacienteId,
                        pacienteEdad: pacienteEdad || null,
                        pacienteNombre: nombrePaciente,
                        historiaId: datos.id,
                        tipo: data.tipo
                    };

                    let historiaHtml = '';

                    // Función auxiliar para mostrar los datos de forma segura
                    const mostrar = (valor) => valor ? htmlspecialchars(valor) : 'No especificado';
                    const mostrarLargo = (valor) => valor ? nl2br(htmlspecialchars(valor)) : 'No especificado';
                    const formatearDireccion = (valor) => {
                        if (!valor) {
                            return 'No especificado';
                        }

                        const textoSeguro = htmlspecialchars(valor);
                        const partes = textoSeguro.split(/,\s*/);

                        if (partes.length > 1) {
                            const puntoCorte = Math.ceil(partes.length / 2);
                            const primeraLinea = partes.slice(0, puntoCorte).join(', ');
                            const segundaLinea = partes.slice(puntoCorte).join(', ');
                            return `${primeraLinea}<br>${segundaLinea}`;
                        }

                        if (textoSeguro.length > 40) {
                            const mitad = Math.floor(textoSeguro.length / 2);
                            let indiceEspacio = textoSeguro.indexOf(' ', mitad);
                            if (indiceEspacio === -1) {
                                indiceEspacio = textoSeguro.lastIndexOf(' ', mitad);
                            }
                            if (indiceEspacio > 0) {
                                const primeraParte = textoSeguro.slice(0, indiceEspacio);
                                const segundaParte = textoSeguro.slice(indiceEspacio + 1);
                                return `${primeraParte}<br>${segundaParte}`;
                            }
                        }

                        return textoSeguro;
                    };

                    function htmlspecialchars(str) {
                        return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                    }
                    function nl2br(str) {
                        return str.replace(/\r\n|\r|\n/g, '<br>');
                    }

                    if (data.tipo === 'adulto') {
                        modalTitulo.textContent = 'Historia Clínica de Adulto';
                        historiaHtml = `
                            <h3>Datos Generales</h3>
                            <div class="dato-item"><strong>N° de Historia:</strong> <p>${mostrar(datos.numero_historia)}</p></div>
                            <div class="dato-item"><strong>Centro de Salud:</strong> <p>${mostrar(datos.centro_salud)}</p></div>
                            <div class="dato-item"><strong>Fecha:</strong> <p>${new Date(datos.fecha).toLocaleDateString('es-ES')}</p></div>
                            <h3>Datos Personales</h3>
                            <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(datos.ci_paciente)}</p></div>
                            <div class="dato-item"><strong>Sexo:</strong> <p>${mostrar(datos.sexo)}</p></div>
                            <div class="dato-item"><strong>Teléfono:</strong> <p>${mostrar(datos.telefono)}</p></div>
                            <div class="dato-item"><strong>Edo. Civil:</strong> <p>${mostrar(datos.estado_civil)}</p></div>
                            <div class="dato-item"><strong>Nacionalidad:</strong> <p>${mostrar(datos.nacionalidad)}</p></div>
                            <div class="dato-item"><strong>Hijos:</strong> <p>${mostrar(datos.hijos)}</p></div>
                            <div class="dato-item"><strong>Religión:</strong> <p>${mostrar(datos.religion)}</p></div>
                            <div class="dato-item"><strong>Grado de Instrucción:</strong> <p>${mostrar(datos.grado_instruccion)}</p></div>
                            <div class="dato-item"><strong>Ocupación:</strong> <p>${mostrar(datos.ocupacion)}</p></div>
                            <div class="dato-item"><strong>Dirección:</strong> <p>${formatearDireccion(datos.direccion)}</p></div>
                            <h3>Motivo y Antecedentes</h3>
                            <div class="dato-item"><strong>Motivo de Consulta:</strong> <p>${mostrarLargo(datos.motivo_consulta)}</p></div>
                            <div class="dato-item"><strong>Antecedentes Personales:</strong> <p>${mostrarLargo(datos.antecedentes_personales)}</p></div>
                            <div class="dato-item"><strong>Antecedentes Familiares:</strong> <p>${mostrarLargo(datos.antecedentes_familiares)}</p></div>
                            <div class="dato-item"><strong>Antecedentes Psiquiátricos:</strong> <p>${mostrarLargo(datos.antecedentes_psiquiatricos)}</p></div>
                            <div class="dato-item"><strong>Antecedentes Médicos:</strong> <p>${mostrarLargo(datos.antecedentes_medicos)}</p></div>
                            <div class="dato-item"><strong>Antecedentes de Pareja:</strong> <p>${mostrarLargo(datos.antecedentes_pareja)}</p></div>
                            <h3>Diagnóstico</h3>
                            <div class="dato-item"><strong>Impresión Diagnóstica:</strong> <p>${mostrarLargo(datos.impresion_diagnostica)}</p></div>
                        `;
                    } else if (data.tipo === 'infantil') {
                        modalTitulo.textContent = 'Historia Clínica Infantil';
                        let hermanosHtml = '<p>No se registraron hermanos.</p>';
                        if (datos.hermanos) {
                            try {
                                const hermanos = JSON.parse(datos.hermanos);
                                if (hermanos && hermanos.length > 0) {
                                    hermanosHtml = '<ul>';
                                    hermanos.forEach(h => {
                                        hermanosHtml += `<li>${mostrar(h.nombre)} (${mostrar(h.edad)} años) - ${mostrar(h.sexo)} - ${mostrar(h.ocupacion)}</li>`;
                                    });
                                    hermanosHtml += '</ul>';
                                }
                            } catch (e) { /* Mantener el mensaje por defecto si el JSON es inválido */ }
                        }
                        historiaHtml = `
                            <h3>Datos Generales</h3>
                            <div class="dato-item"><strong>N° de Historia:</strong> <p>${mostrar(datos.numero_historia)}</p></div>
                            <div class="dato-item"><strong>Centro de Salud:</strong> <p>${mostrar(datos.centro_salud)}</p></div>
                            <div class="dato-item"><strong>Fecha:</strong> <p>${new Date(datos.fecha).toLocaleDateString('es-ES')}</p></div>
                            <h3>Datos Personales del Infante</h3>
                            <div class="dato-item"><strong>Lugar de Nacimiento:</strong> <p>${mostrar(datos.lugar_nacimiento)}</p></div>
                            <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(datos.ci_infante)}</p></div>
                            <div class="dato-item"><strong>Institución Escolar:</strong> <p>${mostrar(datos.institucion_escolar)}</p></div>
                            <h3>Datos del Padre</h3>
                            <div class="dato-item"><strong>Nombre:</strong> <p>${mostrar(datos.padre_nombre)}</p></div>
                            <div class="dato-item"><strong>Edad:</strong> <p>${mostrar(datos.padre_edad)}</p></div>
                            <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(datos.padre_ci)}</p></div>
                            <div class="dato-item"><strong>Nacionalidad:</strong> <p>${mostrar(datos.padre_nacionalidad)}</p></div>
                            <div class="dato-item"><strong>Religión:</strong> <p>${mostrar(datos.padre_religion)}</p></div>
                            <div class="dato-item"><strong>Grado de Instrucción:</strong> <p>${mostrar(datos.padre_instruccion)}</p></div>
                            <div class="dato-item"><strong>Ocupación:</strong> <p>${mostrar(datos.padre_ocupacion)}</p></div>
                            <div class="dato-item"><strong>Teléfono:</strong> <p>${mostrar(datos.padre_telefono)}</p></div>
                            <div class="dato-item"><strong>Dirección:</strong> <p>${formatearDireccion(datos.padre_direccion)}</p></div>
                            <h3>Datos de la Madre</h3>
                            <div class="dato-item"><strong>Nombre:</strong> <p>${mostrar(datos.madre_nombre)}</p></div>
                            <div class="dato-item"><strong>Edad:</strong> <p>${mostrar(datos.madre_edad)}</p></div>
                            <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(datos.madre_ci)}</p></div>
                            <div class="dato-item"><strong>Nacionalidad:</strong> <p>${mostrar(datos.madre_nacionalidad)}</p></div>
                            <div class="dato-item"><strong>Religión:</strong> <p>${mostrar(datos.madre_religion)}</p></div>
                            <div class="dato-item"><strong>Grado de Instrucción:</strong> <p>${mostrar(datos.madre_instruccion)}</p></div>
                            <div class="dato-item"><strong>Ocupación:</strong> <p>${mostrar(datos.madre_ocupacion)}</p></div>
                            <div class="dato-item"><strong>Teléfono:</strong> <p>${mostrar(datos.madre_telefono)}</p></div>
                            <div class="dato-item"><strong>Dirección:</strong> <p>${formatearDireccion(datos.madre_direccion)}</p></div>
                            <h3>Dinámica Familiar</h3>
                            <div class="dato-item"><strong>¿Padres viven juntos?:</strong> <p>${mostrar(datos.padres_viven_juntos)}</p></div>
                            <div class="dato-item"><strong>¿Están casados?:</strong> <p>${mostrar(datos.estan_casados)}</p></div>
                            <div class="dato-item"><strong>Motivo de separación:</strong> <p>${mostrarLargo(datos.motivo_separacion)}</p></div>
                            <div class="dato-item"><strong>Hermanos:</strong> ${hermanosHtml}</div>
                            <h3>Motivos y Antecedentes</h3>
                            <div class="dato-item"><strong>Motivo de Consulta:</strong> <p>${mostrarLargo(datos.motivo_consulta)}</p></div>
                            <div class="dato-item"><strong>Tipo de Embarazo:</strong> <p>${mostrar(datos.antecedentes_embarazo)}</p></div>
                            <div class="dato-item"><strong>Parto (Lugar):</strong> <p>${mostrar(datos.antecedentes_parto)}</p></div>
                            <div class="dato-item"><strong>Estado del niño/a al nacer:</strong> <p>${mostrar(datos.estado_nino_nacer)}</p></div>
                            <div class="dato-item"><strong>Desarrollo Psicomotor:</strong> <p>${mostrarLargo(datos.desarrollo_psicomotor)}</p></div>
                            <div class="dato-item"><strong>Hábitos de Independencia:</strong> <p>${mostrarLargo(datos.habitos_independencia)}</p></div>
                            <div class="dato-item"><strong>Condiciones de Salud:</strong> <p>${mostrarLargo(datos.condiciones_salud)}</p></div>
                            <div class="dato-item"><strong>Vida Social:</strong> <p>${mostrarLargo(datos.vida_social)}</p></div>
                            <h3>Plan Terapéutico</h3>
                            <div class="dato-item"><strong>Plan Psicoterapéutico:</strong> <p>${mostrarLargo(datos.plan_psicoterapeutico)}</p></div>
                        `;
                    }
                    modalBody.innerHTML = historiaHtml;
                });
        }
    }

    function imprimirHistoriaClinica({ contenidoHtml, titulo, pacienteInfo, profesionalNombre }) {
        const escapeHtml = (valor) => {
            if (valor === null || valor === undefined) {
                return '';
            }
            return valor.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const safeProfesional = profesionalNombre ? escapeHtml(profesionalNombre) : '';
        const safePacienteInfo = pacienteInfo ? escapeHtml(pacienteInfo) : '';

        const printContainer = document.createElement('div');
        printContainer.className = 'historia-print-container';
        printContainer.style.display = 'block';
        printContainer.innerHTML = `
            <div class="historia-print-wrapper">
                <div class="historia-print-header">
                    <p class="historia-print-profesional">Profesional a cargo: ${safeProfesional || 'No especificado'}</p>
                    <h2 class="historia-print-title">${escapeHtml(titulo || 'Historia Clínica')}</h2>
                    ${safePacienteInfo ? `<p class="historia-print-paciente">${safePacienteInfo}</p>` : ''}
                </div>
                <div class="historia-print-body">
                    ${contenidoHtml || ''}
                </div>
            </div>
        `;

        document.body.appendChild(printContainer);

        const cleanup = () => {
            if (printContainer.parentNode) {
                printContainer.parentNode.removeChild(printContainer);
            }
            window.removeEventListener('afterprint', cleanup);
        };

        window.addEventListener('afterprint', cleanup);

        setTimeout(() => {
            window.print();
            setTimeout(cleanup, 400);
        }, 50);
    }

    function cerrarModalVerHistoria() {
        if (modalVerHistoria) {
            modalVerHistoria.style.display = 'none';
            
            // Si sabemos qué paciente estábamos gestionando, volvemos a abrir su modal
            if (currentManagedPatientId) {
                abrirModalGestionarPaciente(currentManagedPatientId);
            }
        }
    }

    function abrirModalEditarHistoria({ historiaId, tipo, pacienteId, pacienteNombre, pacienteEdad }) {
        if (!modalEditarHistoria || !historiaId || !tipo) {
            return;
        }

        const titulo = document.getElementById('editar-historia-titulo');
        const nombreDisplay = document.getElementById('editar-historia-paciente-nombre');
        const cuerpo = document.getElementById('editar-historia-body');

        if (titulo) {
            titulo.textContent = (tipo === 'adulto') ? 'Editar Historia Clínica de Adulto' : 'Editar Historia Clínica Infantil';
        }
        if (nombreDisplay) {
            nombreDisplay.textContent = pacienteEdad ? `${pacienteNombre} (${pacienteEdad} años)` : pacienteNombre;
        }
        if (cuerpo) {
            cuerpo.innerHTML = '<p>Cargando formulario de edición...</p>';
        }

        if (modalVerHistoria) {
            modalVerHistoria.style.display = 'none';
        }
        modalEditarHistoria.style.display = 'flex';

        currentHistoriaContext = {
            pacienteId,
            pacienteEdad: pacienteEdad || null,
            pacienteNombre,
            historiaId,
            tipo
        };

        fetch(`#?historia_id=${historiaId}&tipo=${tipo}&ajax=1`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('No se pudo cargar el formulario de edición.');
                }
                return response.text();
            })
            .then(html => {
                if (cuerpo) {
                    cuerpo.innerHTML = html;
                    prepararFormularioEdicion(cuerpo);
                }
            })
            .catch(error => {
                if (cuerpo) {
                    cuerpo.innerHTML = `<p style="color: red;">${error.message}</p>`;
                }
            });
    }

    function cerrarModalEditarHistoria(reabrirVista = false) {
        if (modalEditarHistoria) {
            modalEditarHistoria.style.display = 'none';
            const cuerpoEdicion = document.getElementById('editar-historia-body');
            if (cuerpoEdicion) {
                cuerpoEdicion.innerHTML = '';
            }
        }

        if (reabrirVista && modalVerHistoria) {
            modalVerHistoria.style.display = 'flex';
        }
    }

    function prepararFormularioEdicion(contenedor) {
        if (!contenedor) return;

        const mensajeErrorExistente = contenedor.querySelector('.alert-error-edicion');
        if (mensajeErrorExistente) {
            mensajeErrorExistente.remove();
        }

        const form = contenedor.querySelector('#editar-historia-form');
        if (form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();

                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Guardando...';
                }

                const formData = new FormData(form);

                fetch('#', {
                    method: 'POST',
                    body: formData
                })
                .then(respuesta => respuesta.json())
                .then(datos => {
                    if (!datos.success) {
                        throw new Error(datos.message || 'No se pudo actualizar la historia clínica.');
                    }

                    cerrarModalEditarHistoria(false);
                    if (currentHistoriaContext) {
                        abrirModalVerHistoria(currentHistoriaContext.pacienteId, currentHistoriaContext.pacienteEdad);
                    }
                    alert('Historia clínica actualizada correctamente.');
                })
                .catch(error => {
                    mostrarErrorEdicion(contenedor, error.message);
                })
                .finally(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Guardar cambios';
                    }
                });
            });
        }

        const cancelBtn = contenedor.querySelector('.btn-secondary');
        if (cancelBtn) {
            cancelBtn.onclick = function(event) {
                event.preventDefault();
                cerrarModalEditarHistoria(true);
            };
        }

        // Botón para añadir nuevos hermanos (solo formulario infantil)
        const addHermanoBtn = contenedor.querySelector('#add-hermano-btn-edit');
        if (addHermanoBtn) {
            addHermanoBtn.addEventListener('click', function() {
                const hermanosContainer = contenedor.querySelector('#hermanos-container');
                if (!hermanosContainer) return;

                const wrapper = document.createElement('div');
                wrapper.className = 'hermano-entry';
                wrapper.innerHTML = `
                    <div class="form-group"><label>Nombre:</label><input type="text" name="hermano_nombre[]"></div>
                    <div class="form-group"><label>Edad:</label><input type="number" name="hermano_edad[]"></div>
                    <div class="form-group"><label>Sexo:</label><input type="text" name="hermano_sexo[]"></div>
                    <div class="form-group"><label>Ocupación:</label><input type="text" name="hermano_ocupacion[]"></div>
                    <div class="form-group"><label>¿Vive en casa?:</label><select name="hermano_vive_hogar[]"><option value="Sí">Sí</option><option value="No">No</option></select></div>
                    <button type="button" class="remove-hermano-btn" title="Quitar">&times;</button>
                `;

                const removeBtn = wrapper.querySelector('.remove-hermano-btn');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => wrapper.remove());
                }

                hermanosContainer.appendChild(wrapper);
            });
        }

        contenedor.querySelectorAll('.remove-hermano-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const bloque = this.closest('.hermano-entry');
                if (bloque) bloque.remove();
            });
        });
    }

    function mostrarErrorEdicion(contenedor, mensaje) {
        if (!contenedor) return;

        let alerta = contenedor.querySelector('.alert-error-edicion');
        if (!alerta) {
            alerta = document.createElement('div');
            alerta.className = 'alert-error-edicion';
            alerta.style.backgroundColor = '#f8d7da';
            alerta.style.color = '#721c24';
            alerta.style.padding = '12px 15px';
            alerta.style.borderRadius = '8px';
            alerta.style.marginBottom = '15px';
            alerta.style.fontSize = '14px';
            contenedor.prepend(alerta);
        }
        alerta.textContent = mensaje;
        alerta.style.display = 'block';
    }

    // --- NUEVAS FUNCIONES PARA LA MODAL DE ASIGNAR CITA ---
    const modalAsignarCita = document.getElementById('modal-asignar-cita');

    function abrirModalAsignarCita(citaId) {
        if (modalAsignarCita) {
            const pacienteNombreDisplay = document.getElementById('asignar-paciente-nombre');
            const motivoConsultaDisplay = document.getElementById('asignar-motivo-consulta');
            const citaIdInput = document.getElementById('asignar-cita-id');
            const profesionalSolicitadoDisplay = document.getElementById('asignar-profesional-solicitado'); // Nuevo
            const psicologoSelector = document.getElementById('asignar-ecografista-id'); // Nuevo

            pacienteNombreDisplay.textContent = 'Cargando...';
            motivoConsultaDisplay.textContent = '...';
            profesionalSolicitadoDisplay.textContent = '...'; // Nuevo
            citaIdInput.value = citaId;
            modalAsignarCita.style.display = 'flex';

            fetch(`get_cita_details_secretaria.php?cita_id=${citaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        pacienteNombreDisplay.textContent = 'Error';
                        motivoConsultaDisplay.textContent = data.error;
                        return;
                    }
                    pacienteNombreDisplay.textContent = data.paciente_nombre;
                    motivoConsultaDisplay.textContent = data.motivo_consulta;
                    
                    // --- LÓGICA AÑADIDA ---
                    // Mostramos el profesional que el paciente solicitó
                    profesionalSolicitadoDisplay.textContent = data.profesional_solicitado_nombre || 'No especificado';
                    // Pre-seleccionamos a ese profesional en el combo box
                    psicologoSelector.value = data.profesional_solicitado_id;
                });
        }
    }
    function cerrarModalAsignarCita() {
        if (modalAsignarCita) {
            modalAsignarCita.style.display = 'none';
            document.getElementById('form-asignar-cita').reset();
        }
    }


    // --- NUEVAS FUNCIONES PARA LA MODAL DE DETALLES DE CITA ---
    const modalDetalleCitaPaciente = document.getElementById('modal-detalle-cita-paciente');

    function abrirModalDetalleCitaPaciente(citaId) {
        if (modalDetalleCitaPaciente) {
            const modalBody = document.getElementById('detalle-cita-body');
            const fechaDisplay = document.getElementById('detalle-cita-fecha');

            modalBody.innerHTML = '<p>Cargando detalles...</p>';
            fechaDisplay.textContent = '...';
            modalDetalleCitaPaciente.style.display = 'flex';

            fetch(`get_cita_details_paciente.php?id=${citaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    fechaDisplay.textContent = `Cita para el ${data.fecha_cita_formateada}`;
                    
                    const mostrar = (valor) => valor || 'No especificado';
                    const mostrarLargo = (valor) => valor ? valor.replace(/\n/g, '<br>') : 'No especificado';

                    let detalleHtml = '';

                    // --- LÓGICA CORREGIDA Y DEFINITIVA PARA CITAS POSPUESTAS ---
                    // Se muestra si el estado es 'pendiente_paciente' O 'reprogramada'
                    if (data.estado === 'pendiente_paciente' || data.estado === 'reprogramada') {
                        detalleHtml += `
                            <div class="reprogramacion-info">
                                <h4>¡Atención! El profesional ha propuesto una nueva fecha</h4>
                                <p><strong>Nueva Fecha Sugerida:</strong> ${data.fecha_propuesta_formateada}</p>
                                <p><strong>Motivo:</strong> <em>"${mostrarLargo(data.reprogramacion_motivo)}"</em></p>
                            </div>
                            <div class="modal-actions-propuesta">
                                <a href="gestionar_propuesta.php?cita_id=${data.id}&accion=rechazar" class="btn-secondary">Rechazar Propuesta</a>
                                <a href="gestionar_propuesta.php?cita_id=${data.id}&accion=aceptar" class="btn-submit">Aceptar Nueva Fecha</a>
                            </div>
                        `;
                    }

                    detalleHtml += `
                        <h3>Detalles de la Consulta</h3>
                        <div class="dato-item"><strong>Tipo de Cita:</strong> <p>${mostrar(data.tipo_cita)}</p></div>
                        <div class="dato-item"><strong>Modalidad:</strong> <p>${mostrar(data.modalidad)}</p></div>
                        <div class="dato-item"><strong>Motivo Principal:</strong> <p>${mostrar(data.motivo_principal)}</p></div>
                        <div class="dato-item"><strong>Descripción Adicional:</strong> <p>${mostrarLargo(data.motivo_consulta)}</p></div>

                        <h3>Profesional y Preferencias</h3>
                        <div class="dato-item"><strong>Especialidad Requerida:</strong> <p>${mostrar(data.profesional_rol)}</p></div>
                        <div class="dato-item"><strong>Profesional Asignado:</strong> <p>${mostrar(data.profesional_nombre)}</p></div>
                        <div class="dato-item"><strong>Notas Adicionales:</strong> <p>${mostrar(data.notas_paciente)}</p></div>
                    `;

                    modalBody.innerHTML = detalleHtml;
                });
        }
    }
    function cerrarModalDetalleCitaPaciente() {
        if (modalDetalleCitaPaciente) {
            modalDetalleCitaPaciente.style.display = 'none';
        }
    }


    // --- NUEVAS FUNCIONES PARA LA MODAL DE DETALLES DE PROFESIONAL ---
    const modalProfesionalDetalle = document.getElementById('modal-profesional-detalle');

    function abrirModalProfesionalDetalle(profesionalId) {
        if (modalProfesionalDetalle) {
            const modalBody = document.getElementById('profesional-detalle-body');
            const nombreDisplay = document.getElementById('profesional-detalle-nombre');
            const rolDisplay = document.getElementById('profesional-detalle-rol');

            modalBody.innerHTML = '<p>Cargando...</p>';
            nombreDisplay.textContent = '...';
            rolDisplay.textContent = '...';
            modalProfesionalDetalle.style.display = 'flex';

            fetch(`get_professional_details.php?id=${profesionalId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    nombreDisplay.textContent = data.nombre_completo;
                    rolDisplay.textContent = data.rol_formateado;
                    
                    const mostrar = (valor) => valor || 'No especificado';

                    let detalleHtml = `
                        <div class="dato-item"><strong>Nombre Completo:</strong> <p>${mostrar(data.nombre_completo)}</p></div>
                        <div class="dato-item"><strong>Cédula:</strong> <p>${mostrar(data.cedula)}</p></div>
                        <div class="dato-item"><strong>Correo Electrónico:</strong> <p>${mostrar(data.correo)}</p></div>
                        <div class="dato-item"><strong>Especialidades:</strong> <p>${mostrar(data.especialidades)}</p></div>
                        <div class="dato-item"><strong>Estado de la Cuenta:</strong> <p>${mostrar(data.estado_formateado)}</p></div>
                        <div class="dato-item"><strong>Miembro desde:</strong> <p>${mostrar(data.fecha_registro_formateada)}</p></div>
                    `;
                    modalBody.innerHTML = detalleHtml;
                });
        }
    }
    function cerrarModalProfesionalDetalle() {
        if (modalProfesionalDetalle) {
            modalProfesionalDetalle.style.display = 'none';
        }
    }


    // --- NUEVAS FUNCIONES PARA LA MODAL DE ÉXITO ---
    const modalExitoPaciente = document.getElementById('eco-modal-exito-paciente-panel');

    function abrirModalExitoPaciente(nombre, password) {
        if (!modalExitoPaciente) return;
        document.getElementById('exito-paciente-nombre').textContent = nombre;
        document.getElementById('exito-paciente-password').textContent = password;
        if (typeof EcoModal !== 'undefined') {
            EcoModal.open('eco-modal-exito-paciente-panel');
        }
    }
    function cerrarModalExitoPaciente() {
        if (typeof EcoModal !== 'undefined') {
            EcoModal.close('eco-modal-exito-paciente-panel');
        }
    }


    // --- FUNCIONES PARA LAS MODALES (ABRIR/CERRAR) ---
    // (Aquí van todas tus funciones para abrir y cerrar las modales que ya funcionan)
    function abrirModalSolicitudDetalle(citaId) {
        if (modalSolicitudDetalle) {
            const modalBody = document.getElementById('solicitud-detalle-body');
            const pacienteNombreDisplay = document.getElementById('solicitud-paciente-nombre');

            modalBody.innerHTML = '<p>Cargando...</p>';
            pacienteNombreDisplay.textContent = '...';
            modalSolicitudDetalle.style.display = 'flex';

            fetch(`get_solicitud_details.php?id=${citaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                        return;
                    }

                    pacienteNombreDisplay.textContent = 'Paciente: ' + data.paciente_nombre;
                    const mostrar = (valor) => valor || 'No especificado';

                    // --- HTML ACTUALIZADO CON LOS NUEVOS CAMPOS ---
                    let detalleHtml = `
                        <h3>Datos del Paciente</h3>
                        <div class="dato-item"><strong>Identificacion:</strong> <p>${mostrar(data.paciente_cedula)}</p></div>
                        <div class="dato-item"><strong>Edad:</strong> <p>${mostrar(data.paciente_edad)} años</p></div>

                        <h3>Detalles de la Consulta</h3>
                        <div class="dato-item"><strong>Tipo de Cita:</strong> <p>${mostrar(data.tipo_cita_formateado)}</p></div>
                        <div class="dato-item"><strong>Modalidad:</strong> <p>${mostrar(data.modalidad_formateada)}</p></div>
                        <div class="dato-item"><strong>Motivo Principal:</strong> <p>${mostrar(data.motivo_principal)}</p></div>
                        <div class="dato-item"><strong>Descripción Adicional:</strong> <p>${mostrar(data.motivo_consulta)}</p></div>
                        <div class="dato-item"><strong>Notas del Paciente:</strong> <p>${mostrar(data.notas_paciente)}</p></div>
                        <div class="dato-item"><strong>Fecha y Hora Solicitada:</strong> <p>${data.fecha_solicitada_formateada}</p></div>
                    `;
                    modalBody.innerHTML = detalleHtml;
                });
        }
    }
    function cerrarModalSolicitudDetalle() {
        if (modalSolicitudDetalle) {
            modalSolicitudDetalle.style.display = 'none';
        }
    }





    // --- FUNCIONES PARA LA MODAL DE CONFLICTO ---
    const modalConflictoCita = document.getElementById('modal-conflicto-cita');

    function abrirModalConflicto(citaId, pacienteNombre) {
        if (modalConflictoCita) {
            const proponerBtn = document.getElementById('btn-proponer-fecha-conflicto');
            
            // Quitamos el enlace y añadimos un evento de clic
            proponerBtn.removeAttribute('href');
            proponerBtn.onclick = function() {
                cerrarModalConflicto();
                abrirModalProponerFecha(citaId, pacienteNombre);
            };

            modalConflictoCita.style.display = 'flex';
        }
    }
    function cerrarModalConflicto() {
        if (modalConflictoCita) {
            modalConflictoCita.style.display = 'none';
        }
    }

    // --- FUNCIÓN MODIFICADA PARA VERIFICAR ANTES DE CONFIRMAR ---
    function intentarConfirmarCita(citaId) {
        fetch(`check_conflict.php?cita_id=${citaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.conflict) {
                    // Si hay conflicto, abrimos la modal de advertencia y le pasamos el nombre
                    abrirModalConflicto(citaId, data.paciente_nombre);
                } else {
                    // Si no hay conflicto, procedemos a confirmar la cita
                    window.location.href = `confirmar_cita.php?cita_id=${citaId}`;
                }
            })
            .catch(error => console.error('Error al verificar conflicto:', error));
    }



    // --- MODAL DETALLES DE HISTORIAL (PSICÓLOGO) ---
    const modalHistorialDetalle = document.getElementById('modal-historial-detalle');
    function abrirModalHistorialDetalle(citaId) {
        if (modalHistorialDetalle) {
            const modalBody = document.getElementById('historial-detalle-body');
            const pacienteNombreDisplay = document.getElementById('historial-detalle-paciente-nombre');
            
            pacienteNombreDisplay.textContent = 'Cargando...';
            modalBody.innerHTML = '<p>Cargando detalles...</p>';
            modalHistorialDetalle.style.display = 'flex';

            fetch(`get_cita_details_psicologo.php?cita_id=${citaId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const cita = result.data;
                        pacienteNombreDisplay.textContent = cita.paciente_nombre;

                        const fechaFormateada = new Date(cita.fecha_cita).toLocaleString('es-VE', { dateStyle: 'long', timeStyle: 'short' });
                        let estado_texto = cita.estado.replace('_', ' ');

                        let contenidoHTML = `
                            <div class="detalle-grid">
                                <div class="detalle-item"><strong>Paciente:</strong><span>${cita.paciente_nombre}</span></div>
                                <div class="detalle-item"><strong>Cédula:</strong><span>${cita.paciente_cedula}</span></div>
                                <div class="detalle-item"><strong>Fecha y Hora:</strong><span>${fechaFormateada}</span></div>
                                <div class="detalle-item"><strong>Estado Actual:</strong><span><span class="status-badge status-${cita.estado}">${estado_texto}</span></span></div>
                            </div>
                            <div class="detalle-item-full"><strong>Motivo de Consulta:</strong><p>${cita.motivo_consulta}</p></div>
                        `;
                        
                        const fechaCita = new Date(cita.fecha_cita);
                        const ahora = new Date();

                        if (fechaCita < ahora && (cita.estado === 'confirmada' || cita.estado === 'reprogramada')) {
                            contenidoHTML += `
                                <div class="modal-actions" style="justify-content: center; gap: 10px; flex-wrap: wrap;">
                                    <button class="btn-submit" onclick="marcarCitaComoCompletada(${cita.id})">
                                        <i class="fa-solid fa-check"></i> Marcar como Completada
                                    </button>
                                    <button class="btn-secondary" onclick="marcarCitaComoNoAsistio(${cita.id})">
                                        <i class="fa-solid fa-user-clock"></i> No asistió
                                    </button>
                                </div>
                            `;
                        }
                        
                        modalBody.innerHTML = contenidoHTML;
                    } else {
                        modalBody.innerHTML = `<p style="color:red;">${result.message}</p>`;
                    }
                });
        }
    }

    function cerrarModalHistorialDetalle() {
        if (modalHistorialDetalle) {
            modalHistorialDetalle.style.display = 'none';
        }
    }

    function marcarCitaComoCompletada(citaId) {
        if (confirm('¿Estás seguro de que quieres marcar esta cita como completada?')) {
            fetch('marcar_completada.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window.ECO_PANEL.csrf },
                    body: 'cita_id=' + encodeURIComponent(citaId)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        cerrarModalHistorialDetalle();
                        const buscador = document.getElementById('buscador-historial-citas');
                        if (buscador) {
                            buscarHistorialPsicologo(buscador.value); // Recargar la tabla
                        }
                    } else {
                        alert('Error: No se pudo completar la cita.');
                    }
                });
        }
    }

    function marcarCitaComoNoAsistio(citaId) {
        if (confirm('¿Marcar esta cita como inasistencia (no-show)? El paciente no se presentó.')) {
            fetch('marcar_no_asistio.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window.ECO_PANEL.csrf },
                    body: 'cita_id=' + encodeURIComponent(citaId)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        cerrarModalHistorialDetalle();
                        const buscador = document.getElementById('buscador-historial-citas');
                        if (buscador) {
                            buscarHistorialPsicologo(buscador.value); // Recargar la tabla
                        }
                    } else {
                        alert('Error: ' + (result.message || 'No se pudo marcar la inasistencia.'));
                    }
                });
        }
    }



    // --- CÓDIGO QUE SE EJECUTA UNA SOLA VEZ CUANDO LA PÁGINA CARGA ---
    document.addEventListener('DOMContentLoaded', function() {

        // --- NUEVA LÓGICA PARA LA VALIDACIÓN DE CONTRASEÑA EN EL PERFIL ---
        const nuevaPassword = document.getElementById("nueva_contrasena");
        const confirmarNuevaPassword = document.getElementById("confirmar_nueva_contrasena");

        function validateNewPassword(){
          if(nuevaPassword.value !== confirmarNuevaPassword.value) {
            confirmarNuevaPassword.setCustomValidity("Las contraseñas no coinciden.");
          } else {
            confirmarNuevaPassword.setCustomValidity('');
          }
        }
        
        if (nuevaPassword && confirmarNuevaPassword) {
            nuevaPassword.onchange = validateNewPassword;
            confirmarNuevaPassword.onkeyup = validateNewPassword;
        }

        // --- LÓGICA DE BÚSQUEDA Y CARGA DE TABLAS DINÁMICAS ---
        const buscadorHistorial = document.getElementById('buscador-historial-citas');
        const tablaHistorialContainer = document.getElementById('tabla-historial-citas-container');
        
        window.buscarHistorialPsicologo = function(query) {
             if (!tablaHistorialContainer) return;
             fetch('buscar_historial_citas.php', {
                 method: 'POST',
                 headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                 body: 'query=' + encodeURIComponent(query)
             })
             .then(response => response.text())
             .then(data => { tablaHistorialContainer.innerHTML = data; });
        }
        
        if (buscadorHistorial) {
            buscarHistorialPsicologo(''); // Carga inicial
            buscadorHistorial.addEventListener('keyup', function() {
                buscarHistorialPsicologo(this.value);
            });
        }

        // --- LÓGICA PARA HACER CLICABLE LA TABLA DE HISTORIAL DE CITAS ---
        if (tablaHistorialContainer) {
            tablaHistorialContainer.addEventListener('click', function(event) {
                const row = event.target.closest('tr.clickable-row');
                if (row && row.dataset.citaId) {
                    const citaId = row.dataset.citaId;
                    abrirModalHistorialDetalle(citaId);
                }
            });
        }







        

        // --- NUEVA LÓGICA PARA HACER CLICABLE LAS TARJETAS DE PRÓXIMAS CITAS ---
        const proximasCitasContainer = document.querySelector('#vista-proximas-citas .appointments-list-premium');
        if (proximasCitasContainer) {
            proximasCitasContainer.addEventListener('click', function(event) {
                // Si el clic fue directamente en un botón de acción, no hacemos nada.
                if (event.target.closest('.actions-pro')) {
                    return;
                }

                // Si el clic fue en cualquier otra parte de la tarjeta, abrimos la modal de detalles.
                const card = event.target.closest('.appointment-card-pro');
                if (card && card.dataset.citaId) {
                    const citaId = card.dataset.citaId;
                    abrirModalSolicitudDetalle(citaId);
                }
            });
        }

        // --- LÓGICA PARA HACER CLICABLE LA TABLA DE SOLICITUDES ---
        const tablaSolicitudesContainer = document.getElementById('vista-citas');
        if (tablaSolicitudesContainer) {
            tablaSolicitudesContainer.addEventListener('click', function(event) {
                // Si el clic fue directamente en un botón de acción, no hacemos nada.
                if (event.target.closest('.action-links')) {
                    return;
                }

                // Si el clic fue en cualquier otra parte de la fila, abrimos la modal.
                const row = event.target.closest('tr');
                if (row && row.dataset.citaId) {
                    const citaId = row.dataset.citaId;
                    abrirModalSolicitudDetalle(citaId);
                }
            });
        }

        

        // --- NUEVA LÓGICA PARA EL CAMPO DE TELÉFONO COMPUESTO ---
        const phoneTypeSelect = document.getElementById('telefono_tipo_modal');
        const phoneCodeSelect = document.getElementById('telefono_codigo_modal');
        const phoneInput = document.getElementById('telefono_numero_modal');

        const mobileCodes = ['+58 412', '+58 414', '+58 416', '+58 424', '+58 426'];
        const landlineCodes = ['+58 212', '+58 241', '+58 243', '+58 251', '+58 261']; // Caracas, Valencia, Maracay, Bqto, etc.

        function updatePhoneCodes() {
            const selectedType = phoneTypeSelect.value;
            phoneCodeSelect.innerHTML = ''; // Limpiar opciones anteriores

            const codes = (selectedType === 'fijo') ? landlineCodes : mobileCodes;
            
            // Cambiamos el maxlength para el número
            phoneInput.maxLength = 7; // Tanto fijos como móviles en Vzla tienen 7 dígitos

            codes.forEach(code => {
                const option = document.createElement('option');
                option.value = code;
                option.textContent = code;
                phoneCodeSelect.appendChild(option);
            });
        }

        if (phoneTypeSelect) {
            phoneTypeSelect.addEventListener('change', updatePhoneCodes);
            // Carga inicial de los códigos
            updatePhoneCodes();
        }
        

        // --- LÓGICA PARA EL ACORDEÓN DE PREGUNTAS FRECUENTES ---
        const faqQuestions = document.querySelectorAll('.faq-question');
        faqQuestions.forEach(button => {
            button.addEventListener('click', () => {
                const answer = button.nextElementSibling;
                button.classList.toggle('active');

                if (button.classList.contains('active')) {
                    answer.style.maxHeight = answer.scrollHeight + "px";
                    answer.style.paddingTop = "20px";
                } else {
                    answer.style.maxHeight = 0;
                    answer.style.paddingTop = "0";
                }
            });
        });

        // --- LÓGICA DEL GRÁFICO DE FRECUENCIA DE CITAS (PACIENTE) ---
        const patientChartCanvas = document.getElementById('patientCitasChart');
        if (patientChartCanvas) {
            fetch('get_patient_chart_data.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(patientChartCanvas, {
                        type: 'line', // Tipo de gráfico: línea
                        data: {
                            labels: chartData.labels, // ['Mar', 'Abr', ...]
                            datasets: [{
                                // --- LÍNEA CORREGIDA ---
                                label: 'Citas Confirmadas',
                                data: chartData.data, // [2, 4, 3, ...]
                                fill: true,
                                backgroundColor: 'rgba(2, 177, 244, 0.1)',
                                borderColor: '#02b1f4',
                                tension: 0.4, // Hace la línea curva
                                pointBackgroundColor: '#02b1f4',
                                pointRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1 // Asegura que el eje Y vaya de 1 en 1
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false // Ocultamos la leyenda para un look más limpio
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico del paciente:', error));
        }


        // --- LÓGICA PARA EL FORMULARIO MODAL DE ASIGNAR CITA ---
        const formAsignarCita = document.getElementById('form-asignar-cita');
        if(formAsignarCita) {
            flatpickr("#calendario-asignar", {
                enableTime: true, dateFormat: "Y-m-d H:i", altInput: true,
                altFormat: "d/m/Y h:i K", locale: "es", minuteIncrement: 15,
            });

            formAsignarCita.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_cita.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalAsignarCita();
                        alert('¡Cita programada con éxito!');
                        window.location.reload(); // Recargamos para actualizar la lista
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo programar la cita.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Confirmar Cita';
                    submitButton.disabled = false;
                });
            });
        }

    // --- NOTAS RÁPIDAS PARA ADMINISTRACIÓN Y SECRETARÍA ---
        const quickNotesForm = document.getElementById('nota-rapida-form');
        const quickNotesTextarea = document.getElementById('nota-rapida-texto');
        const quickNotesList = document.getElementById('lista-notas-rapidas');
        const quickNotesEmptyState = document.getElementById('estado-notas-vacio');
        const quickNotesEmptyTitle = document.getElementById('quick-notes-empty-title');
        const quickNotesEmptyMessage = document.getElementById('quick-notes-empty-message');
        const quickNotesTotalIndicator = document.getElementById('quick-notes-total');
        const quickNotesPendingIndicator = document.getElementById('quick-notes-pending');
        const quickNotesCompletedIndicator = document.getElementById('quick-notes-completed');
        const quickNotesFilterButtons = document.querySelectorAll('.quick-notes-tab');
    const QUICK_NOTES_KEY = 'quickNotes_' + window.ECO_PANEL.usuarioId;
    const LEGACY_QUICK_NOTES_KEY = 'secretariaQuickNotes';
        let quickNotesData = [];
        let quickNotesFilter = 'all';

        const saveQuickNotes = () => {
            try {
                localStorage.setItem(QUICK_NOTES_KEY, JSON.stringify(quickNotesData));
            } catch (error) {
                console.error('No se pudo guardar las notas rápidas:', error);
            }
        };

        const updateQuickNotesStats = () => {
            if (!quickNotesTotalIndicator) return;
            const total = quickNotesData.length;
            const completed = quickNotesData.filter(note => note.completed).length;
            const pending = total - completed;
            quickNotesTotalIndicator.textContent = total;
            if (quickNotesPendingIndicator) quickNotesPendingIndicator.textContent = pending;
            if (quickNotesCompletedIndicator) quickNotesCompletedIndicator.textContent = completed;
        };

        const getSortedNotes = () => {
            return [...quickNotesData].sort((a, b) => {
                if (a.completed === b.completed) {
                    return b.createdAt - a.createdAt;
                }
                return a.completed ? 1 : -1;
            });
        };

        const getFilteredNotes = () => {
            const sortedNotes = getSortedNotes();
            return sortedNotes.filter(note => {
                if (quickNotesFilter === 'completed') return note.completed;
                if (quickNotesFilter === 'pending') return !note.completed;
                return true; // 'all'
            });
        };

        const renderQuickNotes = () => {
            if (!quickNotesList) return;

            quickNotesList.innerHTML = '';
            updateQuickNotesStats();

            const filteredNotes = getFilteredNotes();

            if (!filteredNotes.length) {
                if (quickNotesEmptyState) {
                    quickNotesEmptyState.style.display = 'flex';
                    if (quickNotesEmptyTitle) {
                        quickNotesEmptyTitle.textContent = quickNotesFilter === 'completed'
                            ? 'Sin notas completadas'
                            : quickNotesFilter === 'pending'
                            ? 'Todo al día'
                            : 'No tienes notas todavía';
                    }
                    if (quickNotesEmptyMessage) {
                        quickNotesEmptyMessage.textContent = quickNotesFilter === 'completed'
                            ? 'Marca alguna nota como completada para verla aquí.'
                            : quickNotesFilter === 'pending'
                            ? 'Cuando agregues un recordatorio pendiente aparecerá en este listado.'
                            : 'Agrega un recordatorio y aparecerá aquí.';
                    }
                }
                return;
            }

            if (quickNotesEmptyState) {
                quickNotesEmptyState.style.display = 'none';
            }

            filteredNotes.forEach(note => {
                const listItem = document.createElement('li');
                listItem.className = 'quick-note-item' + (note.completed ? ' is-completed' : '');
                listItem.dataset.id = String(note.id);

                const mainRow = document.createElement('div');
                mainRow.className = 'quick-note-main';

                const label = document.createElement('label');
                label.className = 'quick-note-toggle';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'quick-note-toggle-input';
                checkbox.dataset.action = 'toggle';
                checkbox.checked = Boolean(note.completed);

                const textSpan = document.createElement('span');
                textSpan.className = 'quick-note-text';
                textSpan.textContent = note.text;

                label.appendChild(checkbox);
                label.appendChild(textSpan);
                mainRow.appendChild(label);

                const metaRow = document.createElement('div');
                metaRow.className = 'quick-note-meta';

                const statusBadge = document.createElement('span');
                statusBadge.className = 'quick-note-badge';
                statusBadge.textContent = note.completed ? 'Completada' : 'Pendiente';

                const timestamp = document.createElement('span');
                timestamp.className = 'quick-note-timestamp';
                const fecha = new Date(note.createdAt);
                timestamp.innerHTML = `<i class="fa-solid fa-clock"></i> ${fecha.toLocaleString('es-VE', { dateStyle: 'medium', timeStyle: 'short' })}`;

                const actionsRow = document.createElement('div');
                actionsRow.className = 'quick-note-actions-row';

                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'quick-note-delete';
                deleteButton.dataset.action = 'delete';
                deleteButton.innerHTML = '<i class="fa-solid fa-trash-can"></i> Eliminar';

                actionsRow.appendChild(deleteButton);

                metaRow.appendChild(statusBadge);
                metaRow.appendChild(timestamp);
                metaRow.appendChild(actionsRow);

                listItem.appendChild(mainRow);
                listItem.appendChild(metaRow);

                quickNotesList.appendChild(listItem);
            });
        };

        const loadQuickNotes = () => {
            try {
                const stored = localStorage.getItem(QUICK_NOTES_KEY);
                if (stored) {
                    quickNotesData = JSON.parse(stored) || [];
                } else {
                    const legacyStored = localStorage.getItem(LEGACY_QUICK_NOTES_KEY);
                    if (legacyStored) {
                        quickNotesData = JSON.parse(legacyStored) || [];
                        saveQuickNotes();
                        localStorage.removeItem(LEGACY_QUICK_NOTES_KEY);
                    } else {
                        quickNotesData = [];
                    }
                }
            } catch (error) {
                console.warn('No se pudieron recuperar las notas rápidas guardadas:', error);
                quickNotesData = [];
            }
            renderQuickNotes();
        };

        if (quickNotesForm && quickNotesTextarea && quickNotesList) {
            loadQuickNotes();

            if (quickNotesFilterButtons.length) {
                quickNotesFilterButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        const targetFilter = button.dataset.quickNotesFilter || 'all';
                        if (quickNotesFilter === targetFilter) return;
                        quickNotesFilter = targetFilter;
                        quickNotesFilterButtons.forEach(btn => btn.classList.toggle('is-active', btn === button));
                        renderQuickNotes();
                    });
                });
            }

            quickNotesForm.addEventListener('submit', event => {
                event.preventDefault();
                const noteText = quickNotesTextarea.value.trim();
                if (!noteText) {
                    quickNotesTextarea.focus();
                    return;
                }

                const timestamp = Date.now();
                quickNotesData.push({
                    id: timestamp,
                    text: noteText,
                    createdAt: timestamp,
                    completed: false
                });

                saveQuickNotes();
                renderQuickNotes();
                quickNotesForm.reset();
                quickNotesTextarea.focus();
            });

            quickNotesList.addEventListener('change', event => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) return;
                if (target.dataset.action !== 'toggle') return;

                const noteId = Number(target.closest('li')?.dataset.id);
                if (!noteId) return;

                quickNotesData = quickNotesData.map(note => note.id === noteId ? { ...note, completed: target.checked } : note);
                saveQuickNotes();
                renderQuickNotes();
            });

            quickNotesList.addEventListener('click', event => {
                const button = event.target instanceof HTMLElement ? event.target.closest('button') : null;
                if (!button || button.dataset.action !== 'delete') return;

                const noteId = Number(button.closest('li')?.dataset.id);
                if (!noteId) return;

                quickNotesData = quickNotesData.filter(note => note.id !== noteId);
                saveQuickNotes();
                renderQuickNotes();
            });
        }

        // --- LÓGICA DEL BUSCADOR DE HISTORIAL DE CITAS (SECRETARIA) ---
        const buscadorHistorialSecretaria = document.getElementById('buscador-historial-secretaria');
        const contenedorTablaHistorialSecretaria = document.getElementById('tabla-historial-secretaria-container');

        function buscarHistorialGeneral(query) {
            if (!contenedorTablaHistorialSecretaria) return;
            fetch('buscar_citas_secretaria.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'query=' + encodeURIComponent(query)
            })
            .then(response => response.text())
            .then(data => {
                contenedorTablaHistorialSecretaria.innerHTML = data;
            })
            .catch(error => console.error('Error en la búsqueda del historial:', error));
        }

        if (buscadorHistorialSecretaria) {
            // Carga inicial de la tabla
            buscarHistorialGeneral('');
            
            // Búsqueda en tiempo real al escribir
            buscadorHistorialSecretaria.addEventListener('keyup', function() {
                buscarHistorialGeneral(this.value);
            });
        }


                // --- VALIDACIÓN DE CAMPOS EN TIEMPO REAL PARA MODAL DE HISTORIA INFANTIL ---
        const formHistoriaInfantil = document.getElementById('form-crear-historia-infantil');
        if (formHistoriaInfantil) {
            formHistoriaInfantil.addEventListener('keydown', function(event) {
                const target = event.target;
                
                // Permitir teclas de control como Backspace, Tab, Flechas, etc.
                if (event.key.length > 1) {
                    return;
                }

                // Validación para campos que solo aceptan números
                if (target.classList.contains('validate-numeric')) {
                    if (!/^[0-9]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }

                // Validación para campos que solo aceptan texto (letras y espacios)
                if (target.classList.contains('validate-text-only')) {
                    if (!/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }
            });
        }

        // --- VALIDACIÓN DE CAMPOS EN TIEMPO REAL PARA MODAL DE HISTORIA DE ADULTO ---
        const formHistoriaAdulto = document.getElementById('form-crear-historia');
        if (formHistoriaAdulto) {
            formHistoriaAdulto.addEventListener('keydown', function(event) {
                const target = event.target;
                
                // Permitir teclas de control como Backspace, Tab, Flechas, etc.
                if (event.key.length > 1) {
                    return;
                }

                // Validación para campos que solo aceptan números
                if (target.classList.contains('validate-numeric')) {
                    if (!/^[0-9]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }

                // Validación para campos que solo aceptan texto (letras y espacios)
                if (target.classList.contains('validate-text-only')) {
                    if (!/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }
            });
        }


        // --- NUEVA LÓGICA PARA LOS BOTONES DE SELECCIÓN DE HISTORIA ---
        // Ambos botones (Adulto e Infantil) ahora llevan a la modal de ecografías
        const btnSeleccionarAdulto   = document.getElementById('btn-seleccionar-adulto');
        const btnSeleccionarInfantil = document.getElementById('btn-seleccionar-infantil');

        // Botón "Historia de Adulto" → valida edad ≥ 18, luego abre Modal 2 (ecografías)
        if (btnSeleccionarAdulto) {
            btnSeleccionarAdulto.addEventListener('click', function () {
                const edad = parseInt(this.dataset.pacienteEdad, 10);
                if (edad < 18) {
                    alert('Error: Este paciente es menor de edad. Debes usar la tarjeta de Historia Infantil.');
                    return;
                }
                abrirModalSeleccionarEcografia(
                    this.dataset.pacienteId,
                    this.dataset.pacienteCedula,
                    this.dataset.pacienteNombre,
                    this.dataset.pacienteEdad
                );
            });
        }

        // Botón "Historia Infantil" → valida edad < 18, luego abre Modal 2 (ecografías)
        if (btnSeleccionarInfantil) {
            btnSeleccionarInfantil.addEventListener('click', function () {
                const edad = parseInt(this.dataset.pacienteEdad, 10);
                if (edad >= 18) {
                    alert('Error: Este paciente es mayor de edad. Debes usar la tarjeta de Historia de Adulto.');
                    return;
                }
                abrirModalSeleccionarEcografia(
                    this.dataset.pacienteId,
                    this.dataset.pacienteCedula,
                    this.dataset.pacienteNombre,
                    this.dataset.pacienteEdad
                );
            });
        }

// --- LÓGICA PARA EL FORMULARIO MODAL DE CREAR HISTORIA (ADULTO) ---
const formCrearHistoria = document.getElementById('form-crear-historia');
if (formCrearHistoria) {
    formCrearHistoria.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        
        // 1. CAPTURAMOS EL ID DEL PACIENTE AQUÍ (igual que en el infantil)
        const pacienteId = formData.get('paciente_id'); 
        
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.textContent = 'Guardando...';
        submitButton.disabled = true;

        // Verificar que paciente_id tiene valor
        if (!pacienteId) {
            alert('Error: No se encontró el ID del paciente.');
            // Reactivar botón si hay error
            submitButton.textContent = 'Guardar Historia';
            submitButton.disabled = false;
            return; 
        }

        console.log('Enviando paciente_id (Adulto):', pacienteId);

        fetch('#', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            console.log(data);
            if (data.success) {
                // 2. LLAMAMOS A LAS MISMAS FUNCIONES QUE EN EL INFANTIL

                // Asumo que tienes una función para cerrar el modal de adulto.
                // Si no, puedes usar: $('#tu-modal-de-adulto').modal('hide');
                cerrarModalCrearHistoria(); // O el nombre que tenga tu función para cerrar este modal.

                alert('¡Historia clínica de adulto guardada con éxito!');
                
                // Usamos la misma función para abrir el modal de gestión, pasándole el ID.
                abrirModalGestionarPaciente(pacienteId);

            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => console.error('Error en fetch:', err))
        .finally(() => {
            submitButton.textContent = 'Guardar Historia';
            submitButton.disabled = false;
        });
    });
}


        // --- LÓGICA PARA LOS FORMULARIO INFANTIL ---
        const formCrearHistoriaInfantil = document.getElementById('form-crear-historia-infantil');
        if (formCrearHistoriaInfantil) {
            formCrearHistoriaInfantil.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const pacienteId = formData.get('paciente_id');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('#', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalCrearHistoriaInfantil();
                        alert('¡Historia clínica infantil guardada con éxito!');
                        abrirModalGestionarPaciente(pacienteId);
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo guardar la historia.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Historia Infantil';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA PARA AÑADIR HERMANOS EN LA MODAL ---
        const addHermanoBtnModal = document.getElementById('add-hermano-btn-modal');
        const hermanosContainerModal = document.getElementById('hermanos-container-modal');
        if (addHermanoBtnModal) {
            addHermanoBtnModal.addEventListener('click', function() {
                const hermanoDiv = document.createElement('div');
                hermanoDiv.className = 'hermano-entry form-grid';
                hermanoDiv.style.cssText = 'margin-bottom: 15px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; position: relative;';
                hermanoDiv.innerHTML = `
                    <div class="form-group"><label>Nombre:</label><input type="text" name="hermano_nombre[]"></div>
                    <div class="form-group"><label>Edad:</label><input type="number" name="hermano_edad[]"></div>
                    <div class="form-group"><label>Sexo:</label><input type="text" name="hermano_sexo[]"></div>
                    <div class="form-group"><label>Ocupación:</label><input type="text" name="hermano_ocupacion[]"></div>
                    <div class="form-group">
    <label>¿Vive en casa?:</label>
    <select name="hermano_vive_hogar[]" required>
        <option value="Sí">Sí</option>
        <option value="No">No</option>
    </select>
</div>
                    <button type="button" class="remove-hermano-btn" onclick="this.closest('.hermano-entry').remove()"><i class="fa-solid fa-trash-can"></i></button>
                `;
                hermanosContainerModal.appendChild(hermanoDiv);
            });
        }


        // --- LÓGICA PARA LA MODAL DE CREAR PACIENTE ---
        const btnAbrirModal = document.getElementById('btn-abrir-modal-paciente');
        const btnCerrarModal = document.querySelector('#modal-crear-paciente .modal-close');
        const formCrearPaciente = document.getElementById('form-crear-paciente');
        const modalErrorDiv = document.getElementById('modal-paciente-error');

        if (btnAbrirModal) {
            btnAbrirModal.addEventListener('click', abrirModalCrearPaciente);
        }
        if (btnCerrarModal) {
            btnCerrarModal.addEventListener('click', cerrarModalCrearPaciente);
        }
        if (formCrearPaciente) {
            // Inicializar Flatpickr para el campo de fecha de nacimiento
            flatpickr("#fecha_nacimiento_modal", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d/m/Y",
                locale: "es",
                maxDate: "today",

                // --- LÓGICA AÑADIDA PARA CENTRAR EL CALENDARIO ---
                position: function(self, dom) {
                    // Hacemos que el calendario sea 'fixed' para posicionarlo en la pantalla
                    self.calendarContainer.style.position = 'fixed';
                    
                    // Calculamos el centro de la pantalla
                    const topPosition = (window.innerHeight - self.calendarContainer.offsetHeight) / 2;
                    const leftPosition = (window.innerWidth - self.calendarContainer.offsetWidth) / 2;
                    
                    // Asignamos las posiciones
                    self.calendarContainer.style.top = `${topPosition}px`;
                    self.calendarContainer.style.left = `${leftPosition}px`;
                }
                // --- FIN DE LA LÓGICA AÑADIDA ---
            });
            formCrearPaciente.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;
                if(modalErrorDiv) modalErrorDiv.style.display = 'none';

                fetch('guardar_paciente.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalCrearPaciente();
                        formCrearPaciente.reset(); // Limpiar el formulario
                        // Llamamos a la nueva función para abrir la modal de éxito
                        abrirModalExitoPaciente(data.nombre, data.password);
                        if (window.buscarMisPacientes) {
                            window.buscarMisPacientes(''); // Refrescar la lista de pacientes
                        }
                        if (typeof buscarPacientesSecretaria === 'function') {
                            buscarPacientesSecretaria('');
                        }
                    } else {
                        if(modalErrorDiv) {
                            modalErrorDiv.textContent = data.message;
                            modalErrorDiv.style.display = 'block';
                        }
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Crear Paciente';
                    submitButton.disabled = false;
                });
            });
        }

        // Lógica para el botón de cierre de la modal infantil
        const btnCerrarModalInfantil = document.querySelector('#modal-crear-historia-infantil .modal-close-btn');
        if (btnCerrarModalInfantil) {
            btnCerrarModalInfantil.addEventListener('click', cerrarModalCrearHistoriaInfantil);
        }

        // --- LÓGICA PARA EL FORMULARIO MODAL DE CREAR INFORME (UNIFICADA) ---
        const formCrearInforme = document.getElementById('form-crear-informe');
        if (formCrearInforme) {

            // 1. Lógica para el envío del formulario (AJAX)
            formCrearInforme.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const pacienteId = formData.get('paciente_id');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_informe_estudio.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalCrearInforme();
                        alert('¡Informe guardado con éxito!');
                        abrirModalGestionarPaciente(pacienteId);
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo guardar el informe.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Informe';
                    submitButton.disabled = false;
                });
            });

            // 2. Lógica para la validación de campos en tiempo real
            formCrearInforme.addEventListener('keydown', function(event) {
                const target = event.target;
                
                // Permitir teclas de control
                if (event.key.length > 1) {
                    return;
                }

                // Validación para campos numéricos
                if (target.classList.contains('validate-numeric')) {
                    if (!/^[0-9]$/.test(event.key)) {
                        event.preventDefault();
                    }
                }
            });
        }

        
        // Cerrar modales al hacer clic fuera
        // NOTA: las modales del flujo de creación de ecografías
        // (seleccionar tipo, sub-modales, formulario, crear informe) NO se cierran
        // con click fuera ni ESC — sólo con la X, para evitar pérdida accidental de datos.
        // El clic fuera de las modales NO las cierra: el usuario debe pulsar la X
        // para evitar la pérdida accidental de datos.

        // --- BUSCADOR DE ESPECIALIDADES (ADMINISTRADOR) ---
        const specialtySearchInput = document.getElementById('specialty-search-input');
        if (specialtySearchInput) {
            specialtySearchInput.addEventListener('input', function() {
                const termino = this.value.trim().toLowerCase();
                document.querySelectorAll('.specialty-row').forEach(function(row) {
                    const contenido = (row.getAttribute('data-search') || '').toLowerCase();
                    row.style.display = termino === '' || contenido.includes(termino) ? '' : 'none';
                });
            });
        }

        const documentSearchInput = document.getElementById('document-search-input');
        if (documentSearchInput) {
            documentSearchInput.addEventListener('input', function() {
                const termino = this.value.trim().toLowerCase();
                document.querySelectorAll('.document-row').forEach(function(row) {
                    const contenido = (row.getAttribute('data-search') || '').toLowerCase();
                    row.style.display = termino === '' || contenido.includes(termino) ? '' : 'none';
                });
            });
        }

        document.addEventListener('click', function(event) {
            const copyButton = event.target.closest('.document-copy-link');
            if (!copyButton) return;

            event.preventDefault();
            const urlRelativa = copyButton.getAttribute('data-url');
            if (!urlRelativa) return;

            const basePath = window.location.origin + window.location.pathname.replace(/[^\\\/]*$/, '');

        const adminTaskForm = document.getElementById('admin-task-form');
        if (adminTaskForm) {
            const adminTaskInput = document.getElementById('admin-task-input');
            const adminTaskList = document.getElementById('admin-task-list');
            const adminTaskEmpty = document.getElementById('admin-task-empty');
            const adminTaskCounter = document.getElementById('admin-task-counter');
            const adminTaskClear = document.getElementById('admin-task-clear');
            const adminTaskFilterButtons = document.querySelectorAll('.admin-task-filters button');
            const adminTaskStorageKey = 'adminTasks_' + window.ECO_PANEL.usuarioId;
            let adminTaskFilterState = 'all';
            let adminTasks = [];

            try {
                const storedTasks = localStorage.getItem(adminTaskStorageKey);
                if (storedTasks) {
                    adminTasks = JSON.parse(storedTasks) || [];
                }
            } catch (error) {
                console.error('No se pudieron cargar las tareas del administrador:', error);
            }

            const guardarTareas = () => {
                try {
                    localStorage.setItem(adminTaskStorageKey, JSON.stringify(adminTasks));
                } catch (error) {
                    console.error('No se pudieron guardar las tareas del administrador:', error);
                }
            };

            const actualizarFiltrosActivos = () => {
                adminTaskFilterButtons.forEach((boton) => {
                    boton.classList.toggle('active', boton.dataset.filter === adminTaskFilterState);
                });
            };

            const renderizarTareas = () => {
                const totalPendientes = adminTasks.filter((tarea) => !tarea.completada).length;
                const totalCompletadas = adminTasks.filter((tarea) => tarea.completada).length;
                const totalTareas = adminTasks.length;

                adminTaskCounter.textContent = `${totalPendientes} pendientes · ${totalCompletadas} completadas`;

                let tareasFiltradas = adminTasks;
                if (adminTaskFilterState === 'pending') {
                    tareasFiltradas = adminTasks.filter((tarea) => !tarea.completada);
                } else if (adminTaskFilterState === 'done') {
                    tareasFiltradas = adminTasks.filter((tarea) => tarea.completada);
                }

                adminTaskList.innerHTML = '';

                if (tareasFiltradas.length === 0) {
                    adminTaskEmpty.style.display = totalTareas === 0 ? 'block' : 'none';
                } else {
                    adminTaskEmpty.style.display = 'none';
                    tareasFiltradas.forEach((tarea) => {
                        const listItem = document.createElement('li');
                        listItem.className = 'admin-task-item' + (tarea.completada ? ' completed' : '');
                        listItem.dataset.id = tarea.id;

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.checked = tarea.completada;
                        checkbox.setAttribute('aria-label', 'Cambiar estado de la tarea');

                        const texto = document.createElement('span');
                        texto.className = 'admin-task-text';
                        texto.textContent = tarea.descripcion;

                        const acciones = document.createElement('div');
                        acciones.className = 'admin-task-actions';

                        const eliminarBtn = document.createElement('button');
                        eliminarBtn.type = 'button';
                        eliminarBtn.className = 'delete-task';
                        eliminarBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Quitar';

                        acciones.appendChild(eliminarBtn);
                        listItem.appendChild(checkbox);
                        listItem.appendChild(texto);
                        listItem.appendChild(acciones);
                        adminTaskList.appendChild(listItem);
                    });
                }
            };

            renderizarTareas();

            adminTaskForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const descripcion = adminTaskInput.value.trim();
                if (descripcion === '') {
                    adminTaskInput.focus();
                    return;
                }

                const nuevaTarea = {
                    id: Date.now().toString(),
                    descripcion,
                    completada: false,
                    creada: new Date().toISOString()
                };

                adminTasks.unshift(nuevaTarea);
                guardarTareas();
                adminTaskInput.value = '';
                adminTaskInput.focus();
                renderizarTareas();
            });

            adminTaskList.addEventListener('change', function(event) {
                if (event.target.type === 'checkbox') {
                    const tareaId = event.target.closest('.admin-task-item')?.dataset.id;
                    if (!tareaId) return;

                    adminTasks = adminTasks.map((tarea) => tarea.id === tareaId ? { ...tarea, completada: event.target.checked } : tarea);
                    guardarTareas();
                    renderizarTareas();
                }
            });

            adminTaskList.addEventListener('click', function(event) {
                if (event.target.closest('.delete-task')) {
                    const listItem = event.target.closest('.admin-task-item');
                    if (!listItem) return;
                    const tareaId = listItem.dataset.id;
                    adminTasks = adminTasks.filter((tarea) => tarea.id !== tareaId);
                    guardarTareas();
                    renderizarTareas();
                }
            });

            adminTaskClear.addEventListener('click', function() {
                if (adminTasks.some((tarea) => tarea.completada) && confirm('¿Eliminar todas las tareas completadas?')) {
                    adminTasks = adminTasks.filter((tarea) => !tarea.completada);
                    guardarTareas();
                    renderizarTareas();
                }
            });

            adminTaskFilterButtons.forEach((boton) => {
                boton.addEventListener('click', function() {
                    adminTaskFilterState = this.dataset.filter || 'all';
                    actualizarFiltrosActivos();
                    renderizarTareas();
                });
            });

            actualizarFiltrosActivos();
        }

            const urlCompleta = new URL(urlRelativa, basePath).href;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(urlCompleta)
                    .then(() => {
                        const contenidoOriginal = copyButton.innerHTML;
                        copyButton.innerHTML = '<i class="fa-solid fa-check"></i> Copiado';
                        setTimeout(() => {
                            copyButton.innerHTML = contenidoOriginal;
                        }, 2000);
                    })
                    .catch(() => {
                        alert('No se pudo copiar el enlace. Copia manualmente: ' + urlCompleta);
                    });
            } else {
                alert('Copia manualmente este enlace: ' + urlCompleta);
            }
        });

        // --- LÓGICA PARA EL FORMULARIO MODAL DE GUARDAR NOTA ---
        const formGuardarNota = document.getElementById('form-guardar-nota');
        if (formGuardarNota) {
            formGuardarNota.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const pacienteId = formData.get('paciente_id');
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_nota.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        formGuardarNota.reset();
                        document.getElementById('fecha_sesion_modal').value = new Date().toISOString().slice(0, 16);
                        abrirModalGestionarNotas(pacienteId); // Recarga los datos de la modal
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo guardar la nota.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Nota';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA PARA EL BOTÓN DE LIMPIAR NOTAS (CORREGIDA) ---
        const btnLimpiarNotas = document.getElementById('btn-limpiar-notas');
        if (btnLimpiarNotas) {
            btnLimpiarNotas.addEventListener('click', function() {
                const pacienteId = document.getElementById('notas-paciente-id').value;
                if (pacienteId && confirm('¿Estás seguro de que quieres borrar TODAS las notas de este paciente? Esta acción es irreversible.')) {
                    const formData = new FormData();
                    formData.append('paciente_id', pacienteId);

                    fetch('limpiar_notas.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Si tuvo éxito, recargamos las notas para ver la lista vacía
                            abrirModalGestionarNotas(pacienteId);
                        } else {
                            alert('Error: ' + (data.message || 'No se pudieron eliminar las notas.'));
                        }
                    })
                    .catch(error => console.error('Error al limpiar notas:', error));
                }
            });
        }

                // --- LÓGICA PARA EL CALENDARIO FLATPICKR EN LA MODAL DE NOTAS ---
        const fechaSesionInput = document.getElementById('fecha_sesion_modal');
        if (fechaSesionInput) {
            flatpickr(fechaSesionInput, {
                enableTime: true,
                dateFormat: "Y-m-d H:i", // Formato para la base de datos
                altInput: true,
                altFormat: "d/m/Y h:i K", // Formato visible para el usuario
                locale: "es",
                defaultDate: new Date() // Pone la fecha y hora actual por defecto
            });
        }

        

        // --- LÓGICA PARA EL FORMULARIO MODAL DE PROGRAMAR CITA ---
        const formProgramarCita = document.getElementById('form-programar-cita');

        if(formProgramarCita) {
            flatpickr("#calendario-programar", {
                enableTime: true, dateFormat: "Y-m-d H:i", altInput: true,
                altFormat: "d/m/Y h:i K", locale: "es", minuteIncrement: 15,
            });

            formProgramarCita.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('guardar_cita_directa.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalProgramarCita();
                        alert('¡Cita programada con éxito!');
                        // Opcional: Refrescar la tabla de historial de citas si está visible
                        if(document.getElementById('vista-historial-citas') && document.getElementById('vista-historial-citas').classList.contains('active')){
                           // Aquí se podría recargar la tabla del historial si fuera necesario
                        }
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo programar la cita.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Cita';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA PARA EL FORMULARIO MODAL DE REPROGRAMAR CITA ---
        const formReprogramarCita = document.getElementById('form-reprogramar-cita');
        if(formReprogramarCita) {
            flatpickr("#calendario-reprogramar", {
                enableTime: true, dateFormat: "Y-m-d H:i", altInput: true,
                altFormat: "d/m/Y h:i K", locale: "es", minuteIncrement: 15,
            });

            formReprogramarCita.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Guardando...';
                submitButton.disabled = true;

                fetch('actualizar_cita.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalReprogramarCita();
                        alert('¡Cita reprogramada con éxito!');
                        // Para ver el cambio, recargamos la página. Es la forma más simple.
                        window.location.href = 'panel.php?vista=proximas-citas';
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo reprogramar la cita.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Guardar Cita';
                    submitButton.disabled = false;
                });
            });
        }

        // --- LÓGICA PARA EL FORMULARIO MODAL DE PROPONER FECHA ---
        const formProponerFecha = document.getElementById('form-proponer-fecha');
        if(formProponerFecha) {
            flatpickr("#calendario-proponer", {
                enableTime: true, dateFormat: "Y-m-d H:i", altInput: true,
                altFormat: "d/m/Y h:i K", locale: "es", minuteIncrement: 15,
            });

            formProponerFecha.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.textContent = 'Enviando...';
                submitButton.disabled = true;

                fetch('guardar_propuesta.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalProponerFecha();
                        alert('¡Propuesta enviada con éxito!');
                        window.location.href = 'panel.php?vista=citas'; // Recarga para actualizar la lista
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo enviar la propuesta.'));
                    }
                })
                .finally(() => {
                    submitButton.textContent = 'Enviar Propuesta';
                    submitButton.disabled = false;
                });
            });
        }

        
        // --- ACTIVAR ORDENAMIENTO EN LAS TABLAS ---
        makeTableSortable(document.getElementById('tabla-pacientes-container'));
        makeTableSortable(document.getElementById('tabla-historial-container'));
        makeTableSortable(tablaHistorialContainer); // Usamos la variable que ya declaramos
        makeTableSortable(document.getElementById('tabla-notas-container'));
        makeTableSortable(document.getElementById('tabla-historial-secretaria-container'));

        // --- LÓGICA PARA EL FORMULARIO DE CITAS DEL PACIENTE ---
        const especialidadSelector = document.getElementById('especialidad_selector');
        const psicologoSelector = document.getElementById('psicologo_selector');
        const datePickerGroup = document.getElementById('date-picker-group');
        const calendarInput = document.getElementById('calendario-paciente');
        const timeSlotsGroup = document.getElementById('time-slots-group');
        const timeSlotsContainer = document.getElementById('time-slots-container');
        const btnEnviarSolicitud = document.getElementById('btn-enviar-solicitud');
        const horaSeleccionadaInput = document.getElementById('hora_seleccionada_input');
        let fp;

        if (especialidadSelector) {
            especialidadSelector.addEventListener('change', function() {
                const rol = this.value;
                psicologoSelector.innerHTML = '<option value="">Cargando...</option>';
                psicologoSelector.disabled = true;
                datePickerGroup.style.display = 'none';
                timeSlotsGroup.style.display = 'none';
                if (fp) fp.destroy();

                if (rol) {
                    fetch(`get_professionals_by_specialty.php?rol=${rol}`)
                    .then(response => response.json())
                    .then(profesionales => {
                        psicologoSelector.innerHTML = '<option value="">Elige un profesional</option>';
                        if (profesionales.length > 0) {
                            profesionales.forEach(prof => {
                                psicologoSelector.innerHTML += `<option value="${prof.id}">${prof.nombre_completo}</option>`;
                            });
                            psicologoSelector.disabled = false;
                        } else {
                            psicologoSelector.innerHTML = '<option value="">-- No hay profesionales disponibles --</option>';
                        }
                    });
                }
            });
        }

        if (psicologoSelector) {
            psicologoSelector.addEventListener('change', function() {
                const psicologoId = this.value;
                datePickerGroup.style.display = 'none';
                timeSlotsGroup.style.display = 'none';
                if (fp) fp.destroy();

                if (psicologoId) {
                    fetch(`get_available_dates.php?ecografista_id=${psicologoId}`)
                    .then(response => response.json())
                    .then(availableDates => {
                        datePickerGroup.style.display = 'block';
                        fp = flatpickr(calendarInput, {
                            locale: "es",
                            dateFormat: "Y-m-d",
                            minDate: "today",
                            enable: availableDates, // Solo habilita las fechas que vienen del servidor
                            
                            // --- LÓGICA AÑADIDA PARA CENTRAR EL CALENDARIO ---
                            position: function(self, dom) {
                                // Hacemos que el calendario sea 'fixed' para posicionarlo en la pantalla
                                self.calendarContainer.style.position = 'fixed';
                                
                                // Calculamos el centro de la pantalla
                                const topPosition = (window.innerHeight - self.calendarContainer.offsetHeight) / 2;
                                const leftPosition = (window.innerWidth - self.calendarContainer.offsetWidth) / 2;
                                
                                // Asignamos las posiciones
                                self.calendarContainer.style.top = `${topPosition}px`;
                                self.calendarContainer.style.left = `${leftPosition}px`;
                            },
                            onChange: function(selectedDates, dateStr) {
                                timeSlotsContainer.innerHTML = 'Cargando...';
                                timeSlotsGroup.style.display = 'block';
                                fetch(`get_available_times.php?ecografista_id=${psicologoId}&fecha=${dateStr}`)
                                .then(res => res.json())
                                .then(times => {
                                    timeSlotsContainer.innerHTML = '';
                                    if (times.length > 0) {
                                        times.forEach(time => {
                                            const timeButton = document.createElement('button');
                                            timeButton.type = 'button';
                                            timeButton.className = 'time-slot-btn';
                                            timeButton.textContent = new Date(`1970-01-01T${time}`).toLocaleTimeString('es-VE', {hour: 'numeric', minute: '2-digit', hour12: true});
                                            timeButton.onclick = () => {
                                                document.querySelectorAll('.time-slot-btn').forEach(btn => btn.classList.remove('selected'));
                                                timeButton.classList.add('selected');
                                                horaSeleccionadaInput.value = time;
                                                btnEnviarSolicitud.disabled = false;
                                                btnEnviarSolicitud.textContent = 'Enviar Solicitud de Cita';
                                            };
                                            timeSlotsContainer.appendChild(timeButton);
                                        });
                                    } else {
                                        timeSlotsContainer.innerHTML = '<p>No hay horarios disponibles para este día.</p>';
                                    }
                                });
                            }
                        });
                    });
                }
            });
        }

        // LÓGICA DEL CALENDARIO DEL PSICÓLOGO (FullCalendar)
        const calendarEl = document.getElementById('calendario');
        if (calendarEl) {
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'es',
                headerToolbar: {
                    left: 'prev,next today manageAvailabilityButton', // <-- Botón movido aquí
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
                allDayText: 'Hora',
                height: 522,
                events: 'get_citas.php',

                // Botón personalizado para gestionar la disponibilidad
                customButtons: {
                    manageAvailabilityButton: {
                     text: 'Mi Disponibilidad', // Texto más corto para el botón
                        click: function() {
                          window.location.href = 'gestionar_disponibilidad.php';
                        }
                    }
                }, // <-- La coma aquí es importante si hay más opciones después

                // Formato de 12 horas
                slotLabelFormat: {
                    hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true
                },
                eventTimeFormat: {
                    hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true
                },
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    const pacienteId = info.event.extendedProps.paciente_id;
                    if (pacienteId) {
                        window.location.href = 'gestionar_paciente.php?paciente_id=' + pacienteId;
                    }
                },
                
            });
            
        }

        

        // LÓGICA DEL CALENDARIO GENERAL (SECRETARIA)
        const generalCalendarEl = document.getElementById('calendario-general');
        if (generalCalendarEl) {
            generalCalendar = new FullCalendar.Calendar(generalCalendarEl, {
                initialView: 'dayGridMonth', // <-- LÍNEA CORREGIDA
                locale: 'es',
                allDayText: 'Hora',
                height: 480,
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
                events: 'get_all_citas.php',
                slotLabelFormat: {
                    hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true
                },
                eventTimeFormat: {
                    hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true
                },
            });
        }

        
        
        // LÓGICA DEL GRÁFICO DE CITAS (Chart.js)
        const chartCanvas = document.getElementById('citasChart');
        if (chartCanvas) {
            fetch('get_chart_data.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(chartCanvas, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Citas Confirmadas',
                                data: chartData.data,
                                backgroundColor: 'rgba(2, 177, 244, 0.6)',
                                borderColor: 'rgba(2, 177, 244, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico:', error));
        }



        // ACTIVAR LA PRIMERA VISTA POR DEFECTO AL CARGAR LA PÁGINA
        const firstViewLink = document.querySelector('.sidebar-nav a.active');
        if (firstViewLink) {
            const vistaInicial = firstViewLink.getAttribute('onclick').match(/'([^']+)'/)[1];
            mostrarVista(vistaInicial, null);
        }
    });
        // --- LÓGICA MEJORADA PARA ABRIR LA PESTAÑA CORRECTA ---
    const urlParams = new URLSearchParams(window.location.search);
    const vistaDesdeUrl = urlParams.get('vista');

    if (vistaDesdeUrl) {
        // Si la URL dice qué vista mostrar (ej: ?vista=pacientes), la mostramos.
        mostrarVista(vistaDesdeUrl, null);
        
        // Limpiamos la URL para que no se quede "pegada" al recargar.
        history.replaceState(null, '', window.location.pathname);

    } else {
        // Si no, mostramos la que esté marcada como 'active' por defecto en el HTML.
        const firstViewLink = document.querySelector('.sidebar-nav a.active');
        if (firstViewLink) {
            const vistaInicial = firstViewLink.getAttribute('onclick').match(/'([^']+)'/)[1];
            mostrarVista(vistaInicial, null);
        }
    }

    // LÓGICA DEL GRÁFICO DE NUEVOS PACIENTES (Bar Chart)
const newPatientsCanvas = document.getElementById('newPatientsChart');
if (newPatientsCanvas) {
    fetch('get_weekly_patients_data.php')
        .then(response => response.json())
        .then(chartData => {
            new Chart(newPatientsCanvas, {
                type: 'bar', // Tipo de gráfico: barras
                data: {
                    labels: chartData.labels, // ['Lun 21', 'Mar 22', ...]
                    datasets: [{
                        label: 'Nuevos Pacientes',
                        data: chartData.data, // [1, 0, 2, ...]
                        backgroundColor: 'rgba(40, 167, 69, 0.6)', // Color verde
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1 // Asegura que el eje Y vaya de 1 en 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // Ocultamos la leyenda para un look más limpio
                        }
                    }
                }
            });
        })
        .catch(error => console.error('Error al cargar datos del gráfico de nuevos pacientes:', error));
}

        // LÓGICA DEL BUSCADOR DE PACIENTES (SECRETARIA)
        const buscadorSecretaria = document.getElementById('buscador-pacientes-secretaria');
        const contenedorTablaSecretaria = document.getElementById('tabla-pacientes-secretaria-container');

        function buscarPacientesSecretaria(query) {
            if (!contenedorTablaSecretaria) return;
            contenedorTablaSecretaria.innerHTML = '<p>Cargando pacientes...</p>';
            fetch('buscar_pacientes_secretaria.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'query=' + encodeURIComponent(query)
            })
            .then(response => response.text())
            .then(data => {
                contenedorTablaSecretaria.innerHTML = data;
                if (typeof makeTableSortable === 'function') {
                    makeTableSortable(contenedorTablaSecretaria);
                }
            })
            .catch(error => {
                console.error('Error en la búsqueda:', error);
                contenedorTablaSecretaria.innerHTML = '<p style="color: #dc3545;">No se pudo cargar la lista de pacientes.</p>';
            });
        }

        if (buscadorSecretaria) {
            buscarPacientesSecretaria('');
            buscadorSecretaria.addEventListener('keyup', function() {
                buscarPacientesSecretaria(this.value);
            });
        } else if (contenedorTablaSecretaria) {
            buscarPacientesSecretaria('');
        }

        // --- LÓGICA DEL GRÁFICO DE EDAD DE PACIENTES (DISEÑO PREMIUM) ---
        const patientAgeCanvas = document.getElementById('patientAgeChart');
        if (patientAgeCanvas) {
            fetch('get_patient_age_distribution.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(patientAgeCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Pacientes',
                                data: chartData.data,
                                // --- NUEVA PALETA DE COLORES AZULADOS ---
                                backgroundColor: [
                                '#02b1f4', // Azul Principal
                                '#17a2b8', // Turquesa
                                '#5bc0de', // Azul Claro
                                '#6c757d'  // Gris Frío
                                ],
                                borderColor: '#ffffff',
                                borderWidth: 4,
                                hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '65%',
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        font: { family: "'Poppins', sans-serif", size: 13 },
                                        boxWidth: 15,
                                        padding: 15
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#333',
                                    titleFont: { size: 14, weight: 'bold', family: "'Poppins', sans-serif" },
                                    bodyFont: { size: 13, family: "'Poppins', sans-serif" },
                                    padding: 10,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) { label += ': '; }
                                            if (context.parsed !== null) { label += context.parsed; }
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico de edad:', error));
        }

         // --- LÓGICA DEL GRÁFICO DE CARGA DE TRABAJO (DISEÑO PREMIUM) ---
        const workloadCanvas = document.getElementById('workloadChart');
        if (workloadCanvas) {
            fetch('get_workload_data.php')
                .then(response => response.json())
                .then(chartData => {
                    const ctx = workloadCanvas.getContext('2d');
                    const gradient = ctx.createLinearGradient(0, 0, workloadCanvas.width, 0);
                    gradient.addColorStop(0, '#00c2ff');
                    gradient.addColorStop(1, '#0361b3ff');

                    new Chart(workloadCanvas, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Citas Asignadas',
                                data: chartData.data,
                                backgroundColor: gradient,
                                borderRadius: 8, // Bordes redondeados
                                borderWidth: 0
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#333',
                                    titleFont: { size: 14, weight: 'bold', family: "'Poppins', sans-serif" },
                                    bodyFont: { size: 13, family: "'Poppins', sans-serif" },
                                    padding: 10,
                                    cornerRadius: 8
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false // Quita las líneas verticales
                                    },
                                    ticks: {
                                        stepSize: 1,
                                        font: { family: "'Poppins', sans-serif" }
                                    }
                                },
                                y: {
                                    grid: {
                                        color: '#f0f0f0' // Líneas horizontales más sutiles
                                    },
                                    ticks: {
                                        font: { family: "'Poppins', sans-serif" }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico de carga de trabajo:', error));
        }

        // LÓGICA DEL GRÁFICO DE CRECIMIENTO DE USUARIOS (Line Chart)
        const growthChartCanvas = document.getElementById('userGrowthChart');
        if (growthChartCanvas) {
            fetch('get_user_growth_data.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(growthChartCanvas, {
                        type: 'line', // Tipo de gráfico: línea
                        data: {
                            labels: chartData.labels, // ['Febrero', 'Marzo', ...]
                            datasets: [{
                                label: 'Nuevos Usuarios',
                                data: chartData.data, // [5, 8, 12, ...]
                                fill: true,
                                backgroundColor: 'rgba(2, 177, 244, 0.2)',
                                borderColor: 'rgba(2, 177, 244, 1)',
                                tension: 0.3 // Hace la línea un poco curva
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico de crecimiento:', error));
        }


        // --- LÓGICA CORREGIDA PARA LOS NUEVOS GRÁFICOS ---
        
        // 1. Gráfico de Tipos de Cita
        const appointmentTypesCanvas = document.getElementById('appointmentTypesChart');
        if (appointmentTypesCanvas) {
            fetch('get_appointment_types_data.php')
            .then(response => response.json())
            .then(chartData => {
                new Chart(appointmentTypesCanvas, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: chartData.datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } },
                        plugins: { legend: { position: 'top' } }
                    }
                });
            });
        }

        // --- LÓGICA PARA EL GRÁFICO DE CITAS DIARIAS (ADMINISTRADOR) ---
        const dailyAppointmentsCanvas = document.getElementById('dailyAppointmentsChart');
        if (dailyAppointmentsCanvas) {
            fetch('get_daily_appointments_data.php')
                .then(response => response.json())
                .then(chartData => {
                    new Chart(dailyAppointmentsCanvas, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Citas Atendidas',
                                data: chartData.data,
                                // --- COLORES CAMBIADOS ---
                                backgroundColor: '#02b1f4', // Azul principal
                                borderColor: '#028ac7',   // Azul más oscuro
                                borderWidth: 1,
                                borderRadius: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error al cargar datos del gráfico diario:', error));
        }

        // 3. Gráfico de Confirmadas vs. Reprogramadas
        const confirmedReprogrammedCanvas = document.getElementById('confirmedReprogrammedChart');
        if (confirmedReprogrammedCanvas) {
            fetch('get_confirmed_reprogrammed_data.php')
            .then(response => response.json())
            .then(chartData => {
                new Chart(confirmedReprogrammedCanvas, {
                    type: 'pie',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            data: chartData.data,
                            backgroundColor: ['#17a2b8', '#ffc107'],
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } }
                    }
                });
            });
        }


    // --- LÓGICA DEL BUSCADOR DE PACIENTES (PSICÓLOGO) ---
        const buscadorPacientes = document.getElementById('buscador-pacientes');
        const tablaPacientesContainer = document.getElementById('tabla-pacientes-container');
        
        let queryActualMisPacientes = '';
        function buscarMisPacientes(query, page = 1) {
            if (!tablaPacientesContainer) return;
            queryActualMisPacientes = query;
            fetch('buscar_pacientes.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'query=' + encodeURIComponent(query) + '&page=' + page
            })
            .then(response => response.text())
            .then(data => { tablaPacientesContainer.innerHTML = data; })
            .catch(error => console.error('Error en la búsqueda:', error));
        }

        if (buscadorPacientes) {
            // Carga inicial de la tabla
            buscarMisPacientes('');
            // Búsqueda en tiempo real al escribir (vuelve a la página 1)
            buscadorPacientes.addEventListener('keyup', function() {
                buscarMisPacientes(this.value, 1);
            });
            // Paginación: los botones del footer reenvían la búsqueda con el mismo término.
            tablaPacientesContainer.addEventListener('click', function(event) {
                const btn = event.target.closest('.eco-pager__btn');
                if (!btn || btn.disabled) return;
                const page = parseInt(btn.dataset.page, 10);
                if (page >= 1) buscarMisPacientes(queryActualMisPacientes, page);
            });
        }

           

        

        


