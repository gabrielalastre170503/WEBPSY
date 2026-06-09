-- =============================================================================
-- Fase 3B (mini) — Facturacion
--   * tipos_ecografias.precio : tarifa por tipo de estudio (USD)
--   * citas: monto_total, monto_pagado, estado_pago, metodo_pago, fecha_pago
--   * seed de precios desde el catalogo historico (precios_ecografias_paciente.php)
-- =============================================================================

USE db_clinica_ecografias;

ALTER TABLE tipos_ecografias
    ADD COLUMN precio DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER activo;

ALTER TABLE citas
    ADD COLUMN monto_total  DECIMAL(10,2) NULL                                                              AFTER tipo_cita,
    ADD COLUMN monto_pagado DECIMAL(10,2) NOT NULL DEFAULT 0.00                                             AFTER monto_total,
    ADD COLUMN estado_pago  ENUM('pendiente','parcial','pagado','exonerado') NOT NULL DEFAULT 'pendiente'   AFTER monto_pagado,
    ADD COLUMN metodo_pago  VARCHAR(40) NULL                                                                AFTER estado_pago,
    ADD COLUMN fecha_pago   DATETIME    NULL                                                                AFTER metodo_pago,
    ADD KEY idx_cita_estado_pago (estado_pago);

-- Seed de tarifas referenciales (USD) por codigo estable de estudio.
UPDATE tipos_ecografias SET precio = CASE codigo
    WHEN 'eco_abdominal'        THEN 15.00
    WHEN 'eco_obstetrica'       THEN 15.00
    WHEN 'eco_tiroides'         THEN 15.00
    WHEN 'ECO_ABD_REN'          THEN 20.00
    WHEN 'ECO_RENAL'            THEN 15.00
    WHEN 'ECO_PELVICA'          THEN 15.00
    WHEN 'ECO_MUSCU'            THEN 20.00
    WHEN 'ECO_PROST'            THEN 15.00
    WHEN 'ECO_MAMA'             THEN 15.00
    WHEN 'ECO_PBLANCAS'         THEN 15.00
    WHEN 'ECO_TEST'             THEN 20.00
    WHEN 'ECO_CUELLO'           THEN 15.00
    WHEN 'ECO_TRANSV'           THEN 20.00
    WHEN 'ECO_MUSCU_HOMBRO'     THEN 20.00
    WHEN 'ECO_MUSCU_CODO'       THEN 20.00
    WHEN 'ECO_MUSCU_MUNECA'     THEN 20.00
    WHEN 'ECO_MUSCU_CADERA'     THEN 20.00
    WHEN 'ECO_MUSCU_RODILLA'    THEN 20.00
    WHEN 'ECO_MUSCU_TOBILLO'    THEN 20.00
    WHEN 'ECO_OBS_I_TRIM'       THEN 15.00
    WHEN 'ECO_OBS_II_III_TRIM'  THEN 20.00
    WHEN 'ECO_PBL_GENERAL'      THEN 15.00
    WHEN 'ECO_PBL_CUELLO'       THEN 15.00
    WHEN 'ECO_PBL_INGUINAL'     THEN 15.00
    WHEN 'ECO_PULMONAR'         THEN 20.00
    ELSE precio
END;

-- Backfill: las citas existentes toman como monto_total el precio de su estudio (si lo tienen).
UPDATE citas c
   JOIN tipos_ecografias t ON t.id = c.tipo_ecografia_id
   SET c.monto_total = t.precio
 WHERE c.monto_total IS NULL AND t.precio > 0;
