-- Ops — Columna ultimo_acceso en usuarios.
-- Guarda la fecha/hora del último inicio de sesión, para mostrarla en el perfil
-- (acceso anterior) y ayudar a detectar accesos no autorizados.
-- Ejecutar como root (DDL).

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS ultimo_acceso DATETIME NULL DEFAULT NULL AFTER fecha_registro;
