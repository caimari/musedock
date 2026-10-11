(function () {
    const dialog = document.getElementById('mcp-progress');
    if (!dialog) return;
    const forms = [...document.querySelectorAll('[data-mcp-request-form]')];
    const summary = document.getElementById('mcp-progress-summary');
    const list = document.getElementById('mcp-progress-list');
    const spinner = document.getElementById('mcp-progress-spinner');
    const close = document.getElementById('mcp-progress-close');
    let running = false;
    dialog.addEventListener('cancel', event => { if (running) event.preventDefault(); });
    close.addEventListener('click', () => location.reload());
    async function run(jobs) {
        if (running) return;
        running = true;
        spinner.hidden = false;
        close.disabled = true;
        dialog.showModal();
        list.replaceChildren();
        const rows = jobs.map(job => {
            const row = document.createElement('li');
            row.textContent = job.form.elements.request.value.slice(0, 12) + ' — En cola';
            list.append(row);
            return row;
        });
        let completed = 0;
        let failures = 0;
        for (let i = 0; i < jobs.length; i++) {
            const { form, action } = jobs[i];
            rows[i].textContent = form.elements.request.value.slice(0, 12) + ' — Procesando; esperando resultado del CMS';
            summary.textContent = 'Solicitud ' + (i + 1) + ' de ' + jobs.length + '. Finalizadas: ' + completed + '.';
            const data = new FormData(form);
            data.set('action', action);
            // Controls named "action" shadow the form.action DOM property.
            const endpoint = new URL(form.getAttribute('action') || location.href, location.href);
            endpoint.hash = '';
            try {
                const response = await fetch(endpoint.href, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-MCP-Progress': '1', 'Accept': 'application/json' } });
                if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Respuesta no verificable. Revisa el estado antes de volver a aprobar.');
                let result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo completar la solicitud.');
                if (result.result?.status === 'queued') {
                    for (;;) {
                        rows[i].textContent = form.elements.request.value.slice(0, 12) + ' — ' + (result.status === 'executing' ? 'En ejecución en el servidor' : 'En cola interna; esperando al procesador');
                        await new Promise(resolve => setTimeout(resolve, 3000));
                        const poll = new FormData(form);
                        poll.set('action', 'operation_status');
                        const response = await fetch(endpoint.href, { method: 'POST', body: poll, credentials: 'same-origin', headers: { 'X-MCP-Progress': '1', 'Accept': 'application/json' } });
                        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('No se pudo consultar el estado. La cola permanece en el servidor; recarga para revisarla.');
                        result = await response.json();
                        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo consultar el estado.');
                        if (result.status === 'completed') break;
                        if (result.status === 'failed') throw new Error(result.error || 'La operación falló.');
                    }
                }
                rows[i].textContent = form.elements.request.value.slice(0, 12) + (action === 'approve' ? ' — Completada' : ' — Rechazada');
                if (result.result?.media?.url) {
                    const link = document.createElement('a');
                    link.href = result.result.media.url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = ' Ver imagen';
                    rows[i].append(link);
                }
                form.querySelectorAll('button').forEach(button => button.disabled = true);
            } catch (error) {
                failures++;
                rows[i].textContent = form.elements.request.value.slice(0, 12) + ' — ' + error.message;
            }
            completed++;
        }
        running = false;
        spinner.hidden = true;
        close.disabled = false;
        summary.textContent = 'Cola finalizada: ' + (completed - failures) + ' correctas; ' + failures + ' con incidencias.';
    }
    forms.forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        run([{ form, action: event.submitter?.value || 'approve' }]);
    }));
    document.getElementById('mcp-approve-all')?.addEventListener('click', () => run(forms.filter(form => !form.querySelector('button').disabled).map(form => ({ form, action: 'approve' }))));
})();
