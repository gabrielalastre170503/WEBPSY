<?php
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    return;
}
?>

<div id="eco-modal-staff-lista" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="staff-lista-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide staff-lista-dialog">
        <div class="eco-modal__main staff-lista-modal-main">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>

            <div class="staff-lista-modal-head">
                <div class="staff-lista-modal-head__icon" id="staff-lista-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <div class="staff-lista-modal-head__text">
                    <h4 class="eco-modal__title" id="staff-lista-title">Equipo activo</h4>
                    <p class="eco-modal__body-text staff-lista-modal-head__sub" id="staff-lista-sub"></p>
                </div>
                <span class="staff-lista-modal-badge" id="staff-lista-count" aria-label="Total"></span>
            </div>

            <div class="staff-lista-modal-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="staff-lista-query" placeholder="Buscar por nombre, correo o cédula…" autocomplete="off" aria-label="Buscar personal">
            </div>

            <div id="staff-lista-body" class="staff-lista-modal-body" role="region" aria-live="polite">
                <p class="staff-lista-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p>
            </div>

            <div class="staff-lista-modal-footer">
                <a href="<?= eco_url('usuarios') ?>?filtro=personal" id="staff-lista-ver-todo" class="btn-secondary">
                    <i class="fa-solid fa-users"></i> Gestión completa de usuarios
                </a>
            </div>
        </div>
    </div>
</div>
