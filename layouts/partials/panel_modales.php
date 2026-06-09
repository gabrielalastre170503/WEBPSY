<?php
/**
 * Modales del panel admin/ecografista (extraidas de panel.php, Fase 5B+).
 * Se incluye dentro de panel.php: comparte scope ($tipos_panel, $tipos_musculo,
 * $tipos_obstetrica, $tipos_partes_blandas, $eco_colores, csrf, etc.).
 */
?>
    <!-- ================== MODAL PARA AÑADIR NUEVO PACIENTE (DISEÑO PREMIUM) ================== -->
<div id="modal-crear-paciente" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel">
            <div class="info-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h3>Nuevo Paciente</h3>
            <p>Rellena los datos para crear un nuevo perfil de paciente en el sistema.</p>
            <p class="info-footer">Se generará una contraseña temporal que deberás proporcionar al paciente para su primer acceso.</p>
        </div>

        <!-- Panel Derecho (Formulario) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalCrearPaciente()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Datos del Paciente</h4>
            <?php
                $profesionalesAsignables = [];
                if ($rol_usuario === 'recepcionista') {
                    if ($resultadoProfesionales = $conex->query("SELECT id, nombre_completo, rol FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC")) {
                        while ($profesional = $resultadoProfesionales->fetch_assoc()) {
                            $profesionalesAsignables[] = $profesional;
                        }
                        $resultadoProfesionales->free();
                    }
                }
                $secretariaSinProfesionales = ($rol_usuario === 'recepcionista' && empty($profesionalesAsignables));
            ?>
            <form action="guardar_paciente.php" method="POST" id="form-crear-paciente">
                <!-- Div para mostrar mensajes de error -->
                <div id="modal-paciente-error" class="alert-box error" style="display: none; margin-bottom: 20px;"></div>
                
                <div class="form-grid">
                <div class="input-group full-width">
                    <label for="nombre_completo_modal">Nombre Completo:</label>
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="nombre_completo" id="nombre_completo_modal" placeholder="Nombre y apellido" required>
                </div>

                <!-- CAMPO DE FECHA DE NACIMIENTO ACTUALIZADO PARA FLATPICKR -->
                <div class="input-group">
                    <label for="fecha_nacimiento_modal">Fecha de Nacimiento:</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-calendar-day"></i>
                        <input type="text" name="fecha_nacimiento" id="fecha_nacimiento_modal" placeholder="Selecciona una fecha..." required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="cedula_numero_modal">Identificacion:</label>
                    <div class="cedula-input-group">
                        <select name="cedula_tipo" id="cedula_tipo_modal">
                            <option value="V-">V</option>
                            <option value="E-">E</option>
                            <option value="P-">P</option>
                        </select>
                        <input type="number" name="cedula_numero" id="cedula_numero_modal" placeholder="De 7 a 8 dígitos" required minlength="7" maxlength="8">
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="correo_modal">Correo Electrónico:</label>
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="correo" id="correo_modal" placeholder="ejemplo@gmail.com" required>
                </div>
                    <?php if ($rol_usuario === 'recepcionista'): ?>
                    <div class="form-group full-width">
                        <label for="profesional_asignado_modal" class="label-tight">Asignar profesional responsable:</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user-doctor"></i>
                            <select name="profesional_asignado" id="profesional_asignado_modal" <?php echo $secretariaSinProfesionales ? 'disabled' : 'required'; ?>>
                                <option value="">Selecciona un profesional</option>
                                <?php foreach ($profesionalesAsignables as $profesional): ?>
                                    <option value="<?php echo (int)$profesional['id']; ?>">
                                        <?php echo htmlspecialchars($profesional['nombre_completo']); ?>
                                        (Ecografista)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($secretariaSinProfesionales): ?>
                            <p class="helper-text error-text">No hay profesionales aprobados disponibles. Registra o aprueba uno primero.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
            </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalCrearPaciente()">Cancelar</button>
                    <button type="submit" class="btn-submit" <?php echo $secretariaSinProfesionales ? 'disabled' : ''; ?>><?php echo $secretariaSinProfesionales ? 'Sin profesionales disponibles' : 'Crear Paciente'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PROGRAMAR CITA (EcoModal + premium legacy) ================== -->
<div id="eco-modal-programar-cita" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-programar-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide eco-modal-dialog--premium-legacy" style="max-width:920px;">
        <div class="modal-content-premium">
            <div class="modal-info-panel">
                <div class="info-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <h3 id="eco-programar-aside-title">Nueva Cita</h3>
                <p>Estás agendando una nueva consulta para:</p>
                <strong id="modal-paciente-nombre-display"></strong>
                <p class="info-footer">Asegúrate de que la fecha y el motivo sean correctos antes de guardar.</p>
            </div>
            <div class="modal-form-panel">
                <button type="button" class="modal-close-btn" onclick="cerrarModalProgramarCita()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4>Detalles de la Cita</h4>
                <form action="guardar_cita_directa.php" method="POST" id="form-programar-cita">
                    <input type="hidden" name="paciente_id" id="modal-paciente-id">
                    <div class="form-group">
                        <label for="calendario-programar">Fecha y Hora</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-calendar-alt"></i>
                            <input type="text" id="calendario-programar" name="fecha_cita" placeholder="Selecciona una fecha..." required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="motivo_consulta_modal">Motivo de la consulta</label>
                        <textarea name="motivo_consulta" id="motivo_consulta_modal" rows="5" required placeholder="Ej: Cita de seguimiento..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="cerrarModalProgramarCita()">Cancelar</button>
                        <button type="submit" class="btn-submit">Guardar Cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL REPROGRAMAR CITA (EcoModal + premium legacy) ================== -->
