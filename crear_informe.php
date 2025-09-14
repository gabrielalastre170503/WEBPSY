<?php
session_start();
include 'conexion.php';

// Seguridad y obtención de datos del paciente
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) { 
    header('Location: login.php'); 
    exit(); 
}
if (!isset($_GET['paciente_id']) || !is_numeric($_GET['paciente_id'])) { 
    die("Error: No se ha especificado un paciente."); 
}

$paciente_id = $_GET['paciente_id'];
$stmt_paciente = $conex->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$paciente = $stmt_paciente->get_result()->fetch_assoc();
if (!$paciente) { 
    die("Error: Paciente no encontrado."); 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Informe Psicológico</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos generales */
        body {
            background-color: #f0f2f5;
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 30px 20px;
        }
        .main-container {
            background: white;
            padding: 180px;
            padding-top: 100px;
            border-radius: 16px;
            width: 100%;
            max-width: 840px;
            margin: 0 auto;
            border: 1px solid #e0e0e0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); /* Sombra añadida */
        }
        /* Títulos */
        .main-container h1, .main-container h3 {
            color: #333;
        }
        .main-container h1 {
            text-align: center;
            font-size: 28px;
            margin-top: 0;
            margin-bottom: 5px;
        }
        .main-container .subtitulo-paciente {
            text-align: center;
            margin-top: 0;
            margin-bottom: 30px;
            color: #777;
        }
        .main-container h3 {
            font-size: 20px;
            margin-top: 30px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            text-align: left;
        }
        /* Rejilla para organizar los campos */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 30px;
        }
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
            font-family: "Poppins", sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #02b1f4;
            box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.2);
        }
        textarea {
            resize: vertical;
        }
        /* --- ESTILOS PARA LOS BOTONES DE ACCIÓN (ESTILO CONTORNO) --- */
.button-group {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 40px;
}

/* Estilo base para todos los botones de acción */
.action-links.approve,
.action-links.secondary {
    padding: 10px 25px;
    font-weight: 500;
    border: 2px solid;
    background-color: transparent;
    transition: all 0.3s ease;
    border-radius: 8px;
    text-decoration: none !important;
}

/* Estilo para el botón principal (azul) */
.action-links.approve {
    border-color: #02b1f4;
    color: #02b1f4 !important;
}
.action-links.approve:hover {
    background-color: #02b1f4;
    color: white !important;
}

/* Estilo para el botón secundario (gris) */
.action-links.secondary {
    border-color: #6c757d;
    color: #6c757d !important;
}
.action-links.secondary:hover {
    background-color: #6c757d;
    color: white !important;
}
    
    .back-link { display: block; text-align: center; margin-top: 20px; color: #818181; text-decoration: none; font-size: 14px; }
    
    /* Estilos para la impresión */
    @media print {
        body { background-color: white; padding: 0; }
        .main-container { box-shadow: none; margin: 0; max-width: 100%; border: 1px solid #ccc; }
        .no-print { display: none; }
    }

        /* --- Estilo para el enlace de volver --- */
.back-link-top {
    display: block;
    text-align: center;
    margin-bottom: 30px;
    margin-top: -20px; /* Sube el enlace para que quede más cerca del título */
    color: #555;
    text-decoration: none;
    font-weight: 500;
}
.back-link-top:hover {
    color: #02b1f4;
}
    </style>
</head>
<body>
<div class="main-container">
    <div class="no-print">
    <h1>Informe Psicológico de: <?php echo htmlspecialchars($paciente['nombre_completo']); ?></h1>
    <br>
    <a href="gestionar_paciente.php?paciente_id=<?php echo $paciente_id; ?>" class="back-link-top">&larr; Volver a Gestión de Paciente</a>
</div>

    <form action="guardar_informe.php" method="POST">
        <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
        
        <h3>Datos de Referencia</h3>
        <div class="form-grid">
            <div class="form-group"><label>N° de Historia:</label><input type="text" name="numero_historia" placeholder="Ej: 12345"></div>
            <div class="form-group"><label>Fecha de Evaluación:</label><input type="date" name="fecha_evaluacion" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group full-width" style="margin-top: -25px;">
    <label>Referido por:</label>
    <input type="text" name="referido_por" placeholder="Ej: Dr. Juan Pérez">
</div>
        </div>

        <div class="form-group full-width" style="margin-top: 10x;">
            <label>Motivo de la Referencia:</label>
            <textarea name="motivo_referencia" rows="3" placeholder="Descripción del motivo..."></textarea>
        </div>
        <div class="form-group full-width" style="margin-bottom: 50px;">
            <label>Actitud ante la Evaluación:</label>
            <textarea name="actitud_ante_evaluacion" rows="4" placeholder="Describe la actitud y comportamiento del paciente..."></textarea>
        </div>

        <h3>Resultados de la Evaluación</h3>
        <div class="form-group full-width"><label>Área Visomotriz:</label><textarea name="area_visomotriz" rows="4"></textarea></div>
        <div class="form-group full-width"><label>Área Intelectual:</label><textarea name="area_intelectual" rows="4"></textarea></div>
        <div class="form-group full-width"><label>Área Emocional:</label><textarea name="area_emocional" rows="4"></textarea></div>
        <div class="form-group full-width"><label>Otros Resultados Relevantes:</label><textarea name="resultados_adicionales" rows="4"></textarea></div>
        
        <h3>Recomendaciones</h3>
        <div class="form-group full-width"><label>Recomendaciones:</label><textarea name="recomendaciones" rows="6"></textarea></div>
        
        <div class="button-group no-print" style="margin-top: 55px;">
    <a href="#" onclick="event.preventDefault(); this.closest('form').submit();" class="action-links approve">Guardar Informe</a>
    <a href="#" onclick="window.print(); return false;" class="action-links secondary">Imprimir</a>
</div>
        <a href="gestionar_paciente.php?paciente_id=<?php echo $paciente_id; ?>" class="back-link no-print">Cancelar y Volver</a>
    </form>
</div>
</body>
</html>