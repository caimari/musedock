/*!
 * MuseDock · diálogos del panel
 * Sustituye los avisos nativos del navegador («musedock.com dice…») por modales SweetAlert2:
 *   - onsubmit="return confirm('…')" y onclick="return confirm('…')" (se interceptan solos, sin tocar las vistas)
 *   - window.alert(…)
 *   - window.mdConfirm(mensaje, opciones) → Promise<boolean>, para código JS nuevo o convertido
 * Si SweetAlert2 no está cargado, todo sigue funcionando con los avisos nativos.
 */
(function () {
    'use strict';
    if (window.__mdDialogs) { return; }
    window.__mdDialogs = true;

    var nativeConfirm = window.confirm.bind(window);
    var nativeAlert = window.alert.bind(window);
    var en = /^en/i.test(document.documentElement.lang || '');
    var T = en
        ? { confirm: 'Confirm', ok: 'Accept', cancel: 'Cancel', notice: 'Notice' }
        : { confirm: 'Confirmar', ok: 'Aceptar', cancel: 'Cancelar', notice: 'Aviso' };
    var DANGER = /elimin|borrar|delete|remove|desinstal|desconect|quitar|vaciar|restaur/i;

    function ready() { return typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function'; }

    /** Modal de confirmación. Devuelve una promesa con true/false. */
    window.mdConfirm = function (message, opts) {
        opts = opts || {};
        if (!ready()) { return Promise.resolve(nativeConfirm(message)); }
        var danger = opts.danger !== undefined ? opts.danger : DANGER.test(String(message));
        return window.Swal.fire({
            title: opts.title || T.confirm,
            text: String(message),
            icon: opts.icon || (danger ? 'warning' : 'question'),
            showCancelButton: true,
            confirmButtonText: opts.confirmText || T.ok,
            cancelButtonText: T.cancel,
            confirmButtonColor: danger ? '#dc3545' : undefined,
            focusCancel: danger
        }).then(function (r) { return !!r.isConfirmed; });
    };

    // alert() informativo → modal (no bloquea el código siguiente, como ya hacía Swal en el resto del panel)
    window.alert = function (message) {
        if (!ready()) { return nativeAlert(message); }
        window.Swal.fire({ title: T.notice, text: String(message), icon: 'info', confirmButtonText: T.ok });
    };

    // Solo se intercepta el patrón simple y completo «return confirm('texto')»; cualquier otro código queda intacto
    var RE = /^\s*return\s+confirm\(\s*(['"])((?:\\.|(?!\1)[^\\])*)\1\s*\)\s*;?\s*$/;
    function messageOf(el, attr) {
        var m = RE.exec(el.getAttribute(attr) || '');
        return m ? m[2].replace(/\\n/g, '\n').replace(/\\(['"\\])/g, '$1') : null;
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.getAttribute || !ready()) { return; }
        var msg = messageOf(form, 'onsubmit');
        if (msg === null) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        var submitter = e.submitter || null;
        window.mdConfirm(msg).then(function (ok) {
            if (!ok) { return; }
            var saved = form.getAttribute('onsubmit');
            form.removeAttribute('onsubmit');
            try { form.requestSubmit(submitter || undefined); } catch (err) { form.submit(); }
            setTimeout(function () { form.setAttribute('onsubmit', saved); }, 0);
        });
    }, true);

    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[onclick]') : null;
        if (!el || !ready()) { return; }
        var msg = messageOf(el, 'onclick');
        if (msg === null) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        window.mdConfirm(msg).then(function (ok) {
            if (!ok) { return; }
            var saved = el.getAttribute('onclick');
            el.removeAttribute('onclick');
            el.click();
            setTimeout(function () { el.setAttribute('onclick', saved); }, 0);
        });
    }, true);
})();
