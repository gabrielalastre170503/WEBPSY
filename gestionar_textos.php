<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: login.php');
    exit();
}

// Obtener los textos actuales de la base de datos
$contenido = [];
$resultado = $conex->query("SELECT clave, valor FROM contenido_web WHERE clave IN ('mision', 'vision', 'valores')");
while ($fila = $resultado->fetch_assoc()) {
    $contenido[$fila['clave']] = $fila['valor'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Contenido - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; margin: 0; padding: 30px; }
        .container { max-width: 900px; margin: 0 auto; }
        .panel { background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); }
        h1 { margin-top: 0; color: #333; }
        .back-link { text-decoration: none; color: #555; font-weight: 500; display: inline-block; margin-bottom: 20px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 16px; color: #333; }
        .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px; box-sizing: border-box; font-family: "Poppins", sans-serif; resize: vertical; min-height: 120px; }
                .btn {
            cursor: pointer;
            border: none;
            padding: 12px 40px; /* Tamaño del botón */
            border-radius: 8px;
            background: linear-gradient(45deg, #02b1f4, #00c2ff); /* Color azul claro */
            color: white;
            font-weight: 500;
            font-size: 16px;
            width: auto; /* Para que no ocupe el 100% */
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
        }
        .form-actions {
            text-align: center; /* Centra el botón */
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="panel.php?vista=admin-contenido" class="back-link"><i class="fa-solid fa-arrow-left"></i> Volver al Panel</a>
        <div class="panel">
            <h1>Editar Contenido de la Sección "Nosotros"</h1>
            <p>Los cambios que realices aquí se reflejarán inmediatamente en la página principal.</p>
            
            <form action="acciones_contenido.php" method="POST">
                <input type="hidden" name="tipo" value="textos_web">
                
                <div class="form-group">
                    <label for="mision">Misión:</label>
                    <textarea name="mision" id="mision" rows="5"><?php echo htmlspecialchars($contenido['mision'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="vision">Visión:</label>
                    <textarea name="vision" id="vision" rows="5"><?php echo htmlspecialchars($contenido['vision'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="valores">Valores:</label>
                    <textarea name="valores" id="valores" rows="3"><?php echo htmlspecialchars($contenido['valores'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
    <button type="submit" name="accion" value="actualizar" class="btn">Guardar Cambios</button>
</div>
            </form>
        </div>
    </div>
</body>
</html>