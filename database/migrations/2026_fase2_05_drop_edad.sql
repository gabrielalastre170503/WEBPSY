-- Fase 2 (cierre): DROP de la columna redundante usuarios.edad (APLICADA).
--
-- La edad se calcula en tiempo real con TIMESTAMPDIFF(YEAR, fecha_nacimiento,
-- CURDATE()) en todos los lectores; ningun codigo escribe ni lee ya la columna.
--
-- Antes del DROP se respaldaron los datos (id, edad, fecha_nacimiento) en
-- _backup_usuarios_edad por si hubiera que auditar/restaurar (50 filas, 17 con
-- edad no nula al momento de aplicar).
--
-- Pasos ejecutados:

CREATE TABLE IF NOT EXISTS _backup_usuarios_edad AS
    SELECT id, edad, fecha_nacimiento FROM usuarios;

ALTER TABLE usuarios DROP COLUMN edad;

-- Restauracion (si fuese necesaria):
--   ALTER TABLE usuarios ADD COLUMN edad INT NULL;
--   UPDATE usuarios u JOIN _backup_usuarios_edad b ON b.id = u.id SET u.edad = b.edad;
