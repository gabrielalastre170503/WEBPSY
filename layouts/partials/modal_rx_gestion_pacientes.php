<?php
/**
 * Modales shell — recepcionista · programar cita (con ecografista), informes del paciente, alta extendida.
 * Requiere $conex (mysqli).
 */
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'recepcionista') {
    return;
}
if (!isset($conex) || !($conex instanceof mysqli)) {
    return;
}

$rx_modal_ecografistas = [];
if ($rq = $conex->query("SELECT id, nombre_completo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC")) {
    while ($row = $rq->fetch_assoc()) {
        $rx_modal_ecografistas[] = $row;
    }
    $rq->free();
}

require_once __DIR__ . '/../../lib/catalogo.php';
$rx_modal_tipos = eco_catalogo_tipos_activos($conex);
?>

<div id="eco-modal-rx-programar-cita" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="rx-prog-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                <h3 id="rx-prog-aside-title">Programar cita</h3>
                <p>Cita confirmada para el paciente:</p>
                <strong id="rx-prog-paciente-nombre">—</strong>
                <p class="eco-modal__hint"><i class="fa-solid fa-user-doctor" style="margin-right:4px;"></i> Elija ecografista y tipo de estudio.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Detalles</h4>
                <div id="rx-prog-error" style="display:none;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);color:#b91c1c;" role="alert"></div>
                <form id="rx-form-programar-cita" novalidate>
                    <input type="hidden" name="paciente_id" id="rx-prog-paciente-id" value="">
                    <div class="eco-field">
                        <label for="rx-prog-ecografista">Ecografista responsable</label>
                        <?php if (empty($rx_modal_ecografistas)): ?>
                            <p style="margin:0;font-size:13px;color:var(--danger);">No hay ecografistas aprobados.</p>
                        <?php else: ?>
                            <select name="ecografista_id" id="rx-prog-ecografista" required>
                                <option value="">Seleccionar…</option>
                                <?php foreach ($rx_modal_ecografistas as $eco): ?>
                                    <option value="<?= (int)$eco['id'] ?>"><?= htmlspecialchars($eco['nombre_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="eco-field">
                        <label for="rx-prog-tipo">Tipo de ecografía</label>
                        <select name="tipo_ecografia_id" id="rx-prog-tipo" required <?= empty($rx_modal_tipos) ? 'disabled' : '' ?>>
                            <option value="">Seleccionar…</option>
                            <?php foreach ($rx_modal_tipos as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars(($t['categoria'] ? $t['categoria'] . ' — ' : '') . $t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="eco-field">
                        <label for="rx-prog-fecha">Fecha y hora</label>
                        <input type="text" name="fecha_cita" id="rx-prog-fecha" required autocomplete="off" placeholder="Seleccionar…">
                    </div>
                    <div class="eco-field">
                        <label for="rx-prog-motivo">Antecedentes médicos y detalles <span style="font-weight:400;color:var(--text-muted);">(opcional)</span></label>
                        <textarea name="motivo_consulta" id="rx-prog-motivo" rows="3" placeholder="Antecedentes médicos y detalles"></textarea>
                    </div>
                    <div class="eco-modal__footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary" id="rx-prog-submit" <?= (empty($rx_modal_ecografistas) || empty($rx_modal_tipos)) ? 'disabled' : '' ?>><i class="fa-solid fa-check"></i> Guardar cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-rx-informes-paciente" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="rx-inf-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__main" style="padding-top:22px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <h4 class="eco-modal__title" id="rx-inf-title">Estudios e informes</h4>
            <p class="eco-modal__body-text" id="rx-inf-sub" style="margin-top:-8px;"></p>
            <div id="rx-inf-body" style="margin-top:12px;min-height:80px;"></div>
        </div>
    </div>
</div>

<div id="eco-modal-rx-crear-paciente-extendido" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="rx-ext-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-address-card"></i></div>
                <h3 id="rx-ext-aside-title">Alta extendida</h3>
                <p>Registro con contraseña definida por el paciente. Debe cumplir requisitos de seguridad.</p>
                <p class="eco-modal__hint"><i class="fa-solid fa-lock" style="margin-right:4px;"></i> Mayúscula + símbolo, mín. 8 caracteres.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Datos completos</h4>
                <div id="rx-ext-error" style="display:none;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);color:#b91c1c;" role="alert"></div>
                <form id="rx-form-crear-paciente-extendido" novalidate>
                    <?= csrf_field() ?>
                    <div class="eco-field">
                        <label for="rx-ext-nombre">Nombre completo</label>
                        <input type="text" class="eco-input" name="nombre_completo" id="rx-ext-nombre" required maxlength="100" autocomplete="name" placeholder="Nombre y apellido">
                    </div>
                    <div class="eco-field">
                        <label for="rx-ext-fnac">Fecha de nacimiento</label>
                        <input type="text" class="eco-input" name="fecha_nacimiento" id="rx-ext-fnac" required autocomplete="off" placeholder="Seleccionar…">
                    </div>
                    <div class="eco-field">
                        <label for="rx-ext-doc">Documento</label>
                        <div class="eco-cedula-row">
                            <select name="cedula_tipo" id="rx-ext-doc-tipo" aria-label="Tipo">
                                <option value="V-">V</option>
                                <option value="E-">E</option>
                                <option value="P-">P</option>
                            </select>
                            <input type="number" class="eco-input" name="cedula_numero" id="rx-ext-doc-num" required min="1000000" max="99999999" placeholder="7–8 dígitos" inputmode="numeric">
                        </div>
                    </div>
                    <div class="eco-field">
                        <label for="rx-ext-correo">Correo</label>
                        <input type="email" class="eco-input" name="correo" id="rx-ext-correo" required maxlength="100" autocomplete="email" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="eco-field">
                        <label for="rx-ext-direccion">Dirección física</label>
                        <input type="text" class="eco-input" name="direccion" id="rx-ext-direccion" required maxlength="255" autocomplete="street-address" placeholder="Estado, Sector">
                    </div>
                    <div class="eco-field">
                        <label for="rx-ext-telefono">Teléfono</label>
                        <input type="tel" class="eco-input" name="telefono" id="rx-ext-telefono" required maxlength="30" autocomplete="tel" placeholder="Ej: 0412-1234567">
                    </div>
                    <div class="eco-field">
                        <label for="rx-ext-pass">Contraseña</label>
                        <input type="password" class="eco-input" name="contrasena" id="rx-ext-pass" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                        <p class="eco-field-hint"><i class="fa-solid fa-circle-info"></i> Mayúscula, símbolo y mínimo 8 caracteres.</p>
                    </div>
                    <div class="eco-field">
                        <label for="rx-ext-pass2">Confirmar contraseña</label>
                        <input type="password" class="eco-input" name="confirmar_contrasena" id="rx-ext-pass2" required minlength="8" autocomplete="new-password" placeholder="Repita la contraseña">
                    </div>
                    <div class="eco-modal__footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary" id="rx-ext-submit"><i class="fa-solid fa-check"></i> Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
