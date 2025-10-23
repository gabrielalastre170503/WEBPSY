<?php
session_start();
include 'conexion.php';

// Seguridad: Solo psicólogos pueden acceder
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    header('Location: login.php');
    exit();
}

$psicologo_id = $_SESSION['usuario_id'];

// Obtener el horario recurrente guardado
$horario_recurrente = [];
$stmt = $conex->prepare("SELECT dia_semana, hora_inicio, hora_fin FROM horarios_recurrentes WHERE psicologo_id = ?");
$stmt->bind_param("i", $psicologo_id);
$stmt->execute();
$resultado = $stmt->get_result();
while ($fila = $resultado->fetch_assoc()) {
    $horario_recurrente[$fila['dia_semana']] = [
        'inicio' => date("H:i", strtotime($fila['hora_inicio'])),
        'fin' => date("H:i", strtotime($fila['hora_fin']))
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Mi Disponibilidad - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* (Tus estilos existentes para la página se mantienen igual) */
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; margin: 0; padding: 30px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .panel { background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); margin-bottom: 30px; }
    h1, h2 { color: #333; margin-top: 0; }
    .section-title { display: flex; align-items: center; gap: 12px; font-size: 22px; }
    .section-title .back-arrow { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; border: 1px solid #d0d6fd; color: #0c3b8c; text-decoration: none; font-size: 16px; transition: all 0.25s ease; background: linear-gradient(135deg, rgba(12,59,140,0.08), rgba(2,177,244,0.12)); }
    .section-title .back-arrow:hover { background: linear-gradient(135deg, rgba(12,59,140,0.18), rgba(2,177,244,0.22)); transform: translateX(-2px); }
        h1 { margin-bottom: 5px; }
        .page-subtitle { color: #777; margin-top: 0; margin-bottom: 20px; }
        .back-link { text-decoration: none; color: #555; font-weight: 500; display: inline-block; margin-bottom: 20px; }
        .management-grid {
    display: grid;
    /* Columna 1 (Horario) fija en 450px, Columna 2 (Calendario) en 600px */
    grid-template-columns: 550px 720px; /* <-- LÍNEA CORREGIDA */
    gap: 30px;
    justify-content: center; /* Centra la rejilla si sobra espacio */
}
        .schedule-form h2 { font-size: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .day-schedule { display: flex; align-items: center; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .day-schedule:last-of-type { border-bottom: none; }
        .day-schedule label.day-name { font-weight: 500; color: #333; }
        .time-inputs { display: flex; align-items: center; gap: 8px; color: #777; }
        .time-inputs input.time-picker { width: 110px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-family: "Poppins", sans-serif; font-size: 14px; text-align: center; cursor: pointer; }
        .btn-save { cursor: pointer; border: none; padding: 12px 25px; border-radius: 8px; background: linear-gradient(45deg, #02b1f4, #00c2ff); color: white; font-weight: 600; font-size: 16px; transition: all 0.3s ease; width: 100%; margin-top: 20px; }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4); }
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 28px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #28a745; }
        input:checked + .slider:before { transform: translateX(22px); }
        #calendar-container { height: 70vh; }
        .fc-daygrid-day.fc-day-today { background-color: #e9f7fe; }
        .calendar-legend { display: flex; justify-content: center; gap: 20px; margin-top: 15px; font-size: 13px; }
        .legend-item { display: flex; align-items: center; }
        .legend-color { width: 15px; height: 15px; border-radius: 4px; margin-right: 8px; }
        @media (max-width: 991px) { .management-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        

        <div class="management-grid">
            <!-- Columna Izquierda: Horario Semanal -->
            <div class="panel schedule-form">
                <h2 class="section-title">
                    <a href="panel.php" class="back-arrow" title="Volver al panel"><i class="fa-solid fa-arrow-left"></i></a>
                    Horario Semanal Fijo
                </h2>
                <form action="guardar_disponibilidad.php" method="POST">
                    <input type="hidden" name="accion" value="guardar_recurrente">
                    <?php
                    $dias = ["1" => "Lunes", "2" => "Martes", "3" => "Miércoles", "4" => "Jueves", "5" => "Viernes", "6" => "Sábado", "7" => "Domingo"];
                    foreach ($dias as $num => $nombre):
                        $checked = isset($horario_recurrente[$num]) ? 'checked' : '';
                        $inicio = $horario_recurrente[$num]['inicio'] ?? '09:00';
                        $fin = $horario_recurrente[$num]['fin'] ?? '17:00';
                    ?>
                    <div class="day-schedule">
                        <label class="switch">
                            <input type="checkbox" name="dias[<?php echo $num; ?>][activo]" id="dia_<?php echo $num; ?>" <?php echo $checked; ?>>
                            <span class="slider"></span>
                        </label>
                        <label for="dia_<?php echo $num; ?>" class="day-name"><?php echo $nombre; ?></label>
                        <div class="time-inputs">
                            De <input type="text" name="dias[<?php echo $num; ?>][inicio]" value="<?php echo $inicio; ?>" class="time-picker">
                            a <input type="text" name="dias[<?php echo $num; ?>][fin]" value="<?php echo $fin; ?>" class="time-picker">
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn-save">Guardar Horario</button>
                </form>
            </div>

            <!-- Columna Derecha: Calendario de Excepciones -->
            <div class="panel">
                <h2>Excepciones y Días Libres</h2>
                
                <div id="calendar-container"></div>
                <div class="calendar-legend">
                    <div class="legend-item"><span class="legend-color" style="background-color: #d4edda;"></span> Día Laborable</div>
                    <div class="legend-item"><span class="legend-color" style="background-color: #f8d7da;"></span> Día No Disponible</div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS de Flatpickr para el nuevo selector de hora -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lógica del calendario de excepciones
        var calendarEl = document.getElementById('calendar-container');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth' },
            events: 'get_eventos_disponibilidad.php',
            dateClick: function(info) {
                if (confirm("¿Quieres marcar el " + info.dateStr + " como día no disponible?")) {
                    window.location.href = `guardar_disponibilidad.php?accion=alternar_dia_libre&fecha=${info.dateStr}`;
                }
            },
            eventClick: function(info) {
                if (info.event.extendedProps.tipo === 'no_disponible') {
                    if (confirm("¿Quieres eliminar este día libre y reactivar tu horario normal?")) {
                        window.location.href = `guardar_disponibilidad.php?accion=eliminar_excepcion&id=${info.event.id}`;
                    }
                }
            }
        });
        calendar.render();

        // --- LÓGICA CORREGIDA PARA LOS CAMPOS DE HORA (FORMATO 12 HORAS) ---
        flatpickr(".time-picker", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",      // Formato para guardar (24h, es mejor para la base de datos)
            altInput: true,         // Muestra un input visible diferente
            altFormat: "h:i K",     // Formato visible para el usuario (ej: 05:30 PM)
            time_24hr: false,       // Usa el formato de 12 horas
            minuteIncrement: 15
        });
    });
    </script>
</body>
</html>