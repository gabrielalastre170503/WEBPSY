<?php
session_start();
include 'conexion.php';

// Seguridad: Solo administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: login.php');
    exit();
}

// Lógica para obtener todas las FAQs existentes
$faqs = $conex->query("SELECT * FROM faqs ORDER BY orden ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Preguntas Frecuentes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos consistentes con las otras páginas de gestión */
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; margin: 0; padding: 30px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .panel-header { margin-bottom: 20px; }
        .panel-header h1 { margin: 0; color: #333; }
        .back-link { text-decoration: none; color: #555; font-weight: 500; display: inline-block; margin-bottom: 20px; }
        .back-link i { margin-right: 5px; }
        .management-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .panel { background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); }
        .panel h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; font-size: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; color: #555; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px; box-sizing: border-box; font-family: "Poppins", sans-serif; transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #02b1f4; box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.2); }
        .btn { cursor: pointer; border: none; padding: 12px 25px; border-radius: 8px; background: linear-gradient(45deg, #02b1f4, #00c2ff); color: white; font-weight: 600; font-size: 16px; width: 100%; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3); }
        .content-list { list-style: none; padding: 0; }
        .content-item { display: flex; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .content-item:last-child { border-bottom: none; }
        .content-item .info { flex-grow: 1; }
        .content-item h4 { margin: 0 0 5px 0; font-weight: 600; }
        .content-item p { margin: 0; font-size: 14px; color: #777; }
        .action-links { flex-shrink: 0; margin-left: 20px; }
        .action-links a { text-decoration: none; color: #dc3545; font-size: 14px; }
        @media (max-width: 991px) { .management-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="panel-header">
            <a href="panel.php?vista=admin-contenido" class="back-link"><i class="fa-solid fa-arrow-left"></i> Volver al Panel de Contenido</a>
            <h1>Gestionar Preguntas Frecuentes (FAQ)</h1>
        </div>

        <div class="management-grid">
            <!-- Columna Izquierda: Formulario para Añadir -->
            <div class="panel">
                <h2>Añadir Nueva Pregunta</h2>
                <form action="acciones_contenido.php" method="POST">
                    <input type="hidden" name="tipo" value="faq">
                    <div class="form-group">
                        <label for="pregunta">Pregunta</label>
                        <textarea name="pregunta" id="pregunta" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="respuesta">Respuesta</label>
                        <textarea name="respuesta" id="respuesta" rows="6" required></textarea>
                    </div>
                    <button type="submit" name="accion" value="agregar" class="btn">Añadir Pregunta</button>
                </form>
            </div>

            <!-- Columna Derecha: Lista de FAQs Existentes -->
            <div class="panel">
                <h2>Preguntas Actuales</h2>
                <ul class="content-list">
                    <?php if ($faqs && $faqs->num_rows > 0): ?>
                        <?php while($faq = $faqs->fetch_assoc()): ?>
                        <li class="content-item">
                            <div class="info">
                                <h4><?php echo htmlspecialchars($faq['pregunta']); ?></h4>
                                <p><?php echo htmlspecialchars(substr($faq['respuesta'], 0, 120)) . '...'; ?></p>
                            </div>
                            <div class="action-links">
                                <a href="acciones_contenido.php?tipo=faq&accion=borrar&id=<?php echo $faq['id']; ?>" onclick="return confirm('¿Estás seguro de que quieres borrar esta pregunta?');">
                                    <i class="fa-solid fa-trash"></i> Borrar
                                </a>
                            </div>
                        </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No hay preguntas frecuentes registradas.</p>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>