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
    function confirmRemoval(domain) {
        return new Promise(resolve => {
            const modal = document.createElement('dialog');
            modal.className = 'mcp-sites-dialog';
            modal.setAttribute('aria-label', 'Confirmar retirada de sitio');
            modal.innerHTML = '<h3 class="h5">¿Quitar este sitio del acceso?</h3><p></p><div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-outline-secondary" data-cancel>Cancelar</button><button type="button" class="btn btn-danger" data-confirm>Quitar sitio</button></div>';
            modal.querySelector('p').textContent = domain + ' dejará de estar seleccionado. El cambio se aplicará cuando guardes el acceso; no elimina el sitio ni sus artículos.';
            const finish = value => { modal.close(); modal.remove(); resolve(value); };
            modal.querySelector('[data-cancel]').addEventListener('click', () => finish(false));
            modal.querySelector('[data-confirm]').addEventListener('click', () => finish(true));
            modal.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
            document.body.append(modal); modal.showModal();
        });
    }
    document.querySelectorAll('[data-mcp-sites-editor]').forEach(editor => {
        const dialog = editor.querySelector('dialog');
        const summary = editor.querySelector('[data-mcp-sites-summary]');
        const inputs = [...editor.querySelectorAll('input[name="sites[]"]')];
        function render() {
            summary.replaceChildren();
            inputs.filter(input => input.checked).forEach(input => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'mcp-site-chip';
                const name = document.createElement('span'); name.textContent = input.dataset.domain;
                const remove = document.createElement('span'); remove.className = 'mcp-site-remove'; remove.textContent = '×'; remove.setAttribute('aria-hidden', 'true');
                button.append(name, remove);
                button.setAttribute('aria-label', 'Quitar ' + input.dataset.domain);
                button.addEventListener('click', async () => { if (await confirmRemoval(input.dataset.domain)) { input.checked = false; render(); } });
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
        inputs.forEach(input => input.addEventListener('change', async () => {
            if (!input.checked) {
                input.checked = true;
                if (await confirmRemoval(input.dataset.domain)) input.checked = false;
            }
            render();
        }));
        render();
    });
})();
