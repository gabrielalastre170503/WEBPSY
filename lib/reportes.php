<?php
/**
 * lib/reportes.php — Inteligencia de negocio / reportes (Fase 6).
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
    function eco_reporte_resumen(mysqli $conex, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
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
                WHERE fecha_cita BETWEEN ? AND ?";
        $st = $conex->prepare($sql);
        $st->bind_param('ss', $ini, $fin);
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
    function eco_reporte_por_tipo(mysqli $conex, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $sql = "SELECT
                    COALESCE(t.nombre, 'Sin tipo') AS tipo,
                    COUNT(*) AS citas,
                    SUM(c.estado = 'completada') AS completadas,
                    COALESCE(SUM(c.monto_total), 0) AS facturado,
                    COALESCE(SUM(c.monto_pagado), 0) AS cobrado
                FROM citas c
                LEFT JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
                WHERE c.fecha_cita BETWEEN ? AND ?
                GROUP BY tipo
                ORDER BY citas DESC, tipo ASC";
        $st = $conex->prepare($sql);
        $st->bind_param('ss', $ini, $fin);
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
    function eco_reporte_por_metodo_pago(mysqli $conex, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $sql = "SELECT
                    COALESCE(NULLIF(metodo_pago, ''), 'Sin método') AS metodo,
                    COUNT(*) AS pagos,
                    COALESCE(SUM(monto_pagado), 0) AS cobrado
                FROM citas
                WHERE fecha_cita BETWEEN ? AND ? AND monto_pagado > 0
                GROUP BY metodo
                ORDER BY cobrado DESC, metodo ASC";
        $st = $conex->prepare($sql);
        $st->bind_param('ss', $ini, $fin);
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
    function eco_reporte_top_pacientes(mysqli $conex, string $desde, string $hasta, int $limit = 10): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $limit = max(1, min($limit, 50));
        $sql = "SELECT
                    u.nombre_completo AS paciente,
                    COUNT(*) AS citas,
                    COALESCE(SUM(c.monto_pagado), 0) AS cobrado
                FROM citas c
                JOIN usuarios u ON u.id = c.paciente_id
                WHERE c.fecha_cita BETWEEN ? AND ?
                GROUP BY c.paciente_id, paciente
                ORDER BY cobrado DESC, citas DESC
                LIMIT ?";
        $st = $conex->prepare($sql);
        $st->bind_param('ssi', $ini, $fin, $limit);
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
    function eco_reporte_comparativa_meses(mysqli $conex, int $n = 6): array
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

        $sql = "SELECT DATE_FORMAT(fecha_cita, '%Y-%m') AS mes,
                       COUNT(*) AS citas,
                       COALESCE(SUM(monto_pagado), 0) AS cobrado
                FROM citas
                WHERE fecha_cita >= ?
                GROUP BY mes";
        $st = $conex->prepare($sql);
        $st->bind_param('s', $desde);
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
    function eco_reporte_satisfaccion(mysqli $conex, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $sql = "SELECT COUNT(*) AS respuestas, COALESCE(AVG(e.puntuacion), 0) AS promedio
                FROM encuestas e
                JOIN citas c ON c.id = e.cita_id
                WHERE c.fecha_cita BETWEEN ? AND ?";
        $st = $conex->prepare($sql);
        $st->bind_param('ss', $ini, $fin);
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
    function eco_reporte_serie_diaria(mysqli $conex, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';
        $sql = "SELECT
                    DATE(fecha_cita) AS dia,
                    COUNT(*) AS citas,
                    COALESCE(SUM(monto_pagado), 0) AS cobrado
                FROM citas
                WHERE fecha_cita BETWEEN ? AND ?
                GROUP BY dia
                ORDER BY dia ASC";
        $st = $conex->prepare($sql);
        $st->bind_param('ss', $ini, $fin);
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