<div id="eco-modal-reprogramar-cita" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-reprogramar-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide eco-modal-dialog--premium-legacy" style="max-width:920px;">
        <div class="modal-content-premium">
            <div class="modal-info-panel info-panel-warning">
                <div class="info-icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <h3 id="eco-reprogramar-aside-title">Reprogramar Cita</h3>
                <p>Paciente:</p>
                <strong id="reprogramar-paciente-nombre"></strong>
                <p class="info-footer">El paciente recibirá una notificación con la nueva fecha y el motivo del cambio.</p>
            </div>
            <div class="modal-form-panel">
                <button type="button" class="modal-close-btn" onclick="cerrarModalReprogramarCita()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4>Nuevos Detalles de la Cita</h4>
                <form action="actualizar_cita.php" method="POST" id="form-reprogramar-cita">
                    <input type="hidden" name="cita_id" id="reprogramar-cita-id">
                    <div class="form-group">
                        <label for="calendario-reprogramar">Seleccionar Nueva Fecha y Hora:</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-calendar-alt"></i>
                            <input type="text" id="calendario-reprogramar" name="nueva_fecha_cita" placeholder="Haz clic para seleccionar..." required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="motivo_reprogramacion_modal">Motivo de la reprogramación:</label>
                        <textarea name="motivo_reprogramacion" id="motivo_reprogramacion_modal" rows="4" required placeholder="Ej: Conflicto de horario imprevisto..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="cerrarModalReprogramarCita()">Cancelar</button>
                        <button type="submit" class="btn-submit">Guardar y Notificar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL PROPONER FECHA (EcoModal + premium legacy) ================== -->
<div id="eco-modal-proponer-fecha" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-proponer-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide eco-modal-dialog--premium-legacy" style="max-width:920px;">
        <div class="modal-content-premium">
            <div class="modal-info-panel info-panel-warning">
                <div class="info-icon">
                    <i class="fa-solid fa-calendar-plus"></i>
                </div>
                <h3 id="eco-proponer-aside-title">Proponer Nueva Fecha</h3>
                <p>Paciente:</p>
                <strong id="proponer-paciente-nombre"></strong>
                <p class="info-footer">El paciente recibirá una notificación con tu propuesta y deberá aceptarla o rechazarla.</p>
            </div>
            <div class="modal-form-panel">
                <button type="button" class="modal-close-btn" onclick="cerrarModalProponerFecha()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4>Detalles de la Propuesta</h4>
                <form action="guardar_propuesta.php" method="POST" id="form-proponer-fecha">
                    <input type="hidden" name="cita_id" id="proponer-cita-id">
                    <div class="form-group">
                        <label for="calendario-proponer">Sugerir nueva fecha y hora:</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-calendar-alt"></i>
                            <input type="text" id="calendario-proponer" name="fecha_propuesta" placeholder="Haz clic para seleccionar..." required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="motivo_reprogramacion_propuesta">Motivo (se notificará al paciente):</label>
                        <textarea name="motivo_reprogramacion" id="motivo_reprogramacion_propuesta" rows="4" required placeholder="Ej: No tengo disponibilidad en el horario solicitado..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="cerrarModalProponerFecha()">Cancelar</button>
                        <button type="submit" class="btn-submit">Enviar Propuesta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA GESTIONAR PACIENTE (DISEÑO PREMIUM) ================== -->
<div id="modal-gestionar-paciente" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium" style="max-width: 900px;">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel">
            <div class="info-icon"><i class="fa-solid fa-user-gear"></i></div>
            <h3>Gestionar Paciente</h3>
            <p>Acciones rápidas para:</p>
            <strong id="gestion-paciente-nombre"></strong>
            <p id="gestion-paciente-edad" class="info-panel-age"></p>
            <p id="gestion-paciente-direccion" class="info-panel-age" style="margin-top:4px;"></p>
            <p class="info-footer">Desde aquí puedes acceder a la historia clínica y a los informes del paciente.</p>
        </div>

        <!-- Panel Derecho (Contenido y Acciones) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalGestionarPaciente()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Información y Acciones</h4>
            <div id="gestion-modal-body">
                <!-- El contenido se cargará aquí con JavaScript -->
                <p>Cargando datos del paciente...</p>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA GESTIONAR NOTAS DE SESIÓN (LAYOUT CORREGIDO) ================== -->
<div id="modal-gestionar-notas" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium" style="max-width: 950px;">
        
                <!-- Panel Derecho (Historial de Notas) -->
        <div class="modal-info-panel info-panel-history">
            <div class="history-header">
                <div class="info-icon">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div>
                    <h3>Historial de Notas</h3>
                    <p id="notas-paciente-nombre"></p>
                </div>
                <!-- BOTÓN AÑADIDO -->
                <button id="btn-limpiar-notas" class="btn-clear-notes" title="Limpiar todo el historial de notas">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>
            <div id="historial-notas-container" class="history-list">
                <!-- Las notas se cargarán aquí con JavaScript -->
                <p>Cargando historial...</p>
            </div>
        </div>

        <!-- Panel Derecho (Ahora es el Formulario para Añadir) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalGestionarNotas()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Añadir Nueva Nota de Sesión</h4>
            <form action="guardar_nota.php" method="POST" id="form-guardar-nota">
                <input type="hidden" name="paciente_id" id="notas-paciente-id">
                <div class="form-group">
    <label for="fecha_sesion_modal">Fecha de la Sesión:</label>
    <div class="input-wrapper">
        <i class="fa-solid fa-calendar-alt"></i>
        <input type="text" name="fecha_sesion" id="fecha_sesion_modal" placeholder="Selecciona fecha y hora..." required>
    </div>
</div>
                <div class="form-group">
                    <label for="nota_modal">Nota de Evolución:</label>
                    <textarea name="nota" id="nota_modal" rows="8" required placeholder="Escribe aquí tus observaciones sobre la sesión..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Guardar Nota</button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- ================== MODAL PARA SELECCIONAR TIPO DE HISTORIA (DISEÑO PREMIUM) ================== -->
