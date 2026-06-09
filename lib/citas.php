<?php
/**
 * lib/citas.php — Fase 2: bitacora de eventos del ciclo de vida de una cita.
 *
 * Tabla: cita_eventos (migracion database/migrations/2026_fase2_03_cita_eventos.sql).
 * Append-only. Cada transicion de la cita (solicitada, confirmada, reprogramada,
 * aceptada, completada, cancelada, pago...) se registra como una fila.
 *
 * Requiere una conexion mysqli activa ($conex). DEGRADA EN SILENCIO: si la tabla
 * no existe o la query falla, no lanza ni rompe el flujo principal de la cita.
 */

if (!function_exists('eco_cita_evento')) {

    /**
     * Registra un evento en la linea de tiempo de una cita.
     *
     * @param mysqli $conex
     * @param int    $citaId
     * @param string $tipo   verbo corto: 'solicitada','confirmada','reprogramada',
     *                       'propuesta','aceptada','rechazada','completada','cancelada','pago_registrado'
     * @param array  $opts   estado_anterior, estado_nuevo, actor_id, actor_rol, detalle (string|array)
     */
    function eco_cita_evento(mysqli $conex, int $citaId, string $tipo, array $opts = []): void
    {
        if ($citaId <= 0 || $tipo === '') {
            return;
        }
        $tipo      = substr($tipo, 0, 40);
        $estadoAnt = isset($opts['estado_anterior']) ? substr((string)$opts['estado_anterior'], 0, 30) : null;
        $estadoNue = isset($opts['estado_nuevo'])    ? substr((string)$opts['estado_nuevo'], 0, 30)    : null;

        $actorId = $opts['actor_id'] ?? ($_SESSION['usuario_id'] ?? null);
        $actorId = ($actorId !== null && $actorId !== '') ? (int)$actorId : null;
        $actorRol = $opts['actor_rol'] ?? ($_SESSION['rol'] ?? null);
        $actorRol = ($actorRol !== null) ? substr((string)$actorRol, 0, 20) : null;

        $detalle = $opts['detalle'] ?? null;
        if (is_array($detalle)) {
            $detalle = json_encode($detalle, JSON_UNESCAPED_UNICODE);
        }

        $sql = "INSERT INTO cita_eventos (cita_id, tipo, estado_anterior, estado_nuevo, actor_id, actor_rol, detalle)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        if (!($st = @$conex->prepare($sql))) {
            return;
        }
        $st->bind_param('isssiss', $citaId, $tipo, $estadoAnt, $estadoNue, $actorId, $actorRol, $detalle);
        @$st->execute();
        $st->close();
    }

    /**
     * Devuelve los eventos de una cita en orden cronologico (para timeline en la ficha).
     *
     * @return array<int,array<string,mixed>>
     */
    function eco_cita_eventos(mysqli $conex, int $citaId): array
    {
        $out = [];
        if ($citaId <= 0) {
            return $out;
        }
        $sql = "SELECT ce.id, ce.tipo, ce.estado_anterior, ce.estado_nuevo, ce.actor_id, ce.actor_rol,
                       ce.detalle, ce.creado_en, u.nombre_completo AS actor_nombre
                FROM cita_eventos ce
                LEFT JOIN usuarios u ON u.id = ce.actor_id
                WHERE ce.cita_id = ?
                ORDER BY ce.creado_en ASC, ce.id ASC";
        if (!($st = @$conex->prepare($sql))) {
            return $out;
        }
        $st->bind_param('i', $citaId);
        if (!@$st->execute()) {
            $st->close();
            return $out;
        }
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        $st->close();
        return $out;
    }

    /** Etiqueta + icono + color de un tipo de evento, para la linea de tiempo. */
    function eco_cita_evento_label(string $tipo): array
    {
        $map = [
            'solicitada'      => ['Cita solicitada',    'fa-solid fa-paper-plane',     '#0284c7'],
            'creada'          => ['Cita creada',        'fa-solid fa-calendar-plus',   '#0284c7'],
            'confirmada'      => ['Cita confirmada',    'fa-solid fa-calendar-check',  '#15803d'],
            'reprogramada'    => ['Cita reprogramada',  'fa-solid fa-calendar-pen',    '#b45309'],
            'propuesta'       => ['Propuesta de fecha', 'fa-solid fa-clock',           '#b45309'],
            'aceptada'        => ['Propuesta aceptada', 'fa-solid fa-check',           '#15803d'],
            'rechazada'       => ['Propuesta rechazada','fa-solid fa-xmark',           '#b91c1c'],
            'completada'      => ['Estudio completado', 'fa-solid fa-clipboard-check', '#6d28d9'],
            'cancelada'       => ['Cita cancelada',     'fa-solid fa-calendar-xmark',  '#b91c1c'],
            'pago_registrado' => ['Pago registrado',    'fa-solid fa-money-bill-wave', '#15803d'],
        ];
        return $map[$tipo] ?? [ucfirst(str_replace('_', ' ', $tipo)), 'fa-solid fa-circle', '#64748b'];
    }

    /**
     * Construye el HTML de la linea de tiempo a partir de los eventos
     * (salida de eco_cita_eventos). Sin datos -> mensaje vacio.
     */
    function eco_cita_timeline_html(array $eventos): string
    {
        if (empty($eventos)) {
            return '<div class="eco-tl-empty"><i class="fa-regular fa-clock"></i> Sin eventos registrados todavía.</div>';
        }
        $h = htmlspecialchars(...);
        $out = '<ol class="eco-tl">';
        foreach ($eventos as $ev) {
            [$label, $icono, $color] = eco_cita_evento_label((string)$ev['tipo']);
            $actor = trim((string)($ev['actor_nombre'] ?? ''));
            $rol   = trim((string)($ev['actor_rol'] ?? ''));
            $quien = $actor !== '' ? $actor : 'Sistema';
            if ($rol !== '') $quien .= ' · ' . ucfirst($rol);
            $fecha = !empty($ev['creado_en']) ? date('d/m/Y H:i', strtotime((string)$ev['creado_en'])) : '';

            $extra = '';
            if (!empty($ev['estado_anterior']) && !empty($ev['estado_nuevo'])
                && $ev['estado_anterior'] !== $ev['estado_nuevo']) {
                $extra = ' <span class="eco-tl-chg">' . $h((string)$ev['estado_anterior'])
                    . ' → ' . $h((string)$ev['estado_nuevo']) . '</span>';
            }

            $out .= '<li class="eco-tl-i">'
                . '<span class="eco-tl-dot" style="--c:' . $h($color) . ';"><i class="' . $h($icono) . '"></i></span>'
                . '<div class="eco-tl-c">'
                . '<div class="eco-tl-t">' . $h($label) . $extra . '</div>'
                . '<div class="eco-tl-m">' . $h($quien) . '</div>'
                . ($fecha !== '' ? '<time class="eco-tl-d">' . $h($fecha) . '</time>' : '')
                . '</div></li>';
        }
        $out .= '</ol>';
        return $out;
    }
}
