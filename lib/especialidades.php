<?php
/**
 * Helpers de especialidades (modelo normalizado).
 *
 * Almacenamiento: tabla catalogo `especialidades` + puente `usuario_especialidades`.
 * El texto CSV historico (p. ej. "Abdominal, Obstetrica") se sigue aceptando en la
 * capa de presentacion: se parsea al guardar y se reconstruye con GROUP_CONCAT al leer.
 */

if (!function_exists('eco_especialidad_split')) {
    /**
     * Parte un texto CSV de especialidades en una lista limpia, sin duplicados
     * (comparacion case-insensitive) y preservando el orden de aparicion.
     *
     * @return string[]
     */
    function eco_especialidad_split(string $csv): array
    {
        $out = [];
        $vistos = [];
        foreach (preg_split('/[,;]+/', $csv) ?: [] as $seg) {
            $nombre = trim($seg);
            if ($nombre === '') {
                continue;
            }
            $clave = mb_strtolower($nombre);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = $nombre;
        }
        return $out;
    }
}

if (!function_exists('eco_sync_especialidades_usuario')) {
    /**
     * Sincroniza las especialidades de un ecografista a partir de un texto CSV.
     * Crea en el catalogo las que falten y reemplaza por completo las filas del
     * puente para ese usuario. Operacion transaccional.
     */
    function eco_sync_especialidades_usuario(mysqli $conex, int $usuarioId, string $csv): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }
        $nombres = eco_especialidad_split($csv);

        $conex->begin_transaction();
        try {
            // 1. Asegurar catalogo y resolver ids (case-insensitive por collation utf8mb4_unicode_ci).
            $ids = [];
            if ($nombres) {
                $insCat = $conex->prepare('INSERT IGNORE INTO especialidades (nombre) VALUES (?)');
                $selCat = $conex->prepare('SELECT id FROM especialidades WHERE nombre = ? LIMIT 1');
                foreach ($nombres as $nombre) {
                    $insCat->bind_param('s', $nombre);
                    $insCat->execute();
                    $selCat->bind_param('s', $nombre);
                    $selCat->execute();
                    $row = $selCat->get_result()->fetch_assoc();
                    if ($row) {
                        $ids[] = (int)$row['id'];
                    }
                }
                $insCat->close();
                $selCat->close();
            }

            // 2. Reemplazar el puente del usuario.
            $del = $conex->prepare('DELETE FROM usuario_especialidades WHERE usuario_id = ?');
            $del->bind_param('i', $usuarioId);
            $del->execute();
            $del->close();

            if ($ids) {
                $insLink = $conex->prepare('INSERT IGNORE INTO usuario_especialidades (usuario_id, especialidad_id) VALUES (?, ?)');
                foreach (array_unique($ids) as $eid) {
                    $insLink->bind_param('ii', $usuarioId, $eid);
                    $insLink->execute();
                }
                $insLink->close();
            }

            $conex->commit();
            return true;
        } catch (\Throwable $e) {
            $conex->rollback();
            error_log('eco_sync_especialidades_usuario: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('eco_catalogo_especialidades')) {
    /**
     * Devuelve los nombres del catalogo de especialidades activas, ordenados.
     *
     * @return string[]
     */
    function eco_catalogo_especialidades(mysqli $conex): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        if ($res = $conex->query("SELECT nombre FROM especialidades WHERE activa = 1 ORDER BY nombre ASC")) {
            while ($row = $res->fetch_assoc()) {
                $cache[] = $row['nombre'];
            }
            $res->free();
        }
        return $cache;
    }
}
