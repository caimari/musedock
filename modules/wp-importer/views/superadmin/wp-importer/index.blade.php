@extends('layouts.app')

@section('title', __('wp_importer.title'))

@push('styles')
<style>
    .wpi-wizard { max-width: 900px; margin: 0 auto; }
    .wpi-step { display: none; }
    .wpi-step.active { display: block; }
    .wpi-steps-bar { display: flex; gap: 0; margin-bottom: 2rem; }
    .wpi-steps-bar .step-indicator {
        flex: 1; text-align: center; padding: 0.75rem 0.5rem;
        background: #f0f0f0; border-bottom: 3px solid #ddd;
        font-size: 0.85rem; color: #999; position: relative;
    }
    .wpi-steps-bar .step-indicator.active { background: #e8f0fe; border-color: #4361ee; color: #4361ee; font-weight: 600; }
    .wpi-steps-bar .step-indicator.completed { background: #e8f5e9; border-color: #28a745; color: #28a745; }
    .wpi-site-info { background: #f8f9fa; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .wpi-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin: 1.5rem 0; }
    .wpi-summary-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem; text-align: center; }
    .wpi-summary-card .count { font-size: 2rem; font-weight: 700; color: #4361ee; }
    .wpi-summary-card .label { font-size: 0.85rem; color: #666; margin-top: 0.25rem; }
    .wpi-conflicts { margin: 1.5rem 0; }
    .wpi-conflicts table { font-size: 0.85rem; }
    .wpi-import-options .form-check { padding: 0.75rem 1rem 0.75rem 2.5rem; background: #f8f9fa; border-radius: 6px; margin-bottom: 0.5rem; }
    .wpi-import-options .form-check:hover { background: #e8f0fe; }
    .wpi-progress-section { margin: 1.5rem 0; }
    .wpi-progress-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
    .wpi-progress-item .status-icon { width: 24px; text-align: center; }
    .wpi-progress-item .status-icon.pending { color: #999; }
    .wpi-progress-item .status-icon.running { color: #4361ee; }
    .wpi-progress-item .status-icon.done { color: #28a745; }
    .wpi-results { margin: 1.5rem 0; }
    .wpi-result-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem 1.5rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; }
    .wpi-result-card .imported { color: #28a745; font-weight: 600; }
    .wpi-result-card .skipped { color: #ffc107; font-weight: 600; }
    .wpi-error-list { max-height: 200px; overflow-y: auto; font-size: 0.8rem; background: #fff5f5; border-radius: 6px; padding: 0.75rem; margin-top: 1rem; }
    .wpi-error-list li { color: #dc3545; margin-bottom: 0.25rem; }
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-wordpress" style="color:#21759b;font-size:2.5rem;"></i> {{ __('wp_importer.title') }}</h2>
            @if(!empty($tenantInfo))
            <a href="/musedock/domain-manager/{{ $tenantInfo->id }}/edit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Volver al Domain Manager
            </a>
            @endif
        </div>

        @if(!empty($tenantInfo))
        <div class="alert alert-info d-flex align-items-center mb-3" style="font-size:0.9rem;">
            <i class="bi bi-building me-2" style="font-size:1.2rem;"></i>
            <div>
                <strong>{{ __('wp_importer.importing_for_tenant') ?: 'Importando para el tenant' }}:</strong>
                {{ $tenantInfo->name }} <span class="text-muted">({{ $tenantInfo->domain }})</span>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="wpi-wizard">

                    {{-- Steps Bar --}}
                    <div class="wpi-steps-bar">
                        <div class="step-indicator active" data-step="1">
                            <i class="bi bi-plug me-1"></i> {{ __('wp_importer.step_connect') }}
                        </div>
                        <div class="step-indicator" data-step="2">
                            <i class="bi bi-list-check me-1"></i> {{ __('wp_importer.step_select') }}
                        </div>
                        <div class="step-indicator" data-step="3">
                            <i class="bi bi-eye me-1"></i> {{ __('wp_importer.step_preview') }}
                        </div>
                        <div class="step-indicator" data-step="4">
                            <i class="bi bi-cloud-download me-1"></i> {{ __('wp_importer.step_import') }}
                        </div>
                        <div class="step-indicator" data-step="5">
                            <i class="bi bi-check-circle me-1"></i> {{ __('wp_importer.step_result') }}
                        </div>
                    </div>

                    {{-- ===== STEP 1: Conectar ===== --}}
                    <div class="wpi-step active" id="step-1">
                        <h4 class="mb-3">{{ __('wp_importer.connect') }}</h4>
                        <p class="text-muted">{{ __('wp_importer.connect_description') }}</p>

                        <div class="mb-3">
                            <label class="form-label fw-bold">{{ __('wp_importer.site_url') }} *</label>
                            <input type="url" class="form-control" id="wpi-site-url" placeholder="https://ejemplo.com" required>
                            <div class="form-text">{{ __('wp_importer.site_url_help') }}</div>
                        </div>

                        <div class="mb-3">
                            <a href="#" class="text-decoration-none" onclick="document.getElementById('wpi-auth-fields').classList.toggle('d-none'); return false;">
                                <i class="bi bi-key me-1"></i> {{ __('wp_importer.credentials_optional') }}
                            </a>
                        </div>

                        <div id="wpi-auth-fields" class="d-none">
                            <div class="alert alert-info py-2" style="font-size: 0.85rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                {{ __('wp_importer.auth_info') }}
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('wp_importer.username') }}</label>
                                    <input type="text" class="form-control" id="wpi-username" placeholder="admin">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('wp_importer.app_password') }}</label>
                                    <input type="password" class="form-control" id="wpi-app-password" placeholder="xxxx xxxx xxxx xxxx">
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-primary" id="wpi-btn-connect" onclick="wpiConnect()">
                            <i class="bi bi-plug me-1"></i> {{ __('wp_importer.connect') }}
                        </button>
                        <div id="wpi-connect-error" class="alert alert-danger mt-3 d-none"></div>
                    </div>

                    {{-- ===== STEP 2: Seleccionar ===== --}}
                    <div class="wpi-step" id="step-2">
                        <h4 class="mb-3">{{ __('wp_importer.select_content') }}</h4>

                        <div class="wpi-site-info" id="wpi-site-info"></div>
                        <div class="wpi-summary-grid" id="wpi-summary-grid"></div>

                        <h5 class="mt-4 mb-3">{{ __('wp_importer.select_content') }}</h5>
                        <div class="wpi-import-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="wpi-opt-categories" checked>
                                <label class="form-check-label" for="wpi-opt-categories">
                                    <i class="bi bi-folder me-1"></i> {{ __('wp_importer.categories') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="wpi-opt-tags" checked>
                                <label class="form-check-label" for="wpi-opt-tags">
                                    <i class="bi bi-tags me-1"></i> {{ __('wp_importer.tags') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="wpi-opt-posts" checked>
                                <label class="form-check-label" for="wpi-opt-posts">
                                    <i class="bi bi-file-text me-1"></i> {{ __('wp_importer.posts') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="wpi-opt-pages" checked>
                                <label class="form-check-label" for="wpi-opt-pages">
                                    <i class="bi bi-file-earmark me-1"></i> {{ __('wp_importer.pages') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="wpi-opt-media" checked>
                                <label class="form-check-label" for="wpi-opt-media">
                                    <i class="bi bi-image me-1"></i> {{ __('wp_importer.media') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="wpi-opt-styles" checked>
                                <label class="form-check-label" for="wpi-opt-styles">
                                    <i class="bi bi-palette me-1"></i> {{ __('wp_importer.styles') }}
                                </label>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-outline-secondary" onclick="wpiGoToStep(1)">
                                <i class="bi bi-arrow-left me-1"></i> {{ __('wp_importer.back') }}
                            </button>
                            <button class="btn btn-primary" id="wpi-btn-preview" onclick="wpiPreview()">
                                <i class="bi bi-eye me-1"></i> {{ __('wp_importer.preview') }}
                            </button>
                        </div>
                    </div>

                    {{-- ===== STEP 3: Preview ===== --}}
                    <div class="wpi-step" id="step-3">
                        <h4 class="mb-3">{{ __('wp_importer.preview') }}</h4>
                        <p class="text-muted">{{ __('wp_importer.preview_description') }}</p>

                        <div class="wpi-summary-grid" id="wpi-preview-summary"></div>

                        <div class="wpi-conflicts" id="wpi-conflicts-section" style="display:none;">
                            <h5><i class="bi bi-exclamation-triangle text-warning me-1"></i> {{ __('wp_importer.conflicts_title') }}</h5>
                            <p class="text-muted" style="font-size:0.85rem;">{{ __('wp_importer.conflicts_description') }}</p>
                            <div id="wpi-conflicts-tables"></div>

                            <div class="mt-3 p-3" style="background:#f8f9fa;border-radius:8px;">
                                <h6 class="mb-2"><i class="bi bi-arrow-repeat me-1"></i> {{ __('wp_importer.duplicate_policy') ?: 'Política de duplicados' }}</h6>
                                <p class="text-muted mb-2" style="font-size:0.82rem;">{{ __('wp_importer.duplicate_policy_desc') ?: 'Elige qué hacer con el contenido duplicado:' }}</p>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio" name="duplicate_policy" id="dp-skip" value="skip" checked>
                                    <label class="form-check-label" for="dp-skip">
                                        <strong>{{ __('wp_importer.policy_skip') ?: 'Omitir' }}</strong>
                                        <span class="text-muted ms-1" style="font-size:0.82rem;">– {{ __('wp_importer.policy_skip_desc') ?: 'No importar si ya existe' }}</span>
                                    </label>
                                </div>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio" name="duplicate_policy" id="dp-overwrite" value="overwrite">
                                    <label class="form-check-label" for="dp-overwrite">
                                        <strong>{{ __('wp_importer.policy_overwrite') ?: 'Sobrescribir' }}</strong>
                                        <span class="text-muted ms-1" style="font-size:0.82rem;">– {{ __('wp_importer.policy_overwrite_desc') ?: 'Actualizar contenido existente con el de WordPress' }}</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="duplicate_policy" id="dp-rename" value="rename">
                                    <label class="form-check-label" for="dp-rename">
                                        <strong>{{ __('wp_importer.policy_rename') ?: 'Renombrar' }}</strong>
                                        <span class="text-muted ms-1" style="font-size:0.82rem;">– {{ __('wp_importer.policy_rename_desc') ?: 'Crear nuevo con slug modificado (-1, -2...)' }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3" style="font-size:0.85rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>{{ __('wp_importer.import_order') }}</strong>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-outline-secondary" onclick="wpiGoToStep(2)">
                                <i class="bi bi-arrow-left me-1"></i> {{ __('wp_importer.back') }}
                            </button>
                            <button class="btn btn-success btn-lg" id="wpi-btn-import" onclick="wpiStartImport()">
                                <i class="bi bi-cloud-download me-1"></i> {{ __('wp_importer.start_import') }}
                            </button>
                        </div>
                    </div>

                    {{-- ===== STEP 4: Progreso ===== --}}
                    <div class="wpi-step" id="step-4">
                        <h4 class="mb-3">{{ __('wp_importer.importing') }}</h4>
                        <p class="text-muted">{{ __('wp_importer.import_warning') }}</p>

                        <div class="progress mb-3" style="height: 24px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="wpi-progress-bar" style="width: 0%">0%</div>
                        </div>

                        <div class="wpi-progress-section" id="wpi-progress-items">
                            <div class="wpi-progress-item" data-phase="media">
                                <span class="status-icon pending"><i class="bi bi-circle"></i></span>
                                <span>{{ __('wp_importer.media') }}</span>
                            </div>
                            <div class="wpi-progress-item" data-phase="categories">
                                <span class="status-icon pending"><i class="bi bi-circle"></i></span>
                                <span>{{ __('wp_importer.categories') }}</span>
                            </div>
                            <div class="wpi-progress-item" data-phase="tags">
                                <span class="status-icon pending"><i class="bi bi-circle"></i></span>
                                <span>{{ __('wp_importer.tags') }}</span>
                            </div>
                            <div class="wpi-progress-item" data-phase="posts">
                                <span class="status-icon pending"><i class="bi bi-circle"></i></span>
                                <span>{{ __('wp_importer.posts') }}</span>
                            </div>
                            <div class="wpi-progress-item" data-phase="pages">
                                <span class="status-icon pending"><i class="bi bi-circle"></i></span>
                                <span>{{ __('wp_importer.pages') }}</span>
                            </div>
                            <div class="wpi-progress-item" data-phase="styles">
                                <span class="status-icon pending"><i class="bi bi-circle"></i></span>
                                <span>{{ __('wp_importer.styles') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- ===== STEP 5: Resultado ===== --}}
                    <div class="wpi-step" id="step-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                            <h4 class="mt-2">{{ __('wp_importer.import_complete') }}</h4>
                        </div>

                        <div class="wpi-results" id="wpi-results"></div>

                        <div id="wpi-results-errors" style="display:none;">
                            <h5 class="text-danger"><i class="bi bi-exclamation-circle me-1"></i> {{ __('wp_importer.errors_title') }}</h5>
                            <ul class="wpi-error-list" id="wpi-error-list"></ul>
                        </div>

                        <div class="mt-4 d-flex gap-2 justify-content-center">
                            @if(!empty($tenantInfo))
                            <a href="/musedock/domain-manager/{{ $tenantInfo->id }}/edit" class="btn btn-outline-primary">
                                <i class="bi bi-building me-1"></i> Volver al Domain Manager
                            </a>
                            @else
                            <a href="{{ $adminPath }}/blog/posts" class="btn btn-outline-primary">
                                <i class="bi bi-file-text me-1"></i> {{ __('wp_importer.view_posts') }}
                            </a>
                            @endif
                            <a href="{{ $adminPath }}/wp-importer" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-repeat me-1"></i> {{ __('wp_importer.new_import') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const WPI_BASE = '{{ $adminPath }}';
const WPI_LANG = {
    imported: {!! json_encode(__('wp_importer.imported')) !!},
    skipped: {!! json_encode(__('wp_importer.skipped')) !!},
    updated: {!! json_encode(__('wp_importer.updated') ?: 'actualizados') !!},
    styles_applied: {!! json_encode(__('wp_importer.styles_applied')) !!},
    google_fonts: {!! json_encode(__('wp_importer.google_fonts_detected')) !!},
    connecting: {!! json_encode(__('wp_importer.connecting')) !!},
};

function wpiGoToStep(step) {
    document.querySelectorAll('.wpi-step').forEach(el => el.classList.remove('active'));
    document.getElementById('step-' + step).classList.add('active');
    document.querySelectorAll('.step-indicator').forEach(el => {
        const s = parseInt(el.dataset.step);
        el.classList.remove('active', 'completed');
        if (s === step) el.classList.add('active');
        else if (s < step) el.classList.add('completed');
    });
}

function wpiSetLoading(btn, loading) {
    if (loading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + WPI_LANG.connecting;
        btn.disabled = true;
    } else {
        btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
        btn.disabled = false;
    }
}

async function wpiConnect() {
    const btn = document.getElementById('wpi-btn-connect');
    const errorDiv = document.getElementById('wpi-connect-error');
    errorDiv.classList.add('d-none');
    wpiSetLoading(btn, true);

    try {
        const formData = new FormData();
        formData.append('site_url', document.getElementById('wpi-site-url').value);
        formData.append('username', document.getElementById('wpi-username').value);
        formData.append('app_password', document.getElementById('wpi-app-password').value);

        const resp = await fetch(WPI_BASE + '/wp-importer/connect', { method: 'POST', body: formData });
        const data = await resp.json();

        if (!data.success) {
            errorDiv.textContent = data.error;
            errorDiv.classList.remove('d-none');
            wpiSetLoading(btn, false);
            return;
        }

        document.getElementById('wpi-site-info').innerHTML = `
            <h5><i class="bi bi-wordpress me-2"></i>${wpiEscape(data.site_name)}</h5>
            <p class="text-muted mb-0">${wpiEscape(data.site_description)}</p>
        `;

        const s = data.summary;
        document.getElementById('wpi-summary-grid').innerHTML = `
            <div class="wpi-summary-card"><div class="count">${s.posts}</div><div class="label">Posts</div></div>
            <div class="wpi-summary-card"><div class="count">${s.pages}</div><div class="label">${wpiEscape({!! json_encode(__('wp_importer.pages')) !!})}</div></div>
            <div class="wpi-summary-card"><div class="count">${s.categories}</div><div class="label">${wpiEscape({!! json_encode(__('wp_importer.categories')) !!})}</div></div>
            <div class="wpi-summary-card"><div class="count">${s.tags}</div><div class="label">Tags</div></div>
            <div class="wpi-summary-card"><div class="count">${s.media}</div><div class="label">Media</div></div>
        `;

        wpiGoToStep(2);
    } catch (e) {
        errorDiv.textContent = 'Error: ' + e.message;
        errorDiv.classList.remove('d-none');
    }
    wpiSetLoading(btn, false);
}

async function wpiPreview() {
    const btn = document.getElementById('wpi-btn-preview');
    wpiSetLoading(btn, true);

    try {
        const formData = new FormData();
        formData.append('import_categories', document.getElementById('wpi-opt-categories').checked ? 1 : 0);
        formData.append('import_tags', document.getElementById('wpi-opt-tags').checked ? 1 : 0);
        formData.append('import_posts', document.getElementById('wpi-opt-posts').checked ? 1 : 0);
        formData.append('import_pages', document.getElementById('wpi-opt-pages').checked ? 1 : 0);
        formData.append('import_media', document.getElementById('wpi-opt-media').checked ? 1 : 0);
        formData.append('import_styles', document.getElementById('wpi-opt-styles').checked ? 1 : 0);

        const resp = await fetch(WPI_BASE + '/wp-importer/preview', { method: 'POST', body: formData });
        const data = await resp.json();

        if (!data.success) {
            alert('Error: ' + data.error);
            wpiSetLoading(btn, false);
            return;
        }

        const p = data.preview;
        document.getElementById('wpi-preview-summary').innerHTML = `
            ${p.categories ? `<div class="wpi-summary-card"><div class="count">${p.categories}</div><div class="label">${wpiEscape({!! json_encode(__('wp_importer.categories')) !!})}</div></div>` : ''}
            ${p.tags ? `<div class="wpi-summary-card"><div class="count">${p.tags}</div><div class="label">Tags</div></div>` : ''}
            ${p.posts ? `<div class="wpi-summary-card"><div class="count">${p.posts}</div><div class="label">Posts</div></div>` : ''}
            ${p.pages ? `<div class="wpi-summary-card"><div class="count">${p.pages}</div><div class="label">${wpiEscape({!! json_encode(__('wp_importer.pages')) !!})}</div></div>` : ''}
            ${p.media ? `<div class="wpi-summary-card"><div class="count">${p.media}</div><div class="label">Media</div></div>` : ''}
            ${p.styles ? `<div class="wpi-summary-card"><div class="count"><i class="bi bi-palette"></i></div><div class="label">${wpiEscape({!! json_encode(__('wp_importer.styles')) !!})}</div></div>` : ''}
        `;

        const conflictsSection = document.getElementById('wpi-conflicts-section');
        if (data.has_conflicts) {
            conflictsSection.style.display = 'block';
            let html = '';
            for (const [type, items] of Object.entries(data.conflicts)) {
                if (items.length === 0) continue;
                html += `<h6 class="mt-3">${wpiCapitalize(type)} (${items.length})</h6>`;
                html += '<table class="table table-sm table-bordered"><thead><tr><th>WordPress</th><th>Slug</th><th>Existente</th><th>Acción</th></tr></thead><tbody>';
                for (const item of items) {
                    html += `<tr>
                        <td>${wpiEscape(item.wp_name || item.wp_title)}</td>
                        <td><code>${wpiEscape(item.wp_slug)}</code></td>
                        <td>${wpiEscape(item.existing_name || item.existing_title)}</td>
                        <td><span class="badge bg-warning text-dark">${wpiEscape(item.action)}</span></td>
                    </tr>`;
                }
                html += '</tbody></table>';
            }
            document.getElementById('wpi-conflicts-tables').innerHTML = html;
        } else {
            conflictsSection.style.display = 'none';
        }

        wpiGoToStep(3);
    } catch (e) {
        alert('Error: ' + e.message);
    }
    wpiSetLoading(btn, false);
}

async function wpiStartImport() {
    const btn = document.getElementById('wpi-btn-import');
    btn.disabled = true;
    wpiGoToStep(4);

    const phases = ['media', 'categories', 'tags', 'posts', 'pages', 'styles'];
    let currentPhase = 0;

    function updateProgress() {
        const pct = Math.round((currentPhase / phases.length) * 100);
        const bar = document.getElementById('wpi-progress-bar');
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';

        document.querySelectorAll('.wpi-progress-item').forEach(el => {
            const phase = el.dataset.phase;
            const idx = phases.indexOf(phase);
            const icon = el.querySelector('.status-icon');
            if (idx < currentPhase) {
                icon.className = 'status-icon done';
                icon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            } else if (idx === currentPhase) {
                icon.className = 'status-icon running';
                icon.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            }
        });
    }

    const progressInterval = setInterval(() => {
        if (currentPhase < phases.length - 1) { currentPhase++; updateProgress(); }
    }, 3000);

    updateProgress();

    try {
        const formData = new FormData();
        const dpRadio = document.querySelector('input[name="duplicate_policy"]:checked');
        if (dpRadio) formData.append('duplicate_policy', dpRadio.value);
        const resp = await fetch(WPI_BASE + '/wp-importer/import', { method: 'POST', body: formData });
        const data = await resp.json();

        clearInterval(progressInterval);

        if (!data.success) { alert('Error: ' + data.error); return; }

        currentPhase = phases.length;
        updateProgress();
        const bar = document.getElementById('wpi-progress-bar');
        bar.style.width = '100%'; bar.textContent = '100%';
        bar.classList.remove('progress-bar-animated'); bar.classList.add('bg-success');

        setTimeout(() => { wpiShowResults(data.results); wpiGoToStep(5); }, 1000);
    } catch (e) {
        clearInterval(progressInterval);
        alert('Error: ' + e.message);
    }
}

function wpiShowResults(results) {
    const sections = [
        { key: 'media', label: 'Media', icon: 'bi-image' },
        { key: 'categories', label: {!! json_encode(__('wp_importer.categories')) !!}, icon: 'bi-folder' },
        { key: 'tags', label: 'Tags', icon: 'bi-tags' },
        { key: 'posts', label: 'Posts', icon: 'bi-file-text' },
        { key: 'pages', label: {!! json_encode(__('wp_importer.pages')) !!}, icon: 'bi-file-earmark' },
    ];

    let html = '';
    for (const section of sections) {
        const data = results[section.key];
        if (!data) continue;
        html += `
            <div class="wpi-result-card">
                <div><i class="bi ${section.icon} me-2"></i> ${section.label}</div>
                <div>
                    <span class="imported">${data.imported} ${WPI_LANG.imported}</span>
                    ${data.updated ? `<span class="ms-2" style="color:#4361ee;font-weight:600;">${data.updated} ${WPI_LANG.updated}</span>` : ''}
                    ${data.skipped ? `<span class="ms-2 skipped">${data.skipped} ${WPI_LANG.skipped}</span>` : ''}
                </div>
            </div>
        `;
    }

    if (results.styles && results.styles.applied) {
        html += `<div class="wpi-result-card">
            <div><i class="bi bi-palette me-2"></i> ${wpiEscape({!! json_encode(__('wp_importer.styles')) !!})}</div>
            <div><span class="imported">${WPI_LANG.styles_applied}</span></div>
        </div>`;
        if (results.styles.google_fonts && results.styles.google_fonts.length > 0) {
            html += `<div class="wpi-result-card">
                <div><i class="bi bi-fonts me-2"></i> ${WPI_LANG.google_fonts}</div>
                <div><span class="text-muted">${results.styles.google_fonts.join(', ')}</span></div>
            </div>`;
        }
    }

    document.getElementById('wpi-results').innerHTML = html;

    const allErrors = [...(results.errors || []), ...(results.media?.errors || [])];
    if (allErrors.length > 0) {
        document.getElementById('wpi-results-errors').style.display = 'block';
        document.getElementById('wpi-error-list').innerHTML = allErrors.map(e => `<li>${wpiEscape(e)}</li>`).join('');
    }
}

function wpiEscape(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function wpiCapitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>
@endpush
