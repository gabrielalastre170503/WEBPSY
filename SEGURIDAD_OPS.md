# Seguridad — Medidas de operación (ops)

Guía de las medidas de seguridad que viven a nivel de infraestructura/operación
(no en el código de la aplicación). Pensada para el responsable de TI del centro.

## 1. Cifrado en reposo (datos clínicos)

La base contiene datos de salud (PHI). El cifrado en reposo se hace a nivel de
motor o de disco, **no** en PHP (cifrar columnas rompería búsquedas por
cédula/nombre y exigiría gestión de claves en la app).

Opciones, de menor a mayor esfuerzo:

1. **Cifrado de disco completo del servidor** (recomendado mínimo):
   BitLocker (Windows) o LUKS/dm-crypt (Linux) sobre el volumen donde está
   `C:\xampp\mysql\data` (o el datadir en producción). Protege ante robo del
   disco/máquina. Cero cambios en la app.

2. **Cifrado de tablas MariaDB (data-at-rest)** con el plugin
   `file_key_management`:
   ```ini
   # my.ini / my.cnf
   [mariadb]
   plugin_load_add = file_key_management
   file_key_management_filename = C:/ruta/segura/keys.enc
   file_key_management_encryption_algorithm = AES_CTR
   innodb_encrypt_tables = ON
   innodb_encrypt_log = ON
   innodb_encryption_threads = 4
   ```
   El archivo de claves debe vivir **fuera** del datadir y con permisos
   restringidos. Cifra los tablespaces InnoDB y el redo log.

3. **Cifrar los respaldos**: los dumps de `database/backups/` contienen PHI.
   Cífralos (p. ej. `gpg -c` o un volumen cifrado) y restringe el acceso.

> En el entorno de desarrollo (XAMPP local) basta el cifrado de disco. El
> cifrado de tablas MariaDB se aplica en el servidor de producción.

## 2. Usuario de base de datos sin privilegios (hecho)

La app corre como `eco_app` (solo `SELECT/INSERT/UPDATE/DELETE`), no como root.
Aprovisionamiento: `database/migrations/2026_ops_01_usuario_app.sql`.
Credenciales en `.env` (gitignored). `root` queda solo para migraciones/admin.

## 3. Cabeceras de seguridad (hecho)

`bootstrap.php` emite en cada respuesta: Content-Security-Policy, X-Content-Type-Options,
X-Frame-Options, Referrer-Policy y Permissions-Policy.

## 4. Política de retención (hecho)

`mantenimiento_retencion.php` (CLI) purga datos efímeros (intentos de login,
enlaces caducados). Programar semanalmente:
```
0 3 * * 0  php /ruta/al/proyecto/mantenimiento_retencion.php --apply
```

## 5. TLS / HTTPS (producción)

En producción la app debe servirse **solo por HTTPS** (certificado válido). Con
HTTPS, la cookie de sesión se marca `Secure` automáticamente (ver `bootstrap.php`)
y conviene añadir `Strict-Transport-Security` en el servidor web.

## 6. Respaldos

Respaldo de la BD antes de cambios mayores (ejemplo ya generado en
`database/backups/`). Mantener copias cifradas y fuera del servidor. Verificar
periódicamente que el restore funciona.
