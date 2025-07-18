<?php
session_start();
include 'conexion.php';

// Seguridad y obtención de datos del paciente
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) { header('Location: login.php'); exit(); }
if (!isset($_GET['paciente_id'])) { die("Error: No se ha especificado un paciente."); }

$paciente_id = $_GET['paciente_id'];
$stmt_paciente = $conex->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$paciente = $stmt_paciente->get_result()->fetch_assoc();
if (!$paciente) { die("Error: Paciente no encontrado."); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe Psicológico</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; }
        .main-container { max-width: 900px; margin: 30px auto; padding: 30px; background-color: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 15px; }
        /* (Reutiliza los demás estilos del formulario de historia) */
        
        /* Estilos para la impresión */
        @media print {
            body { background-color: white; }
            .main-container { box-shadow: none; margin: 0; max-width: 100%; }
            .no-print { display: none; } /* Oculta botones al imprimir */
        }
    </style>
</head>
<body>
<div class="main-container">
    <h1>Informe Psicológico</h1>
    <h3>Paciente: <?php echo htmlspecialchars($paciente['nombre_completo']); ?></h3>
    <form action="guardar_informe.php" method="POST">
        <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
        <div class="form-grid">
            <div class="form-group"><label>N° de Historia:</label><input type="text" name="numero_historia"></div>
            <div class="form-group"><label>Fecha de Evaluación:</label><input type="date" name="fecha_evaluacion" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group"><label>Referido por:</label><input type="text" name="referido_por"></div>
        </div>
        <div class="form-group full-width"><label>Motivo de la Referencia:</label><textarea name="motivo_referencia" rows="3"></textarea></div>
        <div class="form-group full-width"><label>Actitud ante la Evaluación:</label><textarea name="actitud_ante_evaluacion" rows="3"></textarea></div>
        <h3>Resultados</h3>
        <div class="form-group full-width"><label>Área Visomotriz:</label><textarea name="area_visomotriz" rows="4"></textarea></div>
        <div class="form-group full-width"><label>Área Intelectual:</label><textarea name="area_intelectual" rows="4"></textarea></div>
        <div class="form-group full-width"><label>Área Emocional:</label><textarea name="area_emocional" rows="4"></textarea></div>
        <div class="form-group full-width"><label>Otros Resultados:</label><textarea name="resultados_adicionales" rows="4"></textarea></div>
        <h3>Recomendaciones</h3>
        <div class="form-group full-width"><label>Recomendaciones:</label><textarea name="recomendaciones" rows="6"></textarea></div>
        
        <div class="no-print">
            <button type="submit" class="btn">Guardar Informe</button>
            <button type="button" class="btn" onclick="window.print()" style="background-color:#6c757d;">Imprimir</button>
        </div>
    </form>
</div>
</body>
</html>