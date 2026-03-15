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

        const originalHTML = btnAiTaxonomy.innerHTML;
        btnAiTaxonomy.disabled = true;
        btnAiTaxonomy.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analizando...';

        const resultsDiv = document.getElementById('ai-taxonomy-results');

        try {
            const response = await fetch('/api/ai/blog/suggest-taxonomy', {
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
            });

            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Error al generar taxonomía');

            const cats = result.categories || [];
            const tags = result.tags || [];

            if (cats.length === 0 && tags.length === 0) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Sin sugerencias', 'La IA no encontró categorías ni tags adicionales para sugerir.', 'info');
                }
                return;
            }

            let html = '<div class="border rounded p-2 bg-light">';
            html += '<small class="fw-bold d-block mb-2"><i class="bi bi-magic me-1"></i> Sugerencias de IA:</small>';
            html += '<small class="text-muted d-block mb-2">Se añadirán a las ya seleccionadas (no se pierde nada).</small>';

            if (cats.length > 0) {
                html += '<small class="text-muted d-block mb-1">Categorías:</small>';
                cats.forEach((cat, i) => {
                    const badge = cat.is_new
                        ? '<span class="badge bg-success ms-1" style="font-size:0.65em">nueva</span>'
                        : '<span class="badge bg-secondary ms-1" style="font-size:0.65em">existente</span>';
                    html += `<div class="form-check form-check-sm">
                        <input class="form-check-input ai-cat-check" type="checkbox" checked value="${cat.id}" id="ai-cat-${i}" data-name="${cat.name.replace(/"/g, '&quot;')}">
                        <label class="form-check-label small" for="ai-cat-${i}">${cat.name}${badge}</label>
                    </div>`;
                });
            }

            if (tags.length > 0) {
                html += '<small class="text-muted d-block mb-1 mt-2">Tags:</small>';
                tags.forEach((tag, i) => {
                    const badge = tag.is_new
                        ? '<span class="badge bg-success ms-1" style="font-size:0.65em">nuevo</span>'
                        : '<span class="badge bg-secondary ms-1" style="font-size:0.65em">existente</span>';
                    html += `<div class="form-check form-check-sm">
                        <input class="form-check-input ai-tag-check" type="checkbox" checked value="${tag.id}" id="ai-tag-${i}" data-name="${tag.name.replace(/"/g, '&quot;')}">
                        <label class="form-check-label small" for="ai-tag-${i}">${tag.name}${badge}</label>
                    </div>`;
                });
            }

            html += '<div class="mt-2 d-flex gap-2">';
            html += '<button type="button" class="btn btn-sm btn-primary" id="btn-apply-ai-taxonomy"><i class="bi bi-check-lg me-1"></i> Añadir seleccionados</button>';
            html += '<button type="button" class="btn btn-sm btn-outline-secondary" id="btn-cancel-ai-taxonomy">Cancelar</button>';
            html += '</div></div>';

            if (resultsDiv) {
                resultsDiv.innerHTML = html;
                resultsDiv.style.display = 'block';
            }

            document.getElementById('btn-apply-ai-taxonomy').addEventListener('click', function() {
                const selectedCats = [];
                document.querySelectorAll('.ai-cat-check:checked').forEach(cb => {
                    selectedCats.push({ id: cb.value, name: cb.dataset.name });
                });
                const selectedTags = [];
                document.querySelectorAll('.ai-tag-check:checked').forEach(cb => {
                    selectedTags.push({ id: cb.value, name: cb.dataset.name });
                });
                _applyTaxonomyToSelects(selectedCats, selectedTags);
                resultsDiv.style.display = 'none';
                resultsDiv.innerHTML = '';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Taxonomía aplicada', text: 'Las categorías y tags se han seleccionado.', timer: 2000, showConfirmButton: false });
                }
            });

            document.getElementById('btn-cancel-ai-taxonomy').addEventListener('click', function() {
                resultsDiv.style.display = 'none';
                resultsDiv.innerHTML = '';
            });

        } catch (error) {
            console.error('Error AI Taxonomy:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', error.message || 'No se pudo generar la taxonomía con IA.', 'error');
            } else {
                alert('Error: ' + (error.message || 'No se pudo generar la taxonomía'));
            }
        } finally {
            btnAiTaxonomy.disabled = false;
            btnAiTaxonomy.innerHTML = originalHTML;
        }
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
                        <label class="form-label fw-semibold small">Categorías <small class="text-muted">(una por línea)</small></label>
                        <textarea id="swal-paste-categories" class="form-control form-control-sm" rows="4" placeholder="Modelos de lenguaje&#10;Investigación IA&#10;Open Source&#10;Análisis técnico"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Tags <small class="text-muted">(una por línea)</small></label>
                        <textarea id="swal-paste-tags" class="form-control form-control-sm" rows="6" placeholder="Fast-dLLM&#10;NVIDIA Research&#10;LLM Difusión&#10;Qwen2.5&#10;Open Source LLM"></textarea>
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

                const parseLines = (text) => text.split('\n').map(l => l.trim()).filter(l => l.length > 0);

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
