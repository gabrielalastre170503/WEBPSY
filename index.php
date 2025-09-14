<?php
    // Tu lógica PHP se mantiene intacta
    include("conexion.php"); 
    $contenido_web = [];
    $resultado = $conex->query("SELECT clave, valor FROM contenido_web");
    while ($fila = $resultado->fetch_assoc()) {
        $contenido_web[$fila['clave']] = $fila['valor'];
    }
    include("send.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebPSY | Futuro de la Salud Mental</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
    /* --- VARIABLES DE DISEÑO --- */
    :root {
        --color-primario: #3b82f6;
        --color-primario-rgb: 59, 130, 246;
        --color-secundario: #2563eb;
        --color-acento: #0ea5e9;
        --color-fondo: #f8fafc;
        --color-superficie: #ffffff;
        --color-texto: #334155;
        --color-texto-secundario: #64748b;
        --color-blanco: #ffffff;
        --sombra-suave: 0 10px 30px rgba(0, 0, 0, 0.08);
        --sombra-neon: 0 0 15px rgba(var(--color-primario-rgb), 0.3), 0 0 5px rgba(var(--color-primario-rgb), 0.5);
        --border-radius: 12px;
    }

    /* --- ESTILOS GENERALES Y RESET --- */
    * { 
        margin: 0; 
        padding: 0; 
        box-sizing: border-box; 
        font-family: 'Poppins', sans-serif;
        max-width: 100%; /* <-- AJUSTE CLAVE 1: Fuerza a todos los elementos a no ser más anchos que su contenedor */
    }
    
    html, body {
        overflow-x: hidden; /* <-- AJUSTE CLAVE 2: Aplica la regla al HTML y al BODY para máxima efectividad */
    }

    body { 
        background-color: var(--color-fondo); 
        color: var(--color-texto); 
        line-height: 1.7; 
        width: 100vw; /* <-- AJUSTE CLAVE 3: Define el ancho del body al 100% del viewport */
    }

    html { 
        scroll-behavior: smooth; 
    }
    
    .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
    h1, h2, h3 { font-weight: 600; line-height: 1.2; }
    h2 { font-size: 2.5rem; text-align: center; margin-bottom: 50px; color: var(--color-primario); }
    section { padding: 75px 0 !important; } /* Se quitó overflow-x:hidden de aquí porque ahora es global */

    /* --- MENSAJES DE ESTADO --- */
    .mensaje-estado { padding: 15px 20px; margin: 20px auto; text-align: center; border-radius: var(--border-radius); color: var(--color-blanco); font-weight: 500; width: 90%; max-width: 800px; box-shadow: var(--sombra-suave); }
    .mensaje-exito { background: linear-gradient(90deg, #10b981, #059669); }
    .mensaje-error { background: linear-gradient(90deg, #ef4444, #dc2626); }

    /* --- HEADER Y NAVEGACIÓN --- */
    .header {
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 1000;
        padding: 20px 0;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: background 0.3s ease, box-shadow 0.3s ease;
    }

    .menu { display: flex; justify-content: space-between; align-items: center; }
    .logo { font-size: 1.5rem; font-weight: 700; color: var(--color-primario); text-decoration: none; }
    .navbar ul { list-style: none; display: flex; gap: 25px; }
    .navbar a { color: var(--color-texto-secundario); text-decoration: none; font-weight: 500; transition: color 0.3s ease; }
    .navbar a:hover { color: var(--color-primario); }

    /* --- CONTENIDO DEL HEADER Y PARTÍCULAS --- */
    .hero-section {
        height: 99vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        text-align: center;
        background: linear-gradient(135deg, var(--color-fondo), #e0f2fe);
    }

    #particles-js {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: 0; 
    }

    .header-content { z-index: 1; position: relative; }
    .header-txt h1 { font-size: 3.5rem; margin-bottom: 20px; color: var(--color-primario); }
    
    #typing-cursor {
        display: inline-block;
        background-color: var(--color-acento);
        width: 3px;
        height: 3.5rem;
        animation: blink 1s infinite;
        vertical-align: bottom;
    }
    @keyframes blink { 50% { opacity: 0; } }

    .header-txt p { font-size: 1.2rem; margin-bottom: 30px; color: var(--color-texto-secundario); max-width: 600px; margin-left:auto; margin-right:auto;}
    
    .btn-1 {
        display: inline-block;
        background-color: var(--color-primario);
        color: var(--color-blanco);
        padding: 12px 30px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        box-shadow: 0 5px 20px rgba(var(--color-primario-rgb), 0.3);
        transition: all 0.3s ease;
    }
    .btn-1:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 8px 25px rgba(var(--color-primario-rgb), 0.5); }

    /* --- SECCIONES GENERALES --- */
    .about { 
    display: flex; align-items: flex-start; /* <-- CAMBIA "center" POR "flex-start" */
    gap: 51px; }
    .about-img { margin-top: 100px; }
    .about-img img { max-width: 400px; }
    .about-txt p { color: var(--color-texto-secundario); margin-bottom: 15px; }
    
    #servicios {
    padding-top: 50px; /* <-- ¡Ajusta este valor como necesites! */
    }

    #contacto {
    padding-top: 100px !important; /* <-- ¡Ajusta este valor como necesites! */
    padding-bottom: 90px !important;
    }

    #nosotros {
    padding-top: 80px !important; /* <-- ¡Ajustá este valor como necesites! */
}

    /* --- TARJETAS DE SERVICIOS --- */
    .servicios-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; }
    .service-card {
        background: var(--color-superficie);
        padding: 40px 25px;
        text-align: center;
        border-radius: var(--border-radius);
        text-decoration: none;
        color: var(--color-texto);
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: var(--sombra-suave);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .service-card:hover { transform: translateY(-10px); box-shadow: var(--sombra-neon); }
    .service-card i { font-size: 3rem; color: var(--color-primario); margin-bottom: 20px; text-shadow: 0 0 10px rgba(var(--color-primario-rgb), 0.3); }
    .service-card h3 { font-size: 1.4rem; color: var(--color-texto); }

    /* --- FORMULARIO --- */
    .formulario { background-color: var(--color-superficie); border-radius: var(--border-radius); padding: 75px; padding-bottom: 90px; border: 1px solid rgba(0,0,0,0.08); box-shadow: var(--sombra-suave); }
    .formulario form { max-width: 600px; margin: 0 auto; }
    .input-group { display: flex; flex-direction: column; gap: 20px; }
    .input-container { position: relative; }
    .input-container input, .input-container textarea {
        width: 100%; padding: 15px 15px 15px 45px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; background: var(--color-fondo); color: var(--color-texto);
    }
    .input-container input:focus, .input-container textarea:focus { outline: none; border-color: var(--color-primario); box-shadow: 0 0 0 3px rgba(var(--color-primario-rgb), 0.2); }
    .input-container i { position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: var(--color-texto-secundario); }
    .btn { width: 100%; padding: 15px; border: none; background: linear-gradient(90deg, var(--color-primario), var(--color-acento)); color: var(--color-blanco); font-size: 1.1rem; font-weight: 600; border-radius: 8px; cursor: pointer; transition: opacity 0.3s ease; }
    .btn:hover { opacity: 0.9; }

    /* --- FOOTER --- */
    .footer { padding: 40px 0; text-align: center; border-top: 1px solid rgba(0, 0, 0, 0.08); background-color: var(--color-superficie); }
    .footer .link ul { justify-content: center; }
    .footer .link a { color: var(--color-texto-secundario); }
    .footer .link .logo { color: var(--color-primario); }

    /* --- ANIMACIONES DE SCROLL --- */
    .animate-on-scroll {
        opacity: 0;
        transition: opacity 0.8s ease-out, transform 0.8s ease-out;
    }
    .animate-on-scroll.from-left { transform: translateX(-50px); }
    .animate-on-scroll.from-right { transform: translateX(50px); }
    .animate-on-scroll.is-visible { opacity: 1; transform: translateX(0); }
    
    /* --- Responsive --- */
    @media (max-width: 768px) {
        h1 { font-size: 2.5rem !important; }
        h2 { font-size: 2rem; }
        .header-content { flex-direction: column; }
        .about { flex-direction: column; text-align: center; }
        .about-img img { max-width: 80%; }
    }

    /* --- Estilos para el campo de Cédula combinado (SIN ICONO) --- */
.cedula-group {
    display: flex;
    align-items: center;
}

.cedula-select {
    background-color: var(--color-fondo);
    border: 1px solid #e2e8f0;
    border-right: none; /* Quitamos el borde derecho para unirlo al input */
    border-radius: 8px 0 0 8px; /* Redondeamos solo la esquina izquierda */
    padding: 17px 9px;
    height: 100%;
    outline: none;
    color: var(--color-texto);
    cursor: pointer;
    font-weight: 600;
}

.cedula-input {
    /* El input de número hereda los estilos del input-container general, 
       solo necesitamos redondear la esquina correcta */
    border-radius: 0 8px 8px 0 !important; 
    padding-left: 20px !important; /* Ajustamos el padding izquierdo */
}

/* Efecto de foco para el grupo */
.cedula-group:focus-within {
    /* Este selector es opcional, pero mantiene el borde azul al hacer foco */
    border-color: var(--color-primario);
    box-shadow: 0 0 0 3px rgba(var(--color-primario-rgb), 0.2);
    border-radius: 8px; /* Asegura que el redondeo se aplique al contorno */
}

.cedula-input:focus {
    /* Evita que el input tenga su propio borde de foco, ya que lo maneja el grupo */
    box-shadow: none !important;
    z-index: 2;
}

.about-txt p {
    text-align: justify;
}
</style>
</head>
<body>

    <?php if (isset($_GET['status'])): /* Tu código de mensajes PHP */ ?>
        <?php
        $mensaje = ''; $clase_css = '';
        if ($_GET['status'] == 'success') { $mensaje = '¡Consulta agendada con éxito! Nos pondremos en contacto contigo pronto.'; $clase_css = 'mensaje-exito'; } 
        elseif ($_GET['status'] == 'error') { $mensaje = 'Hubo un error al enviar tu consulta. Por favor, inténtalo de nuevo.'; $clase_css = 'mensaje-error'; }
        if ($mensaje) { echo "<div class='mensaje-estado $clase_css'>$mensaje</div>"; }
        ?>
    <?php endif; ?>

    <header id="inicio" class="header">
        <div class="menu container">
            <a href="#inicio" class="logo">WebPSY</a>
            <nav class="navbar">
                <ul>
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="#nosotros">Nosotros</a></li>
                    <li><a href="#servicios">Servicios</a></li>
                    <li><a href="#contacto">Contacto</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section id="hero-section" class="hero-section">
        <div id="particles-js"></div> 
        <div class="header-content container">
            <div class="header-txt">
                <h1 id="typing-headline">Software de Gestión de pacientes</h1><span id="typing-cursor"></span>
                <p>Un sistema innovador y seguro para la gestión integral de pacientes en consultorios de Psicología y Psiquiatría.</p>
                <a href="login.php" class="btn-1">INICIAR SESIÓN</a>
            </div>
        </div>
    </section>

    <section id="nosotros" class="container">
        <div class="about animate-on-scroll from-left">
            <div class="about-img">
                <img src="Images/Psicologo.jpg" alt="Equipo de profesionales">
            </div>
            <div class="about-txt">
                <h2>Sobre Nosotros</h2>
                <p><strong>MISIÓN:</strong><br> <?php echo htmlspecialchars($contenido_web['mision'] ?? 'Misión no definida.'); ?></p>
                <p><strong>VISIÓN:</strong><br> <?php echo htmlspecialchars($contenido_web['vision'] ?? 'Visión no definida.'); ?></p>
                <p><strong>VALORES:</strong><br> <?php echo htmlspecialchars($contenido_web['valores'] ?? 'Valores no definidos.'); ?></p>
            </div>
        </div>
    </section>

    <main id="servicios" class="container animate-on-scroll from-right">
        <h2>Nuestros Servicios</h2>
        <div class="servicios-content">
            <a href="profesionales.php?rol=psicologo" class="service-card">
                <i class="fa-sharp fa-solid fa-hospital-user"></i><h3>Psicología</h3>
            </a>
            <a href="profesionales.php?rol=psiquiatra" class="service-card">
                <i class="fa-sharp fa-solid fa-stethoscope"></i><h3>Psiquiatría</h3>
            </a>
            <a href="terapias.php" class="service-card">
                <i class="fa-solid fa-bed-pulse"></i><h3>Terapia</h3>
            </a>
        </div>
    </main>

    <section id="contacto" class="container animate-on-scroll from-left">
        <div class="formulario">
            <form method="post" autocomplete="off">
                <h2>Crea tu cuenta y agenda</h2>
                <p style="text-align:center; margin-top: -40px; margin-bottom: 30px; color: var(--color-texto-secundario);">Da el primer paso hacia tu bienestar.</p>
                <div class="input-group">
                    <div class="input-container"><i class="fa-solid fa-user"></i><input type="text" name="name" placeholder="Nombre y Apellido" required></div>

                    <div class="input-container">
    <i class="fa-solid fa-calendar-day"></i>
    <input type="text" id="fecha_nacimiento_flatpickr" name="fecha_nacimiento" placeholder="Fecha de nacimiento" required>
</div>
                    <div class="input-container cedula-group">
    <select name="nacionalidad" class="cedula-select" required>
        <option value="V">V</option>
        <option value="E">E</option>
        <option value="P">P</option>
    </select>
    <input type="text" name="cedula_numero" class="cedula-input" placeholder="Número de Documento" required pattern="\d{7,8}" title="Ingresa entre 7 y 8 números" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
</div>
                    <div class="input-container"><i class="fa-solid fa-envelope"></i><input type="email" name="email" placeholder="Correo Electrónico" required></div>
                    <div class="input-container">
    <i class="fa-solid fa-lock"></i>
    <input type="password" name="password" placeholder="Crea una contraseña" required 
           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
           title="La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.">
</div>
                    <input type="submit" name="send" class="btn" value="Registrarme y Solicitar Cita">
                </div>
            </form>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-content container">
            <div class="link"><a href="#inicio" class="logo">WebPSY</a></div>
            <div class="link"><nav class="navbar"><ul><li><a href="#inicio">Inicio</a></li><li><a href="#nosotros">Nosotros</a></li><li><a href="#servicios">Servicios</a></li><li><a href="#contacto">Contacto</a></li></ul></nav></div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {

        // --- ACTIVACIÓN DE FLATICKR ---
    flatpickr("#fecha_nacimiento_flatpickr", {
        locale: "es", // Usa la traducción a español que incluimos
        dateFormat: "d-m-Y", // Formato de la fecha: día-mes-año
        maxDate: "today", // No se pueden seleccionar fechas futuras
        altInput: true, // Muestra un formato amigable al usuario
        altFormat: "j F, Y", // Formato amigable: 29 Agosto, 2025
    });

        // --- ANIMACIÓN DE ESCRITURA ---
        const headline = document.getElementById('typing-headline');
        const text = headline.textContent;
        headline.textContent = '';
        let i = 0;
        function typeWriter() {
            if (i < text.length) {
                headline.textContent += text.charAt(i);
                i++;
                setTimeout(typeWriter, 80); // Velocidad de escritura
            }
        }
        setTimeout(typeWriter, 500); // Inicia después de medio segundo

        // --- ANIMACIONES AL HACER SCROLL ---
        const sections = document.querySelectorAll('.animate-on-scroll');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    // observer.unobserve(entry.target); // Desactiva si quieres que se repita la animación al volver a hacer scroll
                } else {
                    // entry.target.classList.remove('is-visible'); // Reactiva si quieres que se desanime al salir de la vista
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' }); // Ajusta rootMargin si es necesario
        sections.forEach(section => observer.observe(section));

        // --- ANIMACIÓN DE PARTÍCULAS (versión corregida con color visible) ---
const particlesContainer = document.getElementById('particles-js');
if (particlesContainer) {
    const canvas = document.createElement('canvas');
    particlesContainer.appendChild(canvas);
    const ctx = canvas.getContext('2d');
    let particles = [];
    let mouse = { x: null, y: null, radius: 100 };

    const resizeCanvas = () => {
        canvas.width = particlesContainer.offsetWidth;
        canvas.height = particlesContainer.offsetHeight;
        particles = [];
        init();
    };
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    canvas.addEventListener('mousemove', (e) => {
        mouse.x = e.clientX; // Corregido para usar clientX
        mouse.y = e.clientY; // Corregido para usar clientY
    });
    canvas.addEventListener('mouseout', () => {
        mouse.x = null;
        mouse.y = null;
    });

    const particleCount = Math.floor(canvas.width / 20);

    class Particle {
        constructor() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.vx = Math.random() * 0.4 - 0.2;
            this.vy = Math.random() * 0.4 - 0.2;
            this.radius = Math.random() * 2 + 1;
            // --- ¡AQUÍ ESTÁ LA CORRECCIÓN DE COLOR! ---
            // Usamos un gris oscuro (51, 65, 85) en lugar del azul claro.
            this.color = `rgba(51, 65, 85, ${Math.random() * 0.5 + 0.2})`;
        }
        update() {
            if (mouse.x && mouse.y) {
                const dx_mouse = this.x - mouse.x;
                const dy_mouse = this.y - mouse.y;
                const dist_mouse = Math.sqrt(dx_mouse * dx_mouse + dy_mouse * dy_mouse);
                if (dist_mouse < mouse.radius) {
                    const forceDirectionX = dx_mouse / dist_mouse;
                    const forceDirectionY = dy_mouse / dist_mouse;
                    const force = (mouse.radius - dist_mouse) / mouse.radius;
                    this.x += forceDirectionX * force * 2;
                    this.y += forceDirectionY * force * 2;
                }
            }
            this.x += this.vx; this.y += this.vy;
            if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
            if (this.y < 0 || this.y > canvas.height) this.vy *= -1;
        }
        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fillStyle = this.color;
            ctx.fill();
        }
    }
    
    function init() { for (let i = 0; i < particleCount; i++) particles.push(new Particle()); }
    
    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for(let i = 0; i < particles.length; i++){
            particles[i].update();
            particles[i].draw();
            for(let j = i; j < particles.length; j++){
                const dx = particles[i].x - particles[j].x;
                const dy = particles[i].y - particles[j].y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                if(distance < 150){
                    ctx.beginPath();
                    // --- ¡Y AQUÍ TAMBIÉN CORREGIMOS EL COLOR DE LAS LÍNEAS! ---
                    ctx.strokeStyle = `rgba(51, 65, 85, ${0.3 - (distance / 150 * 0.3)})`;
                    ctx.lineWidth = 0.5;
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.stroke();
                }
            }
        }
        requestAnimationFrame(animate);
    }
    init(); animate();
    }
    });
    </script>
</body>
</html>