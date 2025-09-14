<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    exit('Acceso denegado');
}

$termino_busqueda = isset($_POST['query']) ? $_POST['query'] : '';
$busqueda = "%" . $termino_busqueda . "%";

// Consulta actualizada para incluir la cédula del paciente
$sql = "SELECT 
            c.id, c.fecha_cita, c.estado,
            paciente.nombre_completo as paciente_nombre,
            paciente.cedula as paciente_cedula, -- <-- Campo añadido
            psicologo.nombre_completo as psicologo_nombre
        FROM citas c
        JOIN usuarios paciente ON c.paciente_id = paciente.id
        LEFT JOIN usuarios psicologo ON c.psicologo_id = psicologo.id
        WHERE paciente.nombre_completo LIKE ? OR psicologo.nombre_completo LIKE ? OR paciente.cedula LIKE ?
        ORDER BY c.fecha_solicitud DESC";

$stmt = $conex->prepare($sql);
$stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
$stmt->execute();
$resultado = $stmt->get_result();

// Construimos la tabla de resultados
if ($resultado->num_rows > 0) {
    echo "<table class='users-table'><thead><tr><th class='sortable-header'>Paciente</th><th class='sortable-header'>Cédula</th><th class='sortable-header'>Profesional Asignado</th><th class='sortable-header'>Fecha Programada</th><th class='sortable-header'>Estado</th><th>Acciones</th></tr></thead><tbody>";
    while($cita = $resultado->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($cita['paciente_nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($cita['paciente_cedula']) . "</td>"; // <-- Nuevo campo
        echo "<td>" . htmlspecialchars($cita['psicologo_nombre'] ?? 'No Asignado') . "</td>";
        echo "<td>" . ($cita['fecha_cita'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita']))) : 'N/A') . "</td>";
        echo "<td><span class='status-badge status-" . htmlspecialchars($cita['estado']) . "'>" . htmlspecialchars(ucfirst($cita['estado'])) . "</span></td>";
        echo "<td class='action-links'><a href='borrar_cita_admin.php?id=" . $cita['id'] . "' class='reject' onclick=\"return confirm('¿Estás seguro de que quieres eliminar esta cita permanentemente?');\"><i class='fa-solid fa-trash'></i> Eliminar</a></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron citas que coincidan con tu búsqueda.</p>";
}
$stmt->close();
$conex->close();
?>