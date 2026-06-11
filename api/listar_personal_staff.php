<?php
session_start();
include __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    http_response_code(403);
    exit('<p class="staff-lista-empty">Acceso denegado.</p>');
}

$tipo = isset($_POST['tipo']) ? trim((string)$_POST['tipo']) : '';
if (!in_array($tipo, ['ecografista', 'recepcionista'], true)) {
    exit('<p class="staff-lista-empty">Tipo no válido.</p>');
}

$query = isset($_POST['query']) ? trim((string)$_POST['query']) : '';
$like = '%' . $query . '%';

$sql = "SELECT id, nombre_completo, correo, cedula
    FROM usuarios
    WHERE rol = ? AND estado = 'aprobado'
    AND (nombre_completo LIKE ? OR correo LIKE ? OR cedula LIKE ?)
    ORDER BY nombre_completo ASC
    LIMIT 120";

$stmt = $conex->prepare($sql);
$stmt->bind_param('ssss', $tipo, $like, $like, $like);
$stmt->execute();
$res = $stmt->get_result();

$meta = $tipo === 'recepcionista'
    ? ['label' => 'Recepcionista', 'avatar' => 'staff-lista-avatar--rx']
    : ['label' => 'Ecografista', 'avatar' => 'staff-lista-avatar--eco'];

if (!$res || $res->num_rows === 0) {
    echo '<p class="staff-lista-empty">No hay registros que coincidan con la búsqueda.</p>';
    $stmt->close();
    $conex->close();
    exit;
}

echo '<div class="staff-lista-grid">';
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    $nombre = htmlspecialchars($row['nombre_completo'] ?? '');
    $correo = htmlspecialchars($row['correo'] ?? '');
    $cedula = htmlspecialchars($row['cedula'] ?? '—');
    $ini = '';
    foreach (preg_split('/\s+/u', trim($row['nombre_completo'] ?? '')) as $part) {
        if ($part !== '' && mb_strlen($ini) < 2) {
            $ini .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    if ($ini === '') {
        $ini = '?';
    }

    echo '<article class="staff-lista-item">';
    echo '<div class="staff-lista-item__avatar ' . $meta['avatar'] . '">' . htmlspecialchars($ini) . '</div>';
    echo '<div class="staff-lista-item__body">';
    echo '<strong class="staff-lista-item__name">' . $nombre . '</strong>';
    echo '<span class="staff-lista-item__role">' . htmlspecialchars($meta['label']) . '</span>';
    echo '<span class="staff-lista-item__meta"><i class="fa-solid fa-envelope"></i> ' . $correo . '</span>';
    echo '<span class="staff-lista-item__meta"><i class="fa-solid fa-id-card"></i> ' . $cedula . '</span>';
    echo '</div>';
    echo '<button type="button" class="btn-secondary staff-lista-item__btn" data-staff-perfil-id="' . $id . '"><i class="fa-solid fa-user-gear"></i> Perfil</button>';
    echo '</article>';
}
echo '</div>';

$stmt->close();
$conex->close();