<div id="modal-seleccionar-historia" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-select">
        <div class="modal-header-select">
            <h2>Seleccionar Tipo de Expediente</h2>
            <p>Elige el formato de historia clínica adecuado para el paciente.</p>
            <button type="button" class="modal-close-btn" onclick="cerrarModalSeleccionarHistoria()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body-select">
            <div class="selection-grid-premium">
                 <div class="selection-card-premium" id="btn-seleccionar-adulto">
                    <div class="card-icon"><i class="fa-solid fa-user"></i></div>
                    <div class="card-text">
                        <h3>Historia de Adulto</h3>
                        <p>Formulario completo para pacientes mayores de 18 años.</p>
                    </div>
                    <div class="card-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <div class="selection-card-premium" id="btn-seleccionar-infantil">
                    <div class="card-icon"><i class="fa-solid fa-child"></i></div>
                    <div class="card-text">
                        <h3>Historia Infantil</h3>
                        <p>Formulario detallado para niños y adolescentes.</p>
                    </div>
                    <div class="card-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL 2: SELECCIONAR TIPO DE ECOGRAFÍA ================== -->
<div id="modal-seleccionar-ecografia" class="modal-overlay" style="display:none;">
    <div class="modal-content-eco-grid">

        <!-- ── Encabezado ── -->
        <div class="eco-modal-header">
            <button class="eco-btn-back" onclick="volverAModalHistoria()">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-header-title">
                <h2><i class="fa-solid fa-wave-square" style="color:#02b1f4;margin-right:8px;"></i>Seleccionar Tipo de Ecografía</h2>
                <p id="eco-modal-paciente-info">Paciente: —</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalSeleccionarEcografia()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- ── Grid de tarjetas ── -->
        <div class="eco-modal-body">
            <div class="eco-cards-grid">
                <?php
                $eco_colores_map = $eco_colores ?? [];
                $eco_color_def   = $eco_color_default ?? ['bg'=>'linear-gradient(135deg,#64748b,#94a3b8)','badge'=>'#f1f5f9','text'=>'#475569'];
                foreach ($tipos_panel as $t):
                    $cat   = $t['categoria'] ?? '';
                    $col   = $eco_colores_map[$cat] ?? $eco_color_def;
                    $icono = htmlspecialchars($t['icono'] ?: 'fa-solid fa-wave-square');
                    $desc  = htmlspecialchars($t['descripcion'] ?? '');
                ?>
                <div class="eco-card"
                     data-tipo-id="<?php echo (int)$t['id']; ?>"
                     data-tipo-codigo="<?php echo htmlspecialchars($t['codigo'] ?? ''); ?>"
                     data-tipo-nombre="<?php echo htmlspecialchars($t['nombre']); ?>"
                     onclick="seleccionarEcografiaModal(<?php echo (int)$t['id']; ?>, '<?php echo addslashes($t['nombre']); ?>', '<?php echo addslashes($t['codigo'] ?? ''); ?>')">

                    <div class="eco-card-icon" style="background:<?php echo $col['bg']; ?>;">
                        <i class="<?php echo $icono; ?>"></i>
                    </div>

                    <?php if ($cat): ?>
                    <span class="eco-card-badge"
                          style="background:<?php echo $col['badge']; ?>;color:<?php echo $col['text']; ?>;">
                        <?php echo htmlspecialchars($cat); ?>
                    </span>
                    <?php endif; ?>

                    <p class="eco-card-name"><?php echo htmlspecialchars($t['nombre']); ?></p>
                    <?php if ($desc): ?>
                    <p class="eco-card-desc"><?php echo $desc; ?></p>
                    <?php endif; ?>
                    <span class="eco-card-select-hint"><i class="fa-solid fa-circle-check"></i> Seleccionar</span>
                </div>
                <?php endforeach; ?>

                <?php if (empty($tipos_panel)): ?>
                <p style="grid-column:1/-1;text-align:center;color:#aaa;padding:30px 0;">
                    <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                    No hay tipos de ecografía activos configurados.<br>
                    <small>Solicítale al administrador que los registre en la tabla <code>tipos_ecografias</code>.</small>
                </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ================== MODAL 2.5: SUB-SELECCIÓN MUSCULOESQUELÉTICA ================== -->
<div id="modal-seleccionar-musculo" class="modal-overlay" style="display:none;">
    <div class="modal-content-eco-grid">

        <div class="eco-modal-header">
            <button class="eco-btn-back" onclick="volverDeModalMusculo()">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-header-title">
                <h2><i class="fa-solid fa-bone" style="color:#22c55e;margin-right:8px;"></i>Ecografía Musculoesquelética</h2>
                <p id="musculo-modal-paciente-info">Seleccione la articulación a estudiar</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalMusculo()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="eco-modal-body">
            <div class="musculo-cards-grid">
                <?php foreach ($tipos_musculo as $t):
                    $icono = htmlspecialchars($t['icono'] ?: 'fa-solid fa-bone');
                    $desc  = htmlspecialchars($t['descripcion'] ?? '');
                ?>
                <div class="eco-card musculo-card"
                     data-tipo-id="<?php echo (int)$t['id']; ?>"
                     data-tipo-codigo="<?php echo htmlspecialchars($t['codigo']); ?>"
                     data-tipo-nombre="<?php echo htmlspecialchars($t['nombre']); ?>"
                     onclick="seleccionarSubMusculo(<?php echo (int)$t['id']; ?>, '<?php echo addslashes($t['nombre']); ?>')">
                    <div class="eco-card-icon musculo-card-icon">
                        <i class="<?php echo $icono; ?>"></i>
                    </div>
                    <p class="eco-card-name"><?php echo htmlspecialchars($t['nombre']); ?></p>
                    <?php if ($desc): ?>
                    <p class="eco-card-desc"><?php echo $desc; ?></p>
                    <?php endif; ?>
                    <span class="eco-card-select-hint"><i class="fa-solid fa-circle-check"></i> Seleccionar</span>
                </div>
                <?php endforeach; ?>

                <?php if (empty($tipos_musculo)): ?>
                <p style="grid-column:1/-1;text-align:center;color:#aaa;padding:30px 0;">
                    <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                    No hay sub-tipos musculoesqueléticos configurados.<br>
                    <small>Ejecuta <code>database/seed_musculo_subtipos.php</code></small>
                </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ================== MODAL 2.6: SUB-SELECCIÓN OBSTÉTRICA (I / II-III TRIMESTRE) ================== -->
