<?php
    include 'core/conexion.php';
    $contenido_web = [];
    $resultado = $conex->query("SELECT clave, valor FROM contenido_web");
    while ($fila = $resultado->fetch_assoc()) {
        $contenido_web[$fila['clave']] = $fila['valor'];
    }
    include __DIR__ . '/publico/send.php';

    /* ───────────────────────────────────────────────────────────────
       MÉTRICAS REALES desde la base de datos
       ─────────────────────────────────────────────────────────────── */

    // 1. Total de pacientes aprobados
    $r = $conex->query("SELECT COUNT(*) c FROM usuarios WHERE rol='paciente' AND estado='aprobado'");
    $total_pacientes = (int)($r->fetch_assoc()['c'] ?? 0);

    // 2. Tipos de estudio activos (excluyendo sub-categorías técnicas)
    $r = $conex->query("SELECT COUNT(*) c FROM tipos_ecografias
                        WHERE activo=1 AND (categoria IS NULL
                        OR categoria NOT IN ('Musculoesqueletica_Sub','Obstetrica_Sub','Partes_Blandas_Sub'))");
    $total_tipos = (int)($r->fetch_assoc()['c'] ?? 0);

    // 3. Promedio real de horas entre creación y firma del informe
    $r = $conex->query("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, creado_en, actualizado_en))) h
                        FROM informes_estudios
                        WHERE estado IN ('finalizado','firmado')
                          AND TIMESTAMPDIFF(HOUR, creado_en, actualizado_en) BETWEEN 0 AND 720");
    $avg_horas = (int)($r->fetch_assoc()['h'] ?? 0);

    // 4. Tasa de conclusión real: % de citas completadas vs gestionadas
    $r = $conex->query("SELECT
            SUM(CASE WHEN estado='completada' THEN 1 ELSE 0 END) completadas,
            SUM(CASE WHEN estado IN ('completada','cancelada','reprogramada') THEN 1 ELSE 0 END) gestionadas
        FROM citas");
    $row = $r->fetch_assoc();
    $tasa_conclusion = ($row && (int)$row['gestionadas'] > 0)
        ? (int)round(((int)$row['completadas'] / (int)$row['gestionadas']) * 100)
        : 0;

    // 5. Total de informes firmados/finalizados (métrica adicional para el hero)
    $r = $conex->query("SELECT COUNT(*) c FROM informes_estudios WHERE estado IN ('finalizado','firmado')");
    $total_informes = (int)($r->fetch_assoc()['c'] ?? 0);

    /* Helpers de visualización honesta — si el dato real es 0, se muestra etiqueta de compromiso */
    $f_pac = [
        'value' => $total_pacientes > 0 ? number_format($total_pacientes, 0, ',', '.') . '+' : '—',
        'label' => $total_pacientes > 0 ? 'Pacientes registrados' : 'Próximos pacientes',
    ];
    $f_tip = [
        'value' => $total_tipos > 0 ? $total_tipos . '+' : '—',
        'label' => 'Tipos de estudio',
    ];
    $f_hrs = [
        'value' => $avg_horas > 0 ? $avg_horas . 'h' : '24h',
        'label' => $avg_horas > 0 ? 'Promedio de informe' : 'Compromiso de entrega',
    ];
    $f_tasa = [
        'value' => $tasa_conclusion > 0 ? $tasa_conclusion . '%' : '100%',
        'label' => $tasa_conclusion > 0 ? 'Tasa de conclusión' : 'Compromiso clínico',
    ];

    /* Paleta por categoría — coherente con eco_colores_shell (Renal / Abdominal / Pélvica / etc.) */
    $eco_palette = [
        'Abdominal'          => ['c1' => '#02b1f4', 'soft' => '#e0f5fe', 'text' => '#0284c7'],
        'Renal'              => ['c1' => '#0ea5e9', 'soft' => '#e0f2fe', 'text' => '#0369a1'],
        'Obstetrica'         => ['c1' => '#ec4899', 'soft' => '#fce7f3', 'text' => '#be185d'],
        'Cervical'           => ['c1' => '#14b8a6', 'soft' => '#ccfbf1', 'text' => '#0f766e'],
        'Pelvica'            => ['c1' => '#8b5cf6', 'soft' => '#ede9fe', 'text' => '#6d28d9'],
        'Musculoesqueletica' => ['c1' => '#22c55e', 'soft' => '#dcfce7', 'text' => '#15803d'],
        'Prostatica'         => ['c1' => '#3b82f6', 'soft' => '#dbeafe', 'text' => '#1d4ed8'],
        'Mamaria'            => ['c1' => '#f43f5e', 'soft' => '#ffe4e6', 'text' => '#be123c'],
        'Partes Blandas'     => ['c1' => '#f59e0b', 'soft' => '#fef3c7', 'text' => '#b45309'],
        'Testicular'         => ['c1' => '#6366f1', 'soft' => '#e0e7ff', 'text' => '#4338ca'],
        'Pulmonar'           => ['c1' => '#0891b2', 'soft' => '#cffafe', 'text' => '#0e7490'],
    ];
    $eco_palette_default = ['c1' => '#64748b', 'soft' => '#f1f5f9', 'text' => '#475569'];

    /* Catálogo de estudios para la grilla pública (icono + categoría → color de paleta) */
    $servicios_landing = [
        ['n' => 'Ecografía Abdominal',        'i' => 'fa-disease',          'cat' => 'Abdominal'],
        ['n' => 'Ecografía Obstétrica',       'i' => 'fa-baby',             'cat' => 'Obstetrica'],
        ['n' => 'Ecografía Renal',            'i' => 'fa-droplet',          'cat' => 'Renal'],
        ['n' => 'Ecografía de Tiroides',      'i' => 'fa-user-doctor',      'cat' => 'Cervical'],
        ['n' => 'Ecografía Pélvica',          'i' => 'fa-venus',            'cat' => 'Pelvica'],
        ['n' => 'Ecografía Mamaria',          'i' => 'fa-ribbon',           'cat' => 'Mamaria'],
        ['n' => 'Doppler / Vascular',         'i' => 'fa-wave-square',      'cat' => 'Pulmonar'],
        ['n' => 'Partes Blandas',             'i' => 'fa-hand-holding-medical', 'cat' => 'Partes Blandas'],
        ['n' => 'Musculoesquelética',         'i' => 'fa-bone',             'cat' => 'Musculoesqueletica'],
        ['n' => 'Próstata / Testicular',      'i' => 'fa-mars',             'cat' => 'Prostatica'],
    ];

    /* Estado de feedback (registro) proveniente de send.php */
    $flash = $_GET['status'] ?? ($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EcoMadelleine — Centro de diagnóstico ecográfico premium. Dra. Madelleine Toro. Informes digitales firmados en 24 horas.">
    <meta name="theme-color" content="#eaf6ff">
    <title>EcoMadelleine · Diagnóstico Ecográfico Premium</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
    /* ════════════════════ TOKENS ════════════════════ */
    :root{
        --accent:#02b1f4; --accent-2:#0284c7; --accent-hover:#0099d4;
        --accent-soft:#e0f5fe; --violet:#8b5cf6; --pink:#ec4899;
        --bg:#f4f8fc; --surface:#ffffff;
        --text:#0c1a2b; --text-2:#475569; --muted:#8da2b8;
        --border:#e2e8f0; --border-soft:#eef2f7;
        --glass:rgba(255,255,255,.62); --glass-strong:rgba(255,255,255,.78);
        --glass-border:rgba(255,255,255,.7);
        --shadow-sm:0 4px 16px rgba(2,132,199,.07);
        --shadow:0 14px 40px rgba(2,132,199,.12);
        --shadow-lg:0 30px 70px rgba(2,132,199,.18);
        --radius:22px; --radius-lg:30px;
        --maxw:1200px;
        --ease:cubic-bezier(.22,1,.36,1);
    }
    *{margin:0;padding:0;box-sizing:border-box}
    html{scroll-behavior:smooth;scroll-padding-top:90px;-webkit-text-size-adjust:100%}
    body{
        font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
        color:var(--text); background:var(--bg);
        line-height:1.65; overflow-x:hidden; position:relative;
        -webkit-font-smoothing:antialiased;
    }
    a{text-decoration:none;color:inherit}
    img{max-width:100%;display:block}
    .container{width:100%;max-width:var(--maxw);margin:0 auto;padding:0 22px}
    ::selection{background:var(--accent);color:#fff}

    /* ════════════════════ FONDO ANIMADO (orbes glass) ════════════════════ */
    .bg-wrap{position:fixed;inset:0;z-index:-2;overflow:hidden;
        background:
          radial-gradient(1200px 600px at 80% -5%, #dff1ff 0%, transparent 60%),
          radial-gradient(900px 500px at -5% 20%, #ede9ff 0%, transparent 55%),
          linear-gradient(180deg,#f4f9ff 0%, #f4f8fc 40%, #eef5fb 100%);
    }
    .orb{position:absolute;border-radius:50%;filter:blur(60px);opacity:.55;
        animation:float 18s var(--ease) infinite alternate;will-change:transform}
    .orb.a{width:420px;height:420px;background:#7fd4ff;top:-80px;right:-60px}
    .orb.b{width:360px;height:360px;background:#c4b5fd;top:40%;left:-100px;animation-delay:-5s}
    .orb.c{width:300px;height:300px;background:#a7f3d0;bottom:-60px;right:18%;animation-delay:-9s;opacity:.4}
    .orb.d{width:260px;height:260px;background:#fbcfe8;top:60%;right:-40px;animation-delay:-13s;opacity:.35}
    @keyframes float{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(-40px,40px,0) scale(1.12)}}

    /* ════════════════════ NAVBAR ════════════════════ */
    .nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:14px 0;transition:.4s var(--ease)}
    .nav-inner{display:flex;align-items:center;justify-content:space-between;
        background:var(--glass);backdrop-filter:blur(22px) saturate(1.8);-webkit-backdrop-filter:blur(22px) saturate(1.8);
        border:1px solid var(--glass-border);border-radius:18px;padding:10px 14px 10px 18px;
        box-shadow:var(--shadow-sm);transition:.4s var(--ease)}
    .nav.scrolled{padding:8px 0}
    .nav.scrolled .nav-inner{background:var(--glass-strong);box-shadow:var(--shadow)}
    .brand{display:flex;align-items:center;gap:11px;font-weight:800;font-size:1.12rem;letter-spacing:-.02em}
    .brand-logo{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;color:#fff;font-size:1.05rem;
        background:linear-gradient(135deg,var(--accent),#3aa8ff);box-shadow:0 8px 22px rgba(2,177,244,.45);
        animation:pulseLogo 3.5s ease-in-out infinite}
    @keyframes pulseLogo{0%,100%{box-shadow:0 8px 22px rgba(2,177,244,.4)}50%{box-shadow:0 8px 34px rgba(2,177,244,.65)}}
    .brand small{display:block;font-size:.62rem;font-weight:600;color:var(--accent-2);letter-spacing:.06em;text-transform:uppercase}
    .nav-links{display:flex;align-items:center;gap:6px;list-style:none}
    .nav-links a{padding:9px 15px;border-radius:11px;font-weight:600;font-size:.92rem;color:var(--text-2);transition:.25s var(--ease);position:relative}
    .nav-links a:not(.nav-cta):hover{color:var(--accent-2);background:var(--accent-soft)}
    .nav-cta{color:#fff !important;background:linear-gradient(135deg,var(--accent),var(--accent-hover));
        box-shadow:0 8px 20px rgba(2,177,244,.4);display:inline-flex;align-items:center;gap:8px}
    .nav-cta:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(2,177,244,.55)}
    .burger{display:none;width:44px;height:44px;border:none;background:transparent;font-size:1.3rem;color:var(--text);cursor:pointer;border-radius:11px}

    /* ════════════════════ BOTONES ════════════════════ */
    .btn{display:inline-flex;align-items:center;gap:10px;padding:15px 26px;border-radius:15px;font-weight:700;font-size:.97rem;
        cursor:pointer;border:none;transition:.3s var(--ease);font-family:inherit}
    .btn-primary{color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent-hover));box-shadow:0 12px 30px rgba(2,177,244,.42);position:relative;overflow:hidden}
    .btn-primary::after{content:'';position:absolute;inset:0;background:linear-gradient(120deg,transparent,rgba(255,255,255,.45),transparent);transform:translateX(-120%);transition:.6s}
    .btn-primary:hover{transform:translateY(-3px);box-shadow:0 18px 40px rgba(2,177,244,.55)}
    .btn-primary:hover::after{transform:translateX(120%)}
    .btn-glass{background:var(--glass-strong);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
        border:1px solid var(--glass-border);color:var(--accent-2);box-shadow:var(--shadow-sm)}
    .btn-glass:hover{transform:translateY(-3px);box-shadow:var(--shadow);background:#fff}

    /* ════════════════════ HERO ════════════════════ */
    .hero{min-height:100dvh;display:flex;align-items:center;padding:130px 0 70px;position:relative}
    .hero-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:50px;align-items:center}
    .eyebrow{display:inline-flex;align-items:center;gap:9px;padding:8px 16px;border-radius:999px;font-size:.8rem;font-weight:700;
        color:var(--accent-2);background:var(--glass-strong);border:1px solid var(--glass-border);box-shadow:var(--shadow-sm);
        backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);margin-bottom:22px}
    .eyebrow .dot{width:8px;height:8px;border-radius:50%;background:var(--accent);box-shadow:0 0 0 0 rgba(2,177,244,.6);animation:ping 2s infinite}
    @keyframes ping{0%{box-shadow:0 0 0 0 rgba(2,177,244,.55)}70%{box-shadow:0 0 0 10px rgba(2,177,244,0)}100%{box-shadow:0 0 0 0 rgba(2,177,244,0)}}
    .hero h1{font-size:clamp(2.3rem,5.2vw,3.85rem);font-weight:900;line-height:1.07;letter-spacing:-.03em;margin-bottom:20px}
    .hero h1 .grad{background:linear-gradient(120deg,var(--accent),#6366f1 60%,var(--violet));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
    .hero p.lead{font-size:clamp(1.02rem,1.6vw,1.18rem);color:var(--text-2);max-width:540px;margin-bottom:32px}
    .hero-cta{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:38px}
    .hero-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;max-width:560px}
    .stat{background:var(--glass);backdrop-filter:blur(16px) saturate(1.6);-webkit-backdrop-filter:blur(16px) saturate(1.6);
        border:1px solid var(--glass-border);border-radius:16px;padding:16px 12px;text-align:center;box-shadow:var(--shadow-sm);transition:.35s var(--ease)}
    .stat:hover{transform:translateY(-4px);box-shadow:var(--shadow)}
    .stat .num{font-size:1.5rem;font-weight:800;color:var(--accent-2);letter-spacing:-.02em;line-height:1}
    .stat .lbl{font-size:.7rem;color:var(--muted);font-weight:600;margin-top:6px;line-height:1.25}

    /* Visual del hero (tarjeta glass + ondas) */
    .hero-visual{position:relative;display:grid;place-items:center}
    .glass-card{width:100%;max-width:420px;background:var(--glass-strong);backdrop-filter:blur(30px) saturate(2);-webkit-backdrop-filter:blur(30px) saturate(2);
        border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:28px;box-shadow:var(--shadow-lg);
        animation:floatCard 7s ease-in-out infinite;will-change:transform}
    @keyframes floatCard{0%,100%{transform:translateY(0) rotate(-.5deg)}50%{transform:translateY(-16px) rotate(.5deg)}}
    .gc-head{display:flex;align-items:center;gap:13px;margin-bottom:18px}
    .gc-avatar{width:52px;height:52px;border-radius:15px;background:linear-gradient(135deg,var(--accent),var(--violet));display:grid;place-items:center;color:#fff;font-size:1.3rem;box-shadow:0 10px 24px rgba(2,177,244,.4)}
    .gc-head h4{font-size:1rem;font-weight:800}
    .gc-head span{font-size:.78rem;color:var(--accent-2);font-weight:600}
    .wave{height:64px;border-radius:14px;background:linear-gradient(90deg,#eef9ff,#f3edff);display:flex;align-items:center;gap:3px;padding:0 14px;overflow:hidden;margin-bottom:16px}
    .wave i{flex:1;background:linear-gradient(180deg,var(--accent),var(--violet));border-radius:2px;animation:bar 1.3s ease-in-out infinite}
    @keyframes bar{0%,100%{height:14%}50%{height:80%}}
    .gc-rows{display:grid;gap:10px}
    .gc-row{display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.6);border:1px solid var(--border-soft);border-radius:13px;padding:11px 14px;font-size:.85rem}
    .gc-row b{color:var(--accent-2)}
    .gc-badge{display:inline-flex;align-items:center;gap:7px;font-size:.78rem;font-weight:700;color:#15803d;background:#dcfce7;padding:6px 12px;border-radius:999px}
    .float-chip{position:absolute;background:var(--glass-strong);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid var(--glass-border);
        border-radius:15px;padding:11px 15px;box-shadow:var(--shadow);font-size:.82rem;font-weight:700;display:flex;align-items:center;gap:9px}
    .float-chip i{color:var(--accent)}
    .float-chip.c1{top:6%;left:-4%;animation:floatChip 5s ease-in-out infinite}
    .float-chip.c2{bottom:8%;right:-6%;animation:floatChip 6s ease-in-out infinite .8s}
    @keyframes floatChip{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}

    /* ════════════════════ SECCIONES GENÉRICAS ════════════════════ */
    section{position:relative;padding:90px 0}
    .sec-head{text-align:center;max-width:640px;margin:0 auto 56px}
    .sec-tag{display:inline-block;font-size:.8rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--accent-2);margin-bottom:12px}
    .sec-head h2{font-size:clamp(1.9rem,3.6vw,2.7rem);font-weight:900;letter-spacing:-.025em;line-height:1.12}
    .sec-head p{color:var(--text-2);margin-top:14px;font-size:1.05rem}

    .glass{background:var(--glass);backdrop-filter:blur(22px) saturate(1.8);-webkit-backdrop-filter:blur(22px) saturate(1.8);
        border:1px solid var(--glass-border);border-radius:var(--radius);box-shadow:var(--shadow-sm)}

    /* NOSOTROS / features */
    .feat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
    .feat{padding:30px 24px;text-align:left;transition:.4s var(--ease)}
    .feat:hover{transform:translateY(-8px);box-shadow:var(--shadow-lg);background:var(--glass-strong)}
    .feat-ic{width:58px;height:58px;border-radius:17px;display:grid;place-items:center;font-size:1.45rem;color:#fff;margin-bottom:18px;
        background:linear-gradient(135deg,var(--accent),var(--accent-hover));box-shadow:0 12px 28px rgba(2,177,244,.35)}
    .feat:nth-child(2) .feat-ic{background:linear-gradient(135deg,var(--violet),#a78bfa);box-shadow:0 12px 28px rgba(139,92,246,.35)}
    .feat:nth-child(3) .feat-ic{background:linear-gradient(135deg,#22c55e,#4ade80);box-shadow:0 12px 28px rgba(34,197,94,.35)}
    .feat:nth-child(4) .feat-ic{background:linear-gradient(135deg,var(--pink),#f9a8d4);box-shadow:0 12px 28px rgba(236,72,153,.35)}
    .feat h3{font-size:1.12rem;font-weight:800;margin-bottom:9px;letter-spacing:-.01em}
    .feat p{font-size:.92rem;color:var(--text-2)}

    /* SERVICIOS */
    .serv-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px}
    .serv{padding:26px 20px;text-align:center;transition:.4s var(--ease);position:relative;overflow:hidden}
    .serv::before{content:'';position:absolute;inset:0;opacity:0;transition:.4s;background:linear-gradient(180deg,var(--soft,#e0f5fe),transparent)}
    .serv:hover{transform:translateY(-8px);box-shadow:var(--shadow-lg);background:var(--glass-strong)}
    .serv:hover::before{opacity:1}
    .serv-ic{width:62px;height:62px;border-radius:18px;display:grid;place-items:center;font-size:1.5rem;margin:0 auto 16px;position:relative;z-index:1;transition:.4s var(--ease)}
    .serv:hover .serv-ic{transform:scale(1.1) rotate(-6deg)}
    .serv h3{font-size:.97rem;font-weight:700;position:relative;z-index:1;letter-spacing:-.01em}
    .serv .go{margin-top:10px;font-size:.78rem;font-weight:700;color:var(--accent-2);opacity:0;transform:translateY(6px);transition:.35s var(--ease);position:relative;z-index:1}
    .serv:hover .go{opacity:1;transform:translateY(0)}

    /* PROCESO */
    .proc-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;counter-reset:step}
    .proc{padding:30px 24px;position:relative;transition:.4s var(--ease)}
    .proc:hover{transform:translateY(-6px);box-shadow:var(--shadow)}
    .proc .step{counter-increment:step;width:46px;height:46px;border-radius:14px;display:grid;place-items:center;font-weight:900;color:#fff;
        background:linear-gradient(135deg,var(--accent),var(--violet));box-shadow:0 10px 22px rgba(2,177,244,.35);margin-bottom:16px}
    .proc .step::before{content:counter(step,decimal-leading-zero)}
    .proc h3{font-size:1.05rem;font-weight:800;margin-bottom:8px}
    .proc p{font-size:.9rem;color:var(--text-2)}

    /* FAQ */
    .faq-wrap{max-width:780px;margin:0 auto;display:grid;gap:14px}
    .faq-item{overflow:hidden;transition:.35s var(--ease)}
    .faq-q{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 24px;cursor:pointer;font-weight:700;font-size:1.02rem}
    .faq-q i{color:var(--accent);transition:.35s var(--ease);flex-shrink:0}
    .faq-item.open .faq-q i{transform:rotate(45deg)}
    .faq-a{max-height:0;opacity:0;transition:max-height .4s var(--ease),opacity .4s,padding .4s}
    .faq-a p{padding:0 24px;color:var(--text-2);font-size:.94rem}
    .faq-item.open .faq-a{max-height:260px;opacity:1;padding-bottom:20px}
    .faq-item.open{box-shadow:var(--shadow);background:var(--glass-strong)}

    /* CONTACTO + FORM */
    .contacto-grid{display:grid;grid-template-columns:.9fr 1.1fr;gap:30px;align-items:stretch}
    .contacto-info{padding:38px 34px;display:flex;flex-direction:column;gap:8px}
    .contacto-info h2{font-size:1.9rem;font-weight:900;letter-spacing:-.02em;margin-bottom:8px}
    .contacto-info>p{color:var(--text-2);margin-bottom:18px}
    .ci-row{display:flex;align-items:center;gap:15px;padding:14px 0;border-bottom:1px solid var(--border-soft)}
    .ci-row .ic{width:46px;height:46px;border-radius:13px;background:var(--accent-soft);color:var(--accent-2);display:grid;place-items:center;font-size:1.1rem;flex-shrink:0}
    .ci-row .lbl{font-size:.74rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em}
    .ci-row .val{font-weight:700;font-size:.96rem}
    .contacto-socials{display:flex;gap:12px;margin-top:18px}
    .contacto-socials a{width:46px;height:46px;border-radius:13px;background:var(--glass-strong);border:1px solid var(--glass-border);display:grid;place-items:center;color:var(--accent-2);font-size:1.05rem;transition:.3s var(--ease)}
    .contacto-socials a:hover{transform:translateY(-4px) scale(1.05);background:var(--accent);color:#fff;box-shadow:0 10px 24px rgba(2,177,244,.4)}

    .formulario{padding:38px 34px;background:var(--glass-strong);backdrop-filter:blur(30px) saturate(1.9);-webkit-backdrop-filter:blur(30px) saturate(1.9);
        border:1px solid var(--glass-border);border-radius:var(--radius);box-shadow:var(--shadow-lg)}
    .formulario h3{font-size:1.5rem;font-weight:900;letter-spacing:-.02em}
    .form-sub{color:var(--text-2);font-size:.92rem;margin-bottom:22px}
    .input-group{display:grid;gap:13px}
    .input-container{position:relative;display:flex;align-items:center}
    .input-container>i{position:absolute;left:16px;color:var(--muted);font-size:.95rem;pointer-events:none}
    .input-container input{width:100%;height:52px;border:1.5px solid var(--border);border-radius:14px;background:rgba(255,255,255,.7);
        padding:0 16px 0 44px;font-size:.95rem;font-family:inherit;color:var(--text);transition:.25s var(--ease)}
    .input-container input::placeholder{color:var(--muted)}
    .input-container input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 4px var(--accent-soft);background:#fff}
    .cedula-group{gap:10px}
    .cedula-select{height:52px;width:64px;border:1.5px solid var(--border);border-radius:14px;background:rgba(255,255,255,.7);font-family:inherit;font-weight:700;color:var(--text);padding:0 8px;cursor:pointer}
    .cedula-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 4px var(--accent-soft)}
    .cedula-input{padding-left:16px !important}
    .btn-submit{height:54px;border:none;border-radius:14px;font-family:inherit;font-weight:800;font-size:.98rem;color:#fff;cursor:pointer;
        background:linear-gradient(135deg,var(--accent),var(--accent-hover));box-shadow:0 14px 32px rgba(2,177,244,.42);
        display:flex;align-items:center;justify-content:center;gap:10px;transition:.3s var(--ease);margin-top:4px}
    .btn-submit:hover{transform:translateY(-3px);box-shadow:0 20px 44px rgba(2,177,244,.55)}
    .form-legal{font-size:.74rem;color:var(--muted);text-align:center;margin-top:4px}

    /* FOOTER */
    .footer{padding:64px 0 30px;margin-top:40px;position:relative}
    .footer-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:34px;padding-bottom:34px;border-bottom:1px solid var(--border)}
    .footer-brand .brand{margin-bottom:14px}
    .footer-brand p{color:var(--text-2);font-size:.9rem;max-width:280px}
    .footer h5{font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--text);margin-bottom:16px}
    .footer ul{list-style:none;display:grid;gap:10px}
    .footer ul a{color:var(--text-2);font-size:.9rem;transition:.25s}
    .footer ul a:hover{color:var(--accent-2);padding-left:4px}
    .footer-bottom{text-align:center;padding-top:24px;color:var(--muted);font-size:.84rem}

    /* TOAST / flash */
    .toast{position:fixed;top:88px;left:50%;transform:translateX(-50%) translateY(-20px);z-index:200;opacity:0;
        background:var(--glass-strong);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--glass-border);
        border-radius:14px;padding:14px 22px;box-shadow:var(--shadow-lg);font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:10px;transition:.5s var(--ease)}
    .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
    .toast.err{color:#b91c1c}.toast.ok{color:#15803d}

    /* ════════════════════ SCROLL REVEAL ════════════════════ */
    .reveal{opacity:0;transform:translateY(34px);transition:opacity .7s var(--ease),transform .7s var(--ease)}
    .reveal.in{opacity:1;transform:none}
    .reveal[data-delay="1"]{transition-delay:.08s}.reveal[data-delay="2"]{transition-delay:.16s}
    .reveal[data-delay="3"]{transition-delay:.24s}.reveal[data-delay="4"]{transition-delay:.32s}
    .reveal[data-delay="5"]{transition-delay:.40s}

    /* ════════════════════ RESPONSIVE ════════════════════ */
    @media (max-width:1024px){
        .hero-grid{grid-template-columns:1fr;gap:40px;text-align:center}
        .hero p.lead{margin-left:auto;margin-right:auto}.hero-cta{justify-content:center}.hero-stats{margin:0 auto}
        .hero-visual{order:-1}.glass-card{max-width:380px}
        .feat-grid,.proc-grid{grid-template-columns:repeat(2,1fr)}
        .serv-grid{grid-template-columns:repeat(3,1fr)}
        .contacto-grid{grid-template-columns:1fr}
        .footer-grid{grid-template-columns:1fr 1fr}
    }
    @media (max-width:680px){
        .nav-links{position:fixed;inset:0 0 auto 0;top:78px;margin:0 14px;flex-direction:column;align-items:stretch;gap:6px;padding:14px;
            background:var(--glass-strong);backdrop-filter:blur(24px) saturate(1.8);-webkit-backdrop-filter:blur(24px) saturate(1.8);
            border:1px solid var(--glass-border);border-radius:18px;box-shadow:var(--shadow-lg);
            opacity:0;visibility:hidden;transform:translateY(-12px);transition:.35s var(--ease)}
        .nav-links.open{opacity:1;visibility:visible;transform:none}
        .nav-links a{padding:13px 16px;font-size:1rem}.nav-cta{justify-content:center}
        .burger{display:grid;place-items:center}
        .hero{padding:115px 0 50px}
        .hero-stats{grid-template-columns:repeat(2,1fr)}
        .serv-grid{grid-template-columns:repeat(2,1fr)}
        .feat-grid,.proc-grid{grid-template-columns:1fr}
        .footer-grid{grid-template-columns:1fr;gap:26px}
        section{padding:64px 0}
        .float-chip{display:none}
        .contacto-info,.formulario{padding:28px 22px}
    }
    @media (prefers-reduced-motion:reduce){
        *,*::after,*::before{animation:none !important;transition:none !important;scroll-behavior:auto !important}
        .reveal{opacity:1;transform:none}
    }
    </style>
</head>
<body>
    <!-- Fondo animado -->
    <div class="bg-wrap" aria-hidden="true">
        <span class="orb a"></span><span class="orb b"></span><span class="orb c"></span><span class="orb d"></span>
    </div>

    <?php if ($flash): ?>
    <div class="toast <?= ($flash==='registro_ok')?'ok':'err' ?>" id="flash">
        <i class="fa-solid <?= ($flash==='registro_ok')?'fa-circle-check':'fa-circle-exclamation' ?>"></i>
        <?php
            echo ($flash==='registro_ok') ? '¡Registro exitoso! Revisa tu correo para verificar la cuenta.'
               : (($flash==='user_exists') ? 'Ese correo o documento ya está registrado.'
               : 'No pudimos completar el registro. Revisa los datos e inténtalo de nuevo.');
        ?>
    </div>
    <?php endif; ?>

    <!-- ══════════ NAVBAR ══════════ -->
    <header class="nav" id="nav">
        <div class="container nav-inner">
            <a href="#inicio" class="brand">
                <span class="brand-logo"><i class="fa-solid fa-wave-square"></i></span>
                <span>EcoMadelleine<small>Diagnóstico ecográfico</small></span>
            </a>
            <nav>
                <ul class="nav-links" id="navLinks">
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="#nosotros">Nosotros</a></li>
                    <li><a href="#servicios">Servicios</a></li>
                    <li><a href="#faq">FAQ</a></li>
                    <li><a href="#contacto">Contacto</a></li>
                    <li><a href="<?= eco_url('login') ?>" class="nav-cta"><i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión</a></li>
                </ul>
            </nav>
            <button class="burger" id="burger" aria-label="Menú"><i class="fa-solid fa-bars"></i></button>
        </div>
    </header>

    <!-- ══════════ HERO ══════════ -->
    <section id="inicio" class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="eyebrow reveal"><span class="dot"></span> Centro de Diagnóstico por Ultrasonido</span>
                <h1 class="reveal" data-delay="1">Imágenes que dan <span class="grad">claridad</span>, informes que dan <span class="grad">tranquilidad</span>.</h1>
                <p class="lead reveal" data-delay="2">Tecnología de imagen avanzada y la experiencia de la <strong>Dra. Madelleine Toro</strong>. Agenda tu ecografía y recibe tu <strong>informe digital firmado</strong> con resultados claros.</p>
                <div class="hero-cta reveal" data-delay="3">
                    <a href="#contacto" class="btn btn-primary">Solicitar cita <i class="fa-solid fa-arrow-right"></i></a>
                    <a href="<?= eco_url('login') ?>" class="btn btn-glass"><i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión</a>
                </div>
                <div class="hero-stats reveal" data-delay="4">
                    <div class="stat"><div class="num" data-count="<?= htmlspecialchars($f_pac['value']) ?>"><?= htmlspecialchars($f_pac['value']) ?></div><div class="lbl"><?= htmlspecialchars($f_pac['label']) ?></div></div>
                    <div class="stat"><div class="num" data-count="<?= htmlspecialchars($f_tip['value']) ?>"><?= htmlspecialchars($f_tip['value']) ?></div><div class="lbl"><?= htmlspecialchars($f_tip['label']) ?></div></div>
                    <div class="stat"><div class="num" data-count="<?= htmlspecialchars($f_hrs['value']) ?>"><?= htmlspecialchars($f_hrs['value']) ?></div><div class="lbl"><?= htmlspecialchars($f_hrs['label']) ?></div></div>
                    <div class="stat"><div class="num" data-count="<?= htmlspecialchars($f_tasa['value']) ?>"><?= htmlspecialchars($f_tasa['value']) ?></div><div class="lbl"><?= htmlspecialchars($f_tasa['label']) ?></div></div>
                </div>
            </div>

            <div class="hero-visual reveal" data-delay="2">
                <div class="float-chip c1"><i class="fa-solid fa-shield-halved"></i> Informe firmado</div>
                <div class="float-chip c2"><i class="fa-solid fa-clock"></i> Listo en 24 h</div>
                <div class="glass-card">
                    <div class="gc-head">
                        <div class="gc-avatar"><i class="fa-solid fa-user-doctor"></i></div>
                        <div><h4>Dra. Madelleine Toro</h4><span>Médico Ecografista</span></div>
                    </div>
                    <div class="wave"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                    <div class="gc-rows">
                        <div class="gc-row"><span>Estudio</span> <b>Ecografía Abdominal</b></div>
                        <div class="gc-row"><span>Estado</span> <span class="gc-badge"><i class="fa-solid fa-circle-check"></i> Informe firmado</span></div>
                        <div class="gc-row"><span>Entrega</span> <b>Digital · 24 h</b></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════ NOSOTROS ══════════ -->
    <section id="nosotros">
        <div class="container">
            <div class="sec-head reveal">
                <span class="sec-tag">Por qué EcoMadelleine</span>
                <h2>Diagnóstico de precisión, atención que se siente cercana</h2>
                <p>Combinamos equipos de última generación con una lectura clínica experta para darte respuestas claras y a tiempo.</p>
            </div>
            <div class="feat-grid">
                <div class="feat glass reveal" data-delay="1"><div class="feat-ic"><i class="fa-solid fa-microchip"></i></div><h3>Tecnología avanzada</h3><p>Equipos de ultrasonido de alta resolución para imágenes nítidas y diagnósticos confiables.</p></div>
                <div class="feat glass reveal" data-delay="2"><div class="feat-ic"><i class="fa-solid fa-user-doctor"></i></div><h3>Especialista certificada</h3><p>Estudios realizados e interpretados por la Dra. Madelleine Toro, médico ecografista.</p></div>
                <div class="feat glass reveal" data-delay="3"><div class="feat-ic"><i class="fa-solid fa-file-shield"></i></div><h3>Informe en 24 horas</h3><p>Recibe tu informe digital, firmado electrónicamente y verificable, directo en tu portal.</p></div>
                <div class="feat glass reveal" data-delay="4"><div class="feat-ic"><i class="fa-solid fa-heart"></i></div><h3>Atención cercana</h3><p>Agenda en línea, recordatorios automáticos y acompañamiento en cada paso.</p></div>
            </div>
        </div>
    </section>

    <!-- ══════════ SERVICIOS ══════════ -->
    <section id="servicios">
        <div class="container">
            <div class="sec-head reveal">
                <span class="sec-tag">Estudios</span>
                <h2>Una ecografía para cada necesidad</h2>
                <p>Explora nuestros estudios disponibles. Inicia sesión o regístrate para agendar el tuyo.</p>
            </div>
            <div class="serv-grid">
                <?php foreach ($servicios_landing as $i => $s):
                    $p = $eco_palette[$s['cat']] ?? $eco_palette_default; ?>
                    <a href="<?= eco_url('login') ?>" class="serv glass reveal" data-delay="<?= ($i % 5) + 1 ?>" style="--soft:<?= $p['soft'] ?>">
                        <div class="serv-ic" style="background:<?= $p['soft'] ?>;color:<?= $p['text'] ?>"><i class="fa-solid <?= $s['i'] ?>"></i></div>
                        <h3><?= htmlspecialchars($s['n']) ?></h3>
                        <div class="go">Agendar <i class="fa-solid fa-arrow-right"></i></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ══════════ PROCESO ══════════ -->
    <section id="proceso">
        <div class="container">
            <div class="sec-head reveal">
                <span class="sec-tag">Cómo funciona</span>
                <h2>De la cita al informe, en cuatro pasos</h2>
            </div>
            <div class="proc-grid">
                <div class="proc glass reveal" data-delay="1"><div class="step"></div><h3>Agenda tu cita</h3><p>Regístrate y solicita tu estudio en línea, eligiendo el horario que más te convenga.</p></div>
                <div class="proc glass reveal" data-delay="2"><div class="step"></div><h3>Realiza el estudio</h3><p>Te atendemos con equipos de alta resolución y la guía de preparación específica.</p></div>
                <div class="proc glass reveal" data-delay="3"><div class="step"></div><h3>Lectura experta</h3><p>La especialista interpreta y firma tu informe electrónicamente.</p></div>
                <div class="proc glass reveal" data-delay="4"><div class="step"></div><h3>Recibe resultados</h3><p>Tu informe digital queda disponible en tu portal y por enlace seguro.</p></div>
            </div>
        </div>
    </section>

    <!-- ══════════ FAQ ══════════ -->
    <section id="faq">
        <div class="container">
            <div class="sec-head reveal">
                <span class="sec-tag">Preguntas frecuentes</span>
                <h2>Resolvemos tus dudas</h2>
            </div>
            <div class="faq-wrap">
                <?php
                $faqs = [
                    ['¿Necesito preparación para mi ecografía?','Depende del estudio. Algunas (como la abdominal) requieren ayuno; otras, vejiga llena. Al agendar te indicamos la preparación exacta para tu caso.'],
                    ['¿Cuándo recibo mi informe?','Nuestro compromiso es entregar el informe digital firmado en un máximo de 24 horas, disponible en tu portal del paciente.'],
                    ['¿El informe es válido y verificable?','Sí. Cada informe lleva una firma electrónica con sello del servidor (huella SHA-256 + HMAC) que garantiza su integridad y autenticidad.'],
                    ['¿Necesito orden médica?','Es recomendable traer la indicación de tu médico. Si no la tienes, consúltalo con recepción al agendar.'],
                    ['¿Cómo agendo una cita?','Regístrate en el formulario de contacto o inicia sesión, y solicita tu estudio eligiendo fecha y hora disponibles.'],
                ];
                foreach ($faqs as $i => $f): ?>
                    <div class="faq-item glass reveal" data-delay="<?= min($i+1,4) ?>">
                        <div class="faq-q"><span><?= htmlspecialchars($f[0]) ?></span> <i class="fa-solid fa-plus"></i></div>
                        <div class="faq-a"><p><?= htmlspecialchars($f[1]) ?></p></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ══════════ CONTACTO ══════════ -->
    <section id="contacto">
        <div class="container">
            <div class="contacto-grid">
                <aside class="contacto-info glass reveal">
                    <h2>Hablemos</h2>
                    <p>Estamos para ayudarte. Escríbenos o regístrate para agendar tu estudio.</p>
                    <div class="ci-row"><div class="ic"><i class="fa-solid fa-location-dot"></i></div><div><div class="lbl">Ubicación</div><div class="val">Consultorio EcoMadelleine</div></div></div>
                    <div class="ci-row"><div class="ic"><i class="fa-solid fa-phone"></i></div><div><div class="lbl">Teléfono</div><div class="val">+58 412 000 0000</div></div></div>
                    <div class="ci-row"><div class="ic"><i class="fa-regular fa-envelope"></i></div><div><div class="lbl">Correo</div><div class="val">contacto@ecomadelleine.com</div></div></div>
                    <div class="ci-row"><div class="ic"><i class="fa-regular fa-clock"></i></div><div><div class="lbl">Horario</div><div class="val">Lun — Vie · 8:00 a 17:00</div></div></div>
                    <div class="contacto-socials">
                        <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                        <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    </div>
                </aside>

                <div class="formulario reveal" data-delay="1">
                    <h3>Crea tu cuenta</h3>
                    <p class="form-sub">Regístrate para agendar tu próxima ecografía.</p>
                    <form method="post" autocomplete="off">
                        <div class="input-group">
                            <div class="input-container">
                                <i class="fa-solid fa-user"></i>
                                <input type="text" name="name" placeholder="Nombre y Apellido" required>
                            </div>
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
                                <input type="text" name="cedula_numero" class="cedula-input" placeholder="Número de documento" required pattern="\d{7,8}" title="Ingresa entre 7 y 8 números" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                            <div class="input-container">
                                <i class="fa-regular fa-envelope"></i>
                                <input type="email" name="email" placeholder="Correo electrónico" required>
                            </div>
                            <div class="input-container">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" name="password" placeholder="Crea una contraseña" required
                                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                                       title="Mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.">
                            </div>
                            <button type="submit" name="send" class="btn-submit">
                                Registrarme y solicitar estudio <i class="fa-solid fa-arrow-right"></i>
                            </button>
                            <p class="form-legal">Al registrarte aceptas el tratamiento confidencial de tus datos clínicos.</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════ FOOTER ══════════ -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="#inicio" class="brand">
                        <span class="brand-logo"><i class="fa-solid fa-wave-square"></i></span>
                        <span>EcoMadelleine<small>Diagnóstico ecográfico</small></span>
                    </a>
                    <p>Centro de diagnóstico por ultrasonido. Imágenes claras, informes digitales firmados y atención cercana.</p>
                </div>
                <div><h5>Navegación</h5><ul>
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="#nosotros">Nosotros</a></li>
                    <li><a href="#servicios">Servicios</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul></div>
                <div><h5>Pacientes</h5><ul>
                    <li><a href="<?= eco_url('login') ?>">Iniciar sesión</a></li>
                    <li><a href="<?= eco_url('registro') ?>">Crear cuenta</a></li>
                    <li><a href="#contacto">Agendar cita</a></li>
                    <li><a href="<?= eco_url('privacidad') ?>">Aviso de privacidad</a></li>
                </ul></div>
                <div><h5>Contacto</h5><ul>
                    <li><a href="#contacto"><i class="fa-solid fa-location-dot"></i> Ubicación</a></li>
                    <li><a href="#contacto"><i class="fa-solid fa-phone"></i> +58 412 000 0000</a></li>
                    <li><a href="#contacto"><i class="fa-regular fa-envelope"></i> contacto@ecomadelleine.com</a></li>
                </ul></div>
            </div>
            <div class="footer-bottom">© <?= date('Y') ?> EcoMadelleine · Centro de Diagnóstico por Ultrasonido. Todos los derechos reservados.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
    (function(){
        'use strict';
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* Flatpickr fecha de nacimiento */
        if (window.flatpickr) {
            flatpickr.localize(flatpickr.l10ns.es);
            flatpickr('#fecha_nacimiento_flatpickr', { dateFormat:'Y-m-d', maxDate:'today', altInput:true, altFormat:'d / m / Y', disableMobile:true });
        }

        /* Navbar: glass intensifica al hacer scroll */
        var nav = document.getElementById('nav');
        var onScroll = function(){ nav.classList.toggle('scrolled', window.scrollY > 24); };
        onScroll(); window.addEventListener('scroll', onScroll, {passive:true});

        /* Menú móvil */
        var burger = document.getElementById('burger'), links = document.getElementById('navLinks');
        burger.addEventListener('click', function(){ links.classList.toggle('open'); });
        links.addEventListener('click', function(e){ if (e.target.tagName==='A') links.classList.remove('open'); });

        /* Scroll reveal */
        var revs = document.querySelectorAll('.reveal');
        if (reduce || !('IntersectionObserver' in window)) {
            revs.forEach(function(el){ el.classList.add('in'); });
        } else {
            var io = new IntersectionObserver(function(entries){
                entries.forEach(function(en){ if (en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); } });
            }, { threshold:.12, rootMargin:'0px 0px -8% 0px' });
            revs.forEach(function(el){ io.observe(el); });
        }

        /* Contadores animados (solo valores numéricos) */
        function animateCount(el){
            var raw = el.getAttribute('data-count') || el.textContent;
            var m = raw.match(/^(\D*)([\d.,]+)(\D*)$/);
            if (!m){ return; }
            var pre=m[1], suf=m[3], target=parseFloat(m[2].replace(/\./g,'').replace(',', '.'));
            if (isNaN(target)){ return; }
            var dur=1300, start=null;
            function step(ts){ if(!start)start=ts; var p=Math.min((ts-start)/dur,1);
                var val=Math.floor((1-Math.pow(1-p,3))*target);
                el.textContent = pre + val.toLocaleString('es') + suf;
                if(p<1) requestAnimationFrame(step); else el.textContent = raw;
            }
            requestAnimationFrame(step);
        }
        var nums = document.querySelectorAll('.stat .num[data-count]');
        if (!reduce && 'IntersectionObserver' in window){
            var io2 = new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ animateCount(e.target); io2.unobserve(e.target);} }); }, {threshold:.6});
            nums.forEach(function(n){ io2.observe(n); });
        }

        /* FAQ acordeón */
        document.querySelectorAll('.faq-q').forEach(function(q){
            q.addEventListener('click', function(){
                var item = q.parentElement, open = item.classList.contains('open');
                document.querySelectorAll('.faq-item.open').forEach(function(i){ i.classList.remove('open'); });
                if (!open) item.classList.add('open');
            });
        });

        /* Toast flash auto-hide */
        var t = document.getElementById('flash');
        if (t){ requestAnimationFrame(function(){ t.classList.add('show'); });
            setTimeout(function(){ t.classList.remove('show'); }, 6000); }
    })();
    </script>
</body>
</html>
