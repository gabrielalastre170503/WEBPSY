<?php
/**
 * resultado.php — Visor publico de resultados por token (sin login). Fase 3 (b).
 *
 * El paciente abre este enlace (sin cuenta) para ver/descargar las imagenes y
 * adjuntos de su estudio. El acceso lo concede un token de un solo parametro (?t)
 * con caducidad y tope de aperturas; cada carga consume una apertura.
 */
include 'conexion.php';
require_once __DIR__ . '/lib/archivos.php';
require_once __DIR__ . '/lib/tokens.php';
require_once __DIR__ . '/lib/seguridad.php';

$raw = isset($_GET['t']) ? (string)$_GET['t'] : '';
$est = eco_token_abrir($conex, $raw, eco_client_ip());

/* ── Token invalido / expirado / agotado / revocado ──────────────────── */
if (!$est['ok']) {
    $motivos = [
        'expirado' => ['Enlace caducado', 'Este enlace de resultados ya no esta disponible porque ha vencido su periodo de validez.'],
        'agotado'  => ['Enlace agotado', 'Este enlace ya alcanzo su numero maximo de aperturas permitidas.'],
        'revocado' => ['Enlace anulado', 'El profesional anulo este enlace de resultados.'],
        'invalido' => ['Enlace no valido', 'El enlace es incorrecto o ya no existe. Verifica que lo copiaste completo.'],
    ];
    [$titulo, $texto] = $motivos[$est['motivo']] ?? $motivos['invalido'];
    http_response_code(($est['motivo'] === 'invalido') ? 404 : 410);
    ?>
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> — EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body{margin:0;font-family:system-ui,"Segoe UI",sans-serif;background:#eef2f7;color:#1a2332;
             min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
        .box{max-width:440px;background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.1);
             padding:40px 32px;text-align:center;}
        .ic{width:74px;height:74px;border-radius:50%;background:#fff1f2;color:#e11d48;font-size:30px;
            display:flex;align-items:center;justify-content:center;margin:0 auto 18px;}
        h1{font-size:20px;margin:0 0 10px;}
        p{color:#5a6878;font-size:14px;line-height:1.6;margin:0;}
    </style></head><body>
        <div class="box">
            <div class="ic"><i class="fa-solid fa-link-slash"></i></div>
            <h1><?= htmlspecialchars($titulo) ?></h1>
            <p><?= htmlspecialchars($texto) ?></p>
        </div>
    </body></html>
    <?php
    exit();
}

/* ── Token valido: cargar informe ────────────────────────────────────── */
$informe_id = (int)$est['informe_id'];
$sql = "SELECT inf.id, inf.numero_informe, inf.estado, inf.creado_en, inf.fecha_estudio,
               pac.nombre_completo AS paciente_nombre,
               eco.nombre_completo AS ecografista_nombre,
               t.nombre AS tipo_nombre, t.icono AS tipo_icono
        FROM informes_estudios inf
        JOIN usuarios pac       ON pac.id = inf.paciente_id
        JOIN usuarios eco       ON eco.id = inf.ecografista_id
        JOIN tipos_ecografias t ON t.id   = inf.tipo_ecografia_id
        WHERE inf.id = ?";
$stmt = $conex->prepare($sql);
$stmt->bind_param('i', $informe_id);
$stmt->execute();
$informe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$informe) {
    http_response_code(404);
    exit('Resultado no disponible.');
}

eco_auditar($conex, 'resultado_visto', [
    'usuario_id' => null,
    'entidad' => 'informe', 'entidad_id' => $informe_id,
    'detalle' => ['token_id' => $est['token_id'], 'uso' => $est['usos']],
]);

$archivos     = eco_archivos_de_informe($conex, $informe_id);
$archivos_img = array_filter($archivos, static fn($a) => strpos((string)$a['mime'], 'image/') === 0);
$archivos_adj = array_filter($archivos, static fn($a) => strpos((string)$a['mime'], 'image/') !== 0);

$fecha = !empty($informe['fecha_estudio'])
    ? date('d/m/Y', strtotime($informe['fecha_estudio']))
    : date('d/m/Y', strtotime($informe['creado_en']));
$expira_fmt = $est['expira_en'] ? date('d/m/Y H:i', strtotime($est['expira_en'])) : '';
$t_url = rawurlencode($raw);

