<?php
/**
 * lib/core/paginacion.php — Fase 5: paginacion server-side simple para listados que crecen.
 *
 * Pensado para los fragmentos de busqueda AJAX (citas, pacientes) que devuelven una
 * tabla HTML completa. El endpoint:
 *   1. lee [page, perPage, offset] con eco_paginacion_args()
 *   2. cuenta el total (COUNT) con el MISMO WHERE
 *   3. agrega LIMIT ? OFFSET ? a la query de datos
 *   4. tras la tabla, hace echo eco_paginacion_html(...)
 *
 * El JS consumidor solo reenvia el fetch con &page=N al pulsar los botones del footer.
 */

if (!function_exists('eco_paginacion_args')) {

    /**
     * Lee page/per_page del request (POST o GET) y calcula el offset.
     *
     * @return array{0:int,1:int,2:int}  [page, perPage, offset]
     */
    function eco_paginacion_args(int $perPageDefault = 25, int $maxPerPage = 100): array
    {
        $page = (int)($_POST['page'] ?? $_GET['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }
        $perPage = (int)($_POST['per_page'] ?? $_GET['per_page'] ?? $perPageDefault);
        if ($perPage < 1) {
            $perPage = $perPageDefault;
        }
        if ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }
        $offset = ($page - 1) * $perPage;
        return [$page, $perPage, $offset];
    }

    /**
     * Footer de paginacion (anterior / info / siguiente). Los botones llevan data-page
     * para que el JS reenvie la busqueda. Devuelve '' si todo cabe en una sola pagina,
     * de modo que el caller siempre puede hacer echo sin condicionar.
     *
     * @param string $noun etiqueta plural, p. ej. 'citas', 'pacientes'.
     */
    function eco_paginacion_html(int $page, int $perPage, int $total, string $noun = 'resultados'): string
    {
        $pages = (int)max(1, (int)ceil($total / max(1, $perPage)));
        if ($pages <= 1) {
            return '';
        }
        if ($page > $pages) {
            $page = $pages;
        }
        $desde = ($page - 1) * $perPage + 1;
        $hasta = min($total, $page * $perPage);
        $noun  = htmlspecialchars($noun, ENT_QUOTES, 'UTF-8');
        $prevDis = $page <= 1 ? ' disabled' : '';
        $nextDis = $page >= $pages ? ' disabled' : '';

        return '<nav class="eco-pager" data-page="' . $page . '" data-pages="' . $pages
            . '" data-total="' . $total . '" aria-label="Paginación">'
            . '<button type="button" class="eco-pager__btn" data-page="' . ($page - 1) . '"' . $prevDis . '>'
            . '<i class="fa-solid fa-chevron-left"></i> Anterior</button>'
            . '<span class="eco-pager__info">' . $desde . '–' . $hasta . ' de ' . $total . ' ' . $noun . '</span>'
            . '<button type="button" class="eco-pager__btn" data-page="' . ($page + 1) . '"' . $nextDis . '>'
            . 'Siguiente <i class="fa-solid fa-chevron-right"></i></button>'
            . '</nav>';
    }
}
