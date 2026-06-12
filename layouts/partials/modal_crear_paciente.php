<?php
/**
 * Modal: crear paciente (AJAX → guardar_paciente.php).
 * Requiere: conexion cargada ($conex), sesión iniciada, rol ecografista|administrador|recepcionista.
 *
 * IDs expuestos: eco-modal-crear-paciente, eco-modal-exito-paciente
 */
if (!isset($conex) || !($conex instanceof mysqli)) {
    return;
}
$rol_modal = $_SESSION['rol'] ?? '';
?>
<div id="eco-modal-crear-paciente" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-modal-crear-paciente-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-user-plus"></i></div>
                <h3 id="eco-modal-crear-paciente-title">Nuevo paciente</h3>
                <p>Registra los datos básicos. Se generará una contraseña temporal para el primer acceso.</p>
                <p class="eco-modal__hint"><i class="fa-solid fa-key" style="margin-right:4px;"></i> Entrega la contraseña al paciente de forma segura.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Datos del paciente</h4>
                <div id="eco-crear-paciente-error" style="display:none;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.35);color:#b91c1c;" role="alert"></div>
                <form id="form-crear-paciente-eco" action="<?= eco_url('api/guardar_paciente.php') ?>" method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="eco-field">
                        <label for="nombre_completo_eco">Nombre completo</label>
                        <input type="text" name="nombre_completo" id="nombre_completo_eco" required maxlength="100" autocomplete="name" placeholder="Nombre y apellido">
                    </div>
                    <div class="eco-field">
                        <label for="fecha_nacimiento_eco">Fecha de nacimiento</label>
                        <input type="text" name="fecha_nacimiento" id="fecha_nacimiento_eco" required placeholder="Seleccionar…" autocomplete="bday">
                    </div>
                    <div class="eco-field">
                        <label for="cedula_numero_eco">Identificación</label>
                        <div class="eco-cedula-row">
                            <select name="cedula_tipo" id="cedula_tipo_eco" aria-label="Tipo de documento">
                                <option value="V-">V</option>
                                <option value="E-">E</option>
                                <option value="P-">P</option>
                            </select>
                            <input type="number" name="cedula_numero" id="cedula_numero_eco" required min="1000000" max="99999999" placeholder="7–8 dígitos" inputmode="numeric">
                        </div>
                    </div>
                    <div class="eco-field">
                        <label for="correo_eco">Correo electrónico</label>
                        <input type="email" name="correo" id="correo_eco" required maxlength="100" autocomplete="email" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="eco-field">
                        <label for="direccion_eco">Dirección física</label>
                        <input type="text" name="direccion" id="direccion_eco" required maxlength="255" autocomplete="street-address" placeholder="Estado, Sector">
                    </div>
                    <div class="eco-field">
                        <label for="telefono_eco">Teléfono</label>
                        <input type="tel" name="telefono" id="telefono_eco" required maxlength="30" autocomplete="tel" placeholder="Ej: 0412-1234567">
                    </div>
                    <div class="eco-modal__footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary" id="btn-submit-crear-paciente-eco"><i class="fa-solid fa-check"></i> Crear paciente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="eco-modal-exito-paciente" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="eco-modal-exito-titulo">
    <div class="eco-modal__dialog eco-modal__dialog--compact">
        <div class="eco-modal__main" style="padding-top:28px;">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div style="text-align:center;margin-bottom:16px;color:var(--success);font-size:2.5rem;"><i class="fa-solid fa-circle-check"></i></div>
            <h3 id="eco-modal-exito-titulo" style="text-align:center;margin:0 0 8px;font-size:1.05rem;">Paciente creado</h3>
            <p class="eco-modal__body-text" style="text-align:center;">Cuenta para <strong id="eco-exito-paciente-nombre"></strong>. Contraseña temporal:</p>
            <div class="temp-pass-box" id="eco-exito-paciente-pass">—</div>
            <p class="eco-modal__body-text" style="text-align:center;font-size:12px;">Anótela y entréguela al paciente.</p>
            <div class="eco-modal__footer" style="border-top:none;justify-content:center;margin-top:8px;">
                <button type="button" class="btn-primary" id="btn-eco-exito-cerrar"><i class="fa-solid fa-check"></i> Entendido</button>
            </div>
        </div>
    </div>
</div>
