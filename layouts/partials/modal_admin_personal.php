<?php
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    return;
}

$staff_form_fields = static function (string $pfx, string $rol): void {
    ?>
    <input type="hidden" name="rol" value="<?= htmlspecialchars($rol) ?>">
    <?= csrf_field() ?>
    <div class="eco-field">
        <label for="<?= $pfx ?>-nombre">Nombre completo</label>
        <input type="text" class="eco-input" name="nombre_completo" id="<?= $pfx ?>-nombre" required maxlength="100" autocomplete="name" placeholder="Nombre y apellido">
    </div>
    <div class="eco-field">
        <label for="<?= $pfx ?>-fnac">Fecha de nacimiento</label>
        <input type="text" class="eco-input" name="fecha_nacimiento" id="<?= $pfx ?>-fnac" required autocomplete="off" placeholder="Seleccionar…">
    </div>
    <div class="eco-field">
        <label for="<?= $pfx ?>-doc-num">Documento</label>
        <div class="eco-cedula-row">
            <select name="cedula_tipo" id="<?= $pfx ?>-doc-tipo" aria-label="Tipo">
                <option value="V-">V</option>
                <option value="E-">E</option>
                <option value="P-">P</option>
            </select>
            <input type="number" class="eco-input" name="cedula_numero" id="<?= $pfx ?>-doc-num" required min="1000000" max="99999999" placeholder="7–8 dígitos" inputmode="numeric">
        </div>
    </div>
    <div class="eco-field">
        <label for="<?= $pfx ?>-correo">Correo</label>
        <input type="email" class="eco-input" name="correo" id="<?= $pfx ?>-correo" required maxlength="100" autocomplete="email" placeholder="correo@ejemplo.com">
    </div>
    <div class="eco-field">
        <label for="<?= $pfx ?>-direccion">Dirección física</label>
        <input type="text" class="eco-input" name="direccion" id="<?= $pfx ?>-direccion" required maxlength="255" autocomplete="street-address" placeholder="Estado, Sector">
    </div>
    <div class="eco-field">
        <label for="<?= $pfx ?>-telefono">Teléfono</label>
        <input type="tel" class="eco-input" name="telefono" id="<?= $pfx ?>-telefono" required maxlength="30" autocomplete="tel" placeholder="Ej: 0412-1234567">
    </div>
    <div class="eco-field">
        <label for="<?= $pfx ?>-pass">Contraseña</label>
        <input type="password" class="eco-input" name="contrasena" id="<?= $pfx ?>-pass" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
        <p class="eco-field-hint"><i class="fa-solid fa-circle-info"></i> Mayúscula, símbolo y mínimo 8 caracteres.</p>
    </div>
    <div class="eco-field">
        <label for="<?= $pfx ?>-pass2">Confirmar contraseña</label>
        <input type="password" class="eco-input" name="confirmar_contrasena" id="<?= $pfx ?>-pass2" required minlength="8" autocomplete="new-password" placeholder="Repita la contraseña">
    </div>
    <?php
};
?>

<div id="eco-modal-staff-ecografista" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="staff-eco-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside staff-modal-aside--eco">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <h3 id="staff-eco-title">Registrar ecografista</h3>
                <p>Alta de profesional para estudios ecográficos.</p>
                <p class="eco-modal__hint"><i class="fa-solid fa-lock"></i> Cuenta aprobada automáticamente.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Datos del profesional</h4>
                <div id="staff-eco-error" class="staff-modal-alert" hidden role="alert"></div>
                <form id="staff-form-ecografista" class="staff-register-form" data-endpoint="registrar_personal_admin_ajax.php" novalidate>
                    <?php $staff_form_fields('staff-eco', 'ecografista'); ?>
                    <div class="eco-modal__footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-staff-recepcionista" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="staff-rx-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside staff-modal-aside--rx">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-clipboard-user"></i></div>
                <h3 id="staff-rx-title">Registrar recepcionista</h3>
                <p>Personal de recepción y gestión de citas.</p>
                <p class="eco-modal__hint"><i class="fa-solid fa-lock"></i> Cuenta aprobada automáticamente.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Datos del personal</h4>
                <div id="staff-rx-error" class="staff-modal-alert" hidden role="alert"></div>
                <form id="staff-form-recepcionista" class="staff-register-form" data-endpoint="registrar_personal_admin_ajax.php" novalidate>
                    <?php $staff_form_fields('staff-rx', 'recepcionista'); ?>
                    <div class="eco-modal__footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-staff-paciente" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="staff-pat-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside staff-modal-aside--pat">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-user-plus"></i></div>
                <h3 id="staff-pat-title">Registrar paciente</h3>
                <p>Nuevo expediente con acceso al portal.</p>
                <p class="eco-modal__hint"><i class="fa-solid fa-lock"></i> Mayúscula + símbolo, mín. 8 caracteres.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Datos del paciente</h4>
                <div id="staff-pat-error" class="staff-modal-alert" hidden role="alert"></div>
                <form id="staff-form-paciente" class="staff-register-form" data-endpoint="guardar_paciente_extendido_ajax.php" novalidate>
                    <?php $staff_form_fields('staff-pat', 'paciente'); ?>
                    <div class="eco-modal__footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
