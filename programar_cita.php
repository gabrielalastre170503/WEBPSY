<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_GET['cita_id'])) { die("Error: No se especificó una cita."); }

$cita_id = $_GET['cita_id'];
$stmt = $conex->prepare("SELECT c.id, c.motivo_consulta, u.nombre_completo as paciente_nombre FROM citas c JOIN usuarios u ON c.paciente_id = u.id WHERE c.id = ?");
$stmt->bind_param("i", $cita_id);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Programar Cita</title>
    <link rel="stylesheet" href="style.css">
    </head>
<body style="background-color: #f0f2f5; font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh;">
    <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 500px;">
        <h2>Programar Cita</h2>
        <p><strong>Paciente:</strong> <?php echo htmlspecialchars($cita['paciente_nombre']); ?></p>
        <p><strong>Motivo:</strong> <?php echo htmlspecialchars($cita['motivo_consulta']); ?></p>
        <form action="guardar_cita.php" method="POST">
            <input type="hidden" name="cita_id" value="<?php echo $cita['id']; ?>">
            <div class="form-group">
                <label for="fecha_cita">Seleccionar Fecha y Hora:</label>
                <input type="datetime-local" name="fecha_cita" id="fecha_cita" required style="width:100%; padding: 8px; margin-top: 5px;">
            </div>
            <button type="submit" class="btn" style="width: 100%;">Confirmar y Programar Cita</button>
        </form>
    </div>
</body>
</html>