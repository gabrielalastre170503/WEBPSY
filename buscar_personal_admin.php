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
$roles_permitidos = ['ecografista', 'recepcionista'];

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
            echo "<form method='post' action='reset_password.php' style='display:inline' onsubmit=\"return confirm('¿Seguro que quieres restablecer la contraseña?');\">" . csrf_field() . "<input type='hidden' name='id' value='" . (int)$profesional['id'] . "'><button type='submit' class='approve'>Restablecer</button></form>";
            echo "<form method='post' action='borrar_usuario.php' style='display:inline' onsubmit=\"return confirm('¿Estás seguro?');\">" . csrf_field() . "<input type='hidden' name='id' value='" . (int)$profesional['id'] . "'><button type='submit' class='reject'>Borrar</button></form>";
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