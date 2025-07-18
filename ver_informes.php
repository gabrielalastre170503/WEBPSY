<?php
session_start();
include 'conexion.php';

// Seguridad y obtención de datos del paciente
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) { header('Location: login.php'); exit(); }
if (!isset($_GET['paciente_id'])) { die("Error: No se ha especificado un paciente."); }

$paciente_id = $_GET['paciente_id'];
$stmt_paciente = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$paciente = $stmt_paciente->get_result()->fetch_assoc();

// Obtener todos los informes de este paciente
$stmt_informes = $conex->prepare("SELECT id, fecha_evaluacion, motivo_referencia FROM informes_psicologicos WHERE paciente_id = ? ORDER BY fecha_evaluacion DESC");
$stmt_informes->bind_param("i", $paciente_id);
$stmt_informes->execute();
$informes = $stmt_informes->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informes de Paciente</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* (Reutilizamos los estilos del panel de gestión) */
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; }
        .main-container { max-width: 900px; margin: 30px auto; padding: 30px; background-color: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .informes-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .informes-table th, .informes-table td { padding: 15px; text-align: left; border-bottom: 1px solid #e9e9e9; }
    </style>
</head>
<body>
<div class="main-container">
    <h1>Informes de: <?php echo htmlspecialchars($paciente['nombre_completo']); ?></h1>
    <a href="gestionar_paciente.php?paciente_id=<?php echo $paciente_id; ?>">&larr; Volver a la gestión del paciente</a>

    <?php if ($informes->num_rows > 0): ?>
        <table class="informes-table">
            <thead><tr><th>Fecha de Evaluación</th><th>Motivo</th><th>Acción</th></tr></thead>
            <tbody>
            <?php while ($informe = $informes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($informe['fecha_evaluacion']); ?></td>
                    <td><?php echo htmlspecialchars($informe['motivo_referencia']); ?></td>
                    <td><a href="ver_informe_detalle.php?informe_id=<?php echo $informe['id']; ?>">Ver Detalle</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No se han encontrado informes para este paciente.</p>
    <?php endif; ?>
</div>
</body>
</html>