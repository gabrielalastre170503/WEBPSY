<?php
session_start();
include 'conexion.php';

// Seguridad: Solo psicólogos y psiquiatras pueden acceder
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

if (!$cita) { die("Error: La cita solicitada no fue encontrada."); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Programar Cita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Librería para el calendario de fecha y hora -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <style>
        /* --- ESTILOS COMPLETOS Y CORREGIDOS PARA ESTA PÁGINA --- */
        * {
            box-sizing: border-box;
        }
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
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px; /* Ancho cómodo para el formulario */
        }
        .form-container h2 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
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
        
        /* Anulamos los estilos globales del style.css para este formulario */
        .form-container form {
            background: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin-top: 25px !important;
            border-radius: 0 !important;
            width: 100% !important;
            text-align: left;
        }

        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-group input {
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
            font-weight: 600;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #818181;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Programar Cita</h2>

        <div class="info-paciente">
            <p><strong>Paciente:</strong> <?php echo htmlspecialchars($cita['paciente_nombre']); ?></p>
            <p><strong>Motivo:</strong> <?php echo htmlspecialchars($cita['motivo_consulta']); ?></p>
        </div>

        <form action="guardar_cita.php" method="POST">
            <input type="hidden" name="cita_id" value="<?php echo $cita['id']; ?>">
            <div class="form-group">
                <label for="calendario">Seleccionar Fecha y Hora:</label>
                <input type="text" id="calendario" name="fecha_cita" placeholder="Haz clic para seleccionar..." required>
            </div>
            <button type="submit" class="btn">Confirmar y Programar Cita</button>
            <a href="panel.php?vista=citas" class="back-link">Cancelar y Volver</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        // Configuración de Flatpickr
        flatpickr("#calendario", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "d / m / Y  -  h:i K", // Formato Día/Mes/Año y Hora AM/PM
            locale: "es",
            minuteIncrement: 15,
        });
    </script>

</body>
</html>