/* Keep navigation position within this tab and administration area. */
(function () {
    'use strict';

    function initialize() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        const content = sidebar.querySelector('.sidebar-content');
        const brand = sidebar.querySelector('.sidebar-brand');
        const key = 'musedock:sidebar-position:' + (brand ? brand.pathname : location.pathname.split('/')[1]);
        const containers = [sidebar, content].filter(Boolean);
        let restoring = true;

        function save() {
            if (restoring || sidebar.classList.contains('collapsed')) return;
            try {
                sessionStorage.setItem(key, JSON.stringify(containers.map(element => element.scrollTop)));
            } catch (_) { /* Navigation also works when browser storage is unavailable. */ }
        }

        function restore() {
            if (sidebar.classList.contains('collapsed')) return;
            restoring = true;
            try {
                const positions = JSON.parse(sessionStorage.getItem(key) || 'null');
                if (Array.isArray(positions)) {
                    containers.forEach((element, index) => {
                        const position = positions[index];
                        if (Number.isFinite(position) && position >= 0) element.scrollTop = position;
                    });
                }
            } catch (_) { /* Fall back to showing the active navigation entry. */ }

            const activeLinks = sidebar.querySelectorAll('.sidebar-item.active > .sidebar-link');
            const active = activeLinks[activeLinks.length - 1];
            if (active) {
                // Scroll only the sidebar's overflowing ancestors, never the page.
                for (let element = active.parentElement; element; element = element.parentElement) {
                    if (containers.includes(element) && element.scrollHeight > element.clientHeight) {
                        const item = active.getBoundingClientRect();
                        const viewport = element.getBoundingClientRect();
                        if (item.top < viewport.top) element.scrollTop -= viewport.top - item.top;
                        else if (item.bottom > viewport.bottom) element.scrollTop += item.bottom - viewport.bottom;
                    }
                    if (element === sidebar) break;
                }
            }
            restoring = false;
            save();
        }

        containers.forEach(element => element.addEventListener('scroll', save, { passive: true }));
        sidebar.addEventListener('click', save, true);
        window.addEventListener('pagehide', save);
        window.addEventListener('pageshow', restore);
        sidebar.addEventListener('shown.bs.collapse', restore);
        restore();
        if (document.readyState !== 'complete') window.addEventListener('load', restore, { once: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