<div id="modal-seleccionar-obstetrica" class="modal-overlay" style="display:none;">
    <div class="modal-content-eco-grid">

        <div class="eco-modal-header">
            <button class="eco-btn-back" onclick="volverDeModalObstetrica()">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-header-title">
                <h2><i class="fa-solid fa-baby" style="color:#ec4899;margin-right:8px;"></i>Ecografía Obstétrica</h2>
                <p id="obstetrica-modal-paciente-info">Seleccione el trimestre del estudio</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalObstetrica()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="eco-modal-body">
            <div class="obstetrica-cards-grid">
                <?php foreach ($tipos_obstetrica as $t):
                    $icono = htmlspecialchars($t['icono'] ?: 'fa-solid fa-baby');
                    $desc  = htmlspecialchars($t['descripcion'] ?? '');
                ?>
                <div class="eco-card obstetrica-card"
                     data-tipo-id="<?php echo (int)$t['id']; ?>"
                     data-tipo-codigo="<?php echo htmlspecialchars($t['codigo']); ?>"
                     data-tipo-nombre="<?php echo htmlspecialchars($t['nombre']); ?>"
                     onclick="seleccionarSubObstetrica(<?php echo (int)$t['id']; ?>, '<?php echo addslashes($t['nombre']); ?>')">
                    <div class="eco-card-icon obstetrica-card-icon">
                        <i class="<?php echo $icono; ?>"></i>
                    </div>
                    <p class="eco-card-name"><?php echo htmlspecialchars($t['nombre']); ?></p>
                    <?php if ($desc): ?>
                    <p class="eco-card-desc"><?php echo $desc; ?></p>
                    <?php endif; ?>
                    <span class="eco-card-select-hint"><i class="fa-solid fa-circle-check"></i> Seleccionar</span>
                </div>
                <?php endforeach; ?>

                <?php if (empty($tipos_obstetrica)): ?>
                <p style="grid-column:1/-1;text-align:center;color:#aaa;padding:30px 0;">
                    <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                    No hay sub-tipos obstétricos configurados.<br>
                    <small>Ejecuta <code>database/seed_obstetrica_subtipos.php</code></small>
                </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ================== MODAL 2.7: SUB-SELECCIÓN PARTES BLANDAS ================== -->
<div id="modal-seleccionar-partes-blandas" class="modal-overlay" style="display:none;">
    <div class="modal-content-eco-grid">

        <div class="eco-modal-header">
            <button class="eco-btn-back" onclick="volverDeModalPartesBlandas()">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-header-title">
                <h2><i class="fa-solid fa-hand-holding-medical" style="color:#f59e0b;margin-right:8px;"></i>Ecografía de Partes Blandas</h2>
                <p id="pblandas-modal-paciente-info">Seleccione el tipo de estudio</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalPartesBlandas()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="eco-modal-body">
            <div class="pblandas-cards-grid">
                <?php foreach ($tipos_partes_blandas as $t):
                    $icono = htmlspecialchars($t['icono'] ?: 'fa-solid fa-hand-holding-medical');
                    $desc  = htmlspecialchars($t['descripcion'] ?? '');
                ?>
                <div class="eco-card pblandas-card"
                     data-tipo-id="<?php echo (int)$t['id']; ?>"
                     data-tipo-codigo="<?php echo htmlspecialchars($t['codigo']); ?>"
                     data-tipo-nombre="<?php echo htmlspecialchars($t['nombre']); ?>"
                     onclick="seleccionarSubPartesBlandas(<?php echo (int)$t['id']; ?>, '<?php echo addslashes($t['nombre']); ?>')">
                    <div class="eco-card-icon pblandas-card-icon">
                        <i class="<?php echo $icono; ?>"></i>
                    </div>
                    <p class="eco-card-name"><?php echo htmlspecialchars($t['nombre']); ?></p>
                    <?php if ($desc): ?>
                    <p class="eco-card-desc"><?php echo $desc; ?></p>
                    <?php endif; ?>
                    <span class="eco-card-select-hint"><i class="fa-solid fa-circle-check"></i> Seleccionar</span>
                </div>
                <?php endforeach; ?>

                <?php if (empty($tipos_partes_blandas)): ?>
                <p style="grid-column:1/-1;text-align:center;color:#aaa;padding:30px 0;">
                    <i class="fa-solid fa-circle-exclamation" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
                    No hay sub-tipos de partes blandas configurados.<br>
                    <small>Ejecuta <code>database/seed_partes_blandas_subtipos.php</code></small>
                </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ================== MODAL 3: FORMULARIO DE ESTUDIO ECOGRÁFICO ================== -->
<div id="modal-formulario-estudio" class="modal-overlay" style="display:none;">
    <div class="modal-content-form-eco">

        <!-- Encabezado -->
        <div class="modal-form-eco-header">
            <button class="eco-btn-back" onclick="volverAModalEcoDesdeFormulario()">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </button>
            <div class="eco-modal-tipo-icon" id="modal-form-eco-icon">
                <i class="fa-solid fa-wave-square"></i>
            </div>
            <div class="eco-header-tipo-info">
                <h2 id="modal-form-eco-titulo">Formulario de Estudio</h2>
                <p id="modal-form-eco-paciente">Paciente: —</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalFormularioEstudio()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Barra de feedback -->
        <div class="modal-form-eco-feedback-bar" id="modal-form-eco-feedback" style="display:none;"></div>

        <!-- Cuerpo (el formulario se inyecta aquí por AJAX) -->
        <div class="modal-form-eco-body" id="modal-form-eco-body">
            <div class="modal-form-eco-loader">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Cargando formulario…</p>
            </div>
        </div>

    </div>
</div>

