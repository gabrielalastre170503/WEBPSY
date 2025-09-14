<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    exit('Acceso denegado.');
}

$psicologo_id = $_SESSION['usuario_id'];
$query = isset($_POST['query']) ? $conex->real_escape_string($_POST['query']) : '';

$sql = "SELECT c.id, c.fecha_cita, c.estado, u.nombre_completo as paciente_nombre, u.cedula as paciente_cedula
        FROM citas c
        JOIN usuarios u ON c.paciente_id = u.id
        WHERE c.psicologo_id = ?
        AND (u.nombre_completo LIKE ? OR u.cedula LIKE ?)
        ORDER BY c.fecha_cita DESC";

$stmt = $conex->prepare($sql);
$searchTerm = "%" . $query . "%";
$stmt->bind_param("iss", $psicologo_id, $searchTerm, $searchTerm);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    echo "<table class='approvals-table'><thead><tr><th class='sortable-header'>Paciente</th><th class='sortable-header'>Cédula</th><th class='sortable-header'>Fecha Programada</th><th class='sortable-header'>Estado</th></tr></thead><tbody>";
    while($cita = $resultado->fetch_assoc()) {
        $estado_texto = $cita['estado'];
        if ($cita['estado'] == 'pendiente_paciente') {
            $estado_texto = 'Pospuesta';
        }
        
        // Añadimos la clase clickable-row y el data-cita-id
        echo "<tr class='clickable-row' data-cita-id='" . $cita['id'] . "'>";
        echo "<td>" . htmlspecialchars($cita['paciente_nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($cita['paciente_cedula']) . "</td>";
        echo "<td>" . ($cita['fecha_cita'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita']))) : 'N/A') . "</td>";
        echo "<td><span class='status-badge status-" . htmlspecialchars($cita['estado']) . "'>" . htmlspecialchars(ucfirst($estado_texto)) . "</span></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron citas.</p>";
}
$stmt->close();
$conex->close();
?>