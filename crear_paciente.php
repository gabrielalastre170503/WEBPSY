<?php
session_start();
include 'conexion.php';

// Seguridad: Solo roles autorizados pueden acceder a esta página
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['psicologo', 'psiquiatra', 'administrador'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir Nuevo Paciente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos generales */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
            font-family: "Poppins", sans-serif;
        }

        /* Contenedor principal del formulario */
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px; /* Ancho que ya te gustaba */
        }

        /* Título del formulario */
        .form-container h2 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 30px;
            color: #333;
            font-weight: 600;
        }
        
        /* Rejilla para organizar los campos */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Dos columnas */
            gap: 25px; /* Espacio entre los campos */
        }
        
        /* Clase para que un campo ocupe todo el ancho */
        .full-width {
            grid-column: 1 / -1;
        }

        /* Grupo de input (label, icono, campo) */
        .input-group {
            position: relative;
        }

        .input-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px 12px 45px; /* Espacio a la izquierda para el ícono */
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #02b1f4;
            box-shadow: 0 0 0 3px rgba(2, 177, 244, 0.2);
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(2px); /* Ajuste fino para centrar el icono */
            color: #aaa;
            transition: color 0.3s;
        }

        .input-group input:focus + i {
            color: #02b1f4;
        }

        /* Estilos para el botón */
        .btn-submit {
            width: 100%;
            padding: 13px;
            margin-top: 40px;
            font-size: 13.5px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(45deg, #02b1f4, #00c2ff);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(2, 177, 244, 0.3);
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(2, 177, 244, 0.4);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #818181;
            text-decoration: none;
            font-size: 14px;
        }

        /* Adaptación para pantallas pequeñas */
        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr; /* Una sola columna en móviles */
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Añadir Nuevo Paciente al Sistema</h2>
        <form action="guardar_paciente.php" method="POST">
            <div class="form-grid">
                <div class="input-group full-width">
                    <label for="nombre_completo">Nombre Completo</label>
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="nombre_completo" id="nombre_completo" placeholder="Ej: Ana Pérez" required>
                </div>
                <div class="input-group">
                    <label for="cedula">Cédula de Identidad</label>
                     <i class="fa-solid fa-id-card"></i>
                    <input type="number" name="cedula" id="cedula" placeholder="Ej: 20123456" required>
                </div>
                <div class="input-group">
                    <label for="correo">Correo Electrónico</label>
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="correo" id="correo" placeholder="Ej: correo@ejemplo.com" required>
                </div>
            </div>
            <button type="submit" class="btn-submit">Guardar Paciente</button>
            <a href="panel.php" class="back-link">Cancelar y Volver al panel</a>
        </form>
    </div>
</body>
</html>
