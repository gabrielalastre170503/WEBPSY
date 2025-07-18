<?php
session_start();
include 'conexion.php';

// Seguridad y obtención de datos del paciente
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_GET['paciente_id'])) { die("Error: No se ha especificado un paciente."); }

$paciente_id = $_GET['paciente_id'];
$stmt_paciente = $conex->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$paciente = $stmt_paciente->get_result()->fetch_assoc();
if (!$paciente) { die("Error: Paciente no encontrado."); }

$entrevistador_nombre = $_SESSION['nombre_completo'];

// Lógica para buscar historia existente
$historia_existente = null;
$tipo_historia_existente = '';

$sql_check = "SELECT 'adulto' as tipo, a.* FROM historias_adultos a WHERE a.paciente_id = ? UNION ALL SELECT 'infantil' as tipo, i.* FROM historias_infantiles i WHERE i.paciente_id = ? LIMIT 1";
$stmt_check = $conex->prepare($sql_check);
$stmt_check->bind_param("ii", $paciente_id, $paciente_id);
$stmt_check->execute();
$resultado_historia = $stmt_check->get_result();
if ($resultado_historia->num_rows > 0) {
    $historia_existente = $resultado_historia->fetch_assoc();
    $tipo_historia_existente = $historia_existente['tipo'];
}
$stmt_check->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historia Clínica</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; }
        .main-container { max-width: 900px; margin: 30px auto; padding: 30px; background-color: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1, h2, h3 { color: #333; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; margin-top: 25px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group label { margin-bottom: 5px; font-weight: 500; color: #555; }
        .form-group input, .form-group textarea, .form-group select { padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
        .full-width { grid-column: 1 / -1; }
        .btn { cursor: pointer; border: none; padding: 12px 25px; border-radius: 5px; background-color: #02b1f4; color: white; font-size: 16px; font-weight: 500; margin-top: 20px; text-decoration: none; display: inline-block; }
        .hidden-form {
        display: none; /* Se mantiene oculto al inicio */
        justify-content: center; /* NUEVA LÍNEA: Centra su contenido (el formulario) */
        }  
        .hidden-form h2 { 
         text-align: center; 
        }
        .historia-vista .dato-item { margin-bottom: 15px; }
        .historia-vista .dato-item strong { color: #333; display: block; }
        .historia-vista .dato-item p { margin: 5px 0 0 0; padding-left: 10px; border-left: 3px solid #eee; }

        /* --- NUEVO Y MEJORADO DISEÑO PARA BOTONES DE SELECCIÓN --- */
        .selection-container { text-align: center; }
        .selection-container p { font-size: 1.1em; color: #555; }
        .selection-grid {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0 30px 0;
        }
        .selection-card {
            background-color: transparent;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 30px;
            width: 250px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }
        .selection-card:hover {
            transform: translateY(-5px);
            border-color: #02b1f4;
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .selection-card i {
            font-size: 40px;
            color: #02b1f4;
            margin-bottom: 15px;
        }
        .selection-card h3 {
            border: none;
            margin: 0;
            padding: 0;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
<div class="main-container">
    <h1>Historia Clínica de: <?php echo htmlspecialchars($paciente['nombre_completo']); ?></h1>
    <a href="gestionar_paciente.php?paciente_id=<?php echo $paciente_id; ?>" style="display:inline-block; margin-bottom:20px;">&larr; Volver a Gestión de Paciente</a>

    <?php if ($historia_existente): ?>
        <div class="historia-vista">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Historia Clínica Registrada</h2>
                <a href="borrar_historia.php?historia_id=<?php echo $historia_existente['id']; ?>&tipo=<?php echo $tipo_historia_existente; ?>&paciente_id=<?php echo $paciente_id; ?>"
                   onclick="return confirm('¿Estás seguro de que quieres borrar esta historia clínica? Esta acción es PERMANENTE.');"
                   style="background-color: #dc3545; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 14px;">
                   Borrar Historia
                </a>
            </div>
            
            <?php if ($tipo_historia_existente == 'adulto'): ?>
                <h3>Datos Generales</h3>
                <div class="dato-item"><strong>N° de Historia:</strong> <p><?php echo htmlspecialchars($historia_existente['numero_historia']); ?></p></div>
                <div class="dato-item"><strong>Centro de Salud:</strong> <p><?php echo htmlspecialchars($historia_existente['centro_salud']); ?></p></div>
                <div class="dato-item"><strong>Fecha:</strong> <p><?php echo htmlspecialchars($historia_existente['fecha']); ?></p></div>
                <h3>Datos Personales</h3>
                <div class="dato-item"><strong>Cédula:</strong> <p><?php echo htmlspecialchars($historia_existente['ci_paciente']); ?></p></div>
                <div class="dato-item"><strong>Sexo:</strong> <p><?php echo htmlspecialchars($historia_existente['sexo']); ?></p></div>
                <div class="dato-item"><strong>Teléfono:</strong> <p><?php echo htmlspecialchars($historia_existente['telefono']); ?></p></div>
                <div class="dato-item"><strong>Estado Civil:</strong> <p><?php echo htmlspecialchars($historia_existente['estado_civil']); ?></p></div>
                <div class="dato-item"><strong>Nacionalidad:</strong> <p><?php echo htmlspecialchars($historia_existente['nacionalidad']); ?></p></div>
                <div class="dato-item"><strong>Hijos:</strong> <p><?php echo htmlspecialchars($historia_existente['hijos']); ?></p></div>
                <div class="dato-item"><strong>Religión:</strong> <p><?php echo htmlspecialchars($historia_existente['religion']); ?></p></div>
                <div class="dato-item"><strong>Grado de Instrucción:</strong> <p><?php echo htmlspecialchars($historia_existente['grado_instruccion']); ?></p></div>
                <div class="dato-item"><strong>Ocupación:</strong> <p><?php echo htmlspecialchars($historia_existente['ocupacion']); ?></p></div>
                <div class="dato-item"><strong>Dirección:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['direccion'])); ?></p></div>
                <h3>Motivo y Antecedentes</h3>
                <div class="dato-item"><strong>Motivo de Consulta:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['motivo_consulta'])); ?></p></div>
                <div class="dato-item"><strong>Antecedentes Personales:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['antecedentes_personales'])); ?></p></div>
                <div class="dato-item"><strong>Antecedentes Familiares:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['antecedentes_familiares'])); ?></p></div>
                <div class="dato-item"><strong>Antecedentes Psiquiátricos:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['antecedentes_psiquiatricos'])); ?></p></div>
                <div class="dato-item"><strong>Antecedentes Médicos:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['antecedentes_medicos'])); ?></p></div>
                <div class="dato-item"><strong>Antecedentes de Pareja:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['antecedentes_pareja'])); ?></p></div>
                <h3>Diagnóstico</h3>
                <div class="dato-item"><strong>Impresión Diagnóstica:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['impresion_diagnostica'])); ?></p></div>
            <?php else: ?>
                <h3>Datos Generales</h3>
                <div class="dato-item"><strong>N° de Historia:</strong> <p><?php echo htmlspecialchars($historia_existente['numero_historia']); ?></p></div>
                <div class="dato-item"><strong>Centro de Salud:</strong> <p><?php echo htmlspecialchars($historia_existente['centro_salud']); ?></p></div>
                <div class="dato-item"><strong>Fecha:</strong> <p><?php echo htmlspecialchars($historia_existente['fecha']); ?></p></div>
                <h3>Datos Personales del Infante</h3>
                <div class="dato-item"><strong>Lugar de Nacimiento:</strong> <p><?php echo htmlspecialchars($historia_existente['lugar_nacimiento']); ?></p></div>
                <div class="dato-item"><strong>Institución Escolar:</strong> <p><?php echo htmlspecialchars($historia_existente['institucion_escolar']); ?></p></div>
                <h3>Datos Familiares</h3>
                <div class="dato-item"><strong>Datos del Padre:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['datos_padre'])); ?></p></div>
                <div class="dato-item"><strong>Datos de la Madre:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['datos_madre'])); ?></p></div>
                <div class="dato-item"><strong>¿Padres viven juntos?:</strong> <p><?php echo htmlspecialchars($historia_existente['padres_viven_juntos']); ?></p></div>
                <div class="dato-item"><strong>¿Están casados?:</strong> <p><?php echo htmlspecialchars($historia_existente['estan_casados']); ?></p></div>
                <div class="dato-item"><strong>Hermanos:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['hermanos'])); ?></p></div>
                <h3>Motivo y Antecedentes</h3>
                <div class="dato-item"><strong>Motivo de Consulta:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['motivo_consulta'])); ?></p></div>
                <div class="dato-item"><strong>Tipo de Embarazo:</strong> <p><?php echo htmlspecialchars($historia_existente['antecedentes_embarazo']); ?></p></div>
                <div class="dato-item"><strong>Parto (Lugar):</strong> <p><?php echo htmlspecialchars($historia_existente['antecedentes_parto']); ?></p></div>
                <div class="dato-item"><strong>Estado del niño/a al nacer:</strong> <p><?php echo htmlspecialchars($historia_existente['estado_nino_nacer']); ?></p></div>
                <div class="dato-item"><strong>Desarrollo Psicomotor:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['desarrollo_psicomotor'])); ?></p></div>
                <div class="dato-item"><strong>Hábitos de Independencia:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['habitos_independencia'])); ?></p></div>
                <div class="dato-item"><strong>Condiciones Generales de Salud:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['condiciones_salud'])); ?></p></div>
                <div class="dato-item"><strong>Vida Social:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['vida_social'])); ?></p></div>
                <h3>Plan Terapéutico</h3>
                <div class="dato-item"><strong>Plan Psicoterapéutico:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['plan_psicoterapeutico'])); ?></p></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="selection-container">
            <p>Este paciente no tiene una historia clínica. Selecciona el tipo de historia a crear:</p>
            <div class="selection-grid">
                <button class="selection-card" onclick="mostrarForm('adulto')">
                    <i class="fa-solid fa-user"></i>
                    <h3>Historia de Adulto</h3>
                </button>
                <button class="selection-card" onclick="mostrarForm('infantil')">
                    <i class="fa-solid fa-child"></i>
                    <h3>Historia Infantil</h3>
                </button>
            </div>
        </div>

        <div id="form-adulto" class="hidden-form">
            <form action="guardar_historia.php" method="POST">
                <input type="hidden" name="tipo_historia" value="adulto">
                <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                <h2>Historia Clínica de Adulto</h2>
                <div class="form-grid">
                    <div class="form-group"><label>N° de Historia:</label><input type="text" name="numero_historia"></div>
                    <div class="form-group"><label>Centro de Salud:</label><input type="text" name="centro_salud" value="WebPSY Consultorio"></div>
                    <div class="form-group"><label>Fecha:</label><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></div>
                </div>
                <h3>Datos Personales</h3>
                <div class="form-grid">
                    <div class="form-group"><label>Cédula:</label><input type="text" name="ci_paciente" value="<?php echo htmlspecialchars($paciente['cedula']); ?>"></div>
                    <div class="form-group"><label>Sexo:</label><input type="text" name="sexo"></div>
                    <div class="form-group"><label>Teléfono:</label><input type="text" name="telefono"></div>
                    <div class="form-group"><label>Edo. Civil:</label><input type="text" name="estado_civil"></div>
                    <div class="form-group"><label>Nacionalidad:</label><input type="text" name="nacionalidad"></div>
                    <div class="form-group"><label>Hijos (cant y edades):</label><input type="text" name="hijos"></div>
                    <div class="form-group"><label>Religión:</label><input type="text" name="religion"></div>
                    <div class="form-group"><label>Grado de Instrucción:</label><input type="text" name="grado_instruccion"></div>
                    <div class="form-group"><label>Ocupación:</label><input type="text" name="ocupacion"></div>
                    <div class="form-group full-width"><label>Dirección:</label><textarea name="direccion"></textarea></div>
                </div>
                <h3>Motivo y Antecedentes</h3>
                <div class="form-grid">
                    <div class="form-group full-width"><label>Motivo de Consulta:</label><textarea name="motivo_consulta" rows="4"></textarea></div>
                    <div class="form-group full-width"><label>Antecedentes Personales:</label><textarea name="antecedentes_personales" rows="3"></textarea></div>
                    <div class="form-group full-width"><label>Antecedentes Familiares:</label><textarea name="antecedentes_familiares" rows="3"></textarea></div>
                    <div class="form-group full-width"><label>Antecedentes Psiquiátricos:</label><textarea name="antecedentes_psiquiatricos" rows="3"></textarea></div>
                    <div class="form-group full-width"><label>Antecedentes Médicos:</label><textarea name="antecedentes_medicos" rows="3"></textarea></div>
                    <div class="form-group full-width"><label>Antecedentes de Pareja:</label><textarea name="antecedentes_pareja" rows="3"></textarea></div>
                </div>
                <h3>Diagnóstico</h3>
                <div class="form-group full-width"><label>Impresión Diagnóstica:</label><textarea name="impresion_diagnostica" rows="5"></textarea></div>
                <button type="submit" class="btn">Guardar Historia de Adulto</button>
            </form>
        </div>

        <div id="form-infantil" class="hidden-form">
            <form action="guardar_historia.php" method="POST">
                <input type="hidden" name="tipo_historia" value="infantil">
                <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                <h2>Historia Clínica Infantil</h2>
                <div class="form-grid">
                    <div class="form-group"><label>N° de Historia:</label><input type="text" name="numero_historia"></div>
                    <div class="form-group"><label>Centro de Salud:</label><input type="text" name="centro_salud" value="WebPSY Consultorio"></div>
                    <div class="form-group"><label>Fecha:</label><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></div>
                </div>
                <h3>Datos Personales del Infante</h3>
                <div class="form-grid">
                    <div class="form-group"><label>Lugar de Nacimiento:</label><input type="text" name="lugar_nacimiento"></div>
                    <div class="form-group"><label>Institución Escolar:</label><input type="text" name="institucion_escolar"></div>
                </div>
                <h3>Datos Familiares</h3>
                <div class="form-group full-width"><label>Datos del Padre (Nombre, Edad, CI, Ocupación, etc.):</label><textarea name="datos_padre" rows="3"></textarea></div>
                <div class="form-group full-width"><label>Datos de la Madre (Nombre, Edad, CI, Ocupación, etc.):</label><textarea name="datos_madre" rows="3"></textarea></div>
                <div class="form-grid">
                    <div class="form-group"><label>¿Padres viven juntos? (Si/No):</label><input type="text" name="padres_viven_juntos"></div>
                    <div class="form-group"><label>¿Están casados? (Si/No):</label><input type="text" name="estan_casados"></div>
                </div>
                <div class="form-group full-width"><label>Hermanos (Nombre, Edad, Sexo, etc.):</label><textarea name="hermanos" rows="3"></textarea></div>
                <h3>Motivo y Antecedentes</h3>
                <div class="form-group full-width"><label>Motivo de Consulta:</label><textarea name="motivo_consulta" rows="4"></textarea></div>
                <div class="form-grid">
                    <div class="form-group"><label>Tipo de Embarazo:</label><input type="text" name="antecedentes_embarazo"></div>
                    <div class="form-group"><label>Parto (Lugar):</label><input type="text" name="antecedentes_parto"></div>
                    <div class="form-group"><label>Estado del niño/a al nacer:</label><input type="text" name="estado_nino_nacer"></div>
                </div>
                <div class="form-group full-width"><label>Desarrollo Psicomotor (Control cefálico, gateo, etc.):</label><textarea name="desarrollo_psicomotor" rows="3"></textarea></div>
                <div class="form-group full-width"><label>Hábitos de Independencia (Comer solo, etc.):</label><textarea name="habitos_independencia" rows="3"></textarea></div>
                <div class="form-group full-width"><label>Condiciones Generales de Salud (Enfermedades, cirugías, etc.):</label><textarea name="condiciones_salud" rows="3"></textarea></div>
                <div class="form-group full-width"><label>Vida Social:</label><textarea name="vida_social" rows="3"></textarea></div>
                <h3>Plan Terapéutico</h3>
                <div class="form-group full-width"><label>Plan Psicoterapéutico:</label><textarea name="plan_psicoterapeutico" rows="5"></textarea></div>
                <button type="submit" class="btn">Guardar Historia Infantil</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    function mostrarForm(tipo) {
        document.querySelector('.selection-container').style.display = 'none'; // Oculta los botones de selección
        
        // Ocultamos ambos formularios primero
        document.getElementById('form-adulto').style.display = 'none';
        document.getElementById('form-infantil').style.display = 'none';

        if (tipo === 'adulto') {
            // Mostramos el formulario como un contenedor flexible
            document.getElementById('form-adulto').style.display = 'flex';
        } else if (tipo === 'infantil') {
            // Mostramos el formulario como un contenedor flexible
            document.getElementById('form-infantil').style.display = 'flex';
        }
    }
</script>
</body>
</html>