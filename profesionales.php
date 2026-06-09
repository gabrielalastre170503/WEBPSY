<?php
include 'conexion.php';

$titulo_pagina    = 'Nuestros Ecografistas';
$subtitulo_pagina = 'Profesionales especializados en diagnostico por imagen.';

$stmt = $conex->prepare("SELECT nombre_completo, correo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC");
$stmt->execute();
$profesionales = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo_pagina; ?> - EcoMadelleine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f7f9fc; font-family: "Poppins", sans-serif; margin: 0; }
        header { background: white; padding: 18px 0; border-bottom: 1px solid #e0e0e0; }
        .menu { max-width: 1100px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: 700; color: #02b1f4; text-decoration: none; }
        .navbar ul { display: flex; list-style: none; gap: 25px; margin: 0; padding: 0; }
        .navbar a { color: #555; text-decoration: none; font-weight: 500; }
        .navbar a:hover { color: #02b1f4; }
        .page-container { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }
        .page-header { text-align: center; margin-bottom: 50px; }
        .page-header h1 { font-size: 42px; color: #323232; margin-bottom: 10px; }
        .page-header p { font-size: 18px; color: #555; max-width: 700px; margin: 0 auto; }
        .professionals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .professional-card { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); border: 1px solid #e0e0e0; text-align: center; transition: transform .3s, box-shadow .3s; }
        .professional-card:hover { transform: translateY(-8px); box-shadow: 0 12px 25px rgba(0,0,0,0.1); }
        .professional-card .avatar { width: 120px; height: 120px; border-radius: 50%; background: #e9f7fe; color: #02b1f4;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 50px; }
        .professional-card h3 { font-size: 22px; color: #323232; margin: 0 0 5px 0; }
        .role-tag { font-size: 14px; color: #02b1f4; font-weight: 500; margin-bottom: 15px; }
        .contact-info { font-size: 15px; color: #555; }
        .contact-info i { margin-right: 8px; }
    </style>
</head>
<body>
    <header>
        <div class="menu">
            <a href="index.php" class="logo">EcoMadelleine</a>
            <nav class="navbar">
                <ul>
                    <li><a href="index.php#inicio">Inicio</a></li>
                    <li><a href="index.php#nosotros">Nosotros</a></li>
                    <li><a href="index.php#servicios">Estudios</a></li>
                    <li><a href="index.php#contacto">Contacto</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="page-container">
        <div class="page-header">
            <h1><?php echo $titulo_pagina; ?></h1>
            <p><?php echo $subtitulo_pagina; ?></p>
        </div>

        <div class="professionals-grid">
            <?php if ($profesionales && $profesionales->num_rows > 0): ?>
                <?php while ($profesional = $profesionales->fetch_assoc()): ?>
                    <div class="professional-card">
                        <div class="avatar"><i class="fa-solid fa-user-doctor"></i></div>
                        <h3><?php echo htmlspecialchars($profesional['nombre_completo']); ?></h3>
                        <div class="role-tag">Ecografista</div>
                        <div class="contact-info">
                            <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($profesional['correo']); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No hay ecografistas registrados todavia.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
