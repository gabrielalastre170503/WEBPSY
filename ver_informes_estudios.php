<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista'])) {
    header('Location: ' . eco_url('login'));
    exit();
}

if (!isset($_GET['paciente_id']) || !is_numeric($_GET['paciente_id'])) {
    die("Error: Paciente no especificado.");
}
$paciente_id = (int)$_GET['paciente_id'];

$stmt = $conex->prepare("SELECT id, nombre_completo, cedula, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM usuarios WHERE id = ? AND rol = 'paciente'");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$paciente) {
    die("Error: Paciente no encontrado.");
}

$sql = "SELECT inf.id, inf.numero_informe, inf.estado, inf.fecha_estudio, inf.medico_solicitante, inf.creado_en,
               t.nombre AS tipo_nombre, t.categoria AS tipo_categoria, t.icono AS tipo_icono,
               eco.nombre_completo AS ecografista_nombre
        FROM informes_estudios inf
        JOIN tipos_ecografias t ON t.id = inf.tipo_ecografia_id
        JOIN usuarios eco       ON eco.id = inf.ecografista_id
        WHERE inf.paciente_id = ?
        ORDER BY inf.creado_en DESC";
$stmt = $conex->prepare($sql);
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$informes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Estudios - EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; margin: 0; padding: 30px; }
        .main-container { max-width: 1100px; margin: 0 auto; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #333; margin-top: 0; }
        .subheader { color: #666; margin-top: -8px; margin-bottom: 24px; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #555; text-decoration: none; font-weight: 500; }
        .back-link:hover { color: #02b1f4; }
        .toolbar { display: flex; justify-content: flex-end; margin-bottom: 18px; }
        .btn { cursor: pointer; padding: 10px 20px; font-size: 14px; font-weight: 500; border-radius: 6px; background: transparent; border: 2px solid #02b1f4; color: #02b1f4; text-decoration: none; }
        .btn:hover { background: #02b1f4; color: white; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f8fbff; color: #444; font-size: 13px; padding: 12px; border-bottom: 2px solid #e0eaf7; }
        td { padding: 14px 12px; border-bottom: 1px solid #f0f2f5; font-size: 14px; color: #444; }
        tr:hover td { background: #fafbfd; }
        .tipo-cell { display: flex; align-items: center; gap: 10px; }
        .tipo-cell i { color: #02b1f4; font-size: 18px; }
        .categoria-mini { font-size: 11px; color: #02b1f4; font-weight: 600; text-transform: uppercase; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-finalizado { background: #d4edda; color: #155724; }
        .badge-borrador   { background: #fff3cd; color: #856404; }
        .badge-firmado    { background: #cce5ff; color: #004085; }
        .badge-anulado    { background: #f8d7da; color: #721c24; }
        .accion-link { color: #02b1f4; text-decoration: none; font-weight: 500; }
        .accion-link:hover { text-decoration: underline; }
        .empty { text-align: center; padding: 60px 20px; color: #999; }
        .empty i { font-size: 40px; margin-bottom: 16px; color: #ced4da; }
    </style>
</head>
<body>
<div class="main-container">
    <a href="gestionar_paciente.php?paciente_id=<?php echo $paciente_id; ?>" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Volver a Gestion de Paciente
    </a>
    <h1>Historial de Estudios Ecograficos</h1>
    <p class="subheader">
        Paciente: <strong><?php echo htmlspecialchars($paciente['nombre_completo']); ?></strong>
        <?php if (!empty($paciente['cedula'])): ?> &nbsp;|&nbsp; CI: <?php echo htmlspecialchars($paciente['cedula']); ?><?php endif; ?>
    </p>

    <div class="toolbar">
        <a href="nuevo_informe_estudio.php?paciente_id=<?php echo $paciente_id; ?>" class="btn">
            <i class="fa-solid fa-plus"></i> Nuevo Informe
        </a>
    </div>

    <?php if (empty($informes)): ?>
        <div class="empty">
            <i class="fa-solid fa-folder-open"></i>
            <p>Este paciente todavia no tiene informes de estudios ecograficos.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>N&deg; Informe</th>
                    <th>Tipo de Estudio</th>
                    <th>Fecha Estudio</th>
                    <th>Medico Solicitante</th>
                    <th>Ecografista</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($informes as $inf): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inf['numero_informe']); ?></strong></td>
                        <td>
                            <div class="tipo-cell">
                                <i class="<?php echo htmlspecialchars($inf['tipo_icono'] ?: 'fa-solid fa-wave-square'); ?>"></i>
                                <div>
                                    <div class="categoria-mini"><?php echo htmlspecialchars($inf['tipo_categoria']); ?></div>
                                    <?php echo htmlspecialchars($inf['tipo_nombre']); ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($inf['fecha_estudio'] ?? '--'); ?></td>
                        <td><?php echo htmlspecialchars($inf['medico_solicitante'] ?? '--'); ?></td>
                        <td><?php echo htmlspecialchars($inf['ecografista_nombre']); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($inf['estado']); ?>"><?php echo htmlspecialchars($inf['estado']); ?></span></td>
                        <td><a href="<?= eco_url('informe/' . (int)$inf['id']) ?>" class="accion-link">Ver <i class="fa-solid fa-arrow-right"></i></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
