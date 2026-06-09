<?php
/**
 * lib/notificaciones.php — Fase 4 (A): notificaciones in-app.
 *
 * Bandeja por usuario (tabla notificaciones). `eco_notificar()` la usan los
 * endpoints del ciclo de vida de la cita / informe; la campana del topbar
 * consulta y marca como leidas.
 *
 * DEGRADA EN SILENCIO: si la tabla no existe o la query falla, no rompe el
 * flujo principal (igual que auditoria / cita_eventos).
 *
 * Migracion: database/migrations/2026_fase4_01_notificaciones.sql
 */

if (!function_exists('eco_notificar')) {

    /**
     * Crea una notificacion para un usuario. Devuelve el id (0 si no se pudo).
     *
     * @param array $opts mensaje, url, icono (clase Font Awesome)
     */
    function eco_notificar(mysqli $conex, int $usuarioId, string $tipo, string $titulo, array $opts = []): int
    {
        if ($usuarioId <= 0 || trim($titulo) === '') {
            return 0;
        }
        $tipo    = substr($tipo, 0, 40);
        $titulo  = substr($titulo, 0, 140);
        $mensaje = isset($opts['mensaje']) && $opts['mensaje'] !== '' ? substr((string)$opts['mensaje'], 0, 400) : null;
        $url     = isset($opts['url'])     && $opts['url'] !== ''     ? substr((string)$opts['url'], 0, 255)     : null;
        $icono   = isset($opts['icono'])   && $opts['icono'] !== ''   ? substr((string)$opts['icono'], 0, 40)    : null;

        $sql = "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, url, icono)
                VALUES (?, ?, ?, ?, ?, ?)";
        if (!($st = @$conex->prepare($sql))) {
            return 0;
        }
        $st->bind_param('isssss', $usuarioId, $tipo, $titulo, $mensaje, $url, $icono);
        @$st->execute();
        $id = (int)$st->insert_id;
        $st->close();
        return $id;
    }

    /** Numero de notificaciones sin leer de un usuario. */
    function eco_notificaciones_no_leidas(mysqli $conex, int $usuarioId): int
    {
        if ($usuarioId <= 0) return 0;
        if (!($st = @$conex->prepare("SELECT COUNT(*) c FROM notificaciones WHERE usuario_id = ? AND leida = 0"))) {
            return 0;
        }
        $st->bind_param('i', $usuarioId);
        if (!@$st->execute()) { $st->close(); return 0; }
        $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();
        return $c;
    }

    /** Lista las notificaciones recientes de un usuario (mas nuevas primero). */
    function eco_notificaciones_listar(mysqli $conex, int $usuarioId, int $limit = 15): array
    {
        $out = [];
        if ($usuarioId <= 0) return $out;
        $limit = max(1, min($limit, 50));
        // hace_seg se calcula con el reloj de la BD (TIMESTAMPDIFF) para evitar
        // desfases de timezone entre PHP y MySQL.
        $sql = "SELECT id, tipo, titulo, mensaje, url, icono, leida, creado_en,
                       TIMESTAMPDIFF(SECOND, creado_en, NOW()) AS hace_seg
                FROM notificaciones WHERE usuario_id = ?
                ORDER BY creado_en DESC, id DESC LIMIT ?";
        if (!($st = @$conex->prepare($sql))) return $out;
        $st->bind_param('ii', $usuarioId, $limit);
        if (!@$st->execute()) { $st->close(); return $out; }
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) { $out[] = $r; }
        $st->close();
        return $out;
    }

    /** Marca una notificacion como leida (solo si es del usuario). */
    function eco_notificacion_marcar(mysqli $conex, int $id, int $usuarioId): bool
    {
        if ($id <= 0 || $usuarioId <= 0) return false;
        if (!($st = @$conex->prepare(
            "UPDATE notificaciones SET leida = 1, leida_en = NOW() WHERE id = ? AND usuario_id = ? AND leida = 0"
        ))) {
            return false;
        }
        $st->bind_param('ii', $id, $usuarioId);
        $ok = @$st->execute();
        $st->close();
        return (bool)$ok;
    }

    /** Marca todas las del usuario como leidas. Devuelve cuantas cambio. */
    function eco_notificaciones_marcar_todas(mysqli $conex, int $usuarioId): int
    {
        if ($usuarioId <= 0) return 0;
        if (!($st = @$conex->prepare(
            "UPDATE notificaciones SET leida = 1, leida_en = NOW() WHERE usuario_id = ? AND leida = 0"
        ))) {
            return 0;
        }
        $st->bind_param('i', $usuarioId);
        @$st->execute();
        $n = $st->affected_rows;
        $st->close();
        return (int)$n;
    }

    /** Texto relativo "hace X" a partir de segundos transcurridos (reloj BD). */
    function eco_hace_seg(int $d): string
    {
        if ($d < 0)      $d = 0;
        if ($d < 60)     return 'hace un momento';
        if ($d < 3600)   return 'hace ' . floor($d / 60) . ' min';
        if ($d < 86400)  return 'hace ' . floor($d / 3600) . ' h';
        if ($d < 604800) return 'hace ' . floor($d / 86400) . ' d';
        return 'hace ' . floor($d / 604800) . ' sem';
    }
}