<!-- ================== MODAL PARA CREAR HISTORIA CLÍNICA (ADULTO - DISEÑO DE ENCABEZADO) ================== -->
<div id="modal-crear-historia" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-medical"></i>
                <div>
                    <h2>Crear Historia Clínica de Adulto</h2>
                    <p id="historia-paciente-nombre-display-header"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalCrearHistoria()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo del Formulario con Íconos junto a las Etiquetas -->
        <div class="modal-body-premium">
            <form action="#" method="POST" id="form-crear-historia">
                <input type="hidden" name="tipo_historia" value="adulto">
                <input type="hidden" name="paciente_id" id="historia-paciente-id" value="123">
                
                <h3>Datos Generales</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-hashtag"></i> N° de Historia:</label><input type="text" name="numero_historia" id="historia-numero-adulto" class="validate-numeric" readonly required></div>
                    <div class="form-group"><label><i class="fa-solid fa-hospital"></i> Centro de Salud:</label><input type="text" name="centro_salud" value="Clínica EcoMadelleine" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-calendar-day"></i> Fecha:</label><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required readonly></div>
                </div>
                <h3>Datos Personales</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-id-card"></i> Identificacion:</label><input type="text" name="ci_paciente" id="historia-paciente-cedula" readonly required></div>
                    <div class="form-group"><label><i class="fa-solid fa-venus-mars"></i> Sexo:</label><select name="sexo" required><option value="" disabled selected>Seleccione</option><option value="Masculino">Masculino</option><option value="Femenino">Femenino</option></select></div>
                        <div class="form-group">
                        <label><i class="fa-solid fa-phone"></i> Teléfono:</label>
                        <div class="phone-input-group">
                            <select name="telefono_tipo" required>
                                <option value="Móvil" selected>Móvil</option>
                                <option value="Fijo">Fijo</option>
                            </select>
                            <select name="telefono_codigo_pais" required>
                                <option value="+58" selected>(+58)</option>
                                <option value="+57">(+57)</option>
                                <option value="+1">(+1)</option>
                                <option value="+34">(+34)</option>
                                <option value="+51">(+51)</option>
                                <option value="+54">(+54)</option>
                                <option value="+55">(+55)</option>
                                <option value="+56">(+56)</option>
                                <option value="+593">(+593)</option>
                                <option value="+52">(+52)</option>
                                <option value="+507">(+507)</option>
                                <option value="+39">(+39)</option>
                                <option value="+44">(+44)</option>
                                <!-- Puedes añadir más países aquí -->
                            </select>
                            <input type="text" name="telefono_numero" required class="validate-numeric" maxlength="10">
                        </div>
                    </div>
                    <div class="form-group"><label><i class="fa-solid fa-ring"></i> Edo. Civil:</label><select name="estado_civil" required><option value="" disabled selected>Seleccione</option><option value="Soltero(a)">Soltero(a)</option><option value="Casado(a)">Casado(a)</option><option value="Divorciado(a)">Divorciado(a)</option><option value="Viudo(a)">Viudo(a)</option><option value="Unión Libre">Unión Libre</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-flag"></i> Nacionalidad:</label><select name="nacionalidad" required><option value="" disabled selected>Seleccione</option><option value="Venezolana">Venezolana</option><option value="Otra">Otra</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-children"></i> ¿Tiene Hijos?:</label><select name="hijos" required><option value="" disabled selected>Seleccione</option><option value="No">No</option><option value="Sí">Sí</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-cross"></i> Religión:</label><input type="text" name="religion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label><select name="grado_instruccion" required><option value="" disabled selected>Seleccione</option><option value="Sin instrucción">Sin instrucción</option><option value="Primaria">Primaria</option><option value="Secundaria">Secundaria</option><option value="Bachiller">Bachiller</option><option value="Técnico Superior">Técnico Superior</option><option value="Universitario">Universitario</option><option value="Postgrado">Postgrado</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-briefcase"></i> Ocupación:</label><input type="text" name="ocupacion" class="validate-text-only" required></div>
                    <div class="form-group full-width"><label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label><textarea name="direccion" required></textarea></div>
                </div>
                <h3>Motivo y Antecedentes</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-comment-medical"></i> Motivo de Consulta:</label><textarea name="motivo_consulta" rows="4" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-user-pen"></i> Antecedentes Personales:</label><textarea name="antecedentes_personales" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-users"></i> Antecedentes Familiares:</label><textarea name="antecedentes_familiares" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-brain"></i> Antecedentes Psiquiátricos:</label><textarea name="antecedentes_psiquiatricos" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-notes-medical"></i> Antecedentes Médicos:</label><textarea name="antecedentes_medicos" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-heart"></i> Antecedentes de Pareja:</label><textarea name="antecedentes_pareja" rows="3" required></textarea></div>
                <h3>Diagnóstico</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-file-waveform"></i> Impresión Diagnóstica:</label><textarea name="impresion_diagnostica" rows="5" required></textarea></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalCrearHistoria()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar Historia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA CREAR HISTORIA CLÍNICA (INFANTIL - DISEÑO PREMIUM) ================== -->
