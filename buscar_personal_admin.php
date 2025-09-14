<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    exit('Acceso denegado');
}

// Validar entradas
$termino_busqueda = isset($_POST['query']) ? trim($_POST['query']) : '';
$rol_busqueda = isset($_POST['rol']) ? trim($_POST['rol']) : '';
$roles_permitidos = ['psicologo', 'psiquiatra', 'secretaria'];

if (empty($rol_busqueda) || !in_array($rol_busqueda, $roles_permitidos)) {
    exit('<p>Error: Rol no válido.</p>');
}

$busqueda = "%" . $termino_busqueda . "%";

// Consulta que busca por nombre O por cédula para un rol específico
$sql = "SELECT id, nombre_completo, correo, cedula 
        FROM usuarios 
        WHERE rol = ? AND estado = 'aprobado'
        AND (nombre_completo LIKE ? OR cedula LIKE ?)
        ORDER BY nombre_completo ASC";

$stmt = $conex->prepare($sql);
$stmt->bind_param("sss", $rol_busqueda, $busqueda, $busqueda);
$stmt->execute();
$resultado = $stmt->get_result();

// Construimos la tabla de resultados
if ($resultado->num_rows > 0) {
    echo "<table class='approvals-table'><thead><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Acciones</th></tr></thead><tbody>";
    while($profesional = $resultado->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($profesional['nombre_completo']) . "</td>";
        echo "<td>" . htmlspecialchars($profesional['cedula']) . "</td>";
        echo "<td>" . htmlspecialchars($profesional['correo']) . "</td>";
        echo "<td class='action-links'>";
        if ($profesional['id'] != $_SESSION['usuario_id']) {
            echo "<a href='reset_password.php?id=" . $profesional['id'] . "' class='approve' onclick=\"return confirm('¿Seguro que quieres restablecer la contraseña?');\">Restablecer</a>";
            echo "<a href='borrar_usuario.php?id=" . $profesional['id'] . "' class='reject' onclick=\"return confirm('¿Estás seguro?');\">Borrar</a>";
        }
        echo "</td></tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron resultados para tu búsqueda.</p>";
}
$stmt->close();
$conex->close();
?>