if (!function_exists('eco_fmt_bytes')) {
    function eco_fmt_bytes($b): string {
        $b = (int)$b;
        if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
        if ($b >= 1024)    return round($b / 1024) . ' KB';
        return $b . ' B';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resultados — <?= htmlspecialchars($informe['tipo_nombre']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{ --azul:#02b1f4; --azul-osc:#014a82; --tinta:#1a2332; --gris:#5a6878; --borde:#e1e8f0; }
        *{box-sizing:border-box;}
        body{margin:0;background:#eef2f7;font-family:"Poppins",system-ui,sans-serif;color:var(--tinta);padding:24px 16px;}
        .wrap{max-width:880px;margin:0 auto;}
        .card{background:#fff;border-radius:14px;box-shadow:0 6px 24px rgba(0,0,0,.07);overflow:hidden;}
        .head{background:linear-gradient(135deg,var(--azul),var(--azul-osc));color:#fff;padding:26px 28px;}
        .head .marca{font-size:13px;letter-spacing:1px;text-transform:uppercase;opacity:.85;font-weight:600;}
        .head h1{margin:6px 0 0;font-size:22px;font-weight:700;}
        .head .sub{margin-top:4px;font-size:13.5px;opacity:.9;}
        .meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;padding:20px 28px;
              border-bottom:1px solid var(--borde);}
        .meta .lbl{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--gris);font-weight:600;}
        .meta .val{font-size:14.5px;font-weight:600;margin-top:2px;}
        .body{padding:22px 28px 28px;}
        .sec-title{font-size:15px;font-weight:700;margin:0 0 14px;display:flex;align-items:center;gap:8px;}
        .sec-title i{color:var(--azul);}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;}
        .thumb{border:1px solid var(--borde);border-radius:10px;overflow:hidden;cursor:pointer;background:#f8fbff;
               aspect-ratio:4/3;display:block;}
        .thumb img{width:100%;height:100%;object-fit:cover;display:block;}
        .adj{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--borde);
             border-radius:10px;margin-bottom:10px;text-decoration:none;color:var(--tinta);transition:.15s;}
        .adj:hover{border-color:var(--azul);background:#f8fbff;}
        .adj .ai{width:40px;height:40px;border-radius:8px;background:#fff1f2;color:#e11d48;display:flex;
                 align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
        .adj .an{font-weight:600;font-size:14px;}
        .adj .as{font-size:12px;color:var(--gris);}
        .adj .ad{margin-left:auto;color:var(--azul);font-size:13px;font-weight:600;}
        .empty{color:var(--gris);font-size:14px;padding:16px;background:#f8fbff;border-radius:10px;text-align:center;}
        .foot{padding:16px 28px;border-top:1px solid var(--borde);font-size:12.5px;color:var(--gris);
              display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
        .lb{position:fixed;inset:0;background:rgba(8,15,30,.92);display:none;align-items:center;justify-content:center;
            z-index:999;padding:24px;}
        .lb.open{display:flex;}
        .lb img{max-width:96vw;max-height:92vh;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);}
        .lb-close{position:absolute;top:18px;right:22px;color:#fff;font-size:30px;cursor:pointer;background:none;border:none;}
        @media(max-width:560px){ .head,.meta,.body,.foot{padding-left:18px;padding-right:18px;} }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="head">
                <div class="marca"><i class="fa-solid fa-wave-square"></i> EcoMadelleine</div>
                <h1>Resultados de tu estudio</h1>
                <div class="sub"><?= htmlspecialchars($informe['tipo_nombre']) ?></div>
            </div>

            <div class="meta">
                <div><div class="lbl">Paciente</div><div class="val"><?= htmlspecialchars($informe['paciente_nombre']) ?></div></div>
                <div><div class="lbl">Fecha del estudio</div><div class="val"><?= htmlspecialchars($fecha) ?></div></div>
                <div><div class="lbl">Profesional</div><div class="val"><?= htmlspecialchars($informe['ecografista_nombre']) ?></div></div>
                <?php if (!empty($informe['numero_informe'])): ?>
                    <div><div class="lbl">N.º de informe</div><div class="val"><?= htmlspecialchars($informe['numero_informe']) ?></div></div>
                <?php endif; ?>
            </div>

            <div class="body">
                <h2 class="sec-title"><i class="fa-solid fa-images"></i> Imágenes del estudio</h2>
                <?php if (!empty($archivos_img)): ?>
                    <div class="grid">
                        <?php foreach ($archivos_img as $a):
                            $src = 'descargar_resultado.php?t=' . $t_url . '&a=' . (int)$a['id']; ?>
                            <a class="thumb" href="<?= $src ?>" data-full="<?= $src ?>"
                               onclick="return abrirLb(this)">
                                <img src="<?= $src ?>" alt="Imagen del estudio" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">Este estudio todavía no tiene imágenes publicadas.</div>
                <?php endif; ?>

                <?php if (!empty($archivos_adj)): ?>
                    <h2 class="sec-title" style="margin-top:26px;"><i class="fa-solid fa-paperclip"></i> Documentos adjuntos</h2>
                    <?php foreach ($archivos_adj as $a):
                        $src = 'descargar_resultado.php?t=' . $t_url . '&a=' . (int)$a['id']; ?>
                        <a class="adj" href="<?= $src ?>">
                            <span class="ai"><i class="fa-solid fa-file-pdf"></i></span>
                            <span>
                                <span class="an"><?= htmlspecialchars($a['nombre_original']) ?></span><br>
                                <span class="as"><?= eco_fmt_bytes($a['tamano']) ?></span>
                            </span>
                            <span class="ad"><i class="fa-solid fa-download"></i> Descargar</span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="foot">
                <i class="fa-solid fa-shield-halved"></i>
                Enlace seguro y personal.
                <?php if ($expira_fmt): ?>Válido hasta el <strong><?= htmlspecialchars($expira_fmt) ?></strong>.<?php endif; ?>
                No lo compartas con terceros.
            </div>
        </div>
    </div>

    <div class="lb" id="lb" onclick="cerrarLb(event)">
        <button class="lb-close" type="button" aria-label="Cerrar">&times;</button>
        <img id="lb-img" src="" alt="Imagen ampliada">
    </div>

    <script>
        function abrirLb(el){
            document.getElementById('lb-img').src = el.dataset.full;
            document.getElementById('lb').classList.add('open');
            return false;
        }
        function cerrarLb(e){
            if (e.target.id === 'lb' || e.target.classList.contains('lb-close')) {
                document.getElementById('lb').classList.remove('open');
                document.getElementById('lb-img').src = '';
            }
        }
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') {
                document.getElementById('lb').classList.remove('open');
                document.getElementById('lb-img').src = '';
            }
        });
    </script>
</body>
</html>