<div id="modal-crear-historia-infantil" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-child"></i>
                <div>
                    <h2>Crear Historia Clínica Infantil</h2>
                    <p id="historia-paciente-nombre-display-infantil-header"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalCrearHistoriaInfantil()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
         <!-- Cuerpo del Formulario con Íconos junto a las Etiquetas -->
        <div class="modal-body-premium">
            <form action="#" method="POST" id="form-crear-historia-infantil">
                <input type="hidden" name="tipo_historia" value="infantil">
                <input type="hidden" name="paciente_id" id="historia-paciente-id-infantil" value="123">
                
                <h3>Datos Generales</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-hashtag"></i> N° de Historia:</label><input type="text" name="numero_historia" id="historia-numero-infantil" class="validate-numeric" readonly required></div>
                    <div class="form-group"><label><i class="fa-solid fa-hospital"></i> Centro de Salud:</label><input type="text" name="centro_salud" value="Clínica EcoMadelleine" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-calendar-day"></i> Fecha:</label><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required readonly></div>
                </div>

                <h3>Datos Personales del Infante</h3>
                <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="form-group"><label><i class="fa-solid fa-map-pin"></i> Lugar de Nacimiento:</label><input type="text" name="lugar_nacimiento" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-id-card"></i> Identificación:</label><input type="text" name="ci_infante" id="ci_infante_modal" class="validate-numeric" readonly></div>
                    <div class="form-group"><label><i class="fa-solid fa-school"></i> Institución Escolar:</label><input type="text" name="institucion_escolar" required></div>
                </div>
                
                <h3>Datos del Padre</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-user"></i> Nombre y Apellido:</label><input type="text" name="padre_nombre" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-birthday-cake"></i> Edad:</label><input type="text" name="padre_edad" class="validate-numeric" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-id-card"></i> C.I.:</label><input type="text" name="padre_ci" class="validate-numeric" required minlength="7" maxlength="8" placeholder="7 a 8 dígitos"></div>
                    <div class="form-group"><label><i class="fa-solid fa-flag"></i> Nacionalidad:</label><select name="padre_nacionalidad" required><option value="" disabled selected>Seleccione</option><option value="Venezolana">Venezolana</option><option value="Otra">Otra</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-cross"></i> Religión:</label><input type="text" name="padre_religion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label><select name="padre_instruccion" required><option value="" disabled selected>Seleccione</option><option value="Sin instrucción">Sin instrucción</option><option value="Primaria">Primaria</option><option value="Secundaria">Secundaria</option><option value="Bachiller">Bachiller</option><option value="Técnico Superior">Técnico Superior</option><option value="Universitario">Universitario</option><option value="Postgrado">Postgrado</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-briefcase"></i> Ocupación:</label><input type="text" name="padre_ocupacion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-phone"></i> Teléfono:</label><input type="text" name="padre_telefono" class="validate-numeric" required maxlength="11"></div>
                    <div class="form-group full-width"><label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label><textarea name="padre_direccion" rows="2" required></textarea></div>
                </div>

                <h3>Datos de la Madre</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-user"></i> Nombre y Apellido:</label><input type="text" name="madre_nombre" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-birthday-cake"></i> Edad:</label><input type="text" name="madre_edad" class="validate-numeric" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-id-card"></i> C.I.:</label><input type="text" name="madre_ci" class="validate-numeric" required minlength="7" maxlength="8" placeholder="7 a 8 dígitos"></div>
                    <div class="form-group"><label><i class="fa-solid fa-flag"></i> Nacionalidad:</label><select name="madre_nacionalidad" required><option value="" disabled selected>Seleccione</option><option value="Venezolana">Venezolana</option><option value="Otra">Otra</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-cross"></i> Religión:</label><input type="text" name="madre_religion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label><select name="madre_instruccion" required><option value="" disabled selected>Seleccione</option><option value="Sin instrucción">Sin instrucción</option><option value="Primaria">Primaria</option><option value="Secundaria">Secundaria</option><option value="Bachiller">Bachiller</option><option value="Técnico Superior">Técnico Superior</option><option value="Universitario">Universitario</option><option value="Postgrado">Postgrado</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-briefcase"></i> Ocupación:</label><input type="text" name="madre_ocupacion" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-phone"></i> Teléfono:</label><input type="text" name="madre_telefono" class="validate-numeric" required maxlength="11"></div>
                    <div class="form-group full-width"><label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label><textarea name="madre_direccion" rows="2" required></textarea></div>
                </div>

                <h3>Dinámica Familiar</h3>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-house-user"></i> ¿Padres viven juntos?:</label><select name="padres_viven_juntos" required><option value="" disabled selected>Seleccione</option><option value="Sí">Sí</option><option value="No">No</option></select></div>
                    <div class="form-group"><label><i class="fa-solid fa-ring"></i> ¿Están casados?:</label><select name="estan_casados" required><option value="" disabled selected>Seleccione</option><option value="Sí">Sí</option><option value="No">No</option></select></div>
                    <div class="form-group full-width"><label><i class="fa-solid fa-comment-slash"></i> Motivo de separación (si aplica):</label><textarea name="motivo_separacion" rows="2"></textarea></div>
                </div>
                <div class="form-group full-width">
                    <label><i class="fa-solid fa-users"></i> Hermanos</label>
                    <div id="hermanos-container-modal"></div>
                    <button type="button" id="add-hermano-btn-modal" class="btn-outline-primary" style="width: auto; margin-top: 10px;"><i class="fa-solid fa-plus"></i> Añadir Hermano</button>
                </div>

                <h3>Motivos y Antecedentes</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-comment-medical"></i> Motivo de Consulta:</label><textarea name="motivo_consulta" rows="4" required></textarea></div>
                <div class="form-grid">
                    <div class="form-group"><label><i class="fa-solid fa-baby-carriage"></i> Tipo de Embarazo:</label><input type="text" name="antecedentes_embarazo" class="validate-text-only" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-hospital"></i> Parto (Lugar):</label><input type="text" name="antecedentes_parto" required></div>
                    <div class="form-group"><label><i class="fa-solid fa-baby"></i> Estado del niño/a al nacer:</label><input type="text" name="estado_nino_nacer" required></div>
                </div>
                <div class="form-group full-width"><label><i class="fa-solid fa-person-walking"></i> Desarrollo Psicomotor:</label><textarea name="desarrollo_psicomotor" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-hand-sparkles"></i> Hábitos de Independencia:</label><textarea name="habitos_independencia" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-heartbeat"></i> Condiciones Generales de Salud:</label><textarea name="condiciones_salud" rows="3" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-users"></i> Vida Social:</label><textarea name="vida_social" rows="3" required></textarea></div>
                
                <h3>Plan Terapéutico</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-clipboard-list"></i> Plan Psicoterapéutico:</label><textarea name="plan_psicoterapeutico" rows="5" required></textarea></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalCrearHistoriaInfantil()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar Historia Infantil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER INFORMES (DISEÑO PREMIUM) ================== -->
<div id="modal-ver-informes" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium" style="max-width: 700px;">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel" style="background: linear-gradient(160deg, #6f42c1, #5a32a3);">
            <div class="info-icon"><i class="fa-solid fa-folder-open"></i></div>
            <h3>Historial de Ecografías</h3>
            <p>Paciente:</p>
            <strong id="informes-paciente-nombre"></strong>
            <p id="informes-paciente-edad" class="info-panel-age"></p>
            <p id="informes-paciente-cedula" style="font-size:13px;opacity:.8;margin-top:4px;"></p>
            <p class="info-footer">Listado completo de estudios ecográficos realizados al paciente.</p>
        </div>

        <!-- Panel Derecho (Lista de Informes) -->
        <div class="modal-form-panel">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h4 style="margin:0;" id="informes-panel-titulo">Estudios Registrados</h4>
                <button type="button" class="modal-close-btn" style="position:static;" onclick="cerrarModalVerInformes()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="historial-informes-container" class="history-list">
                <p>Cargando historial...</p>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER DETALLE DE INFORME (DISEÑO PREMIUM) ================== -->
<div id="modal-informe-detalle" class="modal-overlay" style="display:none;">
    <div class="modal-content-form-eco">

        <!-- Encabezado -->
        <div class="modal-form-eco-header">
            <div class="eco-modal-tipo-icon" id="inf-det-icon">
                <i class="fa-solid fa-file-waveform"></i>
            </div>
            <div class="eco-header-tipo-info">
                <h2 id="inf-det-titulo">Informe de Estudio</h2>
                <p id="inf-det-paciente">—</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;margin-left:auto;">
                <button type="button" class="eco-btn-cancel" id="inf-det-print" title="Imprimir informe">
                    <i class="fa-solid fa-print"></i> Imprimir
                </button>
                <button type="button" class="modal-close-btn" onclick="cerrarModalInformeDetalle()" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Cuerpo con scroll -->
        <div class="modal-form-eco-body" id="informe-detalle-body">
            <div class="modal-form-eco-loader">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Cargando informe…</p>
            </div>
        </div>

    </div>
</div>


<!-- ================== NUEVA MODAL PARA CREAR INFORME (DISEÑO PREMIUM) ================== -->
    <div id="modal-crear-informe" class="modal-overlay" style="display: none;">
        <div class="modal-content-premium-header">
            <!-- Encabezado -->
            <div class="modal-header-premium" style="background: linear-gradient(160deg, #17a2b8, #107586);">
                <div class="header-content">
                    <i class="fa-solid fa-file-pen"></i>
                    <div>
                        <h2>Crear Nuevo Informe</h2>
                        <p id="informe-paciente-nombre-display"></p>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="cerrarModalCrearInforme()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <!-- Cuerpo del Formulario con Scroll, Íconos y Validación -->
        <div class="modal-body-premium">
            <form action="guardar_informe_estudio.php" method="POST" id="form-crear-informe">
                <?= csrf_field() ?>
                <input type="hidden" name="paciente_id" id="informe-paciente-id">
                
                <h3>Datos de Referencia</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fa-solid fa-hashtag"></i> N° de Historia:</label>
                        <input type="text" name="numero_historia" id="informe-numero-historia" class="validate-numeric" placeholder="Cargando..." readonly required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar-day"></i> Fecha de Evaluación:</label>
                        <input type="date" name="fecha_evaluacion" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group full-width">
                        <label><i class="fa-solid fa-user-md"></i> Referido por:</label>
                        <input type="text" name="referido_por" placeholder="Ej: Dr. Juan Pérez" required>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label><i class="fa-solid fa-comment-medical"></i> Motivo de la Referencia:</label>
                    <textarea name="motivo_referencia" rows="3" placeholder="Descripción del motivo..." required></textarea>
                </div>
                <div class="form-group full-width">
                    <label><i class="fa-solid fa-user-check"></i> Actitud ante la Evaluación:</label>
                    <textarea name="actitud_ante_evaluacion" rows="3" placeholder="Describe la actitud y comportamiento del paciente..." required></textarea>
                </div>

                <h3>Resultados de la Evaluación</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-eye"></i> Área Visomotriz:</label><textarea name="area_visomotriz" rows="4" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-brain"></i> Área Intelectual:</label><textarea name="area_intelectual" rows="4" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-heart-pulse"></i> Área Emocional:</label><textarea name="area_emocional" rows="4" required></textarea></div>
                <div class="form-group full-width"><label><i class="fa-solid fa-clipboard-list"></i> Otros Resultados Relevantes:</label><textarea name="resultados_adicionales" rows="4" required></textarea></div>
                
                <h3>Recomendaciones</h3>
                <div class="form-group full-width"><label><i class="fa-solid fa-prescription"></i> Recomendaciones:</label><textarea name="recomendaciones" rows="6" required></textarea></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalCrearInforme()">Cancelar</button>
                    <button type="submit" class="btn-submit">Guardar Informe</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <!-- ================== MODAL PARA VER HISTORIA CLÍNICA (DISEÑO PREMIUM) ================== -->
<div id="modal-ver-historia" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-medical"></i>
                <div>
                    <h2 id="ver-historia-titulo">Historia Clínica</h2>
                    <p id="ver-historia-paciente-nombre"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalVerHistoria()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo de la Historia con Scroll -->
        <div class="modal-body-premium" id="ver-historia-body">
            <!-- El contenido de la historia se cargará aquí con JavaScript -->
            <p>Cargando historial...</p>
        </div>
    </div>
</div>

    <!-- ================== MODAL PARA EDITAR HISTORIA CLÍNICA (DISEÑO PREMIUM) ================== -->
    <div id="modal-editar-historia" class="modal-overlay" style="display: none;">
        <div class="modal-content-premium-header">
            <div class="modal-header-premium">
                <div class="header-content">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <div>
                        <h2 id="editar-historia-titulo">Editar Historia Clínica</h2>
                        <p id="editar-historia-paciente-nombre"></p>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="cerrarModalEditarHistoria(true)"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body-premium" id="editar-historia-body">
                <p>Cargando formulario de edición...</p>
            </div>
        </div>
    </div>

