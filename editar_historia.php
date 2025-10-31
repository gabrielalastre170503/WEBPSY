<?php
session_start();
include 'conexion.php';

// Seguridad básica
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador', 'secretaria'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['historia_id']) || !is_numeric($_GET['historia_id'])) {
    die('ID de historia no válido.');
}

$historia_id = (int)$_GET['historia_id'];
$tipo = $_GET['tipo'] ?? '';
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Cargar datos según tipo
$table = ($tipo === 'adulto') ? 'historias_adultos' : 'historias_infantiles';
$stmt = $conex->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $historia_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die('Historia no encontrada.');
}
$hist = $res->fetch_assoc();
$stmt->close();

// Si se solicita por AJAX, devolver solo el fragmento del formulario
if ($isAjax) {
?>
<style>
    .modal-edit-body {
        padding: 10px 0 30px 0;
    }
    
    .modal-edit-body h3 {
        grid-column: 1 / -1; /* Ocupa todo el ancho */
        margin-top: 25px;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 2px solid #007bff;
        color: #007bff;
        font-size: 1.2em;
    }
    .modal-edit-body h3:first-of-type {
        margin-top: 0;
    }

    /* Contenedor principal del formulario */
    .form-grid-container {
        display: grid;
        /* Columnas responsivas: se ajustan al espacio disponible */
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px; /* Espacio entre campos */
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px; /* Espacio entre la etiqueta y el campo */
    }

    /* Ocupa todo el ancho de la grilla */
    .form-group.grid-span-full {
        grid-column: 1 / -1;
    }

    .form-group label {
        font-weight: bold;
        font-size: 0.9em;
        color: #333;
    }
    .form-group label i {
        margin-right: 8px;
        color: #555;
    }

    /* Estilo general para todos los campos de entrada */
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="number"],
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box; /* Importante para que el padding no afecte el ancho */
        font-size: 1em;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
    }
    
    /* Estilos para la sección de hermanos */
    #hermanos-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .hermano-entry {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        position: relative;
    }
    .remove-hermano-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: transparent;
        border: none;
        color: #dc3545;
        cursor: pointer;
        font-size: 1.2em;
    }
    .remove-hermano-btn:hover {
        color: #a71d2a;
    }

    /* Botones de acción del modal */
    .modal-actions {
        grid-column: 1 / -1;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }
</style>

