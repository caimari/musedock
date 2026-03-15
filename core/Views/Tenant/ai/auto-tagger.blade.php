@extends('layouts.app')

@section('title', 'Auto-Categorizar y Etiquetar')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="bi bi-tags"></i> Auto-Categorizar y Etiquetar</h4>
            <a href="/{{ admin_path() }}/ai/settings" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Configuracion IA
            </a>
        </div>

        {{-- Estadísticas --}}
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body py-3">
                        <h3 class="mb-0 text-primary">{{ $totalPosts }}</h3>
                        <small class="text-muted">Posts publicados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body py-3">
                        <h3 class="mb-0 text-success">{{ $totalCategories }}</h3>
                        <small class="text-muted">Categorias</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body py-3">
                        <h3 class="mb-0 text-info">{{ $totalTags }}</h3>
                        <small class="text-muted">Tags</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel principal --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-robot"></i> Enriquecer Taxonomia con IA</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Esta herramienta analiza el contenido de tus posts publicados y usa inteligencia artificial para
                    sugerir categorias y tags relevantes que falten o que enriquezcan la organizacion de tu blog.
                </p>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Alcance del analisis</label>
                        <select class="form-select" id="autoTagScope">
                            <option value="all">Todos los posts publicados</option>
                            <option value="untagged">Solo posts con pocas categorias/tags (< 4)</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary" id="btnPreview" onclick="runAutoTag(true)">
                        <span class="btn-text"><i class="bi bi-eye"></i> Previsualizar Sugerencias</span>
                        <span class="btn-loading d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Analizando con IA...
                        </span>
                    </button>
                    <button type="button" class="btn btn-primary" id="btnApply" onclick="runAutoTag(false)">
                        <span class="btn-text"><i class="bi bi-magic"></i> Aplicar Directamente</span>
                        <span class="btn-loading d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Aplicando cambios...
                        </span>
                    </button>
                </div>

                {{-- Resultados --}}
                <div id="autoTagResults" class="d-none"></div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = '<?= csrf_token() ?>';

function toggleBtnSpinner(btn, show) {
    const text = btn.querySelector('.btn-text');
    const loading = btn.querySelector('.btn-loading');
    if (show) {
        text?.classList.add('d-none');
        loading?.classList.remove('d-none');
        btn.disabled = true;
    } else {
        text?.classList.remove('d-none');
        loading?.classList.add('d-none');
        btn.disabled = false;
    }
}

async function runAutoTag(dryRun) {
    const btn = dryRun ? document.getElementById('btnPreview') : document.getElementById('btnApply');
    const resultsContainer = document.getElementById('autoTagResults');
    const scope = document.getElementById('autoTagScope').value;

    toggleBtnSpinner(btn, true);

    if (!dryRun) {
        const confirm = await Swal.fire({
            icon: 'question',
            title: 'Aplicar auto-tagging',
            text: 'Se analizaran los posts y se aplicaran directamente TODAS las categorias y tags sugeridos (sin previsualizar). ¿Continuar?',
            showCancelButton: true,
            confirmButtonText: 'Si, aplicar todo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d'
        });
        if (!confirm.isConfirmed) {
            toggleBtnSpinner(btn, false);
            return;
        }
    }

    try {
        const response = await fetch('/{{ admin_path() }}/ai/auto-tagger/run', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                _csrf: csrfToken,
                dry_run: dryRun,
                scope: scope
            })
        });
        const data = await response.json();

        resultsContainer.classList.remove('d-none');

        if (data.success) {
            if (dryRun && data.suggestions) {
                renderPreview(data, resultsContainer);
            } else if (!dryRun && data.applied) {
                renderApplied(data, resultsContainer);
            }
        } else {
            let errHtml = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ${data.message || 'Error desconocido.'}`;
            if (data.raw) {
                errHtml += `<br><details class="mt-2"><summary>Ver respuesta de la IA</summary><pre class="mt-1 p-2 bg-dark text-light small" style="max-height:300px;overflow:auto;white-space:pre-wrap;">${data.raw.replace(/</g,'&lt;')}</pre></details>`;
            }
            errHtml += '</div>';
            resultsContainer.innerHTML = errHtml;
        }
    } catch (error) {
        resultsContainer.classList.remove('d-none');
        resultsContainer.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error de conexion con el servidor.</div>';
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

function renderPreview(data, container) {
    let html = '<div class="alert alert-info mb-2"><i class="bi bi-info-circle"></i> <strong>Previsualizacion</strong> — Desmarca lo que no quieras aplicar y pulsa "Aplicar Seleccionados".</div>';
    html += `<p class="text-muted small">Modelo: <code>${data.model || 'N/A'}</code> | Tokens: ${data.tokens_used || 0}</p>`;

    const suggestions = data.suggestions;
    let hasAny = false;

    if (!suggestions || suggestions.length === 0) {
        html += '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Todos los posts ya tienen una taxonomia completa.</div>';
        container.innerHTML = html;
        return;
    }

    suggestions.forEach((s, si) => {
        const cats = s.add_categories || [];
        const tags = s.add_tags || [];
        if (cats.length === 0 && tags.length === 0) return;
        hasAny = true;

        html += `<div class="card mb-2"><div class="card-body py-2 px-3">`;
        html += `<strong>Post #${s.post_id}: ${s.post_title || ''}</strong>`;

        if (cats.length > 0) {
            html += '<div class="mt-1"><small class="text-muted fw-semibold">Categorias:</small><br>';
            cats.forEach((c, ci) => {
                const id = `cat_${si}_${ci}`;
                html += `<div class="form-check form-check-inline">
                    <input class="form-check-input at-check" type="checkbox" id="${id}" checked
                           data-post="${s.post_id}" data-type="category" data-name="${c.name}" data-slug="${c.slug}" data-new="${c.is_new}">
                    <label class="form-check-label" for="${id}">
                        <span class="badge bg-primary">${c.name}</span>${c.is_new ? ' <small class="text-warning">(nueva)</small>' : ''}
                    </label>
                </div>`;
            });
            html += '</div>';
        }

        if (tags.length > 0) {
            html += '<div class="mt-1"><small class="text-muted fw-semibold">Tags:</small><br>';
            tags.forEach((t, ti) => {
                const id = `tag_${si}_${ti}`;
                html += `<div class="form-check form-check-inline">
                    <input class="form-check-input at-check" type="checkbox" id="${id}" checked
                           data-post="${s.post_id}" data-type="tag" data-name="${t.name}" data-slug="${t.slug}" data-new="${t.is_new}">
                    <label class="form-check-label" for="${id}">
                        <span class="badge bg-secondary">${t.name}</span>${t.is_new ? ' <small class="text-warning">(nuevo)</small>' : ''}
                    </label>
                </div>`;
            });
            html += '</div>';
        }
        html += '</div></div>';
    });

    if (!hasAny) {
        html += '<div class="alert alert-success"><i class="bi bi-check-circle"></i> La IA no encontro sugerencias adicionales.</div>';
    } else {
        html += `<div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllChecks(true)"><i class="bi bi-check-all"></i> Marcar todos</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllChecks(false)"><i class="bi bi-x-lg"></i> Desmarcar todos</button>
            <button type="button" class="btn btn-sm btn-success" id="btnApplySelected" onclick="applySelected()">
                <span class="btn-text"><i class="bi bi-check-circle"></i> Aplicar Seleccionados</span>
                <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-1"></span> Aplicando...</span>
            </button>
        </div>`;
    }

    container.innerHTML = html;
}

function toggleAllChecks(state) {
    document.querySelectorAll('.at-check').forEach(cb => cb.checked = state);
}

async function applySelected() {
    const checks = document.querySelectorAll('.at-check:checked');
    if (checks.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Nada seleccionado', text: 'Marca al menos una sugerencia.', confirmButtonColor: '#0d6efd' });
        return;
    }

    const postMap = {};
    checks.forEach(cb => {
        const postId = parseInt(cb.dataset.post);
        if (!postMap[postId]) postMap[postId] = { post_id: postId, add_categories: [], add_tags: [] };
        const item = { name: cb.dataset.name, slug: cb.dataset.slug, is_new: cb.dataset.new === 'true' };
        if (cb.dataset.type === 'category') postMap[postId].add_categories.push(item);
        else postMap[postId].add_tags.push(item);
    });

    const btn = document.getElementById('btnApplySelected');
    toggleBtnSpinner(btn, true);

    try {
        const response = await fetch('/{{ admin_path() }}/ai/auto-tagger/run', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: csrfToken, dry_run: false, suggestions: Object.values(postMap) })
        });
        const data = await response.json();
        const container = document.getElementById('autoTagResults');

        if (data.success && data.applied) {
            renderApplied(data, container);
        } else {
            container.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ${data.message || 'Error.'}</div>`;
        }
    } catch (error) {
        document.getElementById('autoTagResults').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error de conexion.</div>';
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

function renderApplied(data, container) {
    const a = data.applied;
    let html = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> <strong>Cambios aplicados correctamente</strong></div>';
    html += '<div class="row mb-3">';
    html += `<div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><h4 class="mb-0">${a.categories_created}</h4><small>Categorias creadas</small></div></div></div>`;
    html += `<div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><h4 class="mb-0">${a.tags_created}</h4><small>Tags creados</small></div></div></div>`;
    html += `<div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><h4 class="mb-0">${a.category_links}</h4><small>Links de categoria</small></div></div></div>`;
    html += `<div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><h4 class="mb-0">${a.tag_links}</h4><small>Links de tag</small></div></div></div>`;
    html += '</div>';
    html += `<p class="text-muted small">Modelo: <code>${data.model || 'N/A'}</code> | Tokens: ${data.tokens_used || 0}</p>`;
    if (a.details && a.details.length > 0) {
        html += '<h6>Detalle por post:</h6><ul class="list-group list-group-flush small">';
        a.details.forEach(d => {
            let desc = [];
            if (d.categories_added.length) desc.push(`+cats: ${d.categories_added.join(', ')}`);
            if (d.tags_added.length) desc.push(`+tags: ${d.tags_added.join(', ')}`);
            html += `<li class="list-group-item"><strong>Post #${d.post_id}: ${d.post_title || ''}</strong> — ${desc.join(' | ')}</li>`;
        });
        html += '</ul>';
    }
    container.innerHTML = html;
}
</script>
@endpush
