<?php
/**
 * lib/seguridad/retencion.php — Política de retención: purga de datos EFÍMEROS de seguridad.
 *
 * Minimización de datos (cumplimiento): elimina periódicamente datos técnicos que
 * ya no son necesarios. NO toca datos clínicos, informes, citas, pacientes ni la
 * bitácora de auditoría (esos se conservan según la normativa sanitaria).
 *
 * Alcance:
 *   - intentos_login : registros de throttling más antiguos que N días.
 *   - descarga_tokens: enlaces temporales caducados hace más de N días.
 */

if (!function_exists('eco_retencion_purgar')) {

    /**
     * @return array{dry_run:bool,intentos_login:int,descarga_tokens:int}
     */
    function eco_retencion_purgar(mysqli $conex, bool $dryRun = true, int $diasIntentos = 90, int $diasTokens = 30): array
    {
        $diasIntentos = max(7, $diasIntentos);
        $diasTokens   = max(1, $diasTokens);

        return [
            'dry_run'         => $dryRun,
            'intentos_login'  => eco_retencion_op($conex, $dryRun, 'intentos_login',
                'creado_en < (NOW() - INTERVAL ? DAY)', $diasIntentos),
            'descarga_tokens' => eco_retencion_op($conex, $dryRun, 'descarga_tokens',
                'expira_en < (NOW() - INTERVAL ? DAY)', $diasTokens),
        ];
    }

    /** Cuenta (dry-run) o borra filas de $tabla que cumplen $cond (un parámetro ? = días). */
    function eco_retencion_op(mysqli $conex, bool $dryRun, string $tabla, string $cond, int $dias): int
    {
        // $tabla y $cond son literales del propio archivo (no entrada externa); $dias va bindeado.
        $sql = $dryRun
            ? "SELECT COUNT(*) AS n FROM `$tabla` WHERE $cond"
            : "DELETE FROM `$tabla` WHERE $cond";
        if (!($st = @$conex->prepare($sql))) {
            return 0;
        }
        $st->bind_param('i', $dias);
        if (!@$st->execute()) {
            $st->close();
            return 0;
        }
        $n = $dryRun ? (int)($st->get_result()->fetch_assoc()['n'] ?? 0) : $st->affected_rows;
        $st->close();
        return (int)$n;
    }
}
