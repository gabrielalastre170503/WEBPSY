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
$stmt_paciente = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$result = $stmt_paciente->get_result();
if ($result->num_rows === 0) {
    die("Error: Paciente no encontrado.");
}
$paciente = $result->fetch_assoc();
$paciente_nombre = $paciente['nombre_completo'];
$stmt_paciente->close();

// Lógica para verificar si ya existe una historia clínica
$tiene_historia = false;
$sql_check = "SELECT 1 FROM historias_adultos WHERE paciente_id = ? UNION ALL SELECT 1 FROM historias_infantiles WHERE paciente_id = ? LIMIT 1";
$stmt_check = $conex->prepare($sql_check);
$stmt_check->bind_param("ii", $paciente_id, $paciente_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    $tiene_historia = true;
}
$stmt_check->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Paciente - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #f0f2f5; 
            font-family: "Poppins", sans-serif; 
            margin: 0; 
            padding: 30px; 
        }
        .main-container { 
            max-width: 900px; 
            margin: 0 auto; 
        }
        .panel-header {
            background-color: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.07);
            margin-bottom: 30px;
        }
        .panel-header h1 { 
            margin: 0; 
            color: #333; 
            font-size: 24px;
        }
        .panel-header p {
            margin: 5px 0 0 0;
            color: #777;
        }
        .back-link { 
            text-decoration: none; 
            color: #555; 
            font-weight: 500; 
            display: inline-block; 
            margin-bottom: 20px; 
        }
        .back-link i { 
            margin-right: 8px; 
        }
        .action-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 30px; 
        }
        .action-card { 
            display: block; 
            padding: 30px; 
            text-align: left; 
            text-decoration: none; 
            color: inherit;
            border-radius: 12px; 
            background-color: #ffffff; 
            border: 1px solid #e9ecef;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04);
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
        }
        .action-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); 
        }
        .action-card .icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .action-card .icon-wrapper i { 
            font-size: 24px; 
            color: #fff; 
        }
        .action-card h3 { 
            margin: 0 0 8px 0; 
            color: #333; 
            font-size: 18px;
            font-weight: 600;
        }
        .action-card p {
            margin: 0;
            font-size: 14px;
            color: #777;
            line-height: 1.6;
        }
        .disabled-card { 
            background-color: #f8f9fa; 
            color: #adb5bd; 
            cursor: not-allowed; 
            border-color: #e9ecef;
        }
        .disabled-card:hover { 
            transform: none; 
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04);
        }
        .disabled-card h3, .disabled-card p { 
            color: #adb5bd !important; 
        }
    </style>
</head>
<body>
<div class="main-container">
    <a href="panel.php?vista=pacientes" class="back-link"><i class="fa-solid fa-arrow-left"></i> Volver a la lista de pacientes</a>
    <div class="panel-header">
        <h1>Panel de Gestión</h1>
        <p>Paciente: <strong><?php echo htmlspecialchars($paciente_nombre); ?></strong></p>
    </div>

    <div class="action-grid">
        <?php if ($tiene_historia): ?>
            <a href="historia_clinica.php?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
                <div class="icon-wrapper" style="background-color: #02b1f4;"><i class="fa-solid fa-file-medical"></i></div>
                <h3>Ver Historia Clínica</h3>
                <p>Consulta el expediente completo y los antecedentes del paciente.</p>
            </a>
        <?php else: ?>
            <a href="historia_clinica.php?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
                <div class="icon-wrapper" style="background-color: #02b1f4;"> <!-- <-- COLOR CAMBIADO AQUÍ -->
                    <i class="fa-solid fa-file-circle-plus"></i>
                </div>
                <h3>Crear Historia Clínica</h3>
                <p>Inicia un nuevo expediente clínico para este paciente.</p>
            </a>
        <?php endif; ?>

        <a href="ver_informes.php?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
            <div class="icon-wrapper" style="background-color: #6f42c1;"><i class="fa-solid fa-folder-open"></i></div>
            <h3>Ver Informes</h3>
            <p>Accede al historial de informes psicológicos generados.</p>
        </a>

        <?php if ($tiene_historia): ?>
            <a href="crear_informe.php?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
                <div class="icon-wrapper" style="background-color: #17a2b8;"><i class="fa-solid fa-file-pen"></i></div>
                <h3>Crear Nuevo Informe</h3>
                <p>Redacta y guarda un nuevo informe para este paciente.</p>
            </a>
        <?php else: ?>
            <div class="action-card disabled-card">
                <div class="icon-wrapper" style="background-color: #adb5bd;"><i class="fa-solid fa-file-pen"></i></div>
                <h3>Crear Nuevo Informe</h3>
                <p>Se requiere una historia clínica para generar un informe.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>