<?php
/**
 * Helpers para renderizar y validar formularios dinámicos de informes ecográficos.
 *
 * Tipos de campo soportados:
 *   text | textarea | number | date | select | radio | checkbox | radio_sinno
 *
 * Tipos de sección soportados:
 *   normal (default) | par  (dos columnas paralelas, ej: riñón der / izq)
 *
 * Estructura del esquema:
 *   { "version":1, "secciones":[{ "id","titulo","icono","tipo_seccion","lados",
 *                                  "ids_lados","campos":[{...}] }] }
 *
 * Estructura del JSON guardado (informes_estudios.datos_clinicos):
 *   { "<seccion_id>": { "<campo_nombre>": <valor> }, ... }
 */

if (!function_exists('eco_render_campo')) {
    /**
     * Renderiza el HTML de un solo campo del esquema.
     */
    function eco_render_campo(
        array  $campo,
        string $seccion_id,
               $valor       = null,
        bool   $solo_lectura = false
    ): string {
        $nombre        = $campo['nombre']        ?? '';
        $etiqueta      = $campo['etiqueta']      ?? $nombre;
        $tipo          = $campo['tipo']          ?? 'text';
        $requerido     = !empty($campo['requerido']);
        $placeholder   = $campo['placeholder']   ?? '';
        $unidad        = $campo['unidad']        ?? '';
        $filas         = (int)($campo['filas']   ?? 3);
        $opciones      = $campo['opciones']      ?? [];
        $ancho         = $campo['ancho']         ?? 'completo';
        $depende_de    = $campo['depende_de']    ?? null;
        $depende_valor = $campo['depende_valor'] ?? 'SI';
        $campo_readonly = !empty($campo['readonly']);

        $clase_ancho = 'campo-' . htmlspecialchars($ancho);
        $req_attr    = $requerido ? 'required' : '';
        $req_mark    = $requerido ? ' <span class="campo-req">*</span>' : '';
        $input_name  = 'campo[' . htmlspecialchars($seccion_id) . '][' . htmlspecialchars($nombre) . ']';
        $valor_html  = htmlspecialchars((string)($valor ?? ''));
        $unidad_html = $unidad
            ? '<span class="campo-unidad">' . htmlspecialchars($unidad) . '</span>'
            : '';

        $label = '<label>' . htmlspecialchars($etiqueta) . $req_mark . '</label>';

        $ro_attr  = $campo_readonly ? ' readonly tabindex="-1"' : '';
        $ro_class = $campo_readonly ? ' campo-readonly' : '';

        /* ── Atributos de campo condicional ── */
        $dep_attrs  = '';
        $dep_class  = '';
        $dep_style  = '';
        if ($depende_de !== null) {
            $dep_input_name = 'campo[' . htmlspecialchars($seccion_id) . '][' . htmlspecialchars($depende_de) . ']';
            $dep_attrs = ' data-depende-de="' . htmlspecialchars($dep_input_name) . '"'
                       . ' data-depende-valor="' . htmlspecialchars($depende_valor) . '"';
            $dep_class = ' campo-condicional';
            /* Oculto por defecto; el JS lo mostrará si el valor actual coincide */
            $dep_style = ' style="display:none;"';
            /* Si ya hay un valor guardado que activa este campo, mostrarlo */
            if ($valor !== null && $valor !== '') {
                $dep_style = '';
            }
        }

        /* ── Modo solo lectura ── */
        if ($solo_lectura) {
            $vista = match ($tipo) {
                'checkbox'    => (!empty($valor) ? 'SI' : 'NO'),
                'radio_sinno' => ($valor ?: '—'),
                default       => nl2br($valor_html),
            };
            $extra = ($unidad && $vista !== '—') ? ' <small style="color:#888;">' . htmlspecialchars($unidad) . '</small>' : '';
            return '<div class="form-group ' . $clase_ancho . $dep_class . '"'
                . $dep_attrs . $dep_style . '>'
                . $label
                . '<p class="campo-valor">'
                . ($vista !== '' ? $vista . $extra : '<em style="color:#bbb;">—</em>')
                . '</p></div>';
        }

        /* ── Sub-encabezado decorativo (no es input, no se guarda) ── */
        if ($tipo === 'info') {
            $texto = $campo['texto'] ?? $etiqueta;
            return '<div class="form-group ' . $clase_ancho . '" style="margin:6px 0 -4px;">'
                . '<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:linear-gradient(90deg,#e0f5fe 0%,#f8fafc 100%);border-left:3px solid #02b1f4;border-radius:6px;font-size:12.5px;font-weight:700;color:#0284c7;letter-spacing:.3px;">'
                . '<i class="fa-solid fa-chevron-right" style="font-size:10px;opacity:.6;"></i>'
                . htmlspecialchars($texto)
                . '</div></div>';
        }

        /* ── Inputs editables ── */
        switch ($tipo) {

            case 'textarea':
                $input = '<textarea name="' . $input_name . '" rows="' . $filas
                    . '" placeholder="' . htmlspecialchars($placeholder) . '" '
                    . $req_attr . $ro_attr . '>' . $valor_html . '</textarea>';
                break;

            case 'select':
                $opts = '<option value="">— Selecciona —</option>';
                foreach ((array)$opciones as $op) {
                    $sel   = ((string)$valor === (string)$op) ? 'selected' : '';
                    $opts .= '<option value="' . htmlspecialchars($op) . '" ' . $sel . '>'
                        . htmlspecialchars($op) . '</option>';
                }
                $input = '<select name="' . $input_name . '" ' . $req_attr . '>' . $opts . '</select>';
                break;

            case 'radio':
                $input = '<div class="radio-group">';
                foreach ((array)$opciones as $op) {
                    $chk   = ((string)$valor === (string)$op) ? 'checked' : '';
                    $input .= '<label class="radio-label">'
                        . '<input type="radio" name="' . $input_name . '" value="'
                        . htmlspecialchars($op) . '" ' . $chk . ' ' . $req_attr . '> '
                        . htmlspecialchars($op) . '</label>';
                }
                $input .= '</div>';
                break;

            /* SI / NO como botones pill compactos */
            case 'radio_sinno':
                $si_chk = ($valor === 'SI') ? 'checked' : '';
                $no_chk = ($valor === 'NO') ? 'checked' : '';
                $input  = '<div class="sinno-group">'
                    . '<label class="sinno-btn sinno-si">'
                    . '<input type="radio" name="' . $input_name . '" value="SI" ' . $si_chk . ' ' . $req_attr . '> SI</label>'
                    . '<label class="sinno-btn sinno-no">'
                    . '<input type="radio" name="' . $input_name . '" value="NO" ' . $no_chk . '> NO</label>'
                    . '</div>';
                break;

            case 'checkbox':
                $chk   = !empty($valor) ? 'checked' : '';
                $input = '<label class="checkbox-label">'
                    . '<input type="checkbox" name="' . $input_name . '" value="1" ' . $chk . '> '
                    . htmlspecialchars($placeholder ?: 'Sí') . '</label>';
                break;

            case 'date':
                $input = '<input type="date" name="' . $input_name . '" value="'
                    . $valor_html . '" ' . $req_attr . $ro_attr . '>';
                break;

            case 'number':
                $step  = isset($campo['paso']) ? ' step="' . htmlspecialchars((string)$campo['paso']) . '"' : ' step="any"';
                $min   = isset($campo['min'])  ? ' min="'  . htmlspecialchars((string)$campo['min'])  . '"' : '';
                $input = '<div class="input-con-unidad">'
                    . '<input type="number"' . $step . $min
                    . ' name="' . $input_name . '"'
                    . ' value="' . $valor_html . '"'
                    . ' placeholder="' . htmlspecialchars($placeholder) . '" '
                    . $req_attr . $ro_attr . '>'
                    . $unidad_html
                    . '</div>';
                break;

            case 'text':
            default:
                $input = '<div class="input-con-unidad">'
                    . '<input type="text" name="' . $input_name . '"'
                    . ' value="' . $valor_html . '"'
                    . ' placeholder="' . htmlspecialchars($placeholder) . '" '
                    . $req_attr . $ro_attr . '>'
                    . $unidad_html
                    . '</div>';
                break;
        }

        return '<div class="form-group ' . $clase_ancho . $dep_class . $ro_class . '"'
            . $dep_attrs . $dep_style . '>'
            . $label . $input . '</div>';
    }
}

if (!function_exists('eco_render_formulario')) {
    /**
     * Renderiza el formulario completo (todas las secciones).
     * No incluye <form> ni botones.
     */
    function eco_render_formulario(
        array $esquema,
        array $datos_previos = [],
        bool  $solo_lectura  = false
    ): string {
        $out = [];

        foreach (($esquema['secciones'] ?? []) as $seccion) {
            $sid      = $seccion['id']           ?? '';
            $titulo   = $seccion['titulo']       ?? '';
            $icono    = $seccion['icono']        ?? '';
            $campos   = $seccion['campos']       ?? [];
            $tipo_sec = $seccion['tipo_seccion'] ?? 'normal';

            $icono_html = $icono
                ? '<i class="' . htmlspecialchars($icono) . '"></i> '
                : '';

            $subtitulo    = $seccion['subtitulo'] ?? '';
            $subtitulo_html = $subtitulo
                ? '<p style="margin:5px 0 0;font-size:12px;color:#0369a1;font-style:italic;line-height:1.4;">'
                  . '<i class="fa-solid fa-circle-info" style="margin-right:5px;opacity:.7;"></i>'
                  . htmlspecialchars($subtitulo) . '</p>'
                : '';

            $out[] = '<div class="form-seccion">';
            $out[] = '<div class="form-seccion-header">'
                . '<h3>' . $icono_html . htmlspecialchars($titulo) . '</h3>'
                . $subtitulo_html
                . '</div>';
            $out[] = '<div class="form-seccion-body">';

            if ($tipo_sec === 'par') {
                /* Dos columnas paralelas (Riñón Derecho / Riñón Izquierdo) */
                $lados     = $seccion['lados'] ?? ['A', 'B'];
                $ids_lados = $seccion['ids_lados'] ?? array_map(
                    static function ($l) use ($sid) {
                        return $sid . '_' . strtolower((string)preg_replace('/\s+/', '_', $l));
                    },
                    $lados
                );

                $par = ['<div class="seccion-par-grid">'];
                foreach ($lados as $idx => $lado) {
                    $sid_lado     = $ids_lados[$idx] ?? ($sid . '_' . $idx);
                    $valores_lado = $datos_previos[$sid_lado] ?? [];

                    $par[] = '<div class="seccion-par-col">';
                    $par[] = '<div class="seccion-par-col-titulo">'
                        . htmlspecialchars((string)$lado) . '</div>';
                    $par[] = '<div class="form-grid form-grid-par">';
                    foreach ($campos as $campo) {
                        $valor = $valores_lado[$campo['nombre'] ?? ''] ?? null;
                        $par[] = eco_render_campo($campo, $sid_lado, $valor, $solo_lectura);
                    }
                    $par[] = '</div></div>';
                }
                $par[] = '</div>';
                $out[] = implode('', $par);

            } else {
                /* Sección estándar */
                $valores_seccion = $datos_previos[$sid] ?? [];
                $norm            = ['<div class="form-grid">'];
                foreach ($campos as $campo) {
                    $valor = $valores_seccion[$campo['nombre'] ?? ''] ?? null;
                    $norm[] = eco_render_campo($campo, $sid, $valor, $solo_lectura);
                }
                $norm[] = '</div>';
                $out[] = implode('', $norm);
            }

            $out[] = '</div></div>';
        }

        return implode('', $out);
    }
}

if (!function_exists('eco_validar_datos')) {
    /**
     * Valida campos requeridos y normaliza los valores del POST.
     * Maneja tanto secciones normales como secciones "par".
     *
     * @return array ['errores'=>string[], 'datos'=>array]
     */
    function eco_validar_datos(array $esquema, array $datos_post): array
    {
        $errores      = [];
        $datos_limpios = [];

        foreach (($esquema['secciones'] ?? []) as $seccion) {
            $sid      = $seccion['id']          ?? '';
            $campos   = $seccion['campos']       ?? [];
            $tipo_sec = $seccion['tipo_seccion'] ?? 'normal';

            $procesar_lado = function (string $sid_actual) use (
                $campos, $datos_post, &$datos_limpios, &$errores
            ): void {
                $datos_limpios[$sid_actual] = [];
                $valores = $datos_post[$sid_actual] ?? [];

                foreach ($campos as $campo) {
                    $nombre    = $campo['nombre']   ?? '';
                    $etiqueta  = $campo['etiqueta'] ?? $nombre;
                    $tipo      = $campo['tipo']     ?? 'text';
                    $requerido = !empty($campo['requerido']);
                    $valor_in  = $valores[$nombre]  ?? null;

                    /* Los tipos decorativos (info) no se guardan ni validan */
                    if ($tipo === 'info') {
                        continue;
                    }

                    if ($tipo === 'checkbox') {
                        $valor_clean = !empty($valor_in) ? 1 : 0;
                    } elseif (is_string($valor_in)) {
                        $valor_clean = trim($valor_in);
                    } else {
                        $valor_clean = $valor_in;
                    }

                    if ($requerido && ($valor_clean === '' || $valor_clean === null)) {
                        $errores[] = 'El campo "' . $etiqueta . '" es obligatorio.';
                    }

                    $datos_limpios[$sid_actual][$nombre] = $valor_clean;
                }
            };

            if ($tipo_sec === 'par') {
                $ids_lados = $seccion['ids_lados'] ?? [$sid . '_a', $sid . '_b'];
                foreach ($ids_lados as $sid_lado) {
                    $procesar_lado($sid_lado);
                }
            } else {
                $procesar_lado($sid);
            }
        }

        return ['errores' => $errores, 'datos' => $datos_limpios];
    }
}
