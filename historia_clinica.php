<?php
session_start();
include 'conexion.php';

// Seguridad y obtención de datos del paciente
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_GET['paciente_id']) || !is_numeric($_GET['paciente_id'])) {
    die("Error: No se ha especificado un paciente válido.");
}

$paciente_id = $_GET['paciente_id'];
$stmt_paciente = $conex->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$paciente_result = $stmt_paciente->get_result();
if ($paciente_result->num_rows === 0) {
    die("Error: Paciente no encontrado.");
}
$paciente = $paciente_result->fetch_assoc();
$stmt_paciente->close();

$entrevistador_nombre = $_SESSION['nombre_completo'];

// --- LÓGICA CORRECTA PARA BUSCAR LA HISTORIA ---
$historia_existente = null;
$tipo_historia_existente = '';

// Primero, buscamos en la tabla de adultos
$stmt_adulto = $conex->prepare("SELECT * FROM historias_adultos WHERE paciente_id = ? LIMIT 1");
$stmt_adulto->bind_param("i", $paciente_id);
$stmt_adulto->execute();
$resultado_adulto = $stmt_adulto->get_result();
if ($resultado_adulto->num_rows > 0) {
    $historia_existente = $resultado_adulto->fetch_assoc();
    $tipo_historia_existente = 'adulto';
}
$stmt_adulto->close();

