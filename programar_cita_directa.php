<?php
session_start();
include 'conexion.php';

// Seguridad: Solo psicólogos y roles autorizados
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_GET['paciente_id'])) { die("Error: No se especificó un paciente."); }

$paciente_id = (int)$_GET['paciente_id'];
$stmt = $conex->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();

if (!$paciente) { die("Error: Paciente no encontrado."); }

$rol_usuario = $_SESSION['rol'] ?? '';
$ecografistas_prog = [];
if ($rol_usuario === 'recepcionista') {
    $ex = $conex->query("SELECT id, nombre_completo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
    if ($ex) {
        while ($r = $ex->fetch_assoc()) {
            $ecografistas_prog[] = $r;
        }
        $ex->free();
    }
}

$tipos_eco = $conex->query("SELECT id, nombre, categoria FROM tipos_ecografias WHERE activo = 1 ORDER BY categoria, nombre");
$href_volver = ($rol_usuario === 'recepcionista') ? 'recepcion_gestion_pacientes.php' : (($rol_usuario === 'ecografista') ? 'mis_pacientes.php' : 'panel.php?vista=pacientes');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Programar Cita Directa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Estilos específicos y aislados para esta página */
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
            margin-bottom: 10px;
            color: #333;
        }
        .form-container .subtitulo {
            text-align: center;
            margin-top: 0;
            margin-bottom: 30px;
            color: #777;
        }
        /* Anulamos estilos globales del style.css para este formulario */
        .form-container form {
            background: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            width: 100% !important;
            text-align: left;
        }
        .form-group {
            position: relative;
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group textarea {
            padding-left: 15px; /* Textarea no necesita espacio para icono */
            resize: vertical;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #02b1f4;
            box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.2);
        }
        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(2px);
            color: #aaa;
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
        <h2>Programar Nueva Cita</h2>
        <p class="subtitulo">Para el paciente: <strong><?php echo htmlspecialchars($paciente['nombre_completo']); ?></strong></p>
        
        <form action="guardar_cita_directa.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">

            <?php if ($rol_usuario === 'recepcionista'): ?>
            <div class="form-group">
                <label for="ecografista_prog">Ecografista responsable:</label>
                <select name="ecografista_id" id="ecografista_prog" required style="width:100%;padding:12px;border:1px solid #ccc;border-radius:8px;font-size:15px;background:white;font-family:inherit;">
                    <option value="">— Seleccione —</option>
                    <?php foreach ($ecografistas_prog as $eco): ?>
                        <option value="<?php echo (int)$eco['id']; ?>"><?php echo htmlspecialchars($eco['nombre_completo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="tipo_ecografia_id">Tipo de Ecografía:</label>
                <i class="fa-solid fa-wave-square"></i>
                <select name="tipo_ecografia_id" id="tipo_ecografia_id" required style="width:100%;padding:12px 15px 12px 45px;border:1px solid #ccc;border-radius:8px;font-size:15px;background:white;">
                    <option value="">-- Selecciona el estudio --</option>
                    <?php while ($t = $tipos_eco->fetch_assoc()): ?>
                        <option value="<?php echo (int)$t['id']; ?>">
                            <?php echo htmlspecialchars(($t['categoria'] ? $t['categoria'] . ' - ' : '') . $t['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="calendario">Fecha y Hora de la Cita:</label>
                <i class="fa-solid fa-calendar-alt"></i>
                <input type="text" id="calendario" name="fecha_cita" placeholder="Haz clic para seleccionar..." required>
            </div>

            <div class="form-group">
                <label for="motivo_consulta">Antecedentes médicos y detalles:</label>
                <textarea name="motivo_consulta" id="motivo_consulta" rows="4" placeholder="Ej: Control de embarazo, dolor abdominal, evaluación de nódulos..."></textarea>
            </div>

            <button type="submit" class="btn-submit">Guardar Cita</button>
            <a href="<?php echo htmlspecialchars($href_volver); ?>" class="back-link">Cancelar y Volver</a>
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