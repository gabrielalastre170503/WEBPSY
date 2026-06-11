<?php
/**
 * lib/reportes/reportes.php — Inteligencia de negocio / reportes (Fase 6).
 *
 * Agregaciones de actividad y facturacion de citas por rango de fechas.
 * Todo se calcula sobre citas.fecha_cita dentro del rango [desde 00:00, hasta 23:59].
 * Funciones puras de lectura (prepared statements); no emiten salida.
 */

if (!function_exists('eco_reporte_rango')) {
    /**
     * Normaliza un rango de fechas. Default: del dia 1 del mes a hoy.
     * @return array{0:string,1:string} [desde, hasta] en formato Y-m-d
     */
    function eco_reporte_rango(?string $desde, ?string $hasta): array
    {
        $valid = static fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
        $d = $valid($desde) ? $desde : date('Y-m-01');
        $h = $valid($hasta) ? $hasta : date('Y-m-d');
        if ($h < $d) {
            [$d, $h] = [$h, $d];
        }
        return [$d, $h];
    }
}

if (!function_exists('eco_reporte_resumen')) {
    /**
     * KPIs globales del periodo.
     * @return array{citas:int,completadas:int,canceladas:int,confirmadas:int,
     *   pendientes:int,pacientes:int,facturado:float,cobrado:float,saldo:float,
     *   exonerados:int,tasa_cobro:float}
     */
    function eco_reporte_resumen(mysqli $conex, string $desde, string $hasta, ?int $ecografistaId = null): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $cond = ''; $types = 'ss'; $args = [$ini, $fin];
        if ($ecografistaId !== null && $ecografistaId > 0) {
            $cond = ' AND ecografista_id = ?'; $types .= 'i'; $args[] = $ecografistaId;
        }
        $sql = "SELECT
                    COUNT(*) AS citas,
                    SUM(estado = 'completada') AS completadas,
                    SUM(estado = 'cancelada') AS canceladas,
                    SUM(estado IN ('confirmada','reprogramada')) AS confirmadas,
                    SUM(estado IN ('pendiente','pendiente_paciente')) AS pendientes,
                    COUNT(DISTINCT paciente_id) AS pacientes,
                    COALESCE(SUM(monto_total), 0) AS facturado,
                    COALESCE(SUM(monto_pagado), 0) AS cobrado,
                    COALESCE(SUM(CASE WHEN estado_pago <> 'exonerado'
                        THEN GREATEST(COALESCE(monto_total,0) - COALESCE(monto_pagado,0), 0)
                        ELSE 0 END), 0) AS saldo,
                    SUM(estado_pago = 'exonerado') AS exonerados,
                    SUM(estado = 'no_asistio') AS no_asistio,
                    SUM(estado IN ('confirmada','reprogramada') AND fecha_cita < NOW()) AS ausentes_presuntas,
                    SUM(estado IN ('confirmada','reprogramada','completada','no_asistio') AND fecha_cita < NOW()) AS agendadas_vencidas
                FROM citas
                WHERE fecha_cita BETWEEN ? AND ?" . $cond;
        $st = $conex->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $r = $st->get_result()->fetch_assoc() ?: [];
        $st->close();

        $out = [
            'citas'       => (int)($r['citas'] ?? 0),
            'completadas' => (int)($r['completadas'] ?? 0),
            'canceladas'  => (int)($r['canceladas'] ?? 0),
            'confirmadas' => (int)($r['confirmadas'] ?? 0),
            'pendientes'  => (int)($r['pendientes'] ?? 0),
            'pacientes'   => (int)($r['pacientes'] ?? 0),
            'facturado'   => (float)($r['facturado'] ?? 0),
            'cobrado'     => (float)($r['cobrado'] ?? 0),
            'saldo'       => (float)($r['saldo'] ?? 0),
            'exonerados'  => (int)($r['exonerados'] ?? 0),
        ];
        $out['tasa_cobro'] = $out['facturado'] > 0
            ? round($out['cobrado'] / $out['facturado'] * 100, 1)
            : 0.0;

        // No-show: ausencias = citas marcadas 'no_asistio' + confirmadas/reprogramadas
        // cuya fecha ya paso sin completarse (ausencias presuntas). La tasa se mide
        // sobre las citas agendadas que ya vencieron.
        $vencidas = (int)($r['agendadas_vencidas'] ?? 0);
        $out['no_show'] = (int)($r['no_asistio'] ?? 0) + (int)($r['ausentes_presuntas'] ?? 0);
        $out['tasa_no_show'] = $vencidas > 0
            ? round($out['no_show'] / $vencidas * 100, 1)
            : 0.0;
        return $out;
    }
}

if (!function_exists('eco_reporte_por_tipo')) {
    /** Desglose por tipo de ecografia. @return list<array> */
    function eco_reporte_por_tipo(mysqli $conex, string $desde, string $hasta, ?int $ecografistaId = null): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $cond = ''; $types = 'ss'; $args = [$ini, $fin];
        if ($ecografistaId !== null && $ecografistaId > 0) {
            $cond = ' AND c.ecografista_id = ?'; $types .= 'i'; $args[] = $ecografistaId;
        }
        $sql = "SELECT
                    COALESCE(t.nombre, 'Sin tipo') AS tipo,
                    COUNT(*) AS citas,
                    SUM(c.estado = 'completada') AS completadas,
                    COALESCE(SUM(c.monto_total), 0) AS facturado,
                    COALESCE(SUM(c.monto_pagado), 0) AS cobrado
                FROM citas c
                LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
                WHERE c.fecha_cita BETWEEN ? AND ?" . $cond . "
                GROUP BY tipo
                ORDER BY citas DESC, tipo ASC";
        $st = $conex->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $res = $st->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'tipo'        => $r['tipo'],
                'citas'       => (int)$r['citas'],
                'completadas' => (int)$r['completadas'],
                'facturado'   => (float)$r['facturado'],
                'cobrado'     => (float)$r['cobrado'],
            ];
        }
        $st->close();
        return $rows;
    }
}

if (!function_exists('eco_reporte_por_ecografista')) {
    /** Productividad por ecografista. @return list<array> */
    function eco_reporte_por_ecografista(mysqli $conex, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $sql = "SELECT
                    COALESCE(u.nombre_completo, 'Sin asignar') AS ecografista,
                    COUNT(*) AS citas,
                    SUM(c.estado = 'completada') AS completadas,
                    COUNT(DISTINCT c.paciente_id) AS pacientes,
                    COALESCE(SUM(c.monto_pagado), 0) AS cobrado
                FROM citas c
                LEFT JOIN usuarios u ON u.id = c.ecografista_id
                WHERE c.fecha_cita BETWEEN ? AND ?
                GROUP BY ecografista
                ORDER BY citas DESC, ecografista ASC";
        $st = $conex->prepare($sql);
        $st->bind_param('ss', $ini, $fin);
        $st->execute();
        $res = $st->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'ecografista' => $r['ecografista'],
                'citas'       => (int)$r['citas'],
                'completadas' => (int)$r['completadas'],
                'pacientes'   => (int)$r['pacientes'],
                'cobrado'     => (float)$r['cobrado'],
            ];
        }
        $st->close();
        return $rows;
    }
}

if (!function_exists('eco_reporte_por_metodo_pago')) {
    /** Ingresos cobrados agrupados por metodo de pago. @return list<array> */
    function eco_reporte_por_metodo_pago(mysqli $conex, string $desde, string $hasta, ?int $ecografistaId = null): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $cond = ''; $types = 'ss'; $args = [$ini, $fin];
        if ($ecografistaId !== null && $ecografistaId > 0) {
            $cond = ' AND ecografista_id = ?'; $types .= 'i'; $args[] = $ecografistaId;
        }
        $sql = "SELECT
                    COALESCE(NULLIF(metodo_pago, ''), 'Sin método') AS metodo,
                    COUNT(*) AS pagos,
                    COALESCE(SUM(monto_pagado), 0) AS cobrado
                FROM citas
                WHERE fecha_cita BETWEEN ? AND ? AND monto_pagado > 0" . $cond . "
                GROUP BY metodo
                ORDER BY cobrado DESC, metodo ASC";
        $st = $conex->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $res = $st->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'metodo'  => $r['metodo'],
                'pagos'   => (int)$r['pagos'],
                'cobrado' => (float)$r['cobrado'],
            ];
        }
        $st->close();
        return $rows;
    }
}

