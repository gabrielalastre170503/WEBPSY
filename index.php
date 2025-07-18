<?php
       include("send.php");
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebPSY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="style.css">
    
</head>
<body>

    <?php if (isset($_GET['status'])): ?>
    <style>
        /* Estilos para la barra de mensaje */
        .mensaje-estado {
            padding: 15px;
            margin: 20px auto;
            text-align: center;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .mensaje-exito {
            background-color: #28a745; /* Color verde */
        }
        .mensaje-error {
            background-color: #dc3545; /* Color rojo */
        }
    </style>

    <?php
    $mensaje = '';
    $clase_css = '';
    
    // Decidimos qué mensaje y color usar
    if ($_GET['status'] == 'success') {
        $mensaje = '¡Consulta agendada con éxito! Nos pondremos en contacto contigo pronto.';
        $clase_css = 'mensaje-exito';
    } elseif ($_GET['status'] == 'error') {
        $mensaje = 'Hubo un error al enviar tu consulta. Por favor, inténtalo de nuevo.';
        $clase_css = 'mensaje-error';
    } elseif ($_GET['status'] == 'empty_fields') {
        $mensaje = 'Error: Todos los campos son obligatorios.';
        $clase_css = 'mensaje-error';
    }

    // Mostramos el mensaje en la página si existe
    if ($mensaje) {
        echo "<div class='mensaje-estado $clase_css'>$mensaje</div>";
    }
    ?>
 <?php endif; ?>

    <header id="inicio" class="header">

        <div class="menu container">
            <a href="#" class="logo"> logo</a>
            <input type="checkbox" id="menu" />
            <label for="menu">
                <img src="Images/menu.png" class="menu-icpmp" alt="menu">
            </label>
            <nav class="navbar">
             <ul>
               <li><a href="#inicio">Inicio</a></li>
               <li><a href="#nosotros">Nosotros</a></li>
               <li><a href="#servicios">Servicios</a></li>
               <li><a href="#contacto">Contacto</a></li>
             </ul>
            </nav>

        </div>

        <div class="header-content container">
            <div class="header-txt">
                <h1>Programa de Salud Mental</h1>
                <p>
                    Sistema Automatizado para el registro y diagnostico de 
                    pacientes del consultorio psicologico Jose Alastre
                </p>
                <a href="#" class="btn-1">INICIAR SESION</a>
            </div>
            <div class="header-img">
                <img src="Images/left.png" alt="">


            </div>

        </div>

    </header>

    <section id="nosotros" class="about container">

        <div class="about-img">
            <img src="Images/about.png" alt="">
        </div>
        <div class="about-txt">
            <h2>Nosotros</h2>
            <p>
                MISIÓN:
                Dar un servicio centrado en la persona, abordando los problemas 
                derivados de su salud mental para lograr la creación y recuperación 
                de un proyecto de vida. Este proyecto de vida se centra en 
                la recuperación a través de las áreas vitales de la persona 
                como pueden ser el bienestar emocional, la inclusión social, 
                el empleo y la integración.
            </p>
            <br>
            <p>
                VISIÓN:
                Ser una empresa de referencia en el sector en términos de calidad de 
                servicio, profesionalidad y compromiso con las personas que atendemos 
                y sus familias, con una clara vocación de incrementar nuestra red de 
                centros para tener una mayor capilaridad y ofrecer servicio en todo 
                el territorio español.
            </p>
            <br>
            <p>
                VALORES:
                Confianza, Empatia, Profesionalidad, Estusiasmo
            </p>

        </div>

    </section>

    <main id="servicios" class="Servicios">

        <h2>Servicios</h2>
        <div class="Servicios-content container">

            <div class="Servicio-1">
                <i class="fa-sharp fa-solid fa-hospital-user"></i>
                <h3>Psicologia</h3>
            </div>

            <div class="Servicio-1">
                <i class="fa-sharp fa-solid fa-stethoscope"></i>
                <h3>Psiquiatria</h3>
            </div>

            <div class="Servicio-1">
                <i class="fa-solid fa-bed-pulse"></i>
                <h3>Terapia</h3>
            </div>

            <div class="Servicio-1">
                <i class="fa-solid fa-hospital"></i>
                <h3>Farmacologia</h3>
            </div>

        </div>

    </main>

    <section id="contacto" class="Formulario container">

        <form method="post" autocomplete="off">
    <h2>Agenda Consulta</h2>
    <div class="input-group">
        
        <div class="input-container">
            <label for="name" class="sr-only">Nombre y Apellido</label>
            <input type="text" id="name" name="name" placeholder="Nombre y Apellido" required>
            <i class="fa-solid fa-user"></i>
        </div>
        
        <div class="input-container">
            <label for="phone" class="sr-only">Teléfono Celular</label>
            <input type="tel" id="phone" name="phone" placeholder="Teléfono Celular" required>
            <i class="fa-solid fa-phone"></i>
        </div>
        
        <div class="input-container">
            <label for="email" class="sr-only">Correo Electrónico</label>
            <input type="email" id="email" name="email" placeholder="Correo" required>
            <i class="fa-solid fa-envelope"></i>
        </div>
        
        <div class="input-container">
            <label for="message" class="sr-only">Detalles de la Consulta</label>
            <textarea id="message" name="message" placeholder="Detalles de la Consulta" required></textarea>
        </div>
        
        <input type="submit" name="send" class="btn" value="Agendar Consulta">
    
    </div>
 </form>

    </section>

    <footer class="footer">

        <div class="footer-content container">

            <div class="link">
                <a href="#" class="logo">logo</a>
            </div>

            <div class="link">
              <ul>
                <li><a href="#inicio">Inicio</a></li>
                <li><a href="#nosotros">Nosotros</a></li>
                <li><a href="#servicios">Servicios</a></li>
                <li><a href="#contacto">Contacto</a></li>
              </ul>
            </div>

        </div>

    </footer>

    <script>
        function myfunction() {
            window.location.href="http://localhost/WebPSY"
        }
    </script>

    
</body>
</html>