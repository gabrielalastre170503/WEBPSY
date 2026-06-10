-- Ops — Usuario MySQL de aplicación con privilegios mínimos (no-root).
--
-- La aplicación NO debe ejecutarse como root. Este usuario tiene SOLO DML
-- (SELECT / INSERT / UPDATE / DELETE) sobre la base de la clínica; NO puede
-- crear, alterar ni borrar tablas. Las migraciones y los seeds se ejecutan
-- por separado con un usuario administrador (root), no con este.
--
-- Pasos:
--   1) Cambia 'CAMBIA_ESTA_CLAVE' por una contraseña fuerte.
--   2) Ejecútalo como root:
--        mysql -u root < database/migrations/2026_ops_01_usuario_app.sql
--   3) Pon el mismo usuario y clave en .env:
--        DB_USER=eco_app
--        DB_PASS=la_clave_que_elegiste
--
-- (La contraseña real vive solo en .env, que está gitignored; aquí va un
--  placeholder a propósito.)

CREATE USER IF NOT EXISTS 'eco_app'@'localhost' IDENTIFIED BY 'CAMBIA_ESTA_CLAVE';

GRANT SELECT, INSERT, UPDATE, DELETE ON db_clinica_ecografias.* TO 'eco_app'@'localhost';

FLUSH PRIVILEGES;

-- Rotar la contraseña más adelante:
--   ALTER USER 'eco_app'@'localhost' IDENTIFIED BY 'NUEVA_CLAVE';
--   FLUSH PRIVILEGES;
--
-- Revocar / eliminar el usuario:
--   DROP USER 'eco_app'@'localhost';
