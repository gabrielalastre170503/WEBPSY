<?php
/**
 * lib/catalogo.php — Fase 5: cache de catalogos de referencia (tipos_ecografias).
 *
 * El catalogo de tipos de ecografia cambia muy poco pero se lee en muchas vistas
 * (panel, modales, dropdowns). Antes cada vista lanzaba 1-4 queries identicas.
 * Aqui se lee UNA sola vez por request (memoizacion estatica) y todas las vistas
 * derivadas (menu por categoria, dropdown plano, lookup por id) se calculan en PHP.
 *
 * Sin cache cross-request a proposito: precio vive en esta tabla y un cache con TTL
 * arriesgaria mostrar precios viejos. La memoizacion por-request es 0% stale.
 *
 * Requiere una conexion mysqli activa ($conex).
 */

if (!function_exists('eco_catalogo_tipos_all')) {

    /**
     * Todos los tipos de ecografia, leidos una sola vez por request.
     * Orden base: posicion, nombre (replica el ORDER BY historico de las vistas).
     *
     * @return array<int,array<string,mixed>>  filas con id, codigo, nombre, categoria,
     *                                          descripcion, icono, precio, posicion, activo
     */
    function eco_catalogo_tipos_all(mysqli $conex, bool $soloActivos = false): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $sql = "SELECT id, codigo, nombre, categoria, descripcion, icono, precio, posicion, activo
                    FROM tipos_ecografias
                    ORDER BY posicion, nombre";
            if ($res = @$conex->query($sql)) {
                while ($row = $res->fetch_assoc()) {
                    $cache[] = $row;
                }
                $res->free();
            }
        }
        if (!$soloActivos) {
            return $cache;
        }
        return array_values(array_filter($cache, static fn($r) => (int)$r['activo'] === 1));
    }

    /**
     * Tipos activos agrupados para el menu de seleccion: principales + 3 sub-categorias.
     * Mismo shape que arman panel.php y modal_gestionar_paciente_ecografista.php.
     *
     * @return array{principales:array,musculo:array,obstetrica:array,partes_blandas:array}
     */
    function eco_catalogo_tipos_menu(mysqli $conex): array
    {
        $out = ['principales' => [], 'musculo' => [], 'obstetrica' => [], 'partes_blandas' => []];
        foreach (eco_catalogo_tipos_all($conex, true) as $row) {
            switch ($row['categoria'] ?? null) {
                case 'Musculoesqueletica_Sub': $out['musculo'][] = $row;        break;
                case 'Obstetrica_Sub':         $out['obstetrica'][] = $row;     break;
                case 'Partes_Blandas_Sub':     $out['partes_blandas'][] = $row; break;
                default:                       $out['principales'][] = $row;    break;
            }
        }
        return $out;
    }

    /**
     * Lista plana de tipos activos ordenada por categoria, nombre (para dropdowns).
     *
     * @return array<int,array<string,mixed>>
     */
    function eco_catalogo_tipos_activos(mysqli $conex): array
    {
        $rows = eco_catalogo_tipos_all($conex, true);
        usort($rows, static function ($a, $b) {
            return [(string)($a['categoria'] ?? ''), (string)$a['nombre']]
               <=> [(string)($b['categoria'] ?? ''), (string)$b['nombre']];
        });
        return $rows;
    }

    /**
     * Un tipo por id desde el cache (null si no existe). Evita un SELECT extra.
     */
    function eco_catalogo_tipo(mysqli $conex, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        foreach (eco_catalogo_tipos_all($conex) as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }
}
