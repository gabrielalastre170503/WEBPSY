<?php
session_start();
include 'conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra'])) {
    exit('Acceso denegado');
}

$psicologo_id = $_SESSION['usuario_id'];
$termino_busqueda = isset($_POST['query']) ? $_POST['query'] : '';

// Preparamos el término para la búsqueda con LIKE
$busqueda = "%" . $termino_busqueda . "%";

// Consulta que busca por nombre O por cédula
$sql = "SELECT DISTINCT u.id, u.nombre_completo, u.correo, u.cedula 
        FROM usuarios u
        LEFT JOIN citas c ON u.id = c.paciente_id
        WHERE u.rol = 'paciente' AND u.estado = 'aprobado'
        AND (u.creado_por_psicologo_id = ? OR c.psicologo_id = ?)
        AND (u.nombre_completo LIKE ? OR u.cedula LIKE ?)";

$stmt = $conex->prepare($sql);
$stmt->bind_param("iiss", $psicologo_id, $psicologo_id, $busqueda, $busqueda);
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