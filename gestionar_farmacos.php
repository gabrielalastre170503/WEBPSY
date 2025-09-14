<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: login.php');
    exit();
}

// Lógica para obtener todos los fármacos existentes
$farmacos = $conex->query("SELECT * FROM farmacos ORDER BY nombre_comercial ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Fármacos - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos consistentes con la página de gestionar terapias */
        body {
            background-color: #f0f2f5;
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 30px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .panel-header {
            margin-bottom: 20px;
        }
        .panel-header h1 {
            margin: 0;
            color: #333;
        }
        .back-link {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-link i {
            margin-right: 5px;
        }
        .management-grid {
            display: grid;
            grid-template-columns: 1fr 2fr; /* Columna del formulario más pequeña */
            gap: 30px;
        }
        .panel {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.07);
        }
        .panel h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            font-size: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #02b1f4;
            box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.2);
        }
        .btn {
            cursor: pointer;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            background: linear-gradient(45deg, #02b1f4, #00c2ff); /* <-- LÍNEA CAMBIADA A AZUL */
            color: white;
            font-weight: 500;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        .content-list {
            list-style: none;
            padding: 0;
        }
        .content-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .content-item:last-child {
            border-bottom: none;
        }
        .content-item .info {
            flex-grow: 1;
        }
        .content-item h4 {
            margin: 0 0 5px 0;
            font-weight: 600;
        }
        .content-item p {
            margin: 0;
            font-size: 14px;
            color: #777;
        }
        .action-links a {
            text-decoration: none;
            color: #dc3545;
            margin-left: 15px;
            font-weight: 500;
            font-size: 14px;
        }
        
        @media (max-width: 991px) {
            .management-grid {
                grid-template-columns: 1fr; /* Una sola columna en móviles */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="panel-header">
            <a href="panel.php?vista=admin-contenido" class="back-link"><i class="fa-solid fa-arrow-left"></i> Volver al Panel de Contenido</a>
            <h1>Gestionar Fármacos</h1>
        </div>

        <div class="management-grid">
            <!-- Columna Izquierda: Formulario para Añadir -->
            <div class="panel">
                <h2>Añadir Nuevo Fármaco</h2>
                <form action="acciones_contenido.php" method="POST">
                    <input type="hidden" name="tipo" value="farmaco">
                    <div class="form-group">
                        <label for="nombre_comercial">Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" id="nombre_comercial" placeholder="Ej: Prozac" required>
                    </div>
                    <div class="form-group">
                        <label for="principio_activo">Principio Activo</label>
                        <input type="text" name="principio_activo" id="principio_activo" placeholder="Ej: Fluoxetina">
                    </div>
                    <div class="form-group">
                        <label for="descripcion_uso">Descripción de Uso</label>
                        <textarea name="descripcion_uso" id="descripcion_uso" rows="5" placeholder="Breve descripción del uso..." required></textarea>
                    </div>
                    <button type="submit" name="accion" value="agregar" class="btn">Añadir Fármaco</button>
                </form>
            </div>

            <!-- Columna Derecha: Lista de Fármacos Existentes -->
            <div class="panel">
                <h2>Fármacos Actuales</h2>
                <ul class="content-list">
    <?php if ($farmacos && $farmacos->num_rows > 0): ?>
        <?php while($farmaco = $farmacos->fetch_assoc()): ?>
        <li class="content-item">
            <div class="info">
                <h4><?php echo htmlspecialchars($farmaco['nombre_comercial']); ?></h4>
                <p><strong>Principio Activo:</strong> <?php echo htmlspecialchars($farmaco['principio_activo']); ?></p>
                <!-- LÍNEA CORREGIDA PARA MOSTRAR LA DESCRIPCIÓN -->
                <p style="margin-top: 5px; font-style: italic; color: #555;"><?php echo htmlspecialchars(substr($farmaco['descripcion_uso'], 0, 100)) . '...'; ?></p>
            </div>
            <div class="action-links">
                <a href="acciones_contenido.php?tipo=farmaco&accion=borrar&id=<?php echo $farmaco['id']; ?>" class="delete" onclick="return confirm('¿Estás seguro de que quieres borrar este fármaco?');">
                    <i class="fa-solid fa-trash"></i>
                </a>
            </div>
        </li>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No hay fármacos registrados. Añade el primero usando el formulario de la izquierda.</p>
    <?php endif; ?>
</ul>
            </div>
        </div>
    </div>
</body>
</html>