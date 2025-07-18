<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seleccionar Tipo de Registro</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Aquí van todos los estilos que te di en el paso anterior */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: "Poppins", sans-serif;
            background-color: #f0f2f5; /* Un gris más suave */
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .main-container {
            text-align: center;
            background-color: white;
            padding: 40px 50px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            width: 90%;
        }
        .main-container h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 30px;
        }
        .roles-grid {
            display: flex;
            justify-content: center;
            gap: 25px; /* Espacio entre las tarjetas */
            flex-wrap: wrap; /* Permite que las tarjetas se ajusten en pantallas pequeñas */
        }
        .rol-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 180px;
            height: 180px;
            text-decoration: none;
            color: #323232;
            background-color: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
        }
        .rol-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
            border-color: #02b1f4;
        }
        .rol-card i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #02b1f4;
        }
        .rol-card span {
            font-size: 18px;
            font-weight: 500;
        }
        .rol-paciente {
            border-color: #5cb85c;
        }
        .rol-paciente i {
            color: #5cb85c;
        }
        .rol-paciente:hover {
            border-color: #4cae4c;
        }
        .back-link {
            margin-top: 40px;
        }
        .back-link a {
            text-decoration: none;
            color: #818181;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <h2>Únete a nuestra comunidad</h2>
        <div class="roles-grid">
            <a href="registro.php?rol=psicologo" class="rol-card">
                <i class="fa-solid fa-user-doctor"></i>
                <span>Psicólogo</span>
            </a>
            
            <a href="registro.php?rol=psiquiatra" class="rol-card">
                <i class="fa-solid fa-brain"></i>
                <span>Psiquiatra</span>
            </a>

            <a href="registro.php?rol=secretaria" class="rol-card">
                <i class="fa-solid fa-user-nurse"></i>
                <span>Secretario/a</span>
            </a>
            
            <a href="registro.php?rol=paciente" class="rol-card rol-paciente">
                <i class="fa-solid fa-user"></i>
                <span>Paciente</span>
            </a>
        </div>
        <div class="back-link">
            <a href="index.php">Volver al inicio</a>
        </div>
    </div>
</body>
</html>