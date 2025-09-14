<?php
include 'conexion.php';

// Determinar qué rol mostrar basado en el parámetro de la URL
$rol_a_mostrar = $_GET['rol'] ?? 'psicologo';
$titulo_pagina = '';
$subtitulo_pagina = '';

// Validar el rol y establecer los títulos
if ($rol_a_mostrar == 'psicologo') {
    $titulo_pagina = 'Nuestros Psicólogos';
    $subtitulo_pagina = 'Profesionales dedicados a la terapia y el acompañamiento emocional.';
} elseif ($rol_a_mostrar == 'psiquiatra') {
    $titulo_pagina = 'Nuestros Psiquiatras';
    $subtitulo_pagina = 'Médicos especialistas en el diagnóstico y tratamiento de trastornos mentales.';
} else {
    // Si el rol no es válido, redirigir o mostrar un error
    header("Location: index.php");
    exit();
}

// Consultar la base de datos para obtener los profesionales
$stmt = $conex->prepare("SELECT nombre_completo, correo FROM usuarios WHERE rol = ? AND estado = 'aprobado' ORDER BY nombre_completo ASC");
$stmt->bind_param("s", $rol_a_mostrar);
$stmt->execute();
$profesionales = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo_pagina; ?> - WebPSY</title>
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
        .professionals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .professional-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
            border: 1px solid #e0e0e0;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .professional-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }
        .professional-card .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #e9f7fe;
            color: #02b1f4;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            font-size: 50px;
        }
        .professional-card h3 {
            font-size: 22px;
            color: #323232;
            margin: 0 0 5px 0;
        }
        .professional-card .role-tag {
            font-size: 14px;
            color: #02b1f4;
            font-weight: 500;
            margin-bottom: 15px;
        }
        .professional-card .contact-info {
            font-size: 15px;
            color: #555;
        }
        .professional-card .contact-info i {
            margin-right: 8px;
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
            <h1><?php echo $titulo_pagina; ?></h1>
            <p><?php echo $subtitulo_pagina; ?></p>
        </div>

        <div class="professionals-grid">
            <?php if ($profesionales && $profesionales->num_rows > 0): ?>
                <?php while ($profesional = $profesionales->fetch_assoc()): ?>
                <div class="professional-card">
                    <div class="avatar">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($profesional['nombre_completo']); ?></h3>
                    <div class="role-tag"><?php echo htmlspecialchars(ucfirst($rol_a_mostrar)); ?></div>
                    <div class="contact-info">
                        <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($profesional['correo']); ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No hay profesionales disponibles para mostrar en este momento.</p>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>