<div class="modal-edit-body">
    <form id="editar-historia-form">
        <input type="hidden" name="tipo_historia" value="<?php echo htmlspecialchars($tipo); ?>">
        <input type="hidden" name="paciente_id" value="<?php echo htmlspecialchars($hist['paciente_id']); ?>">
        <input type="hidden" name="historia_id" value="<?php echo htmlspecialchars($hist['id']); ?>">
        
        <div class="form-grid-container">

        <?php if ($tipo === 'adulto'): ?>
            <h3><i class="fa-solid fa-folder-open"></i> Datos Generales</h3>
            <div class="form-group">
                <label><i class="fa-solid fa-hashtag"></i> N° de Historia:</label>
                <input type="text" name="numero_historia" value="<?php echo htmlspecialchars($hist['numero_historia'] ?? ''); ?>" readonly class="validate-numeric">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-hospital"></i> Centro de Salud:</label>
                <input type="text" name="centro_salud" value="<?php echo htmlspecialchars($hist['centro_salud'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-calendar-day"></i> Fecha:</label>
                <input type="date" name="fecha" value="<?php echo htmlspecialchars($hist['fecha'] ?? ''); ?>">
            </div>

            <h3><i class="fa-solid fa-user"></i> Datos Personales</h3>
            <div class="form-group">
                <label><i class="fa-solid fa-id-card"></i> Cédula:</label>
                <input type="text" name="ci_paciente" value="<?php echo htmlspecialchars($hist['ci_paciente'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-venus-mars"></i> Sexo:</label>
                <input type="text" name="sexo" value="<?php echo htmlspecialchars($hist['sexo'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-phone"></i> Teléfono:</label>
                <input type="text" name="telefono_numero" value="<?php echo htmlspecialchars($hist['telefono'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-ring"></i> Edo. Civil:</label>
                <input type="text" name="estado_civil" value="<?php echo htmlspecialchars($hist['estado_civil'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-flag"></i> Nacionalidad:</label>
                <input type="text" name="nacionalidad" value="<?php echo htmlspecialchars($hist['nacionalidad'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-children"></i> Hijos:</label>
                <input type="text" name="hijos" value="<?php echo htmlspecialchars($hist['hijos'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-cross"></i> Religión:</label>
                <input type="text" name="religion" value="<?php echo htmlspecialchars($hist['religion'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label>
                <input type="text" name="grado_instruccion" value="<?php echo htmlspecialchars($hist['grado_instruccion'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-briefcase"></i> Ocupación:</label>
                <input type="text" name="ocupacion" value="<?php echo htmlspecialchars($hist['ocupacion'] ?? ''); ?>">
            </div>
            <div class="form-group grid-span-full">
                <label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label>
                <textarea name="direccion" rows="2"><?php echo htmlspecialchars($hist['direccion'] ?? ''); ?></textarea>
            </div>

            <h3><i class="fa-solid fa-file-medical"></i> Motivo y Antecedentes</h3>
            <div class="form-group grid-span-full">
                <label>Motivo de Consulta:</label>
                <textarea name="motivo_consulta" rows="4"><?php echo htmlspecialchars($hist['motivo_consulta'] ?? ''); ?></textarea>
            </div>
            <div class="form-group grid-span-full">
                <label>Antecedentes Personales:</label>
                <textarea name="antecedentes_personales" rows="3"><?php echo htmlspecialchars($hist['antecedentes_personales'] ?? ''); ?></textarea>
            </div>
            <div class="form-group grid-span-full">
                <label>Antecedentes Familiares:</label>
                <textarea name="antecedentes_familiares" rows="3"><?php echo htmlspecialchars($hist['antecedentes_familiares'] ?? ''); ?></textarea>
            </div>
            <div class="form-group grid-span-full">
                <label>Antecedentes Psiquiátricos:</label>
                <textarea name="antecedentes_psiquiatricos" rows="3"><?php echo htmlspecialchars($hist['antecedentes_psiquiatricos'] ?? ''); ?></textarea>
            </div>
            <div class="form-group grid-span-full">
                <label>Antecedentes Médicos:</label>
                <textarea name="antecedentes_medicos" rows="3"><?php echo htmlspecialchars($hist['antecedentes_medicos'] ?? ''); ?></textarea>
            </div>
            <div class="form-group grid-span-full">
                <label>Antecedentes de Pareja:</label>
                <textarea name="antecedentes_pareja" rows="3"><?php echo htmlspecialchars($hist['antecedentes_pareja'] ?? ''); ?></textarea>
            </div>

            <h3><i class="fa-solid fa-stethoscope"></i> Impresión Diagnóstica</h3>
            <div class="form-group grid-span-full">
                <label>Impresión Diagnóstica:</label>
                <textarea name="impresion_diagnostica" rows="4"><?php echo htmlspecialchars($hist['impresion_diagnostica'] ?? ''); ?></textarea>
            </div>

        <?php else: /* FORMULARIO INFANTIL */ ?>

            <h3><i class="fa-solid fa-folder-open"></i> Datos Generales</h3>
            <div class="form-group"><label><i class="fa-solid fa-hashtag"></i> N° de Historia:</label><input type="text" name="numero_historia" value="<?php echo htmlspecialchars($hist['numero_historia'] ?? ''); ?>" readonly required></div>
            <div class="form-group"><label><i class="fa-solid fa-hospital"></i> Centro de Salud:</label><input type="text" name="centro_salud" value="<?php echo htmlspecialchars($hist['centro_salud'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-calendar-day"></i> Fecha:</label><input type="date" name="fecha" value="<?php echo htmlspecialchars($hist['fecha'] ?? ''); ?>" required></div>

            <h3><i class="fa-solid fa-child"></i> Datos Personales del Infante</h3>
            <div class="form-group"><label><i class="fa-solid fa-map-pin"></i> Lugar de Nacimiento:</label><input type="text" name="lugar_nacimiento" value="<?php echo htmlspecialchars($hist['lugar_nacimiento'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-id-card"></i> Identificación:</label><input type="text" name="ci_infante" value="<?php echo htmlspecialchars($hist['ci_infante'] ?? ''); ?>" readonly></div>
            <div class="form-group"><label><i class="fa-solid fa-school"></i> Institución Escolar:</label><input type="text" name="institucion_escolar" value="<?php echo htmlspecialchars($hist['institucion_escolar'] ?? ''); ?>" required></div>

            <h3><i class="fa-solid fa-person"></i> Datos del Padre</h3>
            <div class="form-group"><label><i class="fa-solid fa-user"></i> Nombre y Apellido:</label><input type="text" name="padre_nombre" value="<?php echo htmlspecialchars($hist['padre_nombre'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-birthday-cake"></i> Edad:</label><input type="text" name="padre_edad" value="<?php echo htmlspecialchars($hist['padre_edad'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-id-card"></i> C.I.:</label><input type="text" name="padre_ci" value="<?php echo htmlspecialchars($hist['padre_ci'] ?? ''); ?>"></div>
            <div class="form-group"><label><i class="fa-solid fa-flag"></i> Nacionalidad:</label>
                <select name="padre_nacionalidad" required>
                    <option value="" disabled <?php if(empty($hist['padre_nacionalidad'])) echo 'selected'; ?>>Seleccione</option>
                    <option value="Venezolana" <?php if(($hist['padre_nacionalidad'] ?? '') === 'Venezolana') echo 'selected'; ?>>Venezolana</option>
                    <option value="Otra" <?php if(($hist['padre_nacionalidad'] ?? '') === 'Otra') echo 'selected'; ?>>Otra</option>
                </select>
            </div>
            <div class="form-group"><label><i class="fa-solid fa-cross"></i> Religión:</label><input type="text" name="padre_religion" value="<?php echo htmlspecialchars($hist['padre_religion'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label>
                <select name="padre_instruccion" required>
                    <option value="" disabled <?php if(empty($hist['padre_instruccion'])) echo 'selected'; ?>>Seleccione</option>
                    <option value="Sin instrucción" <?php if(($hist['padre_instruccion'] ?? '') === 'Sin instrucción') echo 'selected'; ?>>Sin instrucción</option>
                    <option value="Primaria" <?php if(($hist['padre_instruccion'] ?? '') === 'Primaria') echo 'selected'; ?>>Primaria</option>
                    <option value="Secundaria" <?php if(($hist['padre_instruccion'] ?? '') === 'Secundaria') echo 'selected'; ?>>Secundaria</option>
                    <option value="Bachiller" <?php if(($hist['padre_instruccion'] ?? '') === 'Bachiller') echo 'selected'; ?>>Bachiller</option>
                    <option value="Técnico Superior" <?php if(($hist['padre_instruccion'] ?? '') === 'Técnico Superior') echo 'selected'; ?>>Técnico Superior</option>
                    <option value="Universitario" <?php if(($hist['padre_instruccion'] ?? '') === 'Universitario') echo 'selected'; ?>>Universitario</option>
                    <option value="Postgrado" <?php if(($hist['padre_instruccion'] ?? '') === 'Postgrado') echo 'selected'; ?>>Postgrado</option>
                </select>
            </div>
            <div class="form-group"><label><i class="fa-solid fa-briefcase"></i> Ocupación:</label><input type="text" name="padre_ocupacion" value="<?php echo htmlspecialchars($hist['padre_ocupacion'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-phone"></i> Teléfono:</label><input type="text" name="padre_telefono" value="<?php echo htmlspecialchars($hist['padre_telefono'] ?? ''); ?>" required></div>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label><textarea name="padre_direccion" rows="2" required><?php echo htmlspecialchars($hist['padre_direccion'] ?? ''); ?></textarea></div>

            <h3><i class="fa-solid fa-person-dress"></i> Datos de la Madre</h3>
            <div class="form-group"><label><i class="fa-solid fa-user"></i> Nombre y Apellido:</label><input type="text" name="madre_nombre" value="<?php echo htmlspecialchars($hist['madre_nombre'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-birthday-cake"></i> Edad:</label><input type="text" name="madre_edad" value="<?php echo htmlspecialchars($hist['madre_edad'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-id-card"></i> C.I.:</label><input type="text" name="madre_ci" value="<?php echo htmlspecialchars($hist['madre_ci'] ?? ''); ?>"></div>
            <div class="form-group"><label><i class="fa-solid fa-flag"></i> Nacionalidad:</label>
                <select name="madre_nacionalidad" required>
                    <option value="" disabled <?php if(empty($hist['madre_nacionalidad'])) echo 'selected'; ?>>Seleccione</option>
                    <option value="Venezolana" <?php if(($hist['madre_nacionalidad'] ?? '') === 'Venezolana') echo 'selected'; ?>>Venezolana</option>
                    <option value="Otra" <?php if(($hist['madre_nacionalidad'] ?? '') === 'Otra') echo 'selected'; ?>>Otra</option>
                </select>
            </div>
            <div class="form-group"><label><i class="fa-solid fa-cross"></i> Religión:</label><input type="text" name="madre_religion" value="<?php echo htmlspecialchars($hist['madre_religion'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-graduation-cap"></i> Grado de Instrucción:</label>
                <select name="madre_instruccion" required>
                    <option value="" disabled <?php if(empty($hist['madre_instruccion'])) echo 'selected'; ?>>Seleccione</option>
                    <option value="Sin instrucción" <?php if(($hist['madre_instruccion'] ?? '') === 'Sin instrucción') echo 'selected'; ?>>Sin instrucción</option>
                    <option value="Primaria" <?php if(($hist['madre_instruccion'] ?? '') === 'Primaria') echo 'selected'; ?>>Primaria</option>
                    <option value="Secundaria" <?php if(($hist['madre_instruccion'] ?? '') === 'Secundaria') echo 'selected'; ?>>Secundaria</option>
                    <option value="Bachiller" <?php if(($hist['madre_instruccion'] ?? '') === 'Bachiller') echo 'selected'; ?>>Bachiller</option>
                    <option value="Técnico Superior" <?php if(($hist['madre_instruccion'] ?? '') === 'Técnico Superior') echo 'selected'; ?>>Técnico Superior</option>
                    <option value="Universitario" <?php if(($hist['madre_instruccion'] ?? '') === 'Universitario') echo 'selected'; ?>>Universitario</option>
                    <option value="Postgrado" <?php if(($hist['madre_instruccion'] ?? '') === 'Postgrado') echo 'selected'; ?>>Postgrado</option>
                </select>
            </div>
            <div class="form-group"><label><i class="fa-solid fa-briefcase"></i> Ocupación:</label><input type="text" name="madre_ocupacion" value="<?php echo htmlspecialchars($hist['madre_ocupacion'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-phone"></i> Teléfono:</label><input type="text" name="madre_telefono" value="<?php echo htmlspecialchars($hist['madre_telefono'] ?? ''); ?>" required></div>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-map-marker-alt"></i> Dirección:</label><textarea name="madre_direccion" rows="2" required><?php echo htmlspecialchars($hist['madre_direccion'] ?? ''); ?></textarea></div>

            <h3><i class="fa-solid fa-people-roof"></i> Dinámica Familiar</h3>
            <div class="form-group"><label><i class="fa-solid fa-house-user"></i> ¿Padres viven juntos?:</label>
                <select name="padres_viven_juntos" required>
                    <option value="" disabled <?php if(empty($hist['padres_viven_juntos'])) echo 'selected'; ?>>Seleccione</option>
                    <option value="Sí" <?php if(($hist['padres_viven_juntos'] ?? '') === 'Sí') echo 'selected'; ?>>Sí</option>
                    <option value="No" <?php if(($hist['padres_viven_juntos'] ?? '') === 'No') echo 'selected'; ?>>No</option>
                </select>
            </div>
            <div class="form-group"><label><i class="fa-solid fa-ring"></i> ¿Están casados?:</label>
                <select name="estan_casados" required>
                    <option value="" disabled <?php if(empty($hist['estan_casados'])) echo 'selected'; ?>>Seleccione</option>
                    <option value="Sí" <?php if(($hist['estan_casados'] ?? '') === 'Sí') echo 'selected'; ?>>Sí</option>
                    <option value="No" <?php if(($hist['estan_casados'] ?? '') === 'No') echo 'selected'; ?>>No</option>
                </select>
            </div>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-comment-slash"></i> Motivo de separación (si aplica):</label><textarea name="motivo_separacion" rows="2"><?php echo htmlspecialchars($hist['motivo_separacion'] ?? ''); ?></textarea></div>
            
            <div class="form-group grid-span-full">
                <label><i class="fa-solid fa-users"></i> Hermanos</label>
                <div id="hermanos-container">
                    <?php
                    $hermanos_arr = json_decode($hist['hermanos'] ?? '[]', true);
                    if (is_array($hermanos_arr)) {
                        foreach ($hermanos_arr as $h) { ?>
                            <div class="hermano-entry">
                                <div class="form-group"><label>Nombre:</label><input type="text" name="hermano_nombre[]" value="<?php echo htmlspecialchars($h['nombre'] ?? ''); ?>"></div>
                                <div class="form-group"><label>Edad:</label><input type="number" name="hermano_edad[]" value="<?php echo htmlspecialchars($h['edad'] ?? ''); ?>"></div>
                                <div class="form-group"><label>Sexo:</label><input type="text" name="hermano_sexo[]" value="<?php echo htmlspecialchars($h['sexo'] ?? ''); ?>"></div>
                                <div class="form-group"><label>Ocupación:</label><input type="text" name="hermano_ocupacion[]" value="<?php echo htmlspecialchars($h['ocupacion'] ?? ''); ?>"></div>
                                <div class="form-group"><label>¿Vive en casa?:</label>
                                    <select name="hermano_vive_hogar[]">
                                        <option value="Sí" <?php if(($h['vive_hogar'] ?? '') === 'Sí') echo 'selected'; ?>>Sí</option>
                                        <option value="No" <?php if(($h['vive_hogar'] ?? '') === 'No') echo 'selected'; ?>>No</option>
                                    </select>
                                </div>
                                <button type="button" class="remove-hermano-btn" onclick="this.closest('.hermano-entry').remove()"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        <?php }
                    } ?>
                </div>
                <button type="button" id="add-hermano-btn-edit" class="btn-outline-primary" style="width: auto; margin-top: 10px;"><i class="fa-solid fa-plus"></i> Añadir Hermano</button>
            </div>

            <h3><i class="fa-solid fa-file-medical"></i> Motivos y Antecedentes</h3>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-comment-medical"></i> Motivo de Consulta:</label><textarea name="motivo_consulta" rows="4" required><?php echo htmlspecialchars($hist['motivo_consulta'] ?? ''); ?></textarea></div>
            <div class="form-group"><label><i class="fa-solid fa-baby-carriage"></i> Tipo de Embarazo:</label><input type="text" name="antecedentes_embarazo" value="<?php echo htmlspecialchars($hist['antecedentes_embarazo'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-hospital"></i> Parto (Lugar):</label><input type="text" name="antecedentes_parto" value="<?php echo htmlspecialchars($hist['antecedentes_parto'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fa-solid fa-baby"></i> Estado del niño/a al nacer:</label><input type="text" name="estado_nino_nacer" value="<?php echo htmlspecialchars($hist['estado_nino_nacer'] ?? ''); ?>" required></div>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-person-walking"></i> Desarrollo Psicomotor:</label><textarea name="desarrollo_psicomotor" rows="3" required><?php echo htmlspecialchars($hist['desarrollo_psicomotor'] ?? ''); ?></textarea></div>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-hand-sparkles"></i> Hábitos de Independencia:</label><textarea name="habitos_independencia" rows="3" required><?php echo htmlspecialchars($hist['habitos_independencia'] ?? ''); ?></textarea></div>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-heartbeat"></i> Condiciones Generales de Salud:</label><textarea name="condiciones_salud" rows="3" required><?php echo htmlspecialchars($hist['condiciones_salud'] ?? ''); ?></textarea></div>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-users"></i> Vida Social:</label><textarea name="vida_social" rows="3" required><?php echo htmlspecialchars($hist['vida_social'] ?? ''); ?></textarea></div>
            
            <h3><i class="fa-solid fa-clipboard-list"></i> Plan Terapéutico</h3>
            <div class="form-group grid-span-full"><label><i class="fa-solid fa-clipboard-list"></i> Plan Psicoterapéutico:</label><textarea name="plan_psicoterapeutico" rows="5" required><?php echo htmlspecialchars($hist['plan_psicoterapeutico'] ?? ''); ?></textarea></div>

        <?php endif; ?>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModalVerHistoria()">Cancelar</button>
                <button type="submit" class="btn-submit">Guardar cambios</button>
            </div>
        </div>
    </form>
</div>
<?php
    exit();
}

// Si no es AJAX, mostrar página completa (fallback) - sin cambios aquí
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editar Historia Clínica</title>
</head>
<body>
    <h1>Editar Historia Clínica (<?php echo htmlspecialchars($tipo); ?>)</h1>
    <p>Este formulario debe ser cargado dentro de la aplicación principal.</p>
</body>
</html>