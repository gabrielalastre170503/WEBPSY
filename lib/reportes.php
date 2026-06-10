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
                    SUM(estado_pago = 'exonerado') AS exonerados
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