if (!function_exists('eco_reporte_top_pacientes')) {
    /** Pacientes con mayor monto cobrado en el periodo. @return list<array> */
    function eco_reporte_top_pacientes(mysqli $conex, string $desde, string $hasta, int $limit = 10, ?int $ecografistaId = null): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $limit = max(1, min($limit, 50));
        $cond = ''; $types = 'ss'; $args = [$ini, $fin];
        if ($ecografistaId !== null && $ecografistaId > 0) {
            $cond = ' AND c.ecografista_id = ?'; $types .= 'i'; $args[] = $ecografistaId;
        }
        $types .= 'i'; $args[] = $limit; // LIMIT siempre al final
        $sql = "SELECT
                    u.nombre_completo AS paciente,
                    COUNT(*) AS citas,
                    COALESCE(SUM(c.monto_pagado), 0) AS cobrado
                FROM citas c
                JOIN usuarios u ON u.id = c.paciente_id
                WHERE c.fecha_cita BETWEEN ? AND ?" . $cond . "
                GROUP BY c.paciente_id, paciente
                ORDER BY cobrado DESC, citas DESC
                LIMIT ?";
        $st = $conex->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $res = $st->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'paciente' => $r['paciente'],
                'citas'    => (int)$r['citas'],
                'cobrado'  => (float)$r['cobrado'],
            ];
        }
        $st->close();
        return $rows;
    }
}

if (!function_exists('eco_reporte_comparativa_meses')) {
    /**
     * Comparativa de los ultimos $n meses (citas e ingresos cobrados),
     * rellenando con cero los meses sin actividad.
     * @return list<array{mes:string,citas:int,cobrado:float}>
     */
    function eco_reporte_comparativa_meses(mysqli $conex, int $n = 6, ?int $ecografistaId = null): array
    {
        $n = max(2, min($n, 24));
        $cursor = new DateTime(date('Y-m-01'));
        $cursor->modify('-' . ($n - 1) . ' months');
        $desde = $cursor->format('Y-m-d') . ' 00:00:00';

        $meses = [];
        $c = clone $cursor;
        for ($i = 0; $i < $n; $i++) {
            $k = $c->format('Y-m');
            $meses[$k] = ['mes' => $k, 'citas' => 0, 'cobrado' => 0.0];
            $c->modify('+1 month');
        }

        $cond = ''; $types = 's'; $args = [$desde];
        if ($ecografistaId !== null && $ecografistaId > 0) {
            $cond = ' AND ecografista_id = ?'; $types .= 'i'; $args[] = $ecografistaId;
        }
        $sql = "SELECT DATE_FORMAT(fecha_cita, '%Y-%m') AS mes,
                       COUNT(*) AS citas,
                       COALESCE(SUM(monto_pagado), 0) AS cobrado
                FROM citas
                WHERE fecha_cita >= ?" . $cond . "
                GROUP BY mes";
        $st = $conex->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            if (isset($meses[$r['mes']])) {
                $meses[$r['mes']]['citas']   = (int)$r['citas'];
                $meses[$r['mes']]['cobrado'] = (float)$r['cobrado'];
            }
        }
        $st->close();
        return array_values($meses);
    }
}

if (!function_exists('eco_reporte_satisfaccion')) {
    /**
     * Satisfaccion del periodo a partir de las encuestas (1-5) de citas cuya
     * fecha_cita cae en el rango.
     * @return array{respuestas:int,promedio:float}
     */
    function eco_reporte_satisfaccion(mysqli $conex, string $desde, string $hasta, ?int $ecografistaId = null): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $cond = ''; $types = 'ss'; $args = [$ini, $fin];
        if ($ecografistaId !== null && $ecografistaId > 0) {
            $cond = ' AND c.ecografista_id = ?'; $types .= 'i'; $args[] = $ecografistaId;
        }
        $sql = "SELECT COUNT(*) AS respuestas, COALESCE(AVG(e.puntuacion), 0) AS promedio
                FROM encuestas e
                JOIN citas c ON c.id = e.cita_id
                WHERE c.fecha_cita BETWEEN ? AND ?" . $cond;
        $st = $conex->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $r = $st->get_result()->fetch_assoc() ?: [];
        $st->close();
        return [
            'respuestas' => (int)($r['respuestas'] ?? 0),
            'promedio'   => round((float)($r['promedio'] ?? 0), 2),
        ];
    }
}

