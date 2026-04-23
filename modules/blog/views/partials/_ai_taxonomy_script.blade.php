{{-- AI Auto-Taxonomy + Manual Paste Script - Include inside DOMContentLoaded --}}

// === Shared helper: add items to select and mark selected ===
function _applyTaxonomyToSelects(categories, tags) {
    const catSelect = document.getElementById('categories');
    const tagSelect = document.getElementById('tags');

    (categories || []).forEach(item => {
        if (!item.id) return;
        let option = catSelect.querySelector(`option[value="${item.id}"]`);
        if (!option) {
            option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            catSelect.appendChild(option);
        }
        option.selected = true;
    });

    (tags || []).forEach(item => {
        if (!item.id) return;
        let option = tagSelect.querySelector(`option[value="${item.id}"]`);
        if (!option) {
            option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            tagSelect.appendChild(option);
        }
        option.selected = true;
    });
}

// === 1. AI Auto-Taxonomy ===
(function() {
    const btnAiTaxonomy = document.getElementById('btn-ai-taxonomy');
    if (!btnAiTaxonomy) return;

    btnAiTaxonomy.addEventListener('click', async function() {
        const titleInput = document.getElementById('title-input') || document.getElementById('title') || document.querySelector('[name="title"]');
        const title = titleInput ? titleInput.value.trim() : '';

        let content = '';
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
            content = tinymce.activeEditor.getContent({ format: 'text' }).substring(0, 2000);
        } else {
            const contentArea = document.getElementById('content') || document.querySelector('[name="content"]');
            if (contentArea) content = contentArea.value.substring(0, 2000);
        }

        if (!title && !content) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Sin contenido', 'Escribe un título y contenido antes de generar categorías y tags.', 'warning');
            } else {
                alert('Escribe un título y contenido antes de generar categorías y tags.');
            }
            return;
        }

        const catSelect = document.getElementById('categories');
        const tagSelect = document.getElementById('tags');
        const currentCats = catSelect ? Array.from(catSelect.selectedOptions).map(o => parseInt(o.value)) : [];
        const currentTags = tagSelect ? Array.from(tagSelect.selectedOptions).map(o => parseInt(o.value)) : [];

        if (typeof Swal === 'undefined') {
            alert('SweetAlert2 es necesario para esta función.');
            return;
        }

        const resultsDiv = document.getElementById('ai-taxonomy-results');
        if (resultsDiv) {
            resultsDiv.innerHTML = '';
            resultsDiv.style.display = 'none';
        }

        const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

        // Helper para construir el HTML del contenido del modal con checkboxes
        const buildResultsHtml = (cats, tags) => {
            let html = '<div class="text-start">';
            html += '<p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i> Se añadirán a las ya seleccionadas. Desmarca las que no quieras.</p>';

            if (cats.length > 0) {
                html += '<div class="mb-3">';
                html += '<div class="d-flex align-items-center justify-content-between mb-2">';
                html += `<label class="form-label fw-semibold small mb-0"><i class="bi bi-folder me-1"></i> Categorías (${cats.length})</label>`;
                html += '<div class="d-flex gap-1">';
                html += '<button type="button" class="btn btn-sm btn-link p-0 small" onclick="document.querySelectorAll(\'.ai-cat-check\').forEach(c=>c.checked=true)">Todas</button>';
                html += '<span class="text-muted small">·</span>';
                html += '<button type="button" class="btn btn-sm btn-link p-0 small text-muted" onclick="document.querySelectorAll(\'.ai-cat-check\').forEach(c=>c.checked=false)">Ninguna</button>';
                html += '</div></div>';
                html += '<div class="border rounded p-2" style="max-height:200px;overflow-y:auto;background:#f8f9fa;">';
                cats.forEach((cat, i) => {
                    const badge = cat.is_new
                        ? '<span class="badge bg-success ms-1" style="font-size:0.65em">nueva</span>'
                        : '<span class="badge bg-secondary ms-1" style="font-size:0.65em">existente</span>';
                    html += `<div class="form-check mb-1">
                        <input class="form-check-input ai-cat-check" type="checkbox" checked value="${cat.id}" id="ai-cat-${i}" data-name="${escapeHtml(cat.name)}">
                        <label class="form-check-label small" for="ai-cat-${i}">${escapeHtml(cat.name)}${badge}</label>
                    </div>`;
                });
                html += '</div></div>';
            }

            if (tags.length > 0) {
                html += '<div class="mb-2">';
                html += '<div class="d-flex align-items-center justify-content-between mb-2">';
                html += `<label class="form-label fw-semibold small mb-0"><i class="bi bi-tags me-1"></i> Tags (${tags.length})</label>`;
                html += '<div class="d-flex gap-1">';
                html += '<button type="button" class="btn btn-sm btn-link p-0 small" onclick="document.querySelectorAll(\'.ai-tag-check\').forEach(c=>c.checked=true)">Todos</button>';
                html += '<span class="text-muted small">·</span>';
                html += '<button type="button" class="btn btn-sm btn-link p-0 small text-muted" onclick="document.querySelectorAll(\'.ai-tag-check\').forEach(c=>c.checked=false)">Ninguno</button>';
                html += '</div></div>';
                html += '<div class="border rounded p-2" style="max-height:240px;overflow-y:auto;background:#f8f9fa;">';
                tags.forEach((tag, i) => {
                    const badge = tag.is_new
                        ? '<span class="badge bg-success ms-1" style="font-size:0.65em">nuevo</span>'
                        : '<span class="badge bg-secondary ms-1" style="font-size:0.65em">existente</span>';
                    html += `<div class="form-check mb-1">
                        <input class="form-check-input ai-tag-check" type="checkbox" checked value="${tag.id}" id="ai-tag-${i}" data-name="${escapeHtml(tag.name)}">
                        <label class="form-check-label small" for="ai-tag-${i}">${escapeHtml(tag.name)}${badge}</label>
                    </div>`;
                });
                html += '</div></div>';
            }

            html += '</div>';
            return html;
        };

        // === ABRIR MODAL INMEDIATAMENTE CON SPINNER ===
        const originalHTML = btnAiTaxonomy.innerHTML;
        btnAiTaxonomy.disabled = true;

        const loadingHtml = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status">
                    <span class="visually-hidden">Analizando...</span>
                </div>
                <p class="mb-1 fw-semibold">Analizando contenido con IA...</p>
                <p class="text-muted small mb-0">Esto puede tardar unos segundos.</p>
            </div>
        `;

        // Abrir modal con spinner y lanzar fetch en paralelo
        let apiResultPromise = fetch('/api/ai/blog/suggest-taxonomy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                title: title,
                content: content,
                current_categories: currentCats,
                current_tags: currentTags,
                tenant_id: document.getElementById('post-tenant-id')?.value || '',
                _csrf: document.querySelector('[name="_csrf"]')?.value || '{{ csrf_token() }}'
            })
        }).then(async (r) => {
            // Leer como texto primero para poder manejar respuestas no-JSON (errores 500 con HTML)
            const text = await r.text();
            if (!text || text.trim() === '') {
                return { success: false, message: 'El servidor devolvió una respuesta vacía (HTTP ' + r.status + '). Revisa los logs del servidor.' };
            }
            try {
                return JSON.parse(text);
            } catch (parseErr) {
                // No es JSON válido — probablemente HTML de error
                console.error('Respuesta no-JSON del servidor:', text.substring(0, 500));
                const snippet = text.substring(0, 200).replace(/<[^>]+>/g, '').trim();
                return {
                    success: false,
                    message: 'El servidor devolvió una respuesta inválida (HTTP ' + r.status + ').' + (snippet ? ' Detalle: ' + snippet : '')
                };
            }
        }).catch(e => ({ success: false, message: e.message || 'Error de conexión' }));

        Swal.fire({
            title: '<i class="bi bi-magic me-1"></i> Sugerencias de IA',
            html: loadingHtml,
            width: 550,
            showConfirmButton: false,
            showCancelButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: { popup: 'swal2-taxonomy-modal' },
            didOpen: async () => {
                try {
                    const result = await apiResultPromise;

                    if (!result.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.message || 'No se pudo generar la taxonomía con IA.',
                        });
                        return;
                    }

                    const cats = result.categories || [];
                    const tags = result.tags || [];

                    if (cats.length === 0 && tags.length === 0) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Sin sugerencias',
                            text: 'La IA no encontró categorías ni tags adicionales para sugerir.',
                        });
                        return;
                    }

                    // Reemplazar el contenido del modal con los resultados
                    Swal.update({
                        html: buildResultsHtml(cats, tags),
                        showConfirmButton: true,
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Añadir seleccionados',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#0d6efd',
                        allowOutsideClick: true,
                        allowEscapeKey: true,
                    });
                } catch (error) {
                    console.error('Error AI Taxonomy:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'No se pudo generar la taxonomía con IA.',
                    });
                }
            },
            preConfirm: () => {
                const selectedCats = [];
                document.querySelectorAll('.ai-cat-check:checked').forEach(cb => {
                    selectedCats.push({ id: cb.value, name: cb.dataset.name });
                });
                const selectedTags = [];
                document.querySelectorAll('.ai-tag-check:checked').forEach(cb => {
                    selectedTags.push({ id: cb.value, name: cb.dataset.name });
                });
                if (selectedCats.length === 0 && selectedTags.length === 0) {
                    Swal.showValidationMessage('Selecciona al menos una categoría o tag');
                    return false;
                }
                return { categories: selectedCats, tags: selectedTags };
            }
        }).then((swalResult) => {
            btnAiTaxonomy.disabled = false;
            btnAiTaxonomy.innerHTML = originalHTML;

            if (swalResult.isConfirmed && swalResult.value) {
                _applyTaxonomyToSelects(swalResult.value.categories, swalResult.value.tags);
                Swal.fire({
                    icon: 'success',
                    title: 'Taxonomía aplicada',
                    text: `${swalResult.value.categories.length} categorías y ${swalResult.value.tags.length} tags añadidos.`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    });
})();

// === 2. Manual Paste Taxonomy ===
(function() {
    const btnPaste = document.getElementById('btn-paste-taxonomy');
    if (!btnPaste) return;

    btnPaste.addEventListener('click', function() {
        if (typeof Swal === 'undefined') {
            alert('SweetAlert2 es necesario para esta función.');
            return;
        }

        Swal.fire({
            title: 'Pegar categorías y tags',
            html: `
                <div class="text-start">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Categorías <small class="text-muted">(una por línea o separadas por comas)</small></label>
                        <textarea id="swal-paste-categories" class="form-control form-control-sm" rows="4" placeholder="Modelos de lenguaje, Investigación IA, Open Source&#10;o una por línea"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Tags <small class="text-muted">(uno por línea o separados por comas)</small></label>
                        <textarea id="swal-paste-tags" class="form-control form-control-sm" rows="6" placeholder="Fast-dLLM, NVIDIA Research, LLM Difusión, Qwen2.5, Open Source LLM"></textarea>
                    </div>
                    <small class="text-muted">Si no existen, se crearán automáticamente. No se crearán duplicados.</small>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Crear y asignar',
            cancelButtonText: 'Cancelar',
            width: 500,
            customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-outline-secondary' },
            buttonsStyling: false,
            focusConfirm: false,
            preConfirm: () => {
                const catText = document.getElementById('swal-paste-categories').value.trim();
                const tagText = document.getElementById('swal-paste-tags').value.trim();

                if (!catText && !tagText) {
                    Swal.showValidationMessage('Escribe al menos una categoría o un tag');
                    return false;
                }

                // Acepta líneas y/o comas como separador
                const parseLines = (text) => text.split(/[\n,]/).map(l => l.trim()).filter(l => l.length > 0);

                return {
                    categories: parseLines(catText),
                    tags: parseLines(tagText)
                };
            }
        }).then(async (result) => {
            if (!result.isConfirmed || !result.value) return;

            // Show processing
            Swal.fire({
                title: 'Procesando...',
                html: 'Creando y vinculando categorías y tags...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const response = await fetch('/api/blog/batch-taxonomy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        categories: result.value.categories,
                        tags: result.value.tags,
                        tenant_id: document.getElementById('post-tenant-id')?.value || '',
                        _csrf: document.querySelector('[name="_csrf"]')?.value || '{{ csrf_token() }}'
                    })
                });

                const data = await response.json();
                if (!data.success) throw new Error(data.message || 'Error al procesar');

                // Apply to selects
                _applyTaxonomyToSelects(data.categories, data.tags);

                // Build result summary
                const newCats = (data.categories || []).filter(c => c.is_new).length;
                const existCats = (data.categories || []).filter(c => !c.is_new).length;
                const newTags = (data.tags || []).filter(t => t.is_new).length;
                const existTags = (data.tags || []).filter(t => !t.is_new).length;

                let summary = '';
                if (newCats > 0) summary += `${newCats} categoría(s) creada(s). `;
                if (existCats > 0) summary += `${existCats} categoría(s) existente(s) vinculada(s). `;
                if (newTags > 0) summary += `${newTags} tag(s) creado(s). `;
                if (existTags > 0) summary += `${existTags} tag(s) existente(s) vinculado(s). `;

                Swal.fire({
                    icon: 'success',
                    title: 'Taxonomía aplicada',
                    text: summary || 'Todo listo.',
                    timer: 3000,
                    showConfirmButton: false
                });

            } catch (error) {
                console.error('Error batch taxonomy:', error);
                Swal.fire('Error', error.message || 'No se pudieron procesar las categorías/tags.', 'error');
            }
        });
    });
})();
