<?php
/**
 * Modal KPI — Panel administrador (usuarios, pacientes, personal, citas).
 */
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    return;
}
?>

<div id="eco-modal-admin-kpi" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="admin-kpi-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide admin-kpi-dialog">
        <div class="eco-modal__main admin-kpi-modal-main">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>

            <div class="admin-kpi-modal-head">
                <div class="admin-kpi-modal-head__icon" id="admin-kpi-icon"><i class="fa-solid fa-users"></i></div>
                <div class="admin-kpi-modal-head__text">
                    <h4 class="eco-modal__title" id="admin-kpi-title">Detalle</h4>
                    <p class="eco-modal__body-text admin-kpi-modal-head__sub" id="admin-kpi-sub"></p>
                </div>
                <span class="admin-kpi-modal-badge" id="admin-kpi-count" aria-label="Total"></span>
            </div>

            <div class="admin-kpi-modal-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="admin-kpi-query" placeholder="Buscar…" autocomplete="off" aria-label="Buscar en el listado">
            </div>

            <div id="admin-kpi-body" class="admin-kpi-modal-body" role="region" aria-live="polite">
                <p class="admin-kpi-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando…</p>
            </div>

            <div class="admin-kpi-modal-footer">
                <a href="#" id="admin-kpi-ver-todo" class="btn-secondary admin-kpi-ver-todo">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir gestión completa
                </a>
            </div>
        </div>
    </div>
</div>