// Si no encontramos nada, ENTONCES buscamos en la tabla infantil
if (!$historia_existente) {
    $stmt_infantil = $conex->prepare("SELECT * FROM historias_infantiles WHERE paciente_id = ? LIMIT 1");
    $stmt_infantil->bind_param("i", $paciente_id);
    $stmt_infantil->execute();
    $resultado_infantil = $stmt_infantil->get_result();
    if ($resultado_infantil->num_rows > 0) {
        $historia_existente = $resultado_infantil->fetch_assoc();
        $tipo_historia_existente = 'infantil';
    }
    $stmt_infantil->close();
}
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
        .main-container { max-width: 1200px; margin: 30px auto; padding: 30px; background-color: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
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
        margin-top: -40px;
        }  
        .hidden-form h2 { 
         text-align: center; 
        }
        .historia-vista .dato-item { margin-bottom: 15px; }
        .historia-vista .dato-item strong { color: #333; display: block; }
        .historia-vista .dato-item p { margin: 5px 0 0 0; padding-left: 10px; border-left: 3px solid #eee; }

        /* --- NUEVO DISEÑO ELEGANTE PARA SELECCIÓN DE HISTORIA --- */
.selection-container {
    text-align: center;
    background-color: white; /* <-- LÍNEA CAMBIADA */
    padding: 30px;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    margin-top: -20px; /* <-- AÑADE ESTA LÍNEA */
}
.selection-container p {
    font-size: 1.1em;
    color: #555;
    margin-top: 0;
}
.selection-container h2 {
    border: none;
    padding: 0;
    margin: 5px 0 30px 0;
}
.selection-grid {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap; /* Para que se adapte en pantallas pequeñas */
}
.selection-card {
    background-color: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 30px;
    width: 280px;
    cursor: pointer;
    text-align: center;
    transition: all 0.3s ease;
}
.selection-card:hover {
    transform: translateY(-8px);
    border-color: #02b1f4;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}
.selection-card i {
    font-size: 45px;
    color: #02b1f4;
    margin-bottom: 20px;
}
.selection-card h3 {
    border: none;
    margin: 0 0 10px 0;
    padding: 0;
    font-size: 1.3em;
    color: #333;
}
.selection-card .card-description {
    font-size: 14px;
    color: #777;
    line-height: 1.5;
    margin: 0;
}

/* --- CORRECCIÓN PARA EL FORMULARIO INTERNO --- */
.main-container form {
      /* Quita la sombra del formulario */
    box-shadow: none;
    padding: 30;        /* Quita el espacio interior extra */
    width: 90%;       /* Hace que el formulario ocupe todo el ancho del contenedor */
    text-align: left;  /* Alinea el contenido a la izquierda */
}
/* --- AJUSTE PARA TÍTULOS DENTRO DE FORMULARIOS DE HISTORIA --- */
.hidden-form h2 {
    margin-top: 0; /* Reduce el espacio superior a cero */
    border-bottom: none; /* Quita la línea de abajo para un look más limpio */
    padding-bottom: 0; /* Quita el espacio inferior de la línea */
    margin-bottom: 30px; /* Mantiene un buen espacio antes de los campos */
}
/* --- ESTILO PARA HACER EL ENLACE DE VOLVER MÁS FÁCIL DE PULSAR --- */
.back-link {
    display: inline-block; /* Permite que el enlace tenga padding */
    padding: 20px;         /* Añade un área clicable invisible de 10px alrededor del texto */
    margin-left: -10px;    /* Compensa el padding izquierdo para que el texto no se mueva */
    margin-bottom: 20px;
    color: #555;
    text-decoration: none;
    font-weight: 500;
}

.back-link:hover {
    text-decoration: underline; /* Opcional: añade un subrayado al pasar el mouse */
}
/* Estilo para el botón de eliminar hermano */
.remove-hermano-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    font-size: 16px;
    font-weight: bold;
    line-height: 25px;
    text-align: center;
    cursor: pointer;
    padding: 0;
}
.remove-hermano-btn:hover {
    background-color: #dc3545;
    color: white;
}
/* --- ESTILOS MEJORADOS PARA BOTONES DEL FORMULARIO INFANTIL --- */

/* Estilo para el botón "Añadir Hermano" */
.btn-add {
    width: 100%;
    padding: 12px;
    margin-top: 10px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    background-color: transparent;
    color: #02b1f4;
    border: 2px dashed #02b1f4; /* Borde punteado para indicar "añadir" */
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-add:hover {
    background-color: rgba(2, 177, 244, 0.1); /* Fondo azul muy sutil al pasar el mouse */
    color: #028ac7;
    border-color: #028ac7;
}

/* --- ESTILO PARA BOTONES DE GUARDAR (ESTILO CONTORNO) --- */
        .form-actions {
            text-align: center; /* Centra el botón */
            margin-top: 30px;
        }

        .btn {
            cursor: pointer;
            padding: 12px 40px; /* Tamaño del botón */
            font-size: 17px;
            font-weight: 549;
            border-radius: 8px;
            transition: all 0.3s ease;
            
            /* Estilo de contorno */
            background-color: transparent;
            border: 2px solid #02b1f4;
            color: #02b1f4;
            
            /* Quitamos el ancho completo y la sombra inicial */
            width: auto;
            box-shadow: none;
            margin-top: 0; /* Reseteamos el margen que tenía antes */
        }

        .btn:hover {
            background-color: #02b1f4; /* Se rellena al pasar el mouse */
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
        }

        /* --- AJUSTE DE MARGEN PARA TÍTULOS DE FORMULARIO --- */
.hidden-form h2 {
    margin-top: -90px; /* <-- Reduce el espacio superior a cero */
    border-bottom: none; /* Opcional: Quita la línea de abajo para un look más limpio */
    padding-bottom: 0; /* Opcional: Quita el espacio de la línea */
    margin-bottom: 30px; /* Mantiene un buen espacio antes de los campos */
}
    </style>
</head>
<body>
<div class="main-container">
    <h1>Historia Clínica de: <?php echo htmlspecialchars($paciente['nombre_completo']); ?></h1>
    <a href="gestionar_paciente.php?paciente_id=<?php echo $paciente_id; ?>" class="back-link">&larr; Volver a Gestión de Paciente</a>

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

<?php else: // Si es infantil ?>
    <h3>Datos Generales</h3>
    <div class="dato-item"><strong>N° de Historia:</strong> <p><?php echo htmlspecialchars($historia_existente['numero_historia']); ?></p></div>
    <div class="dato-item"><strong>Centro de Salud:</strong> <p><?php echo htmlspecialchars($historia_existente['centro_salud']); ?></p></div>
    <div class="dato-item"><strong>Fecha:</strong> <p><?php echo htmlspecialchars($historia_existente['fecha']); ?></p></div>

    <h3>Datos Personales del Infante</h3>
    <div class="dato-item"><strong>Lugar de Nacimiento:</strong> <p><?php echo htmlspecialchars($historia_existente['lugar_nacimiento']); ?></p></div>
    <div class="dato-item"><strong>Institución Escolar:</strong> <p><?php echo htmlspecialchars($historia_existente['institucion_escolar']); ?></p></div>

    <h3>Datos del Padre</h3>
    <div class="dato-item"><strong>Nombre y Apellido:</strong> <p><?php echo htmlspecialchars($historia_existente['padre_nombre']); ?></p></div>
    <div class="dato-item"><strong>Edad:</strong> <p><?php echo htmlspecialchars($historia_existente['padre_edad']); ?></p></div>
    <div class="dato-item"><strong>C.I.:</strong> <p><?php echo htmlspecialchars($historia_existente['padre_ci']); ?></p></div>
    <div class="dato-item"><strong>Ocupación:</strong> <p><?php echo htmlspecialchars($historia_existente['padre_ocupacion']); ?></p></div>
    <div class="dato-item"><strong>Teléfono:</strong> <p><?php echo htmlspecialchars($historia_existente['padre_telefono']); ?></p></div>
    <div class="dato-item"><strong>Dirección:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['padre_direccion'])); ?></p></div>

    <h3>Datos de la Madre</h3>
    <div class="dato-item"><strong>Nombre y Apellido:</strong> <p><?php echo htmlspecialchars($historia_existente['madre_nombre']); ?></p></div>
    <div class="dato-item"><strong>Edad:</strong> <p><?php echo htmlspecialchars($historia_existente['madre_edad']); ?></p></div>
    <div class="dato-item"><strong>C.I.:</strong> <p><?php echo htmlspecialchars($historia_existente['madre_ci']); ?></p></div>
    <div class="dato-item"><strong>Ocupación:</strong> <p><?php echo htmlspecialchars($historia_existente['madre_ocupacion']); ?></p></div>
    <div class="dato-item"><strong>Teléfono:</strong> <p><?php echo htmlspecialchars($historia_existente['madre_telefono']); ?></p></div>
    <div class="dato-item"><strong>Dirección:</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['madre_direccion'])); ?></p></div>

    <h3>Dinámica Familiar</h3>
    <div class="dato-item"><strong>¿Padres viven juntos?:</strong> <p><?php echo htmlspecialchars($historia_existente['padres_viven_juntos']); ?></p></div>
    <div class="dato-item"><strong>¿Están casados?:</strong> <p><?php echo htmlspecialchars($historia_existente['estan_casados']); ?></p></div>
    <div class="dato-item"><strong>Motivo de separación (si aplica):</strong> <p><?php echo nl2br(htmlspecialchars($historia_existente['motivo_separacion'])); ?></p></div>
    <div class="dato-item">
        <strong>Hermanos:</strong>
        <?php
            $hermanos = json_decode($historia_existente['hermanos'], true);
            if (!empty($hermanos)) {
                echo '<ul>';
                foreach ($hermanos as $hermano) {
                    echo '<li>' . htmlspecialchars($hermano['nombre']) . ' (' . htmlspecialchars($hermano['edad']) . ' años)</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>No se registraron hermanos.</p>';
            }
        ?>
    </div>

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
        
        <!-- SECCIÓN PARA CREAR UNA NUEVA HISTORIA (DISEÑO MEJORADO) -->
    <div class="selection-container">
        <p>Este paciente aún no tiene una historia clínica registrada.</p>
        <h2>Selecciona el tipo de historia a crear</h2>
        <div class="selection-grid">
            <div class="selection-card" onclick="mostrarForm('adulto')">
                <i class="fa-solid fa-user"></i>
                <h3>Historia de Adulto</h3>
                <p class="card-description">Para pacientes mayores de 18 años.</p>
            </div>
            <div class="selection-card" onclick="mostrarForm('infantil')">
                <i class="fa-solid fa-child"></i>
                <h3>Historia Infantil</h3>
                <p class="card-description">Para niños y adolescentes.</p>
            </div>
        </div>
    </div>

        <div id="form-adulto" class="hidden-form">
            <form action="guardar_historia.php" method="POST">
                <input type="hidden" name="tipo_historia" value="adulto">
                <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                <h2>Historia Clínica de Adulto</h2>
                <div class="form-grid">
                    <div class="form-group"><label>N° de Historia:</label><input type="text" name="numero_historia" value="<?php echo htmlspecialchars($paciente['cedula']); ?>"></div>
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
                    <div class="form-group full-width" style="margin-bottom: 40px;"><label>Dirección:</label><textarea name="direccion"></textarea></div>
                </div>
                <h3 style="margin-top: -10px;" >Motivo y Antecedentes</h3>
                <div class="form-grid">
                    <div class="form-group full-width"><label>Motivo de Consulta:</label><textarea name="motivo_consulta" rows="4"></textarea></div>
                    <div class="form-group full-width" style="margin-top: -30px;"><label>Antecedentes Personales:</label><textarea name="antecedentes_personales" rows="3"></textarea></div>
                    <div class="form-group full-width" style="margin-top: -30px;" ><label>Antecedentes Familiares:</label><textarea name="antecedentes_familiares" rows="3"></textarea></div>
                    <div class="form-group full-width" style="margin-top: -30px;" ><label>Antecedentes Psiquiátricos:</label><textarea name="antecedentes_psiquiatricos" rows="3"></textarea></div>
                    <div class="form-group full-width" style="margin-top: -30px;" ><label>Antecedentes Médicos:</label><textarea name="antecedentes_medicos" rows="3"></textarea></div>
                    <div class="form-group full-width" style="margin-top: -30px;" ><label>Antecedentes de Pareja:</label><textarea name="antecedentes_pareja" rows="3"></textarea></div>
                </div>
                <h3 style="margin-top: 10px;" >Diagnóstico</h3>
                <div class="form-group full-width"><label>Impresión Diagnóstica:</label><textarea name="impresion_diagnostica" rows="5"></textarea></div>
                <div class="form-actions">
    <button type="submit" class="btn">Guardar Historia de Adulto</button>
</div>
            </form>
        </div>

        <!-- FORMULARIO INFANTIL COMPLETO Y OCULTO -->
        <div id="form-infantil" class="hidden-form">
    <form action="guardar_historia.php" method="POST">
        <input type="hidden" name="tipo_historia" value="infantil">
        <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
        <h2>Historia Clínica Infantil</h2>
        <div class="form-grid">
            <div class="form-group"><label>N° de Historia:</label><input type="text" name="numero_historia" value="<?php echo htmlspecialchars($paciente['cedula']); ?>"></div>
            <div class="form-group"><label>Centro de Salud:</label><input type="text" name="centro_salud" value="WebPSY Consultorio"></div>
            <div class="form-group"><label>Fecha:</label><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></div>
        </div>
        <h3>Datos Personales del Infante</h3>
        <div class="form-grid">
            <div class="form-group"><label>Lugar de Nacimiento:</label><input type="text" name="lugar_nacimiento"></div>
            <div class="form-group"><label>Institución Escolar:</label><input type="text" name="institucion_escolar"></div>
        </div>
        
        <h3>Datos del Padre</h3>
        <div class="form-grid">
            
            <div class="form-group">
    <label>Nombre y Apellido:</label>
    <input type="text" name="padre_nombre">
</div>
            
            <div class="form-group"><label>Edad:</label><input type="number" name="padre_edad"></div>
            <div class="form-group"><label>C.I.:</label><input type="text" name="padre_ci"></div>
            <div class="form-group"><label>Nacionalidad:</label><input type="text" name="padre_nacionalidad"></div>
            <div class="form-group"><label>Religión:</label><input type="text" name="padre_religion"></div>
            <div class="form-group"><label>Grado de Instrucción:</label><input type="text" name="padre_instruccion"></div>
            <div class="form-group"><label>Ocupación:</label><input type="text" name="padre_ocupacion"></div>
            <div class="form-group"><label>Teléfono:</label><input type="text" name="padre_telefono"></div>
            <div class="form-group full-width"><label>Dirección:</label><textarea name="padre_direccion" rows="2"></textarea></div>
        </div>

        <h3>Datos de la Madre</h3>
        <div class="form-grid">
            <div class="form-group"><label>Nombre y Apellido:</label><input type="text" name="madre_nombre" id="madre_nombre"></div>
            <div class="form-group"><label>Edad:</label><input type="number" name="madre_edad"></div>
            <div class="form-group"><label>C.I.:</label><input type="text" name="madre_ci"></div>
            <div class="form-group"><label>Nacionalidad:</label><input type="text" name="madre_nacionalidad"></div>
            <div class="form-group"><label>Religión:</label><input type="text" name="madre_religion"></div>
            <div class="form-group"><label>Grado de Instrucción:</label><input type="text" name="madre_instruccion"></div>
            <div class="form-group"><label>Ocupación:</label><input type="text" name="madre_ocupacion"></div>
            <div class="form-group"><label>Teléfono:</label><input type="text" name="madre_telefono"></div>
            <div class="form-group full-width"><label>Dirección:</label><textarea name="madre_direccion" rows="2"></textarea></div>
        </div>

        <h3>Dinámica Familiar</h3>
        <div class="form-grid">
            <div class="form-group"><label>¿Padres viven juntos? (Si/No):</label><input type="text" name="padres_viven_juntos"></div>
            <div class="form-group"><label>¿Están casados? (Si/No):</label><input type="text" name="estan_casados"></div>
            <div class="form-group full-width"><label>Motivo de separación (si aplica):</label><textarea name="motivo_separacion" rows="2"></textarea></div>
            <div class="form-group full-width">
    <label>Tiene Hermanos?</label>
    <!-- Contenedor donde se añadirán los hermanos dinámicamente -->
    <div id="hermanos-container">
        <!-- Los campos para los hermanos se insertarán aquí con JS -->
    </div>
    <!-- Botón para añadir un nuevo hermano -->
    <button type="button" id="add-hermano-btn" class="btn-add">
    <i class="fa-solid fa-plus"></i> Añadir Hermano
</button>
</div>
        </div>

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
        
        <div class="form-actions">
    <button type="submit" class="btn">Guardar Historia Infantil</button>
</div>
    </form>
</div>
    <?php endif; ?>
</div>

<script>
    // Función para mostrar el formulario de Adulto o Infantil
    function mostrarForm(tipo) {
        document.querySelector('.selection-container').style.display = 'none';
        const formAdulto = document.getElementById('form-adulto');
        const formInfantil = document.getElementById('form-infantil');
        
        formAdulto.style.display = 'none';
        formInfantil.style.display = 'none';

        if (tipo === 'adulto') {
            formAdulto.style.display = 'flex';
        } else if (tipo === 'infantil') {
            formInfantil.style.display = 'flex';
        }
    }

    // Lógica que se ejecuta cuando la página ha cargado
    document.addEventListener('DOMContentLoaded', function() {
        const addHermanoBtn = document.getElementById('add-hermano-btn');
        const hermanosContainer = document.getElementById('hermanos-container');

        if (addHermanoBtn) {
            // Evento para AÑADIR un hermano
            addHermanoBtn.addEventListener('click', function() {
                const hermanoDiv = document.createElement('div');
                hermanoDiv.className = 'hermano-entry form-grid';
                hermanoDiv.style.marginBottom = '15px';
                hermanoDiv.style.padding = '15px';
                hermanoDiv.style.border = '1px solid #e0e0e0';
                hermanoDiv.style.borderRadius = '8px';
                hermanoDiv.style.position = 'relative'; // Necesario para el botón de borrar

                // Añadimos los campos y el nuevo botón de borrar
                hermanoDiv.innerHTML = `
                    <div class="form-group">
                        <label>Nombre del Hermano:</label>
                        <input type="text" name="hermano_nombre[]" placeholder="Nombre y Apellido">
                    </div>
                    <div class="form-group">
                        <label>Edad:</label>
                        <input type="number" name="hermano_edad[]" placeholder="Edad">
                    </div>
                    <div class="form-group">
                        <label>Sexo:</label>
                        <input type="text" name="hermano_sexo[]" placeholder="M / F">
                    </div>
                    <div class="form-group">
                        <label>Ocupación:</label>
                        <input type="text" name="hermano_ocupacion[]" placeholder="Estudiante, etc.">
                    </div>
                    <div class="form-group">
                        <label>¿Vive en el hogar?</label>
                        <input type="text" name="hermano_vive_hogar[]" placeholder="Sí / No">
                    </div>
                    <button type="button" class="remove-hermano-btn">&times;</button>
                `;
                hermanosContainer.appendChild(hermanoDiv);
            });

            // Evento para BORRAR un hermano (usando delegación de eventos)
            hermanosContainer.addEventListener('click', function(event) {
                if (event.target && event.target.classList.contains('remove-hermano-btn')) {
                    // Busca el contenedor del hermano (el div .hermano-entry) y lo elimina
                    event.target.closest('.hermano-entry').remove();
                }
            });
        }
    });
</script>
</body>
</html>