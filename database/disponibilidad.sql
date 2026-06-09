-- Tablas de disponibilidad de los profesionales (ecografistas)
-- Renombrando psicologo_id a ecografista_id para consistencia con el nuevo modelo.

USE db_clinica_ecografias;

CREATE TABLE horarios_recurrentes (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    ecografista_id  INT(11) NOT NULL,
    dia_semana      INT(11) NOT NULL COMMENT '1=Lunes, 2=Martes, ..., 7=Domingo',
    hora_inicio     TIME    NOT NULL,
    hora_fin        TIME    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_hr_eco (ecografista_id),
    CONSTRAINT fk_hr_eco FOREIGN KEY (ecografista_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE disponibilidad_excepciones (
    id              INT(11) NOT NULL AUTO_INCREMENT,
    ecografista_id  INT(11) NOT NULL,
    fecha           DATE    NOT NULL,
    tipo            ENUM('no_disponible','disponible') NOT NULL,
    hora_inicio     TIME    NULL,
    hora_fin        TIME    NULL,
    PRIMARY KEY (id),
    KEY idx_de_eco (ecografista_id),
    CONSTRAINT fk_de_eco FOREIGN KEY (ecografista_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migracion desde la BD vieja (renombrando columna)
INSERT INTO db_clinica_ecografias.horarios_recurrentes
    (id, ecografista_id, dia_semana, hora_inicio, hora_fin)
SELECT id, psicologo_id, dia_semana, hora_inicio, hora_fin FROM formulario.horarios_recurrentes;

INSERT INTO db_clinica_ecografias.disponibilidad_excepciones
    (id, ecografista_id, fecha, tipo, hora_inicio, hora_fin)
SELECT id, psicologo_id, fecha, tipo, hora_inicio, hora_fin FROM formulario.disponibilidad_excepciones;
