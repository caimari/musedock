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
