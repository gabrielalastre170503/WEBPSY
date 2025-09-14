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
$sql = "SELECT id, nombre_completo, correo, cedula 
        FROM usuarios 
        WHERE rol = 'paciente' AND estado = 'aprobado'
        AND (nombre_completo LIKE ? OR cedula LIKE ?)";

$stmt = $conex->prepare($sql);
$stmt->bind_param("ss", $busqueda, $busqueda);
$stmt->execute();
$resultado = $stmt->get_result();

// Construimos la tabla de resultados
if ($resultado->num_rows > 0) {
    echo "<table class='approvals-table'><thead><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Acciones</th></tr></thead><tbody>";
    while($paciente = $resultado->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($paciente['nombre_completo']) . "</td>";
        echo "<td>" . htmlspecialchars($paciente['cedula']) . "</td>";
        echo "<td>" . htmlspecialchars($paciente['correo']) . "</td>";
        echo "<td class='action-links'><a href='gestionar_paciente.php?paciente_id=" . $paciente['id'] . "' class='approve'>Gestionar</a></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron pacientes que coincidan con tu búsqueda.</p>";
}
$stmt->close();
$conex->close();
?>