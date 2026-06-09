<?php
/**
 * Helpers del nucleo clinico (informes_estudios): numeracion correlativa,
 * transiciones de estado y reglas de permisos.
 */

if (!function_exists('eco_siguiente_numero_informe')) {
    /**
     * Devuelve el siguiente numero de informe correlativo del anio en curso,
     * con formato INF-AAAA-NNNNN. Atomico y libre de carreras: usa el contador
     * por-conexion LAST_INSERT_ID(). Debe llamarse dentro de la transaccion que
     * inserta/finaliza el informe para no "quemar" numeros si algo falla.
     */
    function eco_siguiente_numero_informe(mysqli $conex): string
    {
        $anio  = (int)date('Y');
        $clave = 'informe_' . $anio;

        $stmt = $conex->prepare(
            "INSERT INTO contadores (clave, valor) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE valor = LAST_INSERT_ID(valor + 1)"
        );
        $stmt->bind_param('s', $clave);
        $stmt->execute();
        $stmt->close();

        $seq = (int)$conex->insert_id;
        return sprintf('INF-%d-%05d', $anio, $seq);
    }
}

if (!function_exists('eco_informe_estado_label')) {
    /** Etiqueta legible de un estado de informe. */
    function eco_informe_estado_label(string $estado): string
    {
        return [
            'borrador'   => 'Borrador',
            'finalizado' => 'Finalizado',
            'firmado'    => 'Firmado',
            'anulado'    => 'Anulado',
        ][$estado] ?? ucfirst($estado);
    }
}

if (!function_exists('eco_puede_gestionar_informe')) {
    /**
     * Reglas de autoria: un administrador puede gestionar cualquier informe;
     * un ecografista solo los que el creo.
     */
    function eco_puede_gestionar_informe(string $rol, int $usuarioId, int $ecografistaIdInforme): bool
    {
        if ($rol === 'administrador') {
            return true;
        }
        return $rol === 'ecografista' && $usuarioId === $ecografistaIdInforme;
    }
}
