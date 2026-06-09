<?php
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    return;
}
?>

<div id="eco-modal-staff-perfil" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="staff-perfil-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split staff-perfil-split">
            <aside class="eco-modal__aside staff-perfil-aside" id="staff-perfil-aside">
                <div class="staff-perfil-aside__avatar staff-perfil-avatar--eco" id="staff-perfil-avatar">?</div>
                <h3 id="staff-perfil-aside-name">—</h3>
                <span class="staff-perfil-aside__role" id="staff-perfil-aside-role">—</span>
                <p class="eco-modal__hint"><i class="fa-solid fa-id-card"></i> <span id="staff-perfil-aside-cedula">—</span></p>
            </aside>
            <section class="eco-modal__main staff-perfil-panel">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <div class="staff-perfil-panel__scroll">
                    <div class="staff-perfil-panel__head">
                        <h4 class="eco-modal__title" id="staff-perfil-title">Perfil del personal</h4>
                    </div>
                    <div id="staff-perfil-loading" class="staff-perfil-loading">
                        <i class="fa-solid fa-spinner fa-spin"></i> Cargando perfil…
                    </div>
                    <div id="staff-perfil-content" class="staff-perfil-body" hidden>
                        <div class="staff-perfil-details">
                            <div class="staff-perfil-detail">
                                <span class="staff-perfil-detail__label"><i class="fa-solid fa-envelope"></i> Correo</span>
                                <span class="staff-perfil-detail__value" id="staff-perfil-correo">—</span>
                            </div>
                            <div class="staff-perfil-detail">
                                <span class="staff-perfil-detail__label"><i class="fa-solid fa-circle-check"></i> Estado</span>
                                <span class="staff-perfil-detail__value"><span class="staff-perfil-estado" id="staff-perfil-estado">—</span></span>
                            </div>
                            <div class="staff-perfil-detail" id="staff-perfil-row-fnac">
                                <span class="staff-perfil-detail__label"><i class="fa-solid fa-cake-candles"></i> Nacimiento</span>
                                <span class="staff-perfil-detail__value" id="staff-perfil-fnac">—</span>
                            </div>
                            <div class="staff-perfil-detail">
                                <span class="staff-perfil-detail__label"><i class="fa-solid fa-calendar-plus"></i> Registro</span>
                                <span class="staff-perfil-detail__value" id="staff-perfil-registro">—</span>
                            </div>
                        </div>
                        <p id="staff-perfil-self-hint" class="staff-perfil-self-hint" hidden>
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            No puedes gestionar tu propia cuenta desde aquí.
                        </p>
                    </div>
                    <div id="staff-perfil-error" class="staff-modal-alert staff-perfil-panel__alert" hidden role="alert"></div>
                </div>
                <footer class="contenedor-botones-modal staff-perfil-footer" id="staff-perfil-actions" hidden>
                    <a href="#"
                       id="staff-perfil-btn-reset"
                       class="staff-perfil-btn staff-perfil-btn--reset">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        <span>Restablecer contraseña</span>
                    </a>
                    <button type="button"
                            id="staff-perfil-btn-estado"
                            class="staff-perfil-btn staff-perfil-btn--disable">
                        <i class="fa-solid fa-user-slash" aria-hidden="true"></i>
                        <span>Inhabilitar acceso</span>
                    </button>
                </footer>
            </section>
        </div>
    </div>
</div>
