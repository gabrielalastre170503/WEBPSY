<?php
session_start();
include 'conexion.php';

// Seguridad: Solo secretarias y administradores pueden acceder
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['secretaria', 'administrador'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_GET['cita_id']) || !is_numeric($_GET['cita_id'])) {
    die("Error: No se especificó una cita.");
}

$cita_id = $_GET['cita_id'];
// Obtenemos los datos de la cita y del paciente
$stmt = $conex->prepare("SELECT c.id, c.motivo_consulta, u.nombre_completo as paciente_nombre FROM citas c JOIN usuarios u ON c.paciente_id = u.id WHERE c.id = ?");
$stmt->bind_param("i", $cita_id);
$stmt->execute();
$result = $stmt->get_result();
$cita = $result->fetch_assoc();
$stmt->close();

if (!$cita) {
    die("Error: La cita no fue encontrada.");
}

// Obtenemos la lista de todos los psicólogos disponibles para el selector
$psicologos_result = $conex->query("SELECT id, nombre_completo FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra') AND estado = 'aprobado'");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Programar Cita - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
            font-family: "Poppins", sans-serif;
        }
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 600px;
        }
        .form-container h2 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .info-paciente {
            background-color: #f8f9fa;
            border-left: 4px solid #02b1f4;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }
        .info-paciente p {
            margin: 5px 0;
        }
        .form-container form {
            background: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            text-align: left;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
            font-family: "Poppins", sans-serif;
        }
        .btn {
            width: 100%;
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Asignar y Programar Cita</h2>
        <div class="info-paciente">
            <p><strong>Paciente:</strong> <?php echo htmlspecialchars($cita['paciente_nombre']); ?></p>
            <p><strong>Motivo:</strong> <?php echo htmlspecialchars($cita['motivo_consulta']); ?></p>
        </div>
        <form action="guardar_cita.php" method="POST">
            <input type="hidden" name="cita_id" value="<?php echo $cita['id']; ?>">
            
            <div class="form-group">
                <label for="psicologo_id">Asignar a Profesional:</label>
                <select name="psicologo_id" id="psicologo_id" required>
                    <option value="">-- Seleccione un profesional --</option>
                    <?php while($psicologo = $psicologos_result->fetch_assoc()): ?>
                        <option value="<?php echo $psicologo['id']; ?>"><?php echo htmlspecialchars($psicologo['nombre_completo']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="calendario">Seleccionar Fecha y Hora:</label>
                <input type="text" id="calendario" name="fecha_cita" placeholder="Haz clic para seleccionar..." required>
            </div>
            <button type="submit" class="btn">Confirmar y Programar Cita</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        flatpickr("#calendario", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "d/m/Y h:i K",
            locale: "es",
            minuteIncrement: 15,
        });
    </script>
</body>
</html>