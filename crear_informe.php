<?php
session_start();
include 'conexion.php';

// Seguridad y obtención de datos del paciente
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) { 
    header('Location: login.php'); 
    exit(); 
}
if (!isset($_GET['paciente_id'])) { 
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
            background-color: white;
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 30px 250px; /* Espacio para que el formulario no pegue a los bordes */
        }

        /* Contenedor principal del formulario */
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 850px; /* Un poco más ancho para los campos de texto */
            margin: 0 auto;
        }

        /* Títulos */
        .form-container h1, .form-container h3 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .form-container h1 {
            font-size: 28px;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .form-container h3 {
            font-size: 20px;
            margin-top: 30px;
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
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            font-family: "Poppins", sans-serif;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #02b1f4;
            box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.2);
        }

        textarea {
            resize: vertical;
        }

        /* Estilos para los botones */
        .button-group {
            display: flex;
            gap: 20px;
            margin-top: 40px;
        }

        .btn {
            flex-grow: 1;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(45deg, #02b1f4, #00c2ff);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3);
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #818181;
            text-decoration: none;
            font-size: 14px;
        }

        /* Estilos para la impresión */
        @media print {
            body { background-color: white; padding: 0; }
            .form-container { box-shadow: none; margin: 0; max-width: 100%; border: 1px solid #ccc; }
            .no-print { display: none; }
        }
        /* --- ESTILOS MEJORADOS PARA LOS BOTONES DEL FORMULARIO DE INFORME --- */
.button-group {
    display: flex;
    justify-content: center; /* Centra los botones */
    gap: 40px; /* Espacio entre los botones */
    margin-top: 30px;
}

.button-group button { /* Estilo base para ambos botones */
    flex-grow: 1; /* Ocupan el mismo espacio */
    max-width: 250px; /* Pero con un ancho máximo */
    padding: 12px;
    font-size: 16px;
    font-weight: 100;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit {
    background: linear-gradient(45deg, #02b1f4, #00c2ff);
    box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3);
}

.btn-submit:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
}

.btn-secondary {
    background: #6c757d;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}
    </style>
</head>
<body>
<div class="main-container">
    <div class="no-print">
        <h1>Informe Psicológico</h1>
        <h3>Paciente: <?php echo htmlspecialchars($paciente['nombre_completo']); ?></h3>
    </div>

    <form action="guardar_informe.php" method="POST">
        <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
        
        <h3>Datos de Referencia</h3>
        <div class="form-grid">
            <div class="form-group"><label>N° de Historia:</label><input type="text" name="numero_historia" placeholder="Ej: 12345"></div>
            <div class="form-group"><label>Fecha de Evaluación:</label><input type="date" name="fecha_evaluacion" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group full-width"><label>Referido por:</label><input type="text" name="referido_por" placeholder="Ej: Dr. Juan Pérez"></div>
        </div>

        <div class="form-group full-width" style="margin-top: 15px;">
            <label>Motivo de la Referencia:</label>
            <textarea name="motivo_referencia" rows="3" placeholder="Descripción del motivo..."></textarea>
            </div>

        <div class="form-group full-width" style="margin-top: 15px;">
            <label>Actitud ante la Evaluación:</label>
            <textarea name="actitud_ante_evaluacion" rows="3" placeholder="Describe la actitud y comportamiento del paciente..."></textarea>
        </div>

        <h3>Resultados de la Evaluación</h3>
        <div class="form-group full-width"><label>Área Visomotriz:</label><textarea name="area_visomotriz" rows="4"></textarea></div>
        <div class="form-group full-width" style="margin-top: 15px;" ><label>Área Intelectual:</label><textarea name="area_intelectual" rows="4"></textarea></div>
        <div class="form-group full-width" style="margin-top: 15px;" ><label>Área Emocional:</label><textarea name="area_emocional" rows="4"></textarea></div>
        <div class="form-group full-width" style="margin-top: 15px;" ><label>Otros Resultados Relevantes:</label><textarea name="resultados_adicionales" rows="4"></textarea></div>
        
        <h3>Recomendaciones</h3>
        <div class="form-group full-width"><label>Recomendaciones:</label><textarea name="recomendaciones" rows="6"></textarea></div>
        
        <div class="button-group no-print">
    <button type="submit" class="btn-submit">Guardar Informe</button>
    <button type="button" class="btn-secondary" onclick="window.print()">Imprimir</button>
</div>
        <a href="gestionar_paciente.php?paciente_id=<?php echo $paciente_id; ?>" class="back-link no-print">Cancelar y Volver</a>
    </form>
</div>
</body>
</html>