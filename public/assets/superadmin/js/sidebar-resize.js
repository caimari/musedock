(function () {
    'use strict';
    const root = document.documentElement;
    const storageKey = 'musedock:sidebar-width:' + location.pathname.split('/')[1];
    const defaultWidth = 260;
    const minimum = 200;
    let width = defaultWidth;
    let handle;
    let drag = null;

    function maximum() { return Math.max(minimum, Math.min(480, Math.floor(window.innerWidth * 0.45))); }
    function apply(value) {
        width = Math.max(minimum, Math.min(maximum(), value));
        root.style.setProperty('--musedock-sidebar-width', width + 'px');
        if (handle) {
            handle.setAttribute('aria-valuenow', String(width));
            handle.setAttribute('aria-valuemax', String(maximum()));
        }
    }
    function save() {
        try { localStorage.setItem(storageKey, String(width)); } catch (_) {}
    }
    try {
        const saved = Number(localStorage.getItem(storageKey));
        if (Number.isFinite(saved) && saved >= minimum && saved !== 220) width = saved;
    } catch (_) {}
    apply(width);

    function initialize() {
        const sidebar = document.getElementById('sidebar');
        handle = document.getElementById('sidebar-resizer');
        if (!sidebar || !handle) return;
        apply(width);

        function finish() {
            if (!drag) return;
            const pointer = drag.pointer;
            drag = null;
            root.classList.remove('sidebar-is-resizing');
            if (handle.hasPointerCapture(pointer)) handle.releasePointerCapture(pointer);
            save();
        }
        handle.addEventListener('pointerdown', event => {
            if (event.button !== 0 || window.innerWidth < 768 || sidebar.classList.contains('collapsed')) return;
            drag = { pointer: event.pointerId, x: event.clientX, width: sidebar.getBoundingClientRect().width };
            handle.setPointerCapture(event.pointerId);
            root.classList.add('sidebar-is-resizing');
            event.preventDefault();
        });
        handle.addEventListener('pointermove', event => {
            if (drag && event.pointerId === drag.pointer) apply(drag.width + event.clientX - drag.x);
        });
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(name => handle.addEventListener(name, finish));
        handle.addEventListener('dblclick', () => { apply(defaultWidth); save(); });
        handle.addEventListener('keydown', event => {
            const change = { ArrowLeft: width - 10, ArrowRight: width + 10, Home: defaultWidth, End: maximum() };
            if (!Object.prototype.hasOwnProperty.call(change, event.key)) return;
            event.preventDefault();
            apply(change[event.key]);
            save();
        });
        window.addEventListener('resize', () => { finish(); apply(width); });
        window.addEventListener('pagehide', finish);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
