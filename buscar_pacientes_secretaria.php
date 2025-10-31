<?php
session_start();
include 'conexion.php';

// Seguridad: Solo secretarias y administradores pueden buscar
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['secretaria', 'administrador'])) {
    exit('Acceso denegado');
}

$termino_busqueda = isset($_POST['query']) ? $_POST['query'] : '';
$busqueda = "%" . $termino_busqueda . "%";

// Consulta que busca en todos los pacientes
$sql = "SELECT id, nombre_completo, correo, cedula, fecha_registro 
    FROM usuarios 
    WHERE rol = 'paciente' AND estado = 'aprobado'
    AND (nombre_completo LIKE ? OR cedula LIKE ?)";

$stmt = $conex->prepare($sql);
$stmt->bind_param("ss", $busqueda, $busqueda);
$stmt->execute();
$resultado = $stmt->get_result();

// Construimos la tabla de resultados
if ($resultado->num_rows > 0) {
    echo "<table class='approvals-table'><thead><tr><th class='sortable-header'>Nombre</th><th class='sortable-header'>Cédula</th><th class='sortable-header'>Correo</th><th class='sortable-header'>Fecha de Ingreso</th><th>Acciones</th></tr></thead><tbody>";
    while($paciente = $resultado->fetch_assoc()) {
        $nombreSeguro = htmlspecialchars($paciente['nombre_completo'], ENT_QUOTES);
        echo "<tr>";
        echo "<td>" . htmlspecialchars($paciente['nombre_completo']) . "</td>";
        echo "<td>" . htmlspecialchars($paciente['cedula']) . "</td>";
        echo "<td>" . htmlspecialchars($paciente['correo']) . "</td>";
    $fechaRegistro = $paciente['fecha_registro'] ? date('d/m/Y', strtotime($paciente['fecha_registro'])) : 'No disponible';
    echo "<td>" . htmlspecialchars($fechaRegistro) . "</td>";
        echo "<td class='action-links'>";
        echo "<button class='approve' onclick='abrirModalGestionarPaciente(" . (int)$paciente['id'] . ")'>Gestionar</button>";
        echo "<button class='btn-secondary' onclick='abrirModalProgramarCita(" . (int)$paciente['id'] . ", \"" . $nombreSeguro . "\")'>Programar Cita</button>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron pacientes que coincidan con tu búsqueda.</p>";
}
$stmt->close();
$conex->close();
?>