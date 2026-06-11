<?php
/**
 * Datos agregados para vistas de administración (especialidades, etc.)
 */

if (!function_exists('eco_admin_build_especialidades_panel')) {
    /**
     * @return array{profesionales: array, resumen: array, catalogo: array, unique_total: int, with_specialty: int, without_specialty: int}
     */
    function eco_admin_build_especialidades_panel(mysqli $conex): array
    {
        $out = [
            'profesionales' => [],
            'resumen' => [],
            'catalogo' => [],
            'unique_total' => 0,
            'with_specialty' => 0,
            'without_specialty' => 0,
        ];

        $profesionales_stmt = $conex->query(
            "SELECT id, nombre_completo, correo, rol, estado,
                    (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', ')
                       FROM usuario_especialidades ue
                       JOIN especialidades e ON e.id = ue.especialidad_id
                      WHERE ue.usuario_id = usuarios.id) AS especialidades
             FROM usuarios WHERE rol IN ('ecografista') ORDER BY nombre_completo ASC"
        );
        if (!$profesionales_stmt) {
            return $out;
        }

        $uniqueEspecialidades = [];
        $specialtySummary = [];

        while ($profesional = $profesionales_stmt->fetch_assoc()) {
            $especialidadesTexto = trim((string)($profesional['especialidades'] ?? ''));
            $especialidadesLimpias = [];

            if ($especialidadesTexto !== '') {
                $segmentos = preg_split('/[,;]+/', $especialidadesTexto);
                foreach ($segmentos as $segmento) {
                    $especialidadLimpia = trim($segmento);
                    if ($especialidadLimpia === '') {
                        continue;
                    }
                    $especialidadesLimpias[] = $especialidadLimpia;
                    $claveEspecialidad = strtolower($especialidadLimpia);

                    if (!isset($uniqueEspecialidades[$claveEspecialidad])) {
                        $uniqueEspecialidades[$claveEspecialidad] = $especialidadLimpia;
                    }
                    if (!isset($specialtySummary[$claveEspecialidad])) {
                        $specialtySummary[$claveEspecialidad] = [
                            'nombre' => $especialidadLimpia,
                            'total' => 0,
                            'profesionales' => [],
                        ];
                    }
                    $specialtySummary[$claveEspecialidad]['total']++;
                    $specialtySummary[$claveEspecialidad]['profesionales'][] = $profesional['nombre_completo'];
                }
            }

            if (!empty($especialidadesLimpias)) {
                $out['with_specialty']++;
            } else {
                $out['without_specialty']++;
            }

            $profesional['especialidades_lista'] = $especialidadesLimpias;
            $profesional['especialidades_texto'] = $especialidadesTexto;
            $profesional['search_text'] = strtolower(
                $profesional['nombre_completo'] . ' ' . $profesional['rol'] . ' ' . $especialidadesTexto . ' ' . $profesional['correo']
            );
            $out['profesionales'][] = $profesional;
        }

        $out['unique_total'] = count($uniqueEspecialidades);
        $out['catalogo'] = array_values($uniqueEspecialidades);

        $resumenEspecialidades = array_values(array_map(function ($item) {
            $item['profesionales'] = array_values(array_unique($item['profesionales']));
            sort($item['profesionales'], SORT_NATURAL | SORT_FLAG_CASE);
            return $item;
        }, $specialtySummary));

        usort($resumenEspecialidades, function ($a, $b) {
            return strcasecmp($a['nombre'], $b['nombre']);
        });
        $out['resumen'] = $resumenEspecialidades;

        return $out;
    }
}
