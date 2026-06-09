/**
 * gestionar_textos.php — vista previa en vivo y contadores
 */
(function () {
    'use strict';

    var limits = { mision: 1200, vision: 1200, valores: 600 };

    function escapeHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function nl2brEscaped(str) {
        return escapeHtml(str).replace(/\n/g, '<br>');
    }

    function updatePreview(id, text) {
        var el = document.getElementById(id);
        if (!el) return;
        var trimmed = (text || '').trim();
        if (!trimmed) {
            el.innerHTML = '<em class="cw-preview-placeholder">Escribe el contenido…</em>';
        } else {
            el.innerHTML = nl2brEscaped(trimmed);
        }
    }

    function updateCount(fieldId) {
        var ta = document.getElementById(fieldId);
        var badge = document.querySelector('[data-count-for="' + fieldId + '"]');
        if (!ta || !badge) return;

        var len = ta.value.length;
        var max = limits[fieldId] || 1200;
        badge.textContent = len + ' / ' + max;
        badge.classList.remove('is-warning', 'is-limit');
        if (len >= max) {
            badge.classList.add('is-limit');
        } else if (len >= max * 0.9) {
            badge.classList.add('is-warning');
        }
    }

    function bindField(ta) {
        var previewId = ta.getAttribute('data-preview');
        var fieldId = ta.id;
        var minH = fieldId === 'valores' ? 100 : 120;

        function sync() {
            if (previewId) updatePreview(previewId, ta.value);
            updateCount(fieldId);
            if (!CSS.supports || !CSS.supports('field-sizing', 'content')) {
                ta.style.height = 'auto';
                ta.style.height = Math.max(minH, ta.scrollHeight) + 'px';
            }
        }

        ta.addEventListener('input', sync);
        sync();
        ta.addEventListener('focus', function () {
            var card = ta.closest('.cw-texto-card');
            document.querySelectorAll('.cw-texto-card.is-focused').forEach(function (c) {
                c.classList.remove('is-focused');
            });
            if (card) card.classList.add('is-focused');
            document.querySelectorAll('.cw-textos-jump__link').forEach(function (a) {
                a.classList.remove('is-active');
            });
            var jump = document.querySelector('.cw-textos-jump__link[href="#bloque-' + fieldId + '"]');
            if (jump) jump.classList.add('is-active');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        ['mision', 'vision', 'valores'].forEach(function (id) {
            var ta = document.getElementById(id);
            if (ta) bindField(ta);
        });

        document.querySelectorAll('.cw-textos-jump__link').forEach(function (link) {
            link.addEventListener('click', function () {
                document.querySelectorAll('.cw-textos-jump__link').forEach(function (a) {
                    a.classList.remove('is-active');
                });
                link.classList.add('is-active');
            });
        });
    });
})();
