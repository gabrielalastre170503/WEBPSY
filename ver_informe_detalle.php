<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) { header('Location: login.php'); exit(); }
if (!isset($_GET['informe_id'])) { die("Error: No se ha especificado un informe."); }

$informe_id = $_GET['informe_id'];
$stmt = $conex->prepare("SELECT i.*, p.nombre_completo as paciente_nombre, p.cedula as paciente_cedula FROM informes_psicologicos i JOIN usuarios p ON i.paciente_id = p.id WHERE i.id = ?");
$stmt->bind_param("i", $informe_id);
$stmt->execute();
$informe = $stmt->get_result()->fetch_assoc();
if (!$informe) { die("Informe no encontrado."); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Informe</title>
    <style>
        body { font-family: "Poppins", sans-serif; line-height: 1.6; }
        .informe-container { max-width: 800px; margin: 30px auto; padding: 40px; border: 1px solid #ccc; }
        h1, h2 { text-align: center; border-bottom: 1px solid #000; padding-bottom: 10px; }
        .dato-item { margin-bottom: 20px; }
        .dato-item strong { display: block; font-size: 1.1em; }
        .no-print { text-align: center; margin-top: 30px; }
        @media print {
            .no-print { display: none; }
            .informe-container { border: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
<div class="informe-container">
    <h1>Informe Psicológico</h1>
    <h2>Datos del Paciente</h2>
    <div class="dato-item"><strong>Nombre y Apellido:</strong> <?php echo htmlspecialchars($informe['paciente_nombre']); ?></div>
    <div class="dato-item"><strong>Cédula:</strong> <?php echo htmlspecialchars($informe['paciente_cedula']); ?></div>
    <div class="dato-item"><strong>N° de Historia:</strong> <?php echo htmlspecialchars($informe['numero_historia']); ?></div>
    <div class="dato-item"><strong>Fecha de Evaluación:</strong> <?php echo htmlspecialchars($informe['fecha_evaluacion']); ?></div>
    
    <h2>Evaluación</h2>
    <div class="dato-item"><strong>Motivo de la Referencia:</strong><p><?php echo nl2br(htmlspecialchars($informe['motivo_referencia'])); ?></p></div>
    <div class="dato-item"><strong>Actitud ante la Evaluación:</strong><p><?php echo nl2br(htmlspecialchars($informe['actitud_ante_evaluacion'])); ?></p></div>
    
    <h2>Resultados</h2>
    <div class="dato-item"><strong>Área Visomotriz:</strong><p><?php echo nl2br(htmlspecialchars($informe['area_visomotriz'])); ?></p></div>
    <div class="dato-item"><strong>Área Intelectual:</strong><p><?php echo nl2br(htmlspecialchars($informe['area_intelectual'])); ?></p></div>
    <div class="dato-item"><strong>Área Emocional:</strong><p><?php echo nl2br(htmlspecialchars($informe['area_emocional'])); ?></p></div>
    
    <h2>Recomendaciones</h2>
    <div class="dato-item"><p><?php echo nl2br(htmlspecialchars($informe['recomendaciones'])); ?></p></div>
</div>
<div class="no-print">
    <button onclick="window.print()" style="padding:10px 20px; cursor:pointer;">Imprimir Informe</button>
    
    <a href="borrar_informe.php?informe_id=<?php echo $informe['id']; ?>" 
       onclick="return confirm('¿Estás seguro de que quieres borrar este informe? Esta acción es irreversible.');"
       style="background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 15px;">
       Borrar Informe
    </a>

    <p style="margin-top: 20px;"><a href="ver_informes.php?paciente_id=<?php echo $informe['paciente_id']; ?>">Volver a la lista de informes</a></p>
</div>
</body>
</html>