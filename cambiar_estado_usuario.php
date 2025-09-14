<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json'); // Importante: indicar que la respuesta es JSON

$response = ['success' => false, 'message' => 'Acción no permitida.'];

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    http_response_code(403);
    echo json_encode($response);
    exit();
}

// Usamos POST para recibir los datos de JavaScript
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['nuevo_estado'])) {
    $usuario_id = $_POST['id'];
    $nuevo_estado = $_POST['nuevo_estado'];

    // Validar que el nuevo estado sea uno de los permitidos
    if (!in_array($nuevo_estado, ['aprobado', 'inhabilitado'])) {
        $response['message'] = 'Estado no válido.';
        echo json_encode($response);
        exit();
    }

    // Seguridad: El administrador no puede inhabilitarse a sí mismo
    if ($usuario_id == $_SESSION['usuario_id']) {
        $response['message'] = 'No puedes cambiar tu propio estado.';
        echo json_encode($response);
        exit();
    }

    // Actualizar el estado del usuario en la base de datos
    $stmt = $conex->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
    $stmt->bind_param("si", $nuevo_estado, $usuario_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Estado del usuario actualizado correctamente.';
    } else {
        $response['message'] = 'Error al actualizar la base de datos.';
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
?>
