<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista'])) {
    exit('Acceso denegado');
}

require_once __DIR__ . '/lib/paginacion.php';
[$page, $perPage, $offset] = eco_paginacion_args(25);

$ecografista_id = $_SESSION['usuario_id'];
$termino_busqueda = isset($_POST['query']) ? $_POST['query'] : '';
$busqueda = "%" . $termino_busqueda . "%";

$where = "WHERE u.rol = 'paciente' AND u.estado = 'aprobado'
        AND (u.creado_por_id = ? OR c.ecografista_id = ?)
        AND (u.nombre_completo LIKE ? OR u.cedula LIKE ? OR u.direccion LIKE ?)";

// Total para la paginación (COUNT DISTINCT por el LEFT JOIN a citas).
$cstmt = $conex->prepare("SELECT COUNT(DISTINCT u.id) AS n
        FROM usuarios u
        LEFT JOIN citas c ON u.id = c.paciente_id
        $where");
$cstmt->bind_param("iisss", $ecografista_id, $ecografista_id, $busqueda, $busqueda, $busqueda);
$cstmt->execute();
$total = (int)($cstmt->get_result()->fetch_assoc()['n'] ?? 0);
$cstmt->close();

// --- CONSULTA ACTUALIZADA PARA INCLUIR fecha_registro ---
$sql = "SELECT DISTINCT u.id, u.nombre_completo, u.correo, u.cedula, u.direccion, u.fecha_registro
        FROM usuarios u
        LEFT JOIN citas c ON u.id = c.paciente_id
        $where
        ORDER BY u.nombre_completo ASC
        LIMIT ? OFFSET ?";

$stmt = $conex->prepare($sql);
$stmt->bind_param("iisssii", $ecografista_id, $ecografista_id, $busqueda, $busqueda, $busqueda, $perPage, $offset);
$stmt->execute();
$resultado = $stmt->get_result();

// --- TABLA DE RESULTADOS CON LA NUEVA COLUMNA ---
if ($resultado->num_rows > 0) {
    // Añadimos la cabecera 'Fecha de Ingreso' y la hacemos ordenable
    echo "<table class='approvals-table'><thead><tr><th class='sortable-header'>Nombre</th><th class='sortable-header'>Cédula</th><th class='sortable-header'>Correo</th><th class='sortable-header'>Dirección</th><th class='sortable-header'>Fecha de Ingreso</th><th>Acciones</th></tr></thead><tbody>";
    while($paciente = $resultado->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($paciente['nombre_completo']) . "</td>";
        echo "<td>" . htmlspecialchars($paciente['cedula']) . "</td>";
        echo "<td>" . htmlspecialchars($paciente['correo']) . "</td>";
        echo "<td>" . htmlspecialchars($paciente['direccion'] ?: '—') . "</td>";
        // Mostramos la fecha de ingreso formateada
        echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime($paciente['fecha_registro']))) . "</td>";
        echo "<td class='action-links'>
        <button class='approve' onclick='abrirModalGestionarPaciente(" . $paciente['id'] . ")'>Gestionar</button>
        <button class='btn-secondary' onclick='abrirModalProgramarCita(" . $paciente['id'] . ", \"" . htmlspecialchars($paciente['nombre_completo'], ENT_QUOTES) . "\")'>Programar Cita</button>
      </td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron pacientes que coincidan con tu búsqueda.</p>";
}
echo eco_paginacion_html($page, $perPage, $total, 'pacientes');
$stmt->close();
$conex->close();
?>