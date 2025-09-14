<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    exit('Acceso denegado');
}

// Recoger y limpiar los datos de entrada
$termino_busqueda = isset($_POST['query']) ? trim($_POST['query']) : '';
$filtro = isset($_POST['filtro']) ? trim($_POST['filtro']) : 'aprobados';
$busqueda_like = "%" . $termino_busqueda . "%";

// Inicializar variables para la consulta
$sql = "";
$types = "";
$params = [];

// Construir la consulta SQL basada en el filtro
switch ($filtro) {
    case 'pendientes':
        $sql = "SELECT id, nombre_completo, correo, cedula, rol, estado FROM usuarios WHERE estado = 'pendiente' AND (nombre_completo LIKE ? OR cedula LIKE ?) ORDER BY nombre_completo ASC";
        $types = "ss";
        $params = [$busqueda_like, $busqueda_like];
        break;
    case 'personal':
        $sql = "SELECT id, nombre_completo, correo, cedula, rol, estado FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra', 'secretaria') AND estado IN ('aprobado', 'inhabilitado') AND (nombre_completo LIKE ? OR cedula LIKE ?) ORDER BY rol, nombre_completo ASC";
        $types = "ss";
        $params = [$busqueda_like, $busqueda_like];
        break;
    case 'doctores':
        $sql = "SELECT id, nombre_completo, correo, cedula, rol, estado FROM usuarios WHERE rol IN ('psicologo', 'psiquiatra') AND estado IN ('aprobado', 'inhabilitado') AND (nombre_completo LIKE ? OR cedula LIKE ?) ORDER BY rol, nombre_completo ASC";
        $types = "ss";
        $params = [$busqueda_like, $busqueda_like];
        break;
    
    // --- NUEVO CASO AÑADIDO ---
    case 'pacientes':
        $sql = "SELECT id, nombre_completo, correo, cedula, rol, estado FROM usuarios WHERE rol = 'paciente' AND estado IN ('aprobado', 'inhabilitado') AND (nombre_completo LIKE ? OR cedula LIKE ?) ORDER BY nombre_completo ASC";
        $types = "ss";
        $params = [$busqueda_like, $busqueda_like];
        break;

    case 'aprobados':
    default:
        $sql = "SELECT id, nombre_completo, correo, cedula, rol, estado FROM usuarios WHERE estado IN ('aprobado', 'inhabilitado') AND (nombre_completo LIKE ? OR cedula LIKE ?) ORDER BY rol, nombre_completo ASC";
        $types = "ss";
        $params = [$busqueda_like, $busqueda_like];
        break;
}

// Preparar y ejecutar la consulta
$stmt = $conex->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();

// Construir la tabla de resultados (el HTML de salida no cambia)
if ($resultado->num_rows > 0) {
    echo "<table class='users-table'><thead><tr><th class='sortable-header'>Nombre</th><th class='sortable-header'>Cédula</th><th class='sortable-header'>Correo</th><th class='sortable-header'>Rol</th><th class='sortable-header'>Estado</th><th>Acciones</th></tr></thead><tbody>";
    while($usuario = $resultado->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($usuario['nombre_completo']) . "</td>";
    echo "<td>" . htmlspecialchars($usuario['cedula'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($usuario['correo']) . "</td>";
    echo "<td>" . htmlspecialchars(ucfirst($usuario['rol'])) . "</td>";
    echo "<td><span class='status-badge status-" . htmlspecialchars($usuario['estado']) . "'>" . htmlspecialchars(ucfirst($usuario['estado'])) . "</span></td>";
    // ... (código que muestra las otras celdas de la tabla) ...
         echo "<td class='action-links'>";
        if ($_SESSION['usuario_id'] != $usuario['id']) {
            // Botón para Habilitar/Inhabilitar
            if ($usuario['estado'] == 'aprobado') {
                echo "<button class='reject' onclick='toggleUserState(" . $usuario['id'] . ", \"inhabilitado\")'>Inhabilitar</button>";
            } else if ($usuario['estado'] == 'inhabilitado') {
                echo "<button class='approve' onclick='toggleUserState(" . $usuario['id'] . ", \"aprobado\")'>Habilitar</button>";
            }
            
            // --- NUEVO BOTÓN AÑADIDO ---
            // Botón para Restablecer Contraseña
            echo "<a href='reset_password.php?id=" . $usuario['id'] . "&filtro=" . urlencode($filtro) . "' class='btn-secondary' onclick=\"return confirm('¿Estás seguro de que quieres restablecer la contraseña de este usuario? Se generará una nueva contraseña temporal.');\">Restablecer</a>";
        }
        echo "</td></tr>";
}
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron usuarios que coincidan con los criterios.</p>";
}
$stmt->close();
$conex->close();
?>