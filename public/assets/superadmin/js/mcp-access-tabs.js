(function () {
    const tabs = [...document.querySelectorAll('[data-mcp-tab]')];
    const panels = [...document.querySelectorAll('[data-mcp-panel]')];
    if (!tabs.length) return;
    function select(name) {
        if (!tabs.some(tab => tab.dataset.mcpTab === name)) name = 'access';
        tabs.forEach(tab => {
            const active = tab.dataset.mcpTab === name;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-pressed', String(active));
        });
        panels.forEach(panel => panel.hidden = panel.dataset.mcpPanel !== name);
        history.replaceState(null, '', location.pathname + location.search + '#mcp-' + name);
    }
    tabs.forEach(tab => tab.addEventListener('click', () => select(tab.dataset.mcpTab)));
    select(document.getElementById('new-credentials') ? 'access' : location.hash.replace('#mcp-', '') || 'access');
    document.querySelectorAll('[data-mcp-permission-preset]').forEach(button => button.addEventListener('click', () => {
        button.closest('form').querySelectorAll('input[name="scopes[]"]').forEach(input => input.checked = button.dataset.mcpPermissionPreset === 'all' || input.value === 'musedock-cms:read');
    }));
    document.querySelectorAll('[data-mcp-go-connection]').forEach(button => button.addEventListener('click', () => {
        document.getElementById('mcp-create-access')?.close();
        select('connection');
        document.querySelector('[data-mcp-panel="connection"]')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }));
    document.getElementById('mcp-new-access')?.addEventListener('click', () => document.getElementById('mcp-create-access').showModal());
})();

(function () {
    document.querySelectorAll('[data-mcp-sites-editor]').forEach(editor => {
        const dialog = editor.querySelector('dialog');
        const summary = editor.querySelector('[data-mcp-sites-summary]');
        const inputs = [...editor.querySelectorAll('input[name="sites[]"]')];
        function render() {
            summary.replaceChildren();
            inputs.filter(input => input.checked).forEach(input => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'btn btn-sm btn-outline-secondary';
                button.textContent = input.dataset.domain + ' ×';
                button.setAttribute('aria-label', 'Quitar ' + input.dataset.domain);
                button.addEventListener('click', () => { input.checked = false; render(); });
                summary.append(button);
            });
            if (!summary.childElementCount) summary.textContent = 'Ningún sitio seleccionado.';
        }
        editor.querySelector('[data-mcp-sites-open]').addEventListener('click', () => dialog.showModal());
        editor.querySelectorAll('[data-mcp-sites-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
        editor.querySelector('[data-mcp-sites-search]').addEventListener('input', event => {
            const query = event.target.value.trim().toLowerCase();
            inputs.forEach(input => { input.closest('label').hidden = !input.dataset.domain.toLowerCase().includes(query); });
        });
        inputs.forEach(input => input.addEventListener('change', render));
        render();
    });
})();