if (!function_exists('eco_reporte_serie_diaria')) {
    /** Serie diaria (citas e ingresos cobrados) para grafico de tendencia. @return list<array> */
    function eco_reporte_serie_diaria(mysqli $conex, string $desde, string $hasta, ?int $ecografistaId = null): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $cond = ''; $types = 'ss'; $args = [$ini, $fin];
        if ($ecografistaId !== null && $ecografistaId > 0) {
            $cond = ' AND ecografista_id = ?'; $types .= 'i'; $args[] = $ecografistaId;
        }
        $sql = "SELECT
                    DATE(fecha_cita) AS dia,
                    COUNT(*) AS citas,
                    COALESCE(SUM(monto_pagado), 0) AS cobrado
                FROM citas
                WHERE fecha_cita BETWEEN ? AND ?" . $cond . "
                GROUP BY dia
                ORDER BY dia ASC";
        $st = $conex->prepare($sql);
        $st->bind_param($types, ...$args);
        $st->execute();
        $res = $st->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'dia'     => $r['dia'],
                'citas'   => (int)$r['citas'],
                'cobrado' => (float)$r['cobrado'],
            ];
        }
        $st->close();
        return $rows;
    }
}

/* ───────────────────────────────────────────────────────────────────────────
 * ECOGRAFISTA — Análisis clínico (gráficos de la antigua estadisticas_ecografista).
 * Todo scopeado a un ecografista. Día/hora/edad/dirección respetan el rango de
 * fechas (pacientes/citas del periodo); pacientes nuevos usa su propia ventana.
 * ─────────────────────────────────────────────────────────────────────────── */

