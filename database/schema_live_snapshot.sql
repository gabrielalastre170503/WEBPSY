/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `citas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) NOT NULL,
  `ecografista_id` int(11) DEFAULT NULL,
  `tipo_ecografia_id` int(11) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_cita` datetime DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `fecha_propuesta` datetime DEFAULT NULL,
  `motivo_principal` varchar(255) DEFAULT NULL,
  `notas_paciente` text DEFAULT NULL,
  `reprogramacion_motivo` text DEFAULT NULL,
  `notificacion_paciente` text DEFAULT NULL,
  `motivo_consulta` text DEFAULT NULL,
  `modalidad` enum('presencial','virtual') NOT NULL DEFAULT 'presencial',
  `tipo_cita` enum('primera_consulta','seguimiento') NOT NULL DEFAULT 'primera_consulta',
  `monto_total` decimal(10,2) DEFAULT NULL,
  `monto_pagado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado_pago` enum('pendiente','parcial','pagado','exonerado') NOT NULL DEFAULT 'pendiente',
  `metodo_pago` varchar(40) DEFAULT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `estado` enum('pendiente','confirmada','cancelada','pendiente_paciente','reprogramada','completada') NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  KEY `idx_cita_pac` (`paciente_id`),
  KEY `idx_cita_eco` (`ecografista_id`),
  KEY `idx_cita_tipo` (`tipo_ecografia_id`),
  KEY `idx_cita_estado_pago` (`estado_pago`),
  CONSTRAINT `fk_cita_eco` FOREIGN KEY (`ecografista_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cita_pac` FOREIGN KEY (`paciente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cita_tipo` FOREIGN KEY (`tipo_ecografia_id`) REFERENCES `tipos_ecografias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contadores` (
  `clave` varchar(40) NOT NULL,
  `valor` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contenido_web`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contenido_web` (
  `clave` varchar(50) NOT NULL,
  `valor` text DEFAULT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `disponibilidad_excepciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disponibilidad_excepciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ecografista_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('no_disponible','disponible') NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_de_eco` (`ecografista_id`),
  CONSTRAINT `fk_de_eco` FOREIGN KEY (`ecografista_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `especialidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `especialidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_especialidad_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faqs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pregunta` varchar(255) NOT NULL,
  `respuesta` text DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `horarios_recurrentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `horarios_recurrentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ecografista_id` int(11) NOT NULL,
  `dia_semana` int(11) NOT NULL COMMENT '1=Lunes, 2=Martes, ..., 7=Domingo',
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hr_eco` (`ecografista_id`),
  CONSTRAINT `fk_hr_eco` FOREIGN KEY (`ecografista_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=207 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `informes_estudios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `informes_estudios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) NOT NULL,
  `ecografista_id` int(11) NOT NULL,
  `tipo_ecografia_id` int(11) NOT NULL,
  `numero_informe` varchar(50) DEFAULT NULL,
  `estado` enum('borrador','finalizado','firmado','anulado') NOT NULL DEFAULT 'borrador',
  `finalizado_en` datetime DEFAULT NULL,
  `firmado_por` int(11) DEFAULT NULL,
  `fecha_firma` datetime DEFAULT NULL,
  `anulado_por` int(11) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `datos_clinicos` longtext NOT NULL CHECK (json_valid(`datos_clinicos`)),
  `esquema_version` int(11) NOT NULL,
  `fecha_estudio` date GENERATED ALWAYS AS (json_unquote(json_extract(`datos_clinicos`,'$.datos_referencia.fecha_estudio'))) STORED,
  `medico_solicitante` varchar(120) GENERATED ALWAYS AS (json_unquote(json_extract(`datos_clinicos`,'$.datos_referencia.medico_solicitante'))) STORED,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inf_numero` (`numero_informe`),
  KEY `idx_inf_paciente` (`paciente_id`),
  KEY `idx_inf_eco` (`ecografista_id`),
  KEY `idx_inf_tipo` (`tipo_ecografia_id`),
  KEY `idx_inf_fecha` (`fecha_estudio`),
  KEY `idx_inf_medico` (`medico_solicitante`),
  KEY `idx_inf_firmado` (`firmado_por`),
  KEY `idx_inf_anulado` (`anulado_por`),
  CONSTRAINT `fk_inf_anulado` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inf_eco` FOREIGN KEY (`ecografista_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_inf_firmado` FOREIGN KEY (`firmado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inf_paciente` FOREIGN KEY (`paciente_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_inf_tipo` FOREIGN KEY (`tipo_ecografia_id`) REFERENCES `tipos_ecografias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notas_clinicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notas_clinicas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) NOT NULL,
  `ecografista_id` int(11) NOT NULL,
  `fecha_sesion` datetime NOT NULL DEFAULT current_timestamp(),
  `contenido` text NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  KEY `ecografista_id` (`ecografista_id`),
  CONSTRAINT `notas_clinicas_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notas_clinicas_ibfk_2` FOREIGN KEY (`ecografista_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipos_ecografias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_ecografias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `categoria` varchar(60) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(60) DEFAULT 'fa-solid fa-wave-square',
  `esquema_campos` longtext NOT NULL CHECK (json_valid(`esquema_campos`)),
  `esquema_version` int(11) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `posicion` smallint(6) NOT NULL DEFAULT 99,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tipos_codigo` (`codigo`),
  KEY `idx_tipos_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuario_especialidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_especialidades` (
  `usuario_id` int(11) NOT NULL,
  `especialidad_id` int(11) NOT NULL,
  PRIMARY KEY (`usuario_id`,`especialidad_id`),
  KEY `idx_ue_esp` (`especialidad_id`),
  CONSTRAINT `fk_ue_esp` FOREIGN KEY (`especialidad_id`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ue_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(100) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `edad` int(3) DEFAULT NULL,
  `cedula` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `correo` varchar(100) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('administrador','ecografista','recepcionista','paciente') NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('aprobado','pendiente','inhabilitado') NOT NULL DEFAULT 'pendiente',
  `creado_por_id` int(11) DEFAULT NULL,
  `token_verificacion` varchar(64) DEFAULT NULL,
  `token_verificacion_expira` datetime DEFAULT NULL,
  `token_recuperacion` varchar(64) DEFAULT NULL,
  `token_recuperacion_expira` datetime DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_correo` (`correo`),
  UNIQUE KEY `uk_usuarios_cedula` (`cedula`),
  KEY `idx_usuarios_rol` (`rol`),
  KEY `idx_usuarios_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

