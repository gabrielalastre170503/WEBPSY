<?php
// Habilitar la visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'conexion.php';

// 1. Seguridad: Verificar si el usuario está logueado y tiene el rol correcto
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}

// 2. Seguridad: Verificar que se pasó un ID de paciente
if (!isset($_GET['paciente_id'])) {
    die("Error Crítico: No se ha especificado un ID de paciente.");
}

$paciente_id = $_GET['paciente_id'];

// 3. Obtener el nombre del paciente
$stmt_paciente = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
$stmt_paciente->bind_param("i", $paciente_id);
$stmt_paciente->execute();
$result = $stmt_paciente->get_result();

if ($result->num_rows === 0) {
    die("Error: Paciente con ID " . htmlspecialchars($paciente_id) . " no fue encontrado.");
}
$paciente = $result->fetch_assoc();
$paciente_nombre = $paciente['nombre_completo'];
$stmt_paciente->close();

// 4. Lógica para verificar si ya existe una historia clínica
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
    <title>Gestionar Paciente</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; }
        .main-container { max-width: 900px; margin: 30px auto; padding: 30px; background-color: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .action-card { display: block; padding: 30px; text-align: center; text-decoration: none; border-radius: 10px; background-color: #fafafa; border: 1px solid #eee; transition: all 0.3s ease; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .action-card i { font-size: 40px; margin-bottom: 15px; }
        .action-card h3 { margin: 0; color: #333; }
        .historia { color: #02b1f4; }
        .informe { color: #17a2b8; }
        .disabled-card { background-color: #f9f9f9; color: #aaa; cursor: not-allowed; }
        .disabled-card i { color: #ccc; }
        .disabled-card:hover { transform: none; box-shadow: none; }
    </style>
</head>
<body>
<div class="main-container">
    <h1>Gestionando a: <?php echo htmlspecialchars($paciente_nombre); ?></h1>
    <a href="panel.php" style="display:inline-block; margin-bottom:20px;">&larr; Volver a la lista de pacientes</a>

    <div class="action-grid">
        <?php if ($tiene_historia): ?>
            <a href="historia_clinica.php?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
                <i class="fa-solid fa-file-medical historia"></i>
                <h3>Ver Historia Clínica</h3>
            </a>
        <?php else: ?>
            <a href="historia_clinica.php?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
                <i class="fa-solid fa-file-circle-plus historia"></i>
                <h3>Crear Historia Clínica</h3>
            </a>
        <?php endif; ?>

        <a href="ver_informes.php?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
            <i class="fa-solid fa-folder-open informe"></i>
            <h3>Ver Informes</h3>
        </a>

        <?php if ($tiene_historia): ?>
            <a href="crear_informe.php?paciente_id=<?php echo $paciente_id; ?>" class="action-card">
                <i class="fa-solid fa-file-pen informe"></i>
                <h3>Crear Nuevo Informe</h3>
            </a>
        <?php else: ?>
            <div class="action-card disabled-card">
                <i class="fa-solid fa-file-pen"></i>
                <h3>Crear Nuevo Informe</h3>
                <small style="display: block; margin-top: 10px;">(Requiere historia clínica)</small>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>