<?php
include 'conexion.php';
$terapias = $conex->query("SELECT nombre, descripcion FROM terapias ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tipos de Terapia - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Estilos específicos para esta página */
        body {
            background-color: #f7f9fc;
        }
        .header-placeholder {
            height: 80px; /* Espacio para el menú fijo */
        }
        .page-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }
        .page-header h1 {
            font-size: 42px;
            color: #323232;
            margin-bottom: 10px;
        }
        .page-header p {
            font-size: 18px;
            color: #555;
            max-width: 700px;
            margin: 0 auto;
        }
        .therapies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .therapy-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
            border: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
        }
        .therapy-card h3 {
            font-size: 22px;
            color: #02b1f4;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .therapy-card p {
            font-size: 16px;
            line-height: 1.7;
            color: #555;
            flex-grow: 1; /* Asegura que todas las tarjetas tengan la misma altura */
        }
    </style>
</head>
<body>

    <!-- Menú de Navegación (copiado de index.php) -->
    <header>
        <div class="menu container">
            <a href="index.php" class="logo">WebPSY</a>
            <input type="checkbox" id="menu" />
            <label for="menu">
                <img src="Images/menu.png" class="menu-icono" alt="menu">
            </label>
            <nav class="navbar">
                <ul>
                    <li><a href="index.php#inicio">Inicio</a></li>
                    <li><a href="index.php#nosotros">Nosotros</a></li>
                    <li><a href="index.php#servicios">Servicios</a></li>
                    <li><a href="index.php#contacto">Contacto</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="header-placeholder"></div>

    <main class="page-container">
        <div class="page-header">
            <h1>Tipos de Terapia</h1>
            <p>Descubre los diferentes enfoques terapéuticos que ofrecemos para ayudarte a encontrar el camino que mejor se adapte a tus necesidades.</p>
        </div>

        <div class="therapies-grid">
            <?php if ($terapias && $terapias->num_rows > 0): ?>
                <?php while ($terapia = $terapias->fetch_assoc()): ?>
                <div class="therapy-card">
                    <h3><?php echo htmlspecialchars($terapia['nombre']); ?></h3>
                    <p><?php echo htmlspecialchars($terapia['descripcion']); ?></p>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No hay terapias disponibles para mostrar en este momento.</p>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>
