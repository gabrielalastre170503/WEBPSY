-- =============================================================================
-- db_clinica_ecografias - Esquema base (v1, historico)
-- Arquitectura JSON-schema-driven: 1 sola tabla de informes_estudios para los
-- 20+ tipos de ecografia, con esquema dinamico en tipos_ecografias.
--
-- AVISO: este archivo es el esquema ORIGINAL (pre-Fase 1). La estructura ACTUAL
-- de la base de datos (Fase 1 + Fase 2) esta en:
--   * database/schema_live_snapshot.sql  -> estructura real al dia (auto-generada)
--   * database/migrations/               -> deltas aplicados (especialidades 1NF,
--                                            limpieza de columnas redundantes de citas, etc.)
-- =============================================================================

DROP DATABASE IF EXISTS db_clinica_ecografias;
CREATE DATABASE db_clinica_ecografias
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_clinica_ecografias;

-- -----------------------------------------------------------------------------
-- 1. usuarios  (login + personal + pacientes unificados)
-- -----------------------------------------------------------------------------
CREATE TABLE usuarios (
    id                INT(11)      NOT NULL AUTO_INCREMENT,
    nombre_completo   VARCHAR(100) NOT NULL,
    fecha_nacimiento  DATE         NULL,
    edad              INT(3)       NULL,
    cedula            VARCHAR(20)  NULL,
    correo            VARCHAR(100) NOT NULL,
    contrasena        VARCHAR(255) NOT NULL,
    rol               ENUM('administrador','ecografista','recepcionista','paciente') NOT NULL,
    fecha_registro    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado            ENUM('aprobado','pendiente','inhabilitado') NOT NULL DEFAULT 'pendiente',
    creado_por_id     INT(11)      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_correo (correo),
    UNIQUE KEY uk_usuarios_cedula (cedula),
    KEY idx_usuarios_rol (rol),
    KEY idx_usuarios_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. tipos_ecografias  (catalogo maestro + esquema dinamico de campos)
--    Cada fila define un tipo de ecografia y como se renderiza su formulario.
-- -----------------------------------------------------------------------------
CREATE TABLE tipos_ecografias (
    id                INT(11)      NOT NULL AUTO_INCREMENT,
    codigo            VARCHAR(40)  NOT NULL,
    nombre            VARCHAR(120) NOT NULL,
    categoria         VARCHAR(60)  NULL,
    descripcion       TEXT         NULL,
    icono             VARCHAR(60)  NULL DEFAULT 'fa-solid fa-wave-square',
    esquema_campos    LONGTEXT     NOT NULL CHECK (JSON_VALID(esquema_campos)),
    esquema_version   INT(11)      NOT NULL DEFAULT 1,
    activo            TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tipos_codigo (codigo),
    KEY idx_tipos_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. informes_estudios  (tabla CENTRALIZADA para los 20+ estudios)
--    datos_clinicos: JSON con los valores capturados, valida estructura.
--    fecha_estudio + medico_solicitante: columnas generadas para indexar.
-- -----------------------------------------------------------------------------
CREATE TABLE informes_estudios (
    id                  INT(11)      NOT NULL AUTO_INCREMENT,
    paciente_id         INT(11)      NOT NULL,
    ecografista_id      INT(11)      NOT NULL,
    tipo_ecografia_id   INT(11)      NOT NULL,
    numero_informe      VARCHAR(50)  NULL,
    estado              ENUM('borrador','finalizado','firmado','anulado') NOT NULL DEFAULT 'borrador',
    datos_clinicos      LONGTEXT     NOT NULL CHECK (JSON_VALID(datos_clinicos)),
    esquema_version     INT(11)      NOT NULL,
    fecha_estudio       DATE         GENERATED ALWAYS AS
        (JSON_UNQUOTE(JSON_EXTRACT(datos_clinicos, '$.datos_referencia.fecha_estudio'))) STORED,
    medico_solicitante  VARCHAR(120) GENERATED ALWAYS AS
        (JSON_UNQUOTE(JSON_EXTRACT(datos_clinicos, '$.datos_referencia.medico_solicitante'))) STORED,
    creado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inf_paciente (paciente_id),
    KEY idx_inf_eco      (ecografista_id),
    KEY idx_inf_tipo     (tipo_ecografia_id),
    KEY idx_inf_fecha    (fecha_estudio),
    KEY idx_inf_medico   (medico_solicitante),
    CONSTRAINT fk_inf_paciente FOREIGN KEY (paciente_id)       REFERENCES usuarios(id)        ON DELETE RESTRICT,
    CONSTRAINT fk_inf_eco      FOREIGN KEY (ecografista_id)    REFERENCES usuarios(id)        ON DELETE RESTRICT,
    CONSTRAINT fk_inf_tipo     FOREIGN KEY (tipo_ecografia_id) REFERENCES tipos_ecografias(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. citas  (vinculadas a tipos_ecografias)
-- -----------------------------------------------------------------------------
CREATE TABLE citas (
    id                  INT(11)      NOT NULL AUTO_INCREMENT,
    paciente_id         INT(11)      NOT NULL,
    ecografista_id      INT(11)      NULL,
    tipo_ecografia_id   INT(11)      NULL,
    fecha_solicitud     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cita          DATETIME     NULL,
    motivo              TEXT         NULL,
    estado              ENUM('pendiente','confirmada','cancelada','reprogramada','completada') NOT NULL DEFAULT 'pendiente',
    notas               TEXT         NULL,
    PRIMARY KEY (id),
    KEY idx_cita_pac  (paciente_id),
    KEY idx_cita_eco  (ecografista_id),
    KEY idx_cita_tipo (tipo_ecografia_id),
    CONSTRAINT fk_cita_pac  FOREIGN KEY (paciente_id)       REFERENCES usuarios(id)        ON DELETE CASCADE,
    CONSTRAINT fk_cita_eco  FOREIGN KEY (ecografista_id)    REFERENCES usuarios(id)        ON DELETE SET NULL,
    CONSTRAINT fk_cita_tipo FOREIGN KEY (tipo_ecografia_id) REFERENCES tipos_ecografias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. contenido_web  (textos del sitio publico)
-- -----------------------------------------------------------------------------
CREATE TABLE contenido_web (
    clave VARCHAR(50) NOT NULL,
    valor TEXT        NULL,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED: migracion desde la BD vieja `formulario` (si existe)
-- =============================================================================

-- usuarios (mapea roles viejos a los nuevos)
INSERT INTO db_clinica_ecografias.usuarios
    (id, nombre_completo, fecha_nacimiento, edad, cedula, correo, contrasena, rol, fecha_registro, estado, creado_por_id)
SELECT
    id, nombre_completo, fecha_nacimiento, edad, cedula, correo, contrasena,
    CASE rol
        WHEN 'administrador' THEN 'administrador'
        WHEN 'paciente'      THEN 'paciente'
        WHEN 'secretaria'    THEN 'recepcionista'
        ELSE 'ecografista'
    END AS rol,
    fecha_registro, estado, creado_por_psicologo_id
FROM formulario.usuarios;

-- contenido_web (sobrescribe valores por defecto si la tabla vieja existe)
INSERT INTO db_clinica_ecografias.contenido_web (clave, valor)
SELECT clave, valor FROM formulario.contenido_web
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Valores por defecto para textos del sitio publico (si no existian)
INSERT IGNORE INTO contenido_web (clave, valor) VALUES
    ('nombre_clinica', 'EcoMadelleine'),
    ('mision',         'Brindar servicios de imagen ecografica de alta resolucion con calidez humana y diagnostico oportuno.'),
    ('vision',         'Ser la clinica de ecografias de referencia regional por nuestra precision diagnostica y trato al paciente.'),
    ('valores',        'Excelencia tecnica, etica profesional, empatia y compromiso con la salud de cada paciente.');

-- =============================================================================
-- SEED: tipos_ecografias  (3 tipos completos como ejemplo del modelo dinamico)
-- =============================================================================

INSERT INTO tipos_ecografias (codigo, nombre, categoria, descripcion, icono, esquema_campos) VALUES
(
    'eco_abdominal',
    'Ecografia Abdominal Total',
    'Abdominal',
    'Estudio integral de higado, vesicula, vias biliares, pancreas, bazo y rinones.',
    'fa-solid fa-stethoscope',
    JSON_OBJECT(
        'version', 1,
        'secciones', JSON_ARRAY(
            JSON_OBJECT(
                'id', 'datos_referencia',
                'titulo', 'Datos de Referencia',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','fecha_estudio',      'etiqueta','Fecha del Estudio',  'tipo','date',    'requerido', true,  'ancho','medio'),
                    JSON_OBJECT('nombre','medico_solicitante', 'etiqueta','Medico Solicitante', 'tipo','text',    'requerido', true,  'ancho','medio'),
                    JSON_OBJECT('nombre','motivo_estudio',     'etiqueta','Motivo del Estudio', 'tipo','textarea','requerido', false, 'ancho','completo','filas',2)
                )
            ),
            JSON_OBJECT(
                'id', 'tecnica',
                'titulo', 'Tecnica',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','equipo',     'etiqueta','Equipo Utilizado','tipo','select','opciones', JSON_ARRAY('GE Voluson E10','Philips Affiniti 70','Mindray DC-70','Samsung HS70A'),'ancho','medio'),
                    JSON_OBJECT('nombre','preparacion','etiqueta','Preparacion del Paciente','tipo','select','opciones', JSON_ARRAY('Adecuada','Parcial','Inadecuada'),'ancho','medio')
                )
            ),
            JSON_OBJECT(
                'id', 'hallazgos',
                'titulo', 'Hallazgos por Organo',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','higado',        'etiqueta','Higado',         'tipo','textarea','filas',3,'placeholder','Tamano, ecogenicidad, contornos, lesiones focales...','ancho','completo'),
                    JSON_OBJECT('nombre','vesicula',      'etiqueta','Vesicula Biliar','tipo','textarea','filas',2,'placeholder','Paredes, contenido, litiasis...','ancho','completo'),
                    JSON_OBJECT('nombre','vias_biliares', 'etiqueta','Vias Biliares',  'tipo','textarea','filas',2,'ancho','completo'),
                    JSON_OBJECT('nombre','pancreas',      'etiqueta','Pancreas',       'tipo','textarea','filas',2,'ancho','completo'),
                    JSON_OBJECT('nombre','bazo',          'etiqueta','Bazo',           'tipo','textarea','filas',2,'ancho','completo'),
                    JSON_OBJECT('nombre','rinon_d',       'etiqueta','Rinon Derecho',  'tipo','text','unidad','cm','placeholder','Ej: 10.2 x 5.1 x 4.0','ancho','medio'),
                    JSON_OBJECT('nombre','rinon_i',       'etiqueta','Rinon Izquierdo','tipo','text','unidad','cm','placeholder','Ej: 10.0 x 5.0 x 4.1','ancho','medio')
                )
            ),
            JSON_OBJECT(
                'id', 'conclusion',
                'titulo', 'Conclusion',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','impresion_diagnostica','etiqueta','Impresion Diagnostica','tipo','textarea','filas',5,'requerido', true,'ancho','completo'),
                    JSON_OBJECT('nombre','recomendaciones',      'etiqueta','Recomendaciones',      'tipo','textarea','filas',3,'ancho','completo')
                )
            )
        )
    )
),
(
    'eco_obstetrica',
    'Ecografia Obstetrica',
    'Obstetrica',
    'Evaluacion del bienestar fetal, biometria y caracteristicas anexas.',
    'fa-solid fa-baby',
    JSON_OBJECT(
        'version', 1,
        'secciones', JSON_ARRAY(
            JSON_OBJECT(
                'id', 'datos_referencia',
                'titulo', 'Datos de Referencia',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','fecha_estudio',      'etiqueta','Fecha del Estudio',  'tipo','date','requerido', true, 'ancho','medio'),
                    JSON_OBJECT('nombre','medico_solicitante', 'etiqueta','Medico Solicitante', 'tipo','text','requerido', true, 'ancho','medio'),
                    JSON_OBJECT('nombre','fum',                'etiqueta','Fecha Ultima Menstruacion (FUM)','tipo','date','ancho','medio'),
                    JSON_OBJECT('nombre','edad_gestacional',   'etiqueta','Edad Gestacional (semanas)','tipo','number','unidad','sem','ancho','medio')
                )
            ),
            JSON_OBJECT(
                'id', 'biometria_fetal',
                'titulo', 'Biometria Fetal',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','dbp',  'etiqueta','Diametro Biparietal (DBP)','tipo','number','unidad','mm','ancho','tercio'),
                    JSON_OBJECT('nombre','cc',   'etiqueta','Circunferencia Cefalica (CC)','tipo','number','unidad','mm','ancho','tercio'),
                    JSON_OBJECT('nombre','ca',   'etiqueta','Circunferencia Abdominal (CA)','tipo','number','unidad','mm','ancho','tercio'),
                    JSON_OBJECT('nombre','lf',   'etiqueta','Longitud Femoral (LF)','tipo','number','unidad','mm','ancho','tercio'),
                    JSON_OBJECT('nombre','peso_fetal_estimado','etiqueta','Peso Fetal Estimado','tipo','number','unidad','g','ancho','tercio'),
                    JSON_OBJECT('nombre','percentil_peso',     'etiqueta','Percentil de Peso','tipo','number','unidad','%','ancho','tercio')
                )
            ),
            JSON_OBJECT(
                'id', 'bienestar_fetal',
                'titulo', 'Bienestar Fetal y Anexos',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','frecuencia_cardiaca','etiqueta','Frecuencia Cardiaca Fetal','tipo','number','unidad','lpm','ancho','medio'),
                    JSON_OBJECT('nombre','movimientos',        'etiqueta','Movimientos Fetales','tipo','select','opciones', JSON_ARRAY('Presentes','Ausentes','Disminuidos'),'ancho','medio'),
                    JSON_OBJECT('nombre','liquido_amniotico',  'etiqueta','Liquido Amniotico','tipo','select','opciones', JSON_ARRAY('Normal','Oligoamnios','Polihidramnios'),'ancho','medio'),
                    JSON_OBJECT('nombre','placenta',           'etiqueta','Placenta','tipo','textarea','filas',2,'placeholder','Localizacion, grado de madurez...','ancho','completo'),
                    JSON_OBJECT('nombre','cordon',             'etiqueta','Cordon Umbilical','tipo','text','ancho','completo')
                )
            ),
            JSON_OBJECT(
                'id', 'conclusion',
                'titulo', 'Conclusion',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','impresion_diagnostica','etiqueta','Impresion Diagnostica','tipo','textarea','filas',5,'requerido', true,'ancho','completo'),
                    JSON_OBJECT('nombre','proximo_control',      'etiqueta','Proximo Control Sugerido','tipo','text','ancho','completo')
                )
            )
        )
    )
),
(
    'eco_tiroides',
    'Ecografia de Tiroides',
    'Cervical',
    'Evaluacion morfologica de la glandula tiroides y deteccion de nodulos.',
    'fa-solid fa-user-doctor',
    JSON_OBJECT(
        'version', 1,
        'secciones', JSON_ARRAY(
            JSON_OBJECT(
                'id', 'datos_referencia',
                'titulo', 'Datos de Referencia',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','fecha_estudio',      'etiqueta','Fecha del Estudio',  'tipo','date','requerido', true, 'ancho','medio'),
                    JSON_OBJECT('nombre','medico_solicitante', 'etiqueta','Medico Solicitante', 'tipo','text','requerido', true, 'ancho','medio'),
                    JSON_OBJECT('nombre','motivo_estudio',     'etiqueta','Motivo del Estudio', 'tipo','textarea','filas',2,'ancho','completo')
                )
            ),
            JSON_OBJECT(
                'id', 'mediciones',
                'titulo', 'Mediciones de la Glandula',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','lobulo_d','etiqueta','Lobulo Derecho (LxAPxT)','tipo','text','unidad','mm','placeholder','Ej: 45 x 15 x 12','ancho','tercio'),
                    JSON_OBJECT('nombre','lobulo_i','etiqueta','Lobulo Izquierdo (LxAPxT)','tipo','text','unidad','mm','placeholder','Ej: 44 x 14 x 12','ancho','tercio'),
                    JSON_OBJECT('nombre','istmo',   'etiqueta','Istmo','tipo','text','unidad','mm','ancho','tercio'),
                    JSON_OBJECT('nombre','volumen_total','etiqueta','Volumen Total','tipo','number','unidad','ml','ancho','medio'),
                    JSON_OBJECT('nombre','ecogenicidad','etiqueta','Ecogenicidad','tipo','select','opciones', JSON_ARRAY('Homogenea','Heterogenea leve','Heterogenea marcada'),'ancho','medio')
                )
            ),
            JSON_OBJECT(
                'id', 'nodulos',
                'titulo', 'Nodulos / Lesiones Focales',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','presencia_nodulos','etiqueta','Presencia de Nodulos','tipo','select','opciones', JSON_ARRAY('No','Si'),'ancho','medio'),
                    JSON_OBJECT('nombre','descripcion_nodulos','etiqueta','Descripcion de Nodulos','tipo','textarea','filas',4,'placeholder','Localizacion, tamano, ecoestructura, vascularidad, TIRADS...','ancho','completo')
                )
            ),
            JSON_OBJECT(
                'id', 'conclusion',
                'titulo', 'Conclusion',
                'campos', JSON_ARRAY(
                    JSON_OBJECT('nombre','impresion_diagnostica','etiqueta','Impresion Diagnostica','tipo','textarea','filas',5,'requerido', true,'ancho','completo'),
                    JSON_OBJECT('nombre','recomendaciones',      'etiqueta','Recomendaciones',      'tipo','textarea','filas',3,'ancho','completo')
                )
            )
        )
    )
);
