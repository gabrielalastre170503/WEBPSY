<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'])) {
    header('Location: ' . eco_url('login'));
    exit();
}
if (!isset($_GET['paciente_id']) || !is_numeric($_GET['paciente_id'])) {
    die("Error: No se ha especificado un paciente valido.");
}

$paciente_id = (int)$_GET['paciente_id'];
$stmt = $conex->prepare("SELECT nombre_completo, cedula, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad, correo FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$paciente) {
    die("Error: Paciente no encontrado.");
}

$stmt = $conex->prepare("SELECT COUNT(*) AS total FROM informes_estudios WHERE paciente_id = ?");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$total_estudios = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$puede_crear = in_array($_SESSION['rol'], ['ecografista', 'administrador']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Paciente - EcoMadelleine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; margin: 0; padding: 30px; }
        .main-container { max-width: 900px; margin: 0 auto; }
        .panel-header { background: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); margin-bottom: 30px; }
        .panel-header h1 { margin: 0; color: #333; font-size: 24px; }
        .panel-header p { margin: 5px 0 0; color: #777; }
        .panel-header .meta { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 14px; font-size: 14px; color: #555; }
        .panel-header .meta span strong { color: #222; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #555; text-decoration: none; font-weight: 500; }
        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .action-card { display: block; padding: 30px; text-align: left; text-decoration: none; color: inherit;
                       border-radius: 12px; background: #fff; border: 1px solid #e9ecef;
                       box-shadow: 0 4px 6px rgba(0,0,0,0.04); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .action-card .icon-wrapper { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .action-card .icon-wrapper i { font-size: 24px; color: #fff; }
        .action-card h3 { margin: 0 0 8px; color: #333; font-size: 18px; font-weight: 600; }
        .action-card p { margin: 0; font-size: 14px; color: #777; line-height: 1.6; }
        .badge-conteo { display: inline-block; background: #02b1f4; color: white; padding: 2px 10px; border-radius: 12px; font-size: 12px; margin-left: 8px; }
    </style>
</head>
<body>
<div class="main-container">
    <a href="<?= eco_url('mis-pacientes') ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Volver a la lista de pacientes</a>
    <div class="panel-header">
        <h1>Panel de Gestion del Paciente</h1>
        <p><strong><?php echo htmlspecialchars($paciente['nombre_completo']); ?></strong></p>
        <div class="meta">
            <?php if (!empty($paciente['cedula'])): ?><span><strong>CI:</strong> <?php echo htmlspecialchars($paciente['cedula']); ?></span><?php endif; ?>
            <?php if (!empty($paciente['edad'])): ?><span><strong>Edad:</strong> <?php echo (int)$paciente['edad']; ?> anos</span><?php endif; ?>
            <?php if (!empty($paciente['correo'])): ?><span><strong>Correo:</strong> <?php echo htmlspecialchars($paciente['correo']); ?></span><?php endif; ?>
        </div>
    </div>

    <div class="action-grid">
        <a href="<?= eco_url('informes-estudio') ?>?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
            <div class="icon-wrapper" style="background-color: #6f42c1;"><i class="fa-solid fa-folder-open"></i></div>
            <h3>Historial de Estudios<span class="badge-conteo"><?php echo $total_estudios; ?></span></h3>
            <p>Consulta todos los informes ecograficos previos registrados para este paciente.</p>
        </a>

        <?php if ($puede_crear): ?>
            <a href="<?= eco_url('nuevo-informe') ?>?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
                <div class="icon-wrapper" style="background-color: #02b1f4;"><i class="fa-solid fa-file-circle-plus"></i></div>
                <h3>Nuevo Informe de Estudio</h3>
                <p>Selecciona el tipo de ecografia y registra los hallazgos del estudio realizado hoy.</p>
            </a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
