@extends('layouts.app')

@section('title', $title ?? 'Gestión de Módulos')

@push('styles')
<style>
.modules-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.modules-stats {
    display: flex;
    gap: 1rem;
}
.stat-badge {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
}
.module-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.module-item {
    display: flex;
    align-items: center;
    padding: 1rem 1.25rem;
    background: #fff;
    border: 1px solid #e9ecef;
    border-bottom: none;
    transition: all 0.15s ease;
}
.module-item:first-child { border-radius: 0.5rem 0.5rem 0 0; }
.module-item:last-child { border-bottom: 1px solid #e9ecef; border-radius: 0 0 0.5rem 0.5rem; }
.module-item:hover { background: #f8f9fa; }
.module-item.enabled { border-left: 3px solid #198754; }
.module-item.disabled { border-left: 3px solid #dee2e6; opacity: 0.85; }
.module-icon {
    width: 48px; height: 48px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0; margin-right: 1rem;
}
.module-icon.enabled { background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white; }
.module-icon.disabled { background: #e9ecef; color: #6c757d; }
.module-info { flex-grow: 1; min-width: 0; }
.module-name { font-weight: 600; font-size: 1rem; color: #212529; margin-bottom: 0.15rem; }
.module-description { font-size: 0.85rem; color: #6c757d; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.module-meta {
    display: flex; align-items: center; gap: 1rem; margin-right: 1rem; flex-shrink: 0;
}
.module-version {
    font-size: 0.75rem; color: #6c757d; background: #f1f3f4;
    padding: 0.2rem 0.5rem; border-radius: 4px; font-family: monospace;
    min-width: 52px; text-align: center;
}
.module-status {
    display: flex; align-items: center; gap: 0.35rem;
    font-size: 0.8rem; font-weight: 500; padding: 0.35rem 0.75rem;
    border-radius: 20px; min-width: 90px; justify-content: center;
}
.module-status.active { background: rgba(25, 135, 84, 0.1); color: #198754; }
.module-status.inactive { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
.module-actions { flex-shrink: 0; }
/* Toggle switch */
.toggle-switch {
    position: relative; width: 50px; height: 28px; cursor: pointer; flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-track {
    position: absolute; inset: 0; background: #dee2e6;
    border-radius: 999px; transition: background 0.25s ease;
}
.toggle-track::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 22px; height: 22px; background: #fff; border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: transform 0.25s ease;
}
.toggle-switch.active .toggle-track { background: #198754; }
.toggle-switch.active .toggle-track::after { transform: translateX(22px); }
/* Tenant toggle (azul) */
.toggle-switch.tenant-toggle .toggle-track { background: #dee2e6; }
.toggle-switch.tenant-toggle.active .toggle-track { background: #0d6efd; }
/* Dashboard toggle (naranja) */
.toggle-switch.dash-toggle .toggle-track { background: #dee2e6; }
.toggle-switch.dash-toggle.active .toggle-track { background: #fd7e14; }
.toggle-label {
    font-size: 0.7rem; color: #6c757d; text-align: center; margin-top: 2px; white-space: nowrap;
}
@media (max-width: 768px) {
    .module-item { flex-wrap: wrap; gap: 0.75rem; }
    .module-meta { width: 100%; margin-right: 0; justify-content: flex-start; order: 3; }
    .module-actions { order: 2; margin-left: auto; }
    .modules-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
}
</style>
@endpush

@section('content')
<div class="container-fluid">
    @php
        $activeCount = 0;
        $installedCount = 0;
        foreach ($modules as $m) {
            if ($m['installed'] ?? false) $installedCount++;
            if ($m['active'] ?? false) $activeCount++;
        }
        $totalCount = count($modules);
    @endphp

    <div class="modules-header">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#198754,#20c997);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-puzzle" style="font-size:1.35rem;color:#fff;"></i>
            </div>
            <div>
                <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Gestión de Módulos</h3>
                <p class="text-muted mb-0" style="font-size:0.85rem;">Instala, activa y controla la disponibilidad de módulos para tenants</p>
            </div>
        </div>
        <div class="modules-stats">
            <div class="stat-badge" style="background:rgba(25,135,84,0.1);border-color:rgba(25,135,84,0.2);color:#198754;">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ $activeCount }} activos</span>
            </div>
            <div class="stat-badge">
                <i class="bi bi-grid-3x3-gap"></i>
                <span>{{ $totalCount }} total</span>
            </div>
            <a href="/musedock/plugin-store" class="stat-badge" style="text-decoration:none;color:#6366f1;border-color:rgba(99,102,241,0.2);background:rgba(99,102,241,0.08);">
                <i class="bi bi-shop"></i>
                <span>Plugin Store</span>
            </a>
            <button type="button" class="stat-badge" id="btnUploadModule" style="cursor:pointer;color:#0d6efd;border-color:rgba(13,110,253,0.2);background:rgba(13,110,253,0.08);">
                <i class="bi bi-upload"></i>
                <span>Subir ZIP</span>
            </button>
        </div>
    </div>

    @if(!empty($autoRegistered))
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Módulos detectados automáticamente:</strong>
        {{ implode(', ', $autoRegistered) }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(empty($modules))
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <i class="bi bi-puzzle" style="font-size:1.75rem;color:#198754;"></i>
                </div>
                <h5 class="mb-2">No hay módulos disponibles</h5>
                <p class="text-muted mb-3" style="max-width:400px;margin:0 auto;">Sube un módulo ZIP o visita el Plugin Store para empezar.</p>
            </div>
        </div>
    @else
        <!-- Leyenda -->
        <div class="d-flex align-items-center gap-4 mb-3 ps-1" style="font-size:0.8rem;color:#6c757d;">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#198754;margin-right:4px;"></span> Activo en CMS</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#fd7e14;margin-right:4px;"></span> Acceso directo en dashboard</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0d6efd;margin-right:4px;"></span> Disponible para tenants</span>
        </div>

        <div class="module-list">
            @foreach ($modules as $module)
                @php
                    $isInstalled = $module['installed'] ?? false;
                    $isActive = $module['active'] ?? false;
                    $tenantDefault = $module['tenant_enabled_default'] ?? true;
                    $iconMap = [
                        'blog' => 'bi-journal-richtext',
                        'pages' => 'bi-file-earmark-text',
                        'media' => 'bi-images',
                        'media-manager' => 'bi-images',
                        'custom-forms' => 'bi-ui-checks',
                        'image-gallery' => 'bi-card-image',
                        'react-sliders' => 'bi-sliders2',
                        'instagram-gallery' => 'bi-instagram',
                        'tickets' => 'bi-ticket-detailed',
                        'wp-importer' => 'bi-wordpress',
                        'ai-writer' => 'bi-robot',
                        'ai-image' => 'bi-stars',
                        'elements' => 'bi-bricks',
                        'hello-world' => 'bi-hand-wave',
                    ];
                    $icon = $iconMap[$module['slug'] ?? ''] ?? 'bi-puzzle';
                @endphp
                <div class="module-item {{ $isActive ? 'enabled' : 'disabled' }}">
                    <div class="module-icon {{ $isActive ? 'enabled' : 'disabled' }}">
                        <i class="bi {{ $icon }}"></i>
                    </div>

                    <div class="module-info">
                        <div class="module-name">{{ $module['name'] ?? $module['slug'] }}</div>
                        <p class="module-description">{{ $module['description'] ?? 'Sin descripción' }}</p>
                    </div>

                    <div class="module-meta">
                        @if(!empty($module['version']))
                            <span class="module-version">v{{ $module['version'] }}</span>
                        @endif
                        @if($isInstalled)
                            <div class="module-status {{ $isActive ? 'active' : 'inactive' }}">
                                <i class="bi {{ $isActive ? 'bi-check-circle-fill' : 'bi-dash-circle' }}"></i>
                                {{ $isActive ? 'Activo' : 'Inactivo' }}
                            </div>
                        @else
                            <div class="module-status inactive">
                                <i class="bi bi-cloud-download"></i>
                                No instalado
                            </div>
                        @endif
                    </div>

                    @php
                        $moduleSettingsMap = [
                            'ai-writer' => '/musedock/aiwriter/settings',
                            'ai-image' => '/musedock/ai-image/settings',
                            'wp-importer' => '/musedock/wp-importer',
                        ];
                        if (!isset($moduleSettingsMap[$module['slug']])) {
                            $moduleJsonPath = APP_ROOT . '/modules/' . $module['slug'] . '/module.json';
                            if (file_exists($moduleJsonPath)) {
                                $moduleMeta = json_decode(file_get_contents($moduleJsonPath), true);
                                if (!empty($moduleMeta['settings_url'])) {
                                    $moduleSettingsMap[$module['slug']] = $moduleMeta['settings_url'];
                                } elseif (!empty($moduleMeta['admin_url'])) {
                                    $moduleSettingsMap[$module['slug']] = $moduleMeta['admin_url'];
                                }
                            }
                        }
                        $settingsUrl = $moduleSettingsMap[$module['slug']] ?? null;
                    @endphp

                    <div class="module-actions d-flex align-items-center">
                        {{-- Settings: ancho fijo para alinear --}}
                        <div style="width:36px;text-align:center;margin-right:12px;">
                            @if($settingsUrl && $isActive)
                            <a href="{{ $settingsUrl }}" title="Configuración"
                               style="color:#6c757d;font-size:1.1rem;transition:color 0.15s;"
                               onmouseover="this.style.color='#212529'" onmouseout="this.style.color='#6c757d'">
                                <i class="bi bi-gear"></i>
                            </a>
                            @endif
                        </div>

                        {{-- Toggle Dashboard: ancho fijo --}}
                        <div style="width:60px;text-align:center;margin-right:12px;">
                            @if($isInstalled)
                            @php $showDash = $module['show_in_dashboard'] ?? false; @endphp
                            <form method="POST" action="/musedock/modules/toggle-dashboard" class="d-inline">
                                @csrf
                                <input type="hidden" name="slug" value="{{ $module['slug'] }}">
                                <label class="toggle-switch dash-toggle {{ $showDash ? 'active' : '' }} toggle-dash-btn"
                                       title="{{ $showDash ? 'Ocultar del dashboard' : 'Mostrar en dashboard' }}"
                                       style="cursor:pointer;">
                                    <input type="checkbox" {{ $showDash ? 'checked' : '' }}>
                                    <span class="toggle-track"></span>
                                </label>
                            </form>
                            <div class="toggle-label">Dashboard</div>
                            @endif
                        </div>

                        {{-- Toggle Tenants: ancho fijo --}}
                        <div style="width:60px;text-align:center;margin-right:12px;">
                            @if($isInstalled)
                            <form method="POST" action="/musedock/modules/toggle-tenant-default" class="tenant-toggle-form"
                                  data-module-name="{{ $module['name'] ?? $module['slug'] }}"
                                  data-current="{{ $tenantDefault ? '1' : '0' }}">
                                @csrf
                                <input type="hidden" name="slug" value="{{ $module['slug'] }}">
                                <label class="toggle-switch tenant-toggle {{ $tenantDefault ? 'active' : '' }} toggle-tenant-btn"
                                       title="{{ $tenantDefault ? 'Ocultar de tenants' : 'Hacer disponible para tenants' }}">
                                    <input type="checkbox" {{ $tenantDefault ? 'checked' : '' }}>
                                    <span class="toggle-track"></span>
                                </label>
                            </form>
                            <div class="toggle-label">Tenants</div>
                            @endif
                        </div>

                        {{-- Toggle CMS / Instalar: ancho fijo --}}
                        <div style="width:60px;text-align:center;">
                            @if(!$isInstalled)
                            <form method="POST" action="/musedock/modules/activate" class="d-inline module-action-form"
                                  data-module-name="{{ $module['name'] ?? $module['slug'] }}" data-action="install">
                                @csrf
                                <input type="hidden" name="slug" value="{{ $module['slug'] }}">
                                <button type="button" class="btn btn-sm btn-success confirm-module-action">
                                    <i class="bi bi-download me-1"></i> Instalar
                                </button>
                            </form>
                            @else
                            <form method="POST" action="/musedock/modules/{{ $isActive ? 'deactivate' : 'activate' }}"
                                  class="module-action-form"
                                  data-module-name="{{ $module['name'] ?? $module['slug'] }}"
                                  data-action="{{ $isActive ? 'deactivate' : 'activate' }}">
                                @csrf
                                <input type="hidden" name="slug" value="{{ $module['slug'] }}">
                                <label class="toggle-switch {{ $isActive ? 'active' : '' }} toggle-active-btn"
                                       title="{{ $isActive ? 'Desactivar' : 'Activar' }}">
                                    <input type="checkbox" {{ $isActive ? 'checked' : '' }}>
                                    <span class="toggle-track"></span>
                                </label>
                            </form>
                            <div class="toggle-label">CMS</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- Formulario oculto para subir módulo -->
<form id="uploadModuleForm" method="POST" action="/musedock/modules/upload" enctype="multipart/form-data" style="display: none;">
    @csrf
    <input type="file" id="moduleZipInput" name="module_zip" accept=".zip">
</form>
@endsection

{{-- SweetAlert2 para mensajes flash --}}
@php
    $successMessage = consume_flash('success');
    $errorMessage = consume_flash('error');
@endphp

@if ($successMessage)
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'success', title: 'Éxito', text: {!! json_encode($successMessage) !!}, timer: 3000, timerProgressBar: true });
    }
});
</script>
@endif

@if ($errorMessage)
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: {!! json_encode($errorMessage) !!} });
    }
});
</script>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ========== SUBIR MÓDULO ZIP ==========
    const btnUpload = document.getElementById('btnUploadModule');
    const uploadForm = document.getElementById('uploadModuleForm');
    const fileInput = document.getElementById('moduleZipInput');

    if (btnUpload) {
        btnUpload.addEventListener('click', function() {
            Swal.fire({
                title: '<i class="bi bi-cloud-upload text-primary"></i> Subir Módulo',
                html: `
                    <div class="text-start">
                        <p class="text-muted mb-3">Selecciona un archivo ZIP con el módulo a instalar.</p>
                        <div class="upload-zone border border-2 border-dashed rounded-3 p-4 text-center" id="dropZone" style="cursor: pointer; transition: all 0.3s;">
                            <i class="bi bi-file-earmark-zip display-4 text-muted"></i>
                            <p class="mb-1 mt-2"><strong>Arrastra el archivo aquí</strong></p>
                            <p class="text-muted small mb-2">o haz clic para seleccionar</p>
                            <span class="badge bg-secondary">Máximo 50MB</span>
                        </div>
                        <div id="selectedFile" class="mt-3 d-none">
                            <div class="alert alert-success py-2 mb-0">
                                <i class="bi bi-file-earmark-check me-2"></i>
                                <span id="fileName"></span>
                                <button type="button" class="btn-close btn-sm float-end" id="clearFile"></button>
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-upload me-1"></i> Subir e Instalar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                width: '500px',
                didOpen: () => {
                    const dropZone = document.getElementById('dropZone');
                    const selectedFileDiv = document.getElementById('selectedFile');
                    const fileNameSpan = document.getElementById('fileName');
                    const clearFileBtn = document.getElementById('clearFile');

                    dropZone.addEventListener('click', () => fileInput.click());
                    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-primary', 'bg-light'); });
                    dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('border-primary', 'bg-light'); });
                    dropZone.addEventListener('drop', (e) => {
                        e.preventDefault();
                        dropZone.classList.remove('border-primary', 'bg-light');
                        const file = e.dataTransfer.files[0];
                        if (file && file.name.endsWith('.zip')) { handleFileSelect(file); }
                        else { Swal.showValidationMessage('Por favor selecciona un archivo .zip'); }
                    });
                    fileInput.addEventListener('change', function() { if (this.files[0]) handleFileSelect(this.files[0]); });
                    clearFileBtn.addEventListener('click', () => {
                        fileInput.value = '';
                        selectedFileDiv.classList.add('d-none');
                        dropZone.classList.remove('d-none');
                    });

                    function handleFileSelect(file) {
                        if (file.size > 50 * 1024 * 1024) { Swal.showValidationMessage('El archivo excede el límite de 50MB'); return; }
                        fileNameSpan.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                        selectedFileDiv.classList.remove('d-none');
                        dropZone.classList.add('d-none');
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        fileInput.files = dt.files;
                    }
                },
                preConfirm: () => {
                    if (!fileInput.files[0]) { Swal.showValidationMessage('Por favor selecciona un archivo ZIP'); return false; }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Subiendo módulo...',
                        html: '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div></div>',
                        allowOutsideClick: false, showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });
                    uploadForm.submit();
                }
            });
        });
    }

    // ========== TOGGLE ACTIVAR/DESACTIVAR CMS (verde) ==========
    document.querySelectorAll('.toggle-active-btn').forEach(function(toggle) {
        const checkbox = toggle.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.addEventListener('click', function(e) { e.preventDefault(); });

        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('.module-action-form');
            const moduleName = form.dataset.moduleName;
            const action = form.dataset.action;
            const isActivating = action === 'activate';

            Swal.fire({
                title: `¿${isActivating ? 'Activar' : 'Desactivar'} "${moduleName}"?`,
                html: `
                    <div class="text-start">
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Esta acción afecta a TODOS los tenants.</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                ${isActivating
                                    ? '<li>El módulo estará disponible para todos los tenants</li><li>Aparecerá en sus menús laterales</li>'
                                    : '<li>Se ocultará de todos los tenants</li><li>Las funcionalidades quedarán inaccesibles</li>'
                                }
                            </ul>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: isActivating ? '#198754' : '#dc3545',
                confirmButtonText: isActivating ? 'Sí, activar' : 'Sí, desactivar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: `${isActivating ? 'Activando' : 'Desactivando'}...`, allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
                    form.submit();
                }
            });
        });
    });

    // ========== TOGGLE DASHBOARD (naranja) ==========
    document.querySelectorAll('.toggle-dash-btn').forEach(function(toggle) {
        const checkbox = toggle.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.addEventListener('click', function(e) { e.preventDefault(); });
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            this.closest('form').submit();
        });
    });

    // ========== TOGGLE DISPONIBILIDAD TENANTS (azul) ==========
    document.querySelectorAll('.toggle-tenant-btn').forEach(function(toggle) {
        const checkbox = toggle.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.addEventListener('click', function(e) { e.preventDefault(); });

        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('.tenant-toggle-form');
            const moduleName = form.dataset.moduleName;
            const isCurrentlyEnabled = form.dataset.current === '1';

            Swal.fire({
                title: `¿${isCurrentlyEnabled ? 'Ocultar' : 'Habilitar'} "${moduleName}" para tenants?`,
                html: isCurrentlyEnabled
                    ? '<p class="text-muted">Los nuevos tenants ya no tendrán este módulo activado por defecto. Los tenants existentes no se ven afectados.</p>'
                    : '<p class="text-muted">Los nuevos tenants tendrán este módulo activado por defecto.</p>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: isCurrentlyEnabled ? '#dc3545' : '#0d6efd',
                confirmButtonText: isCurrentlyEnabled ? 'Sí, ocultar' : 'Sí, habilitar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    });

    // ========== BOTÓN INSTALAR ==========
    document.querySelectorAll('.confirm-module-action').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('.module-action-form');
            const moduleName = form.dataset.moduleName;

            Swal.fire({
                title: `¿Instalar "${moduleName}"?`,
                text: 'Se registrará el módulo y se ejecutarán sus migraciones.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                confirmButtonText: '<i class="bi bi-download me-1"></i> Instalar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Instalando...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush
