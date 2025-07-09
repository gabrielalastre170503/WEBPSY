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

    <header class="header">

        <div class="menu container">
            <a href="#" class="logo"> logo</a>
            <input type="checkbox" id="menu" />
            <label for="menu">
                <img src="Images/menu.png" class="menu-icpmp" alt="menu">
            </label>
            <nav class="navbar">
                <ul>
                    <li><a href="#">Inicio</a></li>
                    <li><a href="#">Nosotros</a></li>
                    <li><a href="#">Ayuda</a></li>
                    <li><a href="#">Contacto</a></li>
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

    <section class="about container">

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

    <main class="Servicios">

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

    <section class="Formulario container">

        <form method="post" autocomplete="off">
            <H2>Agenda Consulta</H2>
            <div class="input-group">
                <div class="input-container">
                    <input type="text" name="name" placeholder="Nombre y Apellido">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="input-container">
                    <input type="tel" name="phone" placeholder="Telefono Celular">
                    <i class="fa-solid fa-phone"></i>
                </div>
                <div class="input-container">
                    <input type="email" name="email" placeholder="Correo">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div class="input-container">
                    <textarea name="message" placeholder="Detalles de la Consulta"></textarea>
                </div>
                <input type="submit" name="send" class="btn" onClick="myfunction()">
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
                    <li><a href="#">Inicio</a></li>
                    <li><a href="#">Nosotros</a></li>
                    <li><a href="#">Servicios</a></li>
                    <li><a href="#">Contacto</a></li>
                </ul>

            </div>

        </div>

    </footer>

    <?php
       include("send.php");
    ?>

    <script>
        function myfunction() {
            window.location.href="http://localhost/WebPSY"
        }
    </script>

    
</body>
</html>