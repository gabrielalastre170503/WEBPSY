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
$stmt = $conex->prepare("SELECT c.id, c.fecha_cita, u.nombre_completo as paciente_nombre FROM citas c JOIN usuarios u ON c.paciente_id = u.id WHERE c.id = ?");
$stmt->bind_param("i", $cita_id);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();
if (!$cita) { die("Error: Cita no encontrada."); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proponer Nueva Fecha</title>
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
            padding: 20px;
        }
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
        }
        .form-container h2 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }
        .info-cita {
            background-color: #fff3cd; /* Amarillo claro para indicar advertencia/cambio */
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }
        .info-cita p {
            margin: 5px 0;
            font-size: 15px;
        }
        /* Anulamos los estilos globales del style.css para este formulario */
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
            color: #555;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            font-family: "Poppins", sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #02b1f4;
            box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.2);
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            margin-top: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(45deg, #02b1f4, #00c2ff);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3);
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
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
        <h2>Proponer Nueva Fecha</h2>
        <div class="info-cita">
            <p><strong>Paciente:</strong> <?php echo htmlspecialchars($cita['paciente_nombre']); ?></p>
            <p><strong>Fecha solicitada por el paciente:</strong> <?php echo date('d/m/Y h:i A', strtotime($cita['fecha_cita'])); ?></p>
        </div>
        <form action="guardar_propuesta.php" method="POST">
            <input type="hidden" name="cita_id" value="<?php echo $cita['id']; ?>">
            <div class="form-group">
                <label for="calendario">Sugerir nueva fecha y hora:</label>
                <input type="text" id="calendario" name="fecha_propuesta" placeholder="Haz clic para seleccionar..." required>
            </div>
            <div class="form-group">
                <label for="motivo_reprogramacion">Motivo (se notificará al paciente):</label>
                <textarea name="motivo_reprogramacion" id="motivo_reprogramacion" rows="3" required placeholder="Ej: No tengo disponibilidad en ese horario, pero puedo atenderte en esta nueva fecha."></textarea>
            </div>
            <button type="submit" class="btn-submit">Enviar Propuesta al Paciente</button>
            <a href="panel.php?vista=citas" class="back-link">Cancelar y Volver</a>
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