/* =====================================================================
   ECOMADELLEINE — API mínima para modales (.eco-modal)
   Uso: EcoModal.open('id'), EcoModal.close('id'), data-eco-modal-close
   ===================================================================== */
(function () {
    'use strict';

    var stack = [];

    function getEl(id) {
        return typeof id === 'string' ? document.getElementById(id) : id;
    }

    function lockScroll(on) {
        if (on) {
            document.body.classList.add('eco-modal-open');
        } else {
            if (!document.querySelector('.eco-modal.eco-modal--open')) {
                document.body.classList.remove('eco-modal-open');
            }
        }
    }

    window.EcoModal = {
        open: function (id) {
            var el = getEl(id);
            if (!el || !el.classList.contains('eco-modal')) return;
            el.classList.add('eco-modal--open');
            /* Refuerzo reflow: sin display:none los keyframes del hijo se aplican de forma fiable */
            void el.offsetHeight;
            el.setAttribute('aria-hidden', 'false');
            el.setAttribute('aria-modal', 'true');
            stack.push(id);
            lockScroll(true);
            var first = el.querySelector('input:not([type="hidden"]), select, textarea, button');
            if (first && typeof first.focus === 'function') {
                setTimeout(function () { try { first.focus(); } catch (e) {} }, 50);
            }
        },

        close: function (id) {
            var el = getEl(id);
            if (!el) return;
            el.classList.remove('eco-modal--open');
            el.setAttribute('aria-hidden', 'true');
            el.removeAttribute('aria-modal');
            stack = stack.filter(function (x) { return x !== (typeof id === 'string' ? id : el.id); });
            lockScroll(false);
        },

        closeTop: function () {
            if (stack.length === 0) return;
            var top = stack[stack.length - 1];
            EcoModal.close(top);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) return;
            var btn = t.closest('[data-eco-modal-close]');
            if (btn) {
                var modal = btn.closest('.eco-modal');
                if (modal && modal.id) {
                    e.preventDefault();
                    EcoModal.close(modal.id);
                }
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var open = document.querySelectorAll('.eco-modal.eco-modal--open');
            if (open.length === 0) return;
            var last = open[open.length - 1];
            // Modales con data-eco-modal-static no se cierran con ESC ni backdrop
            if (last.hasAttribute('data-eco-modal-static')) return;
            if (last.id) EcoModal.close(last.id);
        });

        // El clic fuera del modal (backdrop) NO cierra: el usuario debe pulsar la X.
    });
})();