<!-- ================== MODAL PARA ASIGNAR Y PROGRAMAR CITA (DISEÑO PREMIUM) ================== -->
<div id="modal-asignar-cita" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium">
        <!-- Panel Izquierdo (Informativo) -->
        <div class="modal-info-panel">
            <div class="info-icon"><i class="fa-solid fa-calendar-plus"></i></div>
            <h3>Asignar Cita</h3>
            <p>Paciente:</p>
            <strong id="asignar-paciente-nombre"></strong>
            <p class="info-footer">Selecciona un profesional y una fecha para confirmar la cita.</p>
        </div>

        <!-- Panel Derecho (Formulario) -->
        <div class="modal-form-panel">
            <button type="button" class="modal-close-btn" onclick="cerrarModalAsignarCita()"><i class="fa-solid fa-xmark"></i></button>
            <h4>Detalles de la Programación</h4>
            <form action="guardar_cita.php" method="POST" id="form-asignar-cita">
                <input type="hidden" name="cita_id" id="asignar-cita-id">
                
                <div class="form-group">
                    <label>Motivo de la Consulta:</label>
                    <p id="asignar-motivo-consulta" class="info-text-box"></p>
                </div>

                <!-- CAMPO AÑADIDO PARA MOSTRAR PROFESIONAL ASIGNADO -->
                <div class="form-group inline-info">
                <label>Profesional Asignado:</label>
                <span id="asignar-profesional-solicitado"></span>
                </div>

                <div class="form-group">
                    <label for="asignar-ecografista-id">Asignar a Profesional:</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user-doctor"></i>
                        <select name="ecografista_id" id="asignar-ecografista-id" required>
                            <option value="">-- Seleccione un profesional --</option>
                            <?php 
                            $profesionales_result = $conex->query("SELECT id, nombre_completo, rol FROM usuarios WHERE rol IN ('ecografista') AND estado = 'aprobado'");
                            if ($profesionales_result) {
                                while($prof = $profesionales_result->fetch_assoc()){
                                    echo '<option value="' . $prof['id'] . '">' . htmlspecialchars($prof['nombre_completo']) . ' (' . ucfirst($prof['rol']) . ')</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="calendario-asignar">Seleccionar Fecha y Hora:</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-calendar-alt"></i>
                        <input type="text" id="calendario-asignar" name="fecha_cita" placeholder="Haz clic para seleccionar..." required>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModalAsignarCita()">Cancelar</button>
                    <button type="submit" class="btn-submit">Confirmar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER DETALLES DE CITA (PACIENTE) ================== -->
<div id="modal-detalle-cita-paciente" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header" style="max-width: 700px;">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-invoice"></i>
                <div>
                    <h2>Detalles de la Cita</h2>
                    <p id="detalle-cita-fecha"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalDetalleCitaPaciente()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo de la Cita con Scroll -->
        <div class="modal-body-premium" id="detalle-cita-body">
            <!-- El contenido se cargará aquí con JavaScript -->
            <p>Cargando detalles...</p>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER DETALLES DEL PROFESIONAL ================== -->
<div id="modal-profesional-detalle" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-user-doctor"></i>
                <div>
                    <h2 id="profesional-detalle-nombre"></h2>
                    <p id="profesional-detalle-rol"></p>
                </div>
            </div>
            
        </div>
        
        <!-- Cuerpo de la Información -->
        <div class="modal-body-premium" id="profesional-detalle-body">
            <!-- El contenido se cargará aquí con JavaScript -->
            <p>Cargando detalles...</p>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA MOSTRAR CONTRASEÑA TEMPORAL (EcoModal) ================== -->
<div id="eco-modal-exito-paciente-panel" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-exito-paciente-title">
    <div class="eco-modal__dialog" style="max-width:520px;">
        <div class="eco-modal__main" style="padding-top:28px;text-align:center;">
            <button type="button" class="eco-modal__close" onclick="cerrarModalExitoPaciente()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-icon success-icon">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <h3 id="eco-exito-paciente-title" style="margin:12px 36px 10px;font-size:1.15rem;font-weight:700;color:var(--text-primary);">¡Paciente Creado con Éxito!</h3>
            <p class="eco-modal__body-text" style="text-align:center;">La cuenta para <strong id="exito-paciente-nombre"></strong> ha sido creada. Su contraseña temporal es:</p>
            <div class="temp-password-box">
                <span id="exito-paciente-password"></span>
            </div>
            <p style="font-size:14px;color:var(--text-muted);margin-top:15px;">Por favor, anota esta contraseña y entrégasela al paciente.</p>
            <div style="margin-top:22px;">
                <button type="button" class="btn-submit" style="width:auto;padding:10px 30px;" onclick="cerrarModalExitoPaciente()">Entendido</button>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODAL PARA VER DETALLES DE SOLICITUD (PSICÓLOGO) ================== -->
<div id="modal-solicitud-detalle" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header" style="max-width: 700px;">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-import"></i>
                <div>
                    <h2>Detalles de la Solicitud</h2>
                    <p id="solicitud-paciente-nombre"></p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalSolicitudDetalle()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo de la Solicitud -->
        <div class="modal-body-premium" id="solicitud-detalle-body">
            <!-- El contenido se cargará aquí con JavaScript -->
        </div>
    </div>
</div>

<!-- ================== MODAL PARA CONFLICTO DE CITA ================== -->
<div id="modal-conflicto-cita" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 500px; text-align: center;">
        <div class="modal-icon" style="color: #ffc107; font-size: 50px; margin-bottom: 15px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>Conflicto de Horario</h3>
        <p>Ya tienes otra cita confirmada para este día a esta misma hora. Por favor, asigna una nueva fecha para este paciente.</p>
        <div class="modal-actions" style="justify-content: center; gap: 15px;">
            <button type="button" class="btn-secondary" onclick="cerrarModalConflicto()">Cancelar</button>
            <a href="#" id="btn-proponer-fecha-conflicto" class="btn-submit">Proponer Nueva Fecha</a>
        </div>
    </div>
</div>




<!-- ================== MODAL PARA DETALLES DE CITA DEL HISTORIAL (PSICÓLOGO) ================== -->
<div id="modal-historial-detalle" class="modal-overlay" style="display: none;">
    <div class="modal-content-premium-header" style="max-width: 750px;">
        <!-- Encabezado -->
        <div class="modal-header-premium">
            <div class="header-content">
                <i class="fa-solid fa-file-medical"></i>
                <div>
                    <h2 id="historial-detalle-titulo">Detalles de la Cita</h2>
                    <p id="historial-detalle-paciente-nombre">Paciente: Cargando...</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="cerrarModalHistorialDetalle()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <!-- Cuerpo del Modal -->
        <div class="modal-body-premium" id="historial-detalle-body">
            <!-- El contenido se cargará aquí con JavaScript -->
            <p>Cargando detalles...</p>
        </div>
    </div>
</div>