if (!function_exists('eco_reporte_eco_dia_semana')) {
    /**
     * Citas activas por día de la semana en el rango. @return int[] [Lun..Dom]
     */
    function eco_reporte_eco_dia_semana(mysqli $conex, int $ecografistaId, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $raw = array_fill(1, 7, 0); // DAYOFWEEK: 1=Dom .. 7=Sáb
        $st = $conex->prepare("SELECT DAYOFWEEK(fecha_cita) d, COUNT(*) t FROM citas
                WHERE ecografista_id = ? AND fecha_cita BETWEEN ? AND ?
                  AND estado IN ('confirmada','completada','reprogramada') GROUP BY d");
        $st->bind_param('iss', $ecografistaId, $ini, $fin);
        $st->execute();
        $res = $st->get_result();
        while ($f = $res->fetch_assoc()) { $raw[(int)$f['d']] = (int)$f['t']; }
        $st->close();
        return [$raw[2], $raw[3], $raw[4], $raw[5], $raw[6], $raw[7], $raw[1]];
    }
}

if (!function_exists('eco_reporte_eco_hora')) {
    /**
     * Citas activas por hora (07:00–19:00) en el rango.
     * @return array{labels:string[],data:int[]}
     */
    function eco_reporte_eco_hora(mysqli $conex, int $ecografistaId, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $hmin = 7; $hmax = 19; $map = [];
        for ($h = $hmin; $h <= $hmax; $h++) { $map[$h] = 0; }
        $st = $conex->prepare("SELECT HOUR(fecha_cita) h, COUNT(*) t FROM citas
                WHERE ecografista_id = ? AND fecha_cita BETWEEN ? AND ?
                  AND estado IN ('confirmada','completada','reprogramada') GROUP BY h");
        $st->bind_param('iss', $ecografistaId, $ini, $fin);
        $st->execute();
        $res = $st->get_result();
        while ($f = $res->fetch_assoc()) { $h = (int)$f['h']; if (isset($map[$h])) { $map[$h] = (int)$f['t']; } }
        $st->close();
        return [
            'labels' => array_map(fn($h) => sprintf('%02d:00', $h), array_keys($map)),
            'data'   => array_values($map),
        ];
    }
}

if (!function_exists('eco_reporte_eco_edad')) {
    /**
     * Distribución por grupo de edad de los pacientes con citas en el rango.
     * @return array{labels:string[],data:int[]}
     */
    function eco_reporte_eco_edad(mysqli $conex, int $ecografistaId, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $b = ['0-17' => 0, '18-29' => 0, '30-44' => 0, '45-59' => 0, '60+' => 0, 'Sin dato' => 0];
        $st = $conex->prepare("SELECT TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) age
                FROM usuarios u
                WHERE u.id IN (SELECT DISTINCT paciente_id FROM citas
                               WHERE ecografista_id = ? AND fecha_cita BETWEEN ? AND ?)");
        $st->bind_param('iss', $ecografistaId, $ini, $fin);
        $st->execute();
        $res = $st->get_result();
        while ($f = $res->fetch_assoc()) {
            $a = $f['age'];
            if ($a === null || $a === '') { $b['Sin dato']++; continue; }
            $a = (int)$a;
            if ($a < 18)      $b['0-17']++;
            elseif ($a < 30)  $b['18-29']++;
            elseif ($a < 45)  $b['30-44']++;
            elseif ($a < 60)  $b['45-59']++;
            else              $b['60+']++;
        }
        $st->close();
        return ['labels' => array_keys($b), 'data' => array_values($b)];
    }
}

if (!function_exists('eco_reporte_eco_direccion')) {
    /**
     * Top direcciones de los pacientes con citas en el rango (texto libre;
     * vacías agrupadas como 'Sin dirección'). @return array{labels:string[],data:int[]}
     */
    function eco_reporte_eco_direccion(mysqli $conex, int $ecografistaId, string $desde, string $hasta, int $limit = 10): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $limit = max(1, min($limit, 30));
        $st = $conex->prepare("SELECT COALESCE(NULLIF(TRIM(u.direccion), ''), 'Sin dirección') AS dir, COUNT(*) AS n
                FROM usuarios u
                WHERE u.id IN (SELECT DISTINCT paciente_id FROM citas
                               WHERE ecografista_id = ? AND fecha_cita BETWEEN ? AND ?)
                GROUP BY dir
                ORDER BY n DESC, dir ASC
                LIMIT ?");
        $st->bind_param('issi', $ecografistaId, $ini, $fin, $limit);
        $st->execute();
        $res = $st->get_result();
        $labels = []; $data = [];
        while ($f = $res->fetch_assoc()) { $labels[] = $f['dir']; $data[] = (int)$f['n']; }
        $st->close();
        return ['labels' => $labels, 'data' => $data];
    }
}

if (!function_exists('eco_reporte_eco_pacientes_nuevos')) {
    /**
     * Pacientes registrados por el ecografista en los últimos $n meses.
     * @return array{labels:string[],data:int[]}
     */
    function eco_reporte_eco_pacientes_nuevos(mysqli $conex, int $ecografistaId, int $n = 6): array
    {
        $n = max(2, min($n, 24));
        $meses_es = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
        $labels = []; $map = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $d = new DateTime("first day of -$i month");
            $labels[] = $meses_es[(int)$d->format('n')] . ' ' . $d->format('y');
            $map[$d->format('Y-m')] = 0;
        }
        $desde = (new DateTime('first day of -' . ($n - 1) . ' month'))->format('Y-m-d') . ' 00:00:00';
        $st = $conex->prepare("SELECT DATE_FORMAT(fecha_registro, '%Y-%m') m, COUNT(*) t FROM usuarios
                WHERE creado_por_id = ? AND fecha_registro >= ? GROUP BY m");
        $st->bind_param('is', $ecografistaId, $desde);
        $st->execute();
        $res = $st->get_result();
        while ($f = $res->fetch_assoc()) { if (isset($map[$f['m']])) { $map[$f['m']] = (int)$f['t']; } }
        $st->close();
        return ['labels' => $labels, 'data' => array_values($map)];
    }
}
