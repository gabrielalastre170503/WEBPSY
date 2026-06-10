<?php
/**
 * Modales shell — Agenda general (lista de citas + nueva cita).
 * Requiere $conex (mysqli). Roles: administrador, recepcionista.
 */
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'] ?? '', ['administrador', 'recepcionista'], true)) {
    return;
}
if (!isset($conex) || !($conex instanceof mysqli)) {
    return;
}

$agenda_modal_ecografistas = [];
if ($rq = $conex->query("SELECT id, nombre_completo FROM usuarios WHERE rol = 'ecografista' AND estado = 'aprobado' ORDER BY nombre_completo ASC")) {
    while ($row = $rq->fetch_assoc()) {
        $agenda_modal_ecografistas[] = $row;
    }
    $rq->free();
}

require_once __DIR__ . '/../../lib/catalogo.php';
$agenda_modal_tipos = eco_catalogo_tipos_activos($conex);

$agenda_sin_eco = empty($agenda_modal_ecografistas);
$agenda_sin_tipos = empty($agenda_modal_tipos);
?>

<!-- Lista de citas -->
<div id="eco-modal-agenda-lista" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="agenda-lista-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide agenda-modal-dialog">
        <div class="eco-modal__main agenda-modal-main--full">
            <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            <div class="agenda-modal-head">
                <div class="agenda-modal-head__icon"><i class="fa-solid fa-list-check"></i></div>
                <div>
                    <h4 class="eco-modal__title" id="agenda-lista-title">Vista de lista</h4>
                    <p class="eco-modal__body-text agenda-modal-head__sub">Todas las citas de la clínica · busque por paciente, cédula o ecografista</p>
                </div>
            </div>
            <div class="agenda-modal-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="agenda-lista-query" placeholder="Buscar citas…" autocomplete="off" aria-label="Buscar citas">
            </div>
            <div id="agenda-lista-body" class="agenda-modal-table-wrap" role="region" aria-live="polite">
                <p class="agenda-modal-empty"><i class="fa-solid fa-spinner fa-spin"></i> Cargando citas…</p>
            </div>
        </div>
    </div>
</div>

<!-- Nueva cita -->
<div id="eco-modal-agenda-nueva-cita" class="eco-modal" aria-hidden="true" role="dialog" aria-labelledby="agenda-nueva-aside-title">
    <div class="eco-modal__dialog eco-modal__dialog--wide">
        <div class="eco-modal__split">
            <div class="eco-modal__aside">
                <div class="eco-modal__aside-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                <h3 id="agenda-nueva-aside-title">Nueva cita</h3>
                <p>Programación directa con confirmación inmediata para el paciente.</p>
                <p class="eco-modal__hint"><i class="fa-solid fa-bell" style="margin-right:4px;"></i> Se notificará al paciente al guardar.</p>
            </div>
            <div class="eco-modal__main">
                <button type="button" class="eco-modal__close" data-eco-modal-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <h4 class="eco-modal__title">Detalles de la cita</h4>
                <div id="agenda-nueva-error" class="agenda-modal-alert" style="display:none;" role="alert"></div>
                <form id="agenda-form-nueva-cita" novalidate>
                    <input type="hidden" name="paciente_id" id="agenda-paciente-id" value="">

                    <div class="eco-field">
                        <label for="agenda-paciente-q">Paciente</label>
                        <div class="agenda-paciente-search">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <input type="search" id="agenda-paciente-q" placeholder="Buscar por nombre o cédula (mín. 2 caracteres)…" autocomplete="off" aria-describedby="agenda-paciente-hint">
                        </div>
                        <p id="agenda-paciente-hint" class="eco-modal__body-text" style="font-size:12px;margin:6px 0 0;color:var(--text-muted);">Escriba para buscar y seleccione un paciente de la lista.</p>
                        <div id="agenda-paciente-results" class="agenda-paciente-results" hidden></div>
                        <div id="agenda-paciente-chip" class="agenda-paciente-chip" hidden>
                            <span class="agenda-paciente-chip__avatar" id="agenda-paciente-chip-ini">?</span>
                            <span class="agenda-paciente-chip__text">
                                <strong id="agenda-paciente-chip-nom">—</strong>
                                <small id="agenda-paciente-chip-ci"></small>
                            </span>
                            <button type="button" class="agenda-paciente-chip__clear" id="agenda-paciente-clear" aria-label="Quitar paciente"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>

                    <div class="eco-field">
                        <label for="agenda-ecografista">Ecografista responsable</label>
                        <?php if ($agenda_sin_eco): ?>
                            <p style="margin:0;font-size:13px;color:var(--danger);">No hay ecografistas aprobados.</p>
                        <?php else: ?>
                            <select name="ecografista_id" id="agenda-ecografista" required>
                                <option value="">Seleccionar…</option>
                                <?php foreach ($agenda_modal_ecografistas as $eco): ?>
                                    <option value="<?= (int)$eco['id'] ?>"><?= htmlspecialchars($eco['nombre_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="eco-field">
                        <label for="agenda-tipo">Tipo de ecografía</label>
                        <select name="tipo_ecografia_id" id="agenda-tipo" required <?= $agenda_sin_tipos ? 'disabled' : '' ?>>
                            <option value="">Seleccionar…</option>
                            <?php foreach ($agenda_modal_tipos as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars(($t['categoria'] ? $t['categoria'] . ' — ' : '') . $t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="eco-field">
                        <label for="agenda-fecha">Fecha y hora</label>
                        <input type="text" name="fecha_cita" id="agenda-fecha" required autocomplete="off" placeholder="Seleccionar…">
                    </div>

                    <div class="eco-field">
                        <label for="agenda-motivo">Antecedentes médicos y detalles <span style="font-weight:400;color:var(--text-muted);">(opcional)</span></label>
                        <textarea name="motivo_consulta" id="agenda-motivo" rows="3" placeholder="Antecedentes médicos y detalles"></textarea>
                    </div>

                    <div class="eco-modal__footer">
                        <button type="button" class="btn-secondary" data-eco-modal-close>Cancelar</button>
                        <button type="submit" class="btn-primary" id="agenda-nueva-submit" <?= ($agenda_sin_eco || $agenda_sin_tipos) ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-check"></i> Guardar cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
