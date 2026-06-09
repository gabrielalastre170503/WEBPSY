<?php
session_start();
include 'conexion.php';
require_once __DIR__ . '/lib/estudios_render.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_GET['paciente_id']) || !is_numeric($_GET['paciente_id'])) {
    die("Error: No se ha especificado un paciente válido.");
}

$paciente_id = (int)$_GET['paciente_id'];

$stmt = $conex->prepare("SELECT id, nombre_completo, cedula, fecha_nacimiento, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$paciente) die("Error: Paciente no encontrado.");

// Si viene un tipo_id por GET, pre-seleccionamos ese tipo y saltamos el grid
$tipo_id_presel = (isset($_GET['tipo_id']) && is_numeric($_GET['tipo_id'])) ? (int)$_GET['tipo_id'] : null;

$tipos = [];
$res = $conex->query("SELECT id, codigo, nombre, categoria, descripcion, icono, esquema_campos, esquema_version FROM tipos_ecografias WHERE activo = 1 ORDER BY posicion, nombre");
while ($row = $res->fetch_assoc()) {
    $row['esquema_decoded'] = json_decode($row['esquema_campos'], true) ?: ['secciones' => []];
    $tipos[] = $row;
}

// Datos iniciales para pre-rellenar la sección de encabezado con los datos del paciente
$datos_iniciales_por_tipo = [
    'encabezado' => [
        'nombres_apellidos' => $paciente['nombre_completo'] ?? '',
        'cedula'            => $paciente['cedula'] ?? '',
        'edad'              => $paciente['edad'] ?? '',
        'fecha'             => date('Y-m-d'),
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Informe — EcoMadelleine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Reset y base ── */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: #f0f2f5;
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 24px 16px 48px;
            color: #333;
        }

        /* ── Contenedor principal ── */
        .page-wrapper {
            max-width: 1060px;
            margin: 0 auto;
        }

        /* ── Encabezado de página premium ── */
        .page-header-premium {
            background: #fff;
            border-radius: 16px 16px 0 0;
            padding: 22px 30px;
            display: flex;
            align-items: center;
            gap: 18px;
            border-bottom: 2px solid #f0f2f5;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .page-header-premium .btn-back {
            display: flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #555;
            cursor: pointer;
            transition: all .2s;
            font-family: "Poppins", sans-serif;
            text-decoration: none;
            white-space: nowrap;
        }
        .page-header-premium .btn-back:hover { background: #f1f5f9; border-color: #02b1f4; color: #02b1f4; }
        .page-header-premium .header-icon {
            width: 54px; height: 54px;
            background: linear-gradient(135deg, #02b1f4, #00c2ff);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 22px;
            box-shadow: 0 4px 12px rgba(2,177,244,0.3);
            flex-shrink: 0;
        }
        .page-header-premium .header-info { flex: 1; }
        .page-header-premium .header-info h1 {
            margin: 0 0 3px;
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }
        .page-header-premium .header-info p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
        }
        .page-header-premium .header-info p strong { color: #334155; }

        /* ── Área de contenido (body del formulario) ── */
        .page-body {
            background: #fff;
            border-radius: 0 0 16px 16px;
            padding: 28px 30px 36px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
        }

        /* ── Feedback ── */
        .msg-feedback { margin-bottom: 18px; }
        .msg-error  { background:#fff5f5; color:#c0392b; border:1px solid #fca5a5; padding:12px 16px; border-radius:8px; }
        .msg-ok     { background:#f0fdf4; color:#15803d; border:1px solid #86efac; padding:12px 16px; border-radius:8px; }

        /* ── Grid selector de tipos ── */
        .selection-container { text-align: center; padding: 10px 0 20px; }
        .selection-container h2 { font-size: 19px; color: #1e293b; margin: 0 0 6px; }
        .selection-container > p { color: #64748b; margin: 0 0 26px; font-size: 14px; }
        .selection-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 16px; }
        .selection-card {
            background:#fff; border:1.5px solid #e2e8f0; border-radius:12px;
            padding:22px 14px 18px; cursor:pointer; text-align:center;
            transition:all .25s ease;
        }
        .selection-card:hover { transform:translateY(-5px); border-color:#02b1f4; box-shadow:0 8px 20px rgba(2,177,244,.12); }
        .selection-card i { font-size:36px; color:#02b1f4; margin-bottom:10px; display:block; }
        .selection-card .card-categoria { font-size:10px; color:#02b1f4; text-transform:uppercase; letter-spacing:.5px; font-weight:600; margin-bottom:5px; }
        .selection-card h3 { font-size:13.5px; font-weight:600; color:#1e293b; margin:0 0 4px; }
        .selection-card .card-description { font-size:12px; color:#94a3b8; line-height:1.4; margin:0; }

        /* ── Secciones del formulario ── */
        .hidden-form { display:none; }
        .hidden-form.active { display:block; }

        .form-tipo-titulo {
            display: flex; align-items: center; gap: 14px;
            padding: 0 0 20px;
            border-bottom: 2px solid #f0f2f5;
            margin-bottom: 24px;
        }
        .form-tipo-titulo .tipo-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #02b1f4, #38bdf8);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 20px; flex-shrink: 0;
        }
        .form-tipo-titulo h2 { margin:0; font-size:18px; color:#1e293b; font-weight:700; }
        .form-tipo-titulo p  { margin:3px 0 0; font-size:13px; color:#64748b; }

        /* ── Card de sección ── */
        .form-seccion {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 18px;
            overflow: hidden;
        }
        .form-seccion-header {
            background: linear-gradient(90deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 12px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .form-seccion-header h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #0284c7;
            letter-spacing: .3px;
        }
        .form-seccion-header h3 i { margin-right: 7px; }
        .form-seccion-body { padding: 18px 20px 14px; }

        /* ── Form grid ── */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 14px 16px;
        }
        .form-group { display:flex; flex-direction:column; }
        .form-group.campo-completo { grid-column: span 6; }
        .form-group.campo-medio    { grid-column: span 3; }
        .form-group.campo-tercio   { grid-column: span 2; }

        .form-group label {
            margin-bottom: 5px;
            font-size: 12.5px;
            font-weight: 600;
            color: #475569;
        }
        .campo-req { color: #dc3545; margin-left: 2px; }
        .form-group input:not([type=radio]):not([type=checkbox]),
        .form-group textarea,
        .form-group select {
            padding: 9px 11px;
            border: 1.5px solid #d1d5db;
            border-radius: 7px;
            font-size: 13.5px;
            font-family: "Poppins", sans-serif;
            transition: border-color .2s, box-shadow .2s;
            width: 100%;
            background: #fff;
            color: #1e293b;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #02b1f4;
            box-shadow: 0 0 0 3px rgba(2,177,244,.15);
        }
        .input-con-unidad { position:relative; display:flex; align-items:center; }
        .input-con-unidad input { padding-right: 38px; }
        .campo-unidad {
            position: absolute; right: 10px;
            color: #94a3b8; font-size: 11px; font-weight:600;
            pointer-events: none; white-space: nowrap;
        }

        /* ── Radio groups ── */
        .radio-group { display:flex; gap:12px; flex-wrap:wrap; padding-top:6px; }
        .radio-label {
            display: inline-flex; align-items: center; gap:5px;
            font-size: 13px; color: #334155; cursor:pointer;
            padding: 5px 12px;
            border: 1.5px solid #d1d5db;
            border-radius: 20px;
            transition: all .2s;
        }
        .radio-label:has(input:checked) {
            background: #e0f5fe; border-color: #02b1f4; color: #0284c7; font-weight:600;
        }
        .radio-label input[type=radio] { accent-color: #02b1f4; }

        /* ── SI / NO pills ── */
        .sinno-group { display:flex; gap:8px; padding-top:4px; }
        .sinno-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 16px;
            border: 1.5px solid #d1d5db;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px; font-weight:600;
            transition: all .2s;
            color: #555;
        }
        .sinno-btn input[type=radio] { display:none; }
        .sinno-btn.sinno-si:has(input:checked) {
            background: #dcfce7; border-color: #22c55e; color: #15803d;
        }
        .sinno-btn.sinno-no:has(input:checked) {
            background: #fee2e2; border-color: #f87171; color: #b91c1c;
        }
        .sinno-btn:hover { background: #f8fafc; border-color:#94a3b8; }

        /* ── Sección PAR (Riñones) ── */
        .seccion-par-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .seccion-par-col {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .seccion-par-col-titulo {
            background: linear-gradient(90deg, #0284c7, #0ea5e9);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            padding: 8px 14px;
            text-align: center;
        }
        .form-grid-par {
            grid-template-columns: repeat(2, 1fr);
            padding: 14px;
            gap: 10px 12px;
        }
        .form-grid-par .campo-completo { grid-column: span 2; }
        .form-grid-par .campo-medio    { grid-column: span 1; }
        .form-grid-par .campo-tercio   { grid-column: span 1; }

        /* ── Valor (modo lectura) ── */
        .campo-valor {
            margin: 4px 0 0;
            font-size: 13.5px;
            color: #1e293b;
            padding: 8px 10px;
            background: #f8fafc;
            border-radius: 6px;
            min-height: 36px;
        }

        /* ── Botones de acción ── */
        .form-actions {
            display: flex;
            gap: 14px;
            justify-content: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 2px solid #f0f2f5;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 28px;
            font-size: 14px; font-weight: 600;
            border-radius: 9px; border: none; cursor: pointer;
            font-family: "Poppins", sans-serif;
            transition: all .25s ease;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #02b1f4, #00c2ff);
            color: #fff;
            box-shadow: 0 4px 14px rgba(2,177,244,.3);
        }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(2,177,244,.4); }
        .btn-secondary {
            background: #fff; color: #555;
            border: 1.5px solid #d1d5db;
        }
        .btn-secondary:hover { background: #f1f5f9; border-color: #94a3b8; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr 1fr; }
            .form-group.campo-completo { grid-column:span 2; }
            .form-group.campo-medio,
            .form-group.campo-tercio   { grid-column:span 1; }
            .seccion-par-grid          { grid-template-columns: 1fr; }
            .page-header-premium       { flex-wrap:wrap; gap:12px; }
        }
        @media (max-width: 480px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.campo-completo,
            .form-group.campo-medio,
            .form-group.campo-tercio   { grid-column:span 1; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">

    <!-- ── Encabezado premium ── -->
    <div class="page-header-premium">
        <a href="gestionar_paciente.php?paciente_id=<?php echo $paciente_id; ?>" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
        <div class="header-icon">
            <i class="fa-solid fa-file-waveform"></i>
        </div>
        <div class="header-info">
            <h1>Nuevo Informe de Estudio</h1>
            <p>
                Paciente: <strong><?php echo htmlspecialchars($paciente['nombre_completo']); ?></strong>
                <?php if (!empty($paciente['cedula'])): ?>
                    &nbsp;·&nbsp; CI: <?php echo htmlspecialchars($paciente['cedula']); ?>
                <?php endif; ?>
                <?php if (!empty($paciente['edad'])): ?>
                    &nbsp;·&nbsp; <?php echo (int)$paciente['edad']; ?> años
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="page-body">
        <div id="mensaje-feedback" class="msg-feedback"></div>

        <?php if ($tipo_id_presel === null): ?>
        <!-- ── Selector de tipo ── -->
        <div class="selection-container" id="selection-container">
            <h2>Tipos de Estudio Disponibles</h2>
            <p>Selecciona el tipo de ecografía a registrar para este paciente.</p>
            <?php if (empty($tipos)): ?>
                <p style="color:#94a3b8;"><em>No hay tipos de ecografía configurados.</em></p>
            <?php else: ?>
                <div class="selection-grid">
                    <?php foreach ($tipos as $tipo): ?>
                        <div class="selection-card" onclick="mostrarFormularioEstudio(<?php echo (int)$tipo['id']; ?>)">
                            <i class="<?php echo htmlspecialchars($tipo['icono'] ?: 'fa-solid fa-wave-square'); ?>"></i>
                            <?php if (!empty($tipo['categoria'])): ?>
                                <div class="card-categoria"><?php echo htmlspecialchars($tipo['categoria']); ?></div>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($tipo['nombre']); ?></h3>
                            <p class="card-description"><?php echo htmlspecialchars($tipo['descripcion'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── Formularios por tipo ── -->
        <?php foreach ($tipos as $tipo):
            $es_preseleccionado = ($tipo_id_presel !== null && (int)$tipo['id'] === $tipo_id_presel);
        ?>
        <div id="form-tipo-<?php echo (int)$tipo['id']; ?>"
             class="hidden-form<?php echo $es_preseleccionado ? ' active' : ''; ?>">

            <!-- Cabecera del tipo de estudio -->
            <div class="form-tipo-titulo">
                <div class="tipo-icon">
                    <i class="<?php echo htmlspecialchars($tipo['icono'] ?: 'fa-solid fa-wave-square'); ?>"></i>
                </div>
                <div>
                    <h2><?php echo htmlspecialchars($tipo['nombre']); ?></h2>
                    <p>
                        <?php if (!empty($tipo['categoria'])): ?>
                            <strong><?php echo htmlspecialchars($tipo['categoria']); ?></strong> &nbsp;·&nbsp;
                        <?php endif; ?>
                        <?php echo htmlspecialchars($tipo['descripcion'] ?? ''); ?>
                    </p>
                </div>
            </div>

            <form method="POST" action="guardar_informe_estudio.php" class="form-estudio">
                <?= csrf_field() ?>
                <input type="hidden" name="paciente_id"       value="<?php echo $paciente_id; ?>">
                <input type="hidden" name="tipo_ecografia_id" value="<?php echo (int)$tipo['id']; ?>">
                <input type="hidden" name="esquema_version"   value="<?php echo (int)$tipo['esquema_version']; ?>">

                <?php echo eco_render_formulario($tipo['esquema_decoded'], $datos_iniciales_por_tipo); ?>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="volverASeleccion()">
                        <i class="fa-solid fa-arrow-left"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Informe
                    </button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </div><!-- .page-body -->
</div><!-- .page-wrapper -->

<script>
function mostrarFormularioEstudio(tipoId) {
    const grid = document.getElementById('selection-container');
    if (grid) grid.style.display = 'none';
    document.querySelectorAll('.hidden-form').forEach(f => f.classList.remove('active'));
    const objetivo = document.getElementById('form-tipo-' + tipoId);
    if (objetivo) objetivo.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function volverASeleccion() {
    document.querySelectorAll('.hidden-form').forEach(f => f.classList.remove('active'));
    const grid = document.getElementById('selection-container');
    if (grid) {
        grid.style.display = '';
    } else {
        history.back();
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('.form-estudio').forEach(formulario => {
    formulario.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const feedback  = document.getElementById('mensaje-feedback');
        const submitBtn = formulario.querySelector('button[type="submit"]');
        feedback.innerHTML = '';
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';

        try {
            const resp = await fetch(formulario.action, { method: 'POST', body: new FormData(formulario) });
            const json = await resp.json();
            if (json.success) {
                feedback.innerHTML = '<div class="msg-ok"><i class="fa-solid fa-circle-check"></i> ' + json.message + '</div>';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => {
                    window.location.href = 'ver_informe_estudio.php?informe_id=' + json.informe_id;
                }, 900);
            } else {
                feedback.innerHTML = '<div class="msg-error"><i class="fa-solid fa-triangle-exclamation"></i> ' + (json.message || 'Error al guardar.') + '</div>';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Informe';
            }
        } catch (err) {
            feedback.innerHTML = '<div class="msg-error">Error de red: ' + err.message + '</div>';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Informe';
        }
    });
});
</script>
</body>
</html>
