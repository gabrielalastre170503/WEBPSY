<?php
/**
 * privacidad.php — Aviso de privacidad y política de retención (público).
 * Enlazado desde login y registro. No requiere sesión.
 *
 * El texto es una BASE: el centro debe revisarlo/ajustarlo legalmente y fijar los
 * plazos de retención reales según la normativa sanitaria aplicable.
 */
require_once __DIR__ . '/../lib/seguridad/consentimiento.php'; // versión vigente (referencia)
$ver = defined('ECO_CONSENT_VERSION') ? ECO_CONSENT_VERSION : '1.0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aviso de privacidad · EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --azul:#02b1f4; --azul-dark:#014a82; --ink:#0c1a2e; --gris:#4a5870; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Inter',system-ui,sans-serif; background:linear-gradient(180deg,#eaf3ff 0%,#f5f9ff 100%);
               color:var(--ink); min-height:100vh; padding:32px 18px; }
        .wrap { max-width:820px; margin:0 auto; }
        .back { display:inline-flex; align-items:center; gap:8px; color:var(--azul-dark); font-weight:600;
                text-decoration:none; font-size:14px; margin-bottom:18px; }
        .back:hover { color:var(--azul); }
        .card { background:#fff; border:1px solid rgba(2,177,244,.16); border-radius:22px; padding:38px 42px;
                box-shadow:0 30px 80px rgba(12,26,46,.10); }
        .head { display:flex; align-items:center; gap:16px; margin-bottom:8px; }
        .ic { width:58px; height:58px; border-radius:16px; background:rgba(2,177,244,.12); color:var(--azul-dark);
              display:flex; align-items:center; justify-content:center; font-size:25px; flex-shrink:0; }
        h1 { font-size:24px; font-weight:800; letter-spacing:-.02em; }
        .sub { color:var(--gris); font-size:13.5px; margin-top:2px; }
        h2 { font-size:16px; color:var(--azul-dark); margin:26px 0 8px; }
        p, li { font-size:14px; line-height:1.65; color:#243043; }
        ul { margin:6px 0 6px 20px; }
        a { color:var(--azul-dark); font-weight:600; }
        .ret { width:100%; border-collapse:collapse; margin-top:8px; font-size:13.5px; }
        .ret th, .ret td { text-align:left; padding:9px 10px; border-bottom:1px solid #eef3f8; vertical-align:top; }
        .ret th { color:var(--gris); font-weight:700; background:#fbfdff; }
        .note { margin-top:24px; padding:12px 16px; border-radius:12px; background:rgba(2,177,244,.07);
                border:1px solid rgba(2,177,244,.2); font-size:12.5px; color:var(--azul-dark); }
        .meta { margin-top:22px; font-size:12px; color:#94a3b8; text-align:right; }
    </style>
</head>
<body>
<div class="wrap">
    <a href="<?= eco_url('login') ?>" class="back"><i class="fa-solid fa-arrow-left"></i> Volver al inicio de sesión</a>
    <div class="card">
        <div class="head">
            <div class="ic"><i class="fa-solid fa-user-shield"></i></div>
            <div>
                <h1>Aviso de privacidad</h1>
                <p class="sub">EcoMadelleine · Centro de Diagnóstico</p>
            </div>
        </div>

        <p>Este aviso describe cómo <strong>EcoMadelleine</strong> recoge, usa, protege y conserva tus datos
        personales y de salud cuando utilizas nuestros servicios de diagnóstico ecográfico.</p>

        <h2>1. Responsable del tratamiento</h2>
        <p>EcoMadelleine · Centro de Diagnóstico. Contacto:
        <a href="mailto:soporte@ecomadelleine.com">soporte@ecomadelleine.com</a>.</p>

        <h2>2. Datos que tratamos</h2>
        <ul>
            <li>Identificación y contacto: nombre, cédula, correo, teléfono y dirección.</li>
            <li>Datos de salud: estudios, informes ecográficos e imágenes asociadas.</li>
            <li>Datos de gestión: citas, pagos y comunicaciones con el centro.</li>
            <li>Datos técnicos de seguridad: registros de acceso, IP y eventos de auditoría.</li>
        </ul>

        <h2>3. Finalidades</h2>
        <p>Prestación de la atención clínica, gestión de citas, emisión de informes, facturación,
        recordatorios, cumplimiento de obligaciones legales y mejora de la calidad asistencial.
        No vendemos tus datos ni los cedemos a terceros salvo obligación legal.</p>

        <h2>4. Conservación (política de retención)</h2>
        <p>Conservamos cada categoría de datos solo durante el tiempo necesario para su finalidad y los
        plazos exigidos por la normativa sanitaria aplicable. Cumplido el plazo, los datos se eliminan o
        anonimizan de forma segura.</p>
        <table class="ret">
            <thead><tr><th>Categoría</th><th>Plazo de conservación</th></tr></thead>
            <tbody>
                <tr><td>Historia clínica e informes de estudio</td><td>El exigido por la normativa sanitaria aplicable <em>(p. ej. [10] años desde el último contacto)</em>.</td></tr>
                <tr><td>Datos de cuenta y contacto</td><td>Mientras la cuenta esté activa; tras su baja, el periodo legal mínimo.</td></tr>
                <tr><td>Citas y facturación</td><td>El plazo fiscal/contable aplicable <em>(p. ej. [5] años)</em>.</td></tr>
                <tr><td>Registros de auditoría de acceso</td><td>Conservados para trazabilidad <em>(p. ej. [2] años)</em>.</td></tr>
                <tr><td>Datos efímeros de seguridad (intentos de acceso, enlaces temporales)</td><td>Se eliminan automáticamente de forma periódica (intentos de acceso a los [90] días; enlaces caducados a los [30] días).</td></tr>
            </tbody>
        </table>

        <h2>5. Tus derechos</h2>
        <p>Puedes solicitar acceso, rectificación, actualización o eliminación de tus datos, así como una
        copia de tu historial clínico, escribiendo a
        <a href="mailto:soporte@ecomadelleine.com">soporte@ecomadelleine.com</a>. Como paciente, también
        puedes descargar tu historial desde tu perfil.</p>

        <h2>6. Seguridad</h2>
        <p>Aplicamos medidas técnicas y organizativas para proteger tu información: control de acceso por
        rol, verificación en dos pasos opcional, registro de accesos a datos clínicos y conexiones cifradas.</p>

        <h2>7. Cambios en este aviso</h2>
        <p>Podemos actualizar este aviso. Si los cambios afectan al tratamiento de tus datos, te pediremos
        nuevamente tu consentimiento informado al iniciar sesión.</p>

        <div class="note"><i class="fa-solid fa-circle-info"></i> Documento base. El centro debe revisarlo
        legalmente y fijar los plazos definitivos antes de su publicación en producción.</div>

        <div class="meta">Consentimiento vigente: versión <?= htmlspecialchars($ver) ?></div>
    </div>
</div>
</body>
</html>
