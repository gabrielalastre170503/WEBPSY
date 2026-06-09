<?php
/**
 * Cabecera de tabla ordenable (recepción / ecografista).
 */
function eco_sort_th(string $label, int $col, string $type): string
{
    $typeAttr = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    return '<th class="rx-sort-th" data-sort-col="' . $col . '" data-sort-type="' . $typeAttr . '" role="columnheader" tabindex="0" aria-sort="none">'
        . '<span class="rx-sort-th__inner">'
        . '<span class="rx-sort-th__label">' . htmlspecialchars($label) . '</span>'
        . '<span class="rx-sort-icons" aria-hidden="true">'
        . '<i class="fa-solid fa-caret-up rx-sort-icon rx-sort-up"></i>'
        . '<i class="fa-solid fa-caret-down rx-sort-icon rx-sort-down"></i>'
        . '</span></span></th>';
}
