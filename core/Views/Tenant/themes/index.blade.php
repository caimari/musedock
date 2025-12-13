@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="themes-manager">
    <!-- Header con gradiente -->
    <div class="themes-header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="mb-1"><i class="fas fa-palette me-2"></i>Gestión de Temas</h2>
                <p class="text-muted mb-0">Personaliza el aspecto de tu sitio web</p>
            </div>
            <button class="btn btn-primary btn-lg" id="btn-upload-theme">
                <i class="fas fa-cloud-upload-alt me-2"></i> Subir Tema
            </button>
        </div>
    </div>

    <!-- Tema Activo Destacado -->
    <div class="active-theme-banner mb-4">
        <div class="card border-0 bg-gradient-primary text-white">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <div class="active-theme-icon me-3">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <div>
                            <small class="opacity-75">Tema activo actualmente</small>
                            <h5 class="mb-0 fw-bold">
                                @if($tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug'])
                                    {{ $tenant['custom_theme_slug'] }}
                                    <span class="badge bg-white text-primary ms-2">Personalizado</span>
                                @else
                                    {{ ucfirst($tenant['theme'] ?? 'default') }}
                                    <span class="badge bg-white text-success ms-2">Oficial</span>
                                @endif
                            </h5>
                        </div>
                    </div>
                    <a href="/{{ admin_path() }}/themes/appearance/{{ $tenant['theme_type'] === 'custom' ? $tenant['custom_theme_slug'] : $tenant['theme'] }}" class="btn btn-light btn-sm">
                        <i class="fas fa-paint-brush me-1"></i> Personalizar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Temas Oficiales -->
    <div class="themes-section mb-5">
        <div class="section-header d-flex align-items-center mb-4">
            <div class="section-icon bg-success text-white rounded-circle me-3">
                <i class="fas fa-crown"></i>
            </div>
            <div>
                <h4 class="mb-0">Temas Oficiales</h4>
                <small class="text-muted">Desarrollados por MuseDock, seguros y optimizados</small>
            </div>
            <span class="badge bg-success ms-auto">
                <i class="fas fa-shield-alt me-1"></i> Verificados
            </span>
        </div>

        <div class="row g-4">
            @forelse($globalThemes as $theme)
            @php
                $isActive = $tenant['theme_type'] === 'global' && $tenant['theme'] === $theme['slug'];
                $screenshotPath = $theme['screenshot'] ? "/themes/{$theme['slug']}/{$theme['screenshot']}" : null;
            @endphp
            <div class="col-lg-4 col-md-6">
                <div class="theme-card card h-100 {{ $isActive ? 'active' : '' }}">
                    @if($isActive)
                    <div class="active-ribbon">
                        <span>ACTIVO</span>
                    </div>
                    @endif

                    <div class="theme-preview" onclick="previewTheme('{{ $theme['name'] }}', '{{ $screenshotPath ?? '' }}', '{{ $theme['description'] }}')">
                        @if($screenshotPath)
                        <img src="{{ $screenshotPath }}" alt="{{ $theme['name'] }}" loading="lazy">
                        @else
                        <div class="no-image">
                            <i class="fas fa-image"></i>
                            <span>Sin vista previa</span>
                        </div>
                        @endif
                        <div class="preview-overlay">
                            <i class="fas fa-search-plus fa-2x"></i>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0">{{ $theme['name'] }}</h5>
                            <span class="badge bg-success-subtle text-success">
                                <i class="fas fa-check-circle me-1"></i>Oficial
                            </span>
                        </div>
                        <p class="card-text text-muted small mb-3">{{ $theme['description'] ?: 'Tema oficial de MuseDock' }}</p>

                        <div class="theme-meta d-flex justify-content-between text-muted small">
                            <span><i class="fas fa-user me-1"></i>{{ $theme['author'] }}</span>
                            <span><i class="fas fa-code-branch me-1"></i>v{{ $theme['version'] }}</span>
                        </div>
                    </div>

                    <div class="card-footer bg-transparent border-top-0 pt-0">
                        @if($isActive)
                            <div class="d-flex gap-2">
                                <span class="btn btn-primary btn-sm flex-grow-1 disabled">
                                    <i class="fas fa-check me-1"></i> Tema Activo
                                </span>
                                <a href="/{{ admin_path() }}/themes/appearance/{{ $theme['slug'] }}" class="btn btn-outline-info btn-sm" title="Personalizar colores y estilos">
                                    <i class="fas fa-paint-brush"></i>
                                </a>
                                <a href="/{{ admin_path() }}/widgets" class="btn btn-outline-primary btn-sm" title="Widgets">
                                    <i class="fas fa-th-large"></i>
                                </a>
                            </div>
                        @else
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1 btn-activate-global"
                                        data-slug="{{ $theme['slug'] }}"
                                        data-name="{{ $theme['name'] }}">
                                    <i class="fas fa-power-off me-1"></i> Activar
                                </button>
                                <a href="/{{ admin_path() }}/themes/appearance/{{ $theme['slug'] }}" class="btn btn-outline-secondary btn-sm" title="Ver opciones">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3"></i>
                    <div>
                        <strong>Sin temas disponibles</strong>
                        <p class="mb-0 small">No hay temas oficiales instalados en el sistema.</p>
                    </div>
                </div>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Temas Personalizados -->
    <div class="themes-section">
        <div class="section-header d-flex align-items-center mb-4">
            <div class="section-icon bg-info text-white rounded-circle me-3">
                <i class="fas fa-magic"></i>
            </div>
            <div>
                <h4 class="mb-0">Mis Temas Personalizados</h4>
                <small class="text-muted">Temas exclusivos subidos por ti</small>
            </div>
            <span class="badge bg-info ms-auto">
                {{ count($customThemes) }} tema(s)
            </span>
        </div>

        @if(empty($customThemes))
            <div class="empty-state card border-2 border-dashed">
                <div class="card-body text-center py-5">
                    <div class="empty-icon mb-4">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <h5>No tienes temas personalizados</h5>
                    <p class="text-muted mb-4">Sube tu propio tema para darle un toque único a tu sitio web</p>
                    <button class="btn btn-primary" id="btn-upload-theme-empty">
                        <i class="fas fa-upload me-2"></i> Subir mi primer tema
                    </button>
                </div>
            </div>
        @else
            <div class="row g-4">
                @foreach($customThemes as $theme)
                @php
                    $isActive = $tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug'] === $theme['slug'];
                    $screenshotPath = $theme['screenshot'] ? "/storage/tenants/{$tenantId}/themes/{$theme['slug']}/{$theme['screenshot']}" : null;
                @endphp
                <div class="col-lg-4 col-md-6">
                    <div class="theme-card card h-100 {{ $isActive ? 'active' : '' }} {{ !$theme['validated'] ? 'not-validated' : '' }}">
                        @if($isActive)
                        <div class="active-ribbon">
                            <span>ACTIVO</span>
                        </div>
                        @endif

                        @if(!$theme['validated'])
                        <div class="warning-ribbon">
                            <span><i class="fas fa-exclamation-triangle"></i></span>
                        </div>
                        @endif

                        <div class="theme-preview" onclick="previewTheme('{{ $theme['name'] }}', '{{ $screenshotPath ?? '' }}', '{{ $theme['description'] ?? 'Sin descripción' }}')">
                            @if($screenshotPath)
                            <img src="{{ $screenshotPath }}" alt="{{ $theme['name'] }}" loading="lazy">
                            @else
                            <div class="no-image">
                                <i class="fas fa-image"></i>
                                <span>Sin vista previa</span>
                            </div>
                            @endif
                            <div class="preview-overlay">
                                <i class="fas fa-search-plus fa-2x"></i>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0">{{ $theme['name'] }}</h5>
                                @if($theme['validated'])
                                <span class="badge bg-success-subtle text-success" title="Score: {{ $theme['security_score'] }}/100">
                                    <i class="fas fa-shield-alt me-1"></i>{{ $theme['security_score'] }}%
                                </span>
                                @else
                                <span class="badge bg-danger-subtle text-danger">
                                    <i class="fas fa-times-circle me-1"></i>No seguro
                                </span>
                                @endif
                            </div>
                            <p class="card-text text-muted small mb-3">{{ $theme['description'] ?? 'Sin descripción' }}</p>

                            <div class="theme-meta d-flex justify-content-between text-muted small">
                                <span><i class="fas fa-user me-1"></i>{{ $theme['author'] ?? 'Desconocido' }}</span>
                                <span><i class="fas fa-code-branch me-1"></i>v{{ $theme['version'] }}</span>
                            </div>

                            @if(!empty($theme['validation_errors']) && $theme['validation_errors'] !== '[]')
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 mt-2"
                                    onclick="showValidationErrors({{ json_encode(json_decode($theme['validation_errors'], true)) }})">
                                <i class="fas fa-exclamation-circle me-1"></i>Ver errores de validación
                            </button>
                            @endif
                        </div>

                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <div class="d-flex gap-2">
                                @if($isActive)
                                    <span class="btn btn-primary btn-sm flex-grow-1 disabled">
                                        <i class="fas fa-check me-1"></i> Activo
                                    </span>
                                    <a href="/{{ admin_path() }}/widgets" class="btn btn-outline-primary btn-sm" title="Personalizar">
                                        <i class="fas fa-sliders-h"></i>
                                    </a>
                                @elseif($theme['validated'])
                                    <button type="button" class="btn btn-outline-success btn-sm flex-grow-1 btn-activate-custom"
                                            data-slug="{{ $theme['slug'] }}"
                                            data-name="{{ $theme['name'] }}">
                                        <i class="fas fa-power-off me-1"></i> Activar
                                    </button>
                                @else
                                    <button class="btn btn-secondary btn-sm flex-grow-1" disabled title="Tema no validado">
                                        <i class="fas fa-lock me-1"></i> No Disponible
                                    </button>
                                @endif

                                <button type="button" class="btn btn-outline-info btn-sm btn-revalidate"
                                        data-slug="{{ $theme['slug'] }}" title="Revalidar">
                                    <i class="fas fa-sync-alt"></i>
                                </button>

                                @if(!$theme['active'])
                                <button type="button" class="btn btn-outline-danger btn-sm btn-uninstall"
                                        data-slug="{{ $theme['slug'] }}"
                                        data-name="{{ $theme['name'] }}" title="Eliminar">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Info de Seguridad -->
    <div class="security-info mt-5">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-3"><i class="fas fa-shield-alt text-primary me-2"></i>Seguridad de Temas</h5>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="d-flex mb-2">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <small>Validación automática de código</small>
                                </div>
                                <div class="d-flex mb-2">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <small>Detección de código malicioso</small>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex mb-2">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <small>Aislamiento por tenant</small>
                                </div>
                                <div class="d-flex mb-2">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <small>Score de seguridad 0-100</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="security-badge">
                            <i class="fas fa-lock fa-3x text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forms ocultos para las acciones -->
<form id="form-activate-global" action="" method="POST" style="display:none;">
    {!! csrf_field() !!}
</form>
<form id="form-activate-custom" action="" method="POST" style="display:none;">
    {!! csrf_field() !!}
</form>
<form id="form-revalidate" action="" method="POST" style="display:none;">
    {!! csrf_field() !!}
</form>
<form id="form-uninstall" action="" method="POST" style="display:none;">
    {!! csrf_field() !!}
    @method('DELETE')
</form>

<style>
.themes-manager {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Header */
.themes-header h2 {
    color: #333;
    font-weight: 600;
}

/* Banner tema activo */
.bg-gradient-primary {
    background: var(--primary-gradient);
}

.active-theme-icon {
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Section headers */
.section-icon {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

/* Theme cards */
.theme-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.theme-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.theme-card.active {
    border-color: #667eea;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}

.theme-card.not-validated {
    border-color: #ffc107;
}

/* Ribbons */
.active-ribbon {
    position: absolute;
    top: 15px;
    right: -35px;
    z-index: 10;
    transform: rotate(45deg);
}

.active-ribbon span {
    display: block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 5px 40px;
    letter-spacing: 1px;
}

.warning-ribbon {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 10;
}

.warning-ribbon span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background: #ffc107;
    color: #856404;
    border-radius: 50%;
    font-size: 14px;
}

/* Theme preview */
.theme-preview {
    position: relative;
    height: 180px;
    overflow: hidden;
    cursor: pointer;
    background: #f8f9fa;
}

.theme-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.theme-card:hover .theme-preview img {
    transform: scale(1.05);
}

.theme-preview .no-image {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    color: #adb5bd;
}

.theme-preview .no-image i {
    font-size: 3rem;
    margin-bottom: 10px;
}

.theme-preview .no-image span {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.preview-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    color: white;
}

.theme-preview:hover .preview-overlay {
    opacity: 1;
}

/* Empty state */
.empty-state {
    border-color: #dee2e6 !important;
}

.empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto;
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon i {
    font-size: 2.5rem;
    color: #adb5bd;
}

/* Badges */
.bg-success-subtle {
    background-color: rgba(25, 135, 84, 0.1) !important;
}

.bg-danger-subtle {
    background-color: rgba(220, 53, 69, 0.1) !important;
}

/* Security info */
.security-badge {
    padding: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .themes-header .btn-lg {
        width: 100%;
        margin-top: 15px;
    }

    .active-theme-banner .card-body {
        text-align: center;
    }

    .active-theme-banner .d-flex {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Upload theme modal
    const uploadButtons = document.querySelectorAll('#btn-upload-theme, #btn-upload-theme-empty');
    uploadButtons.forEach(btn => {
        btn.addEventListener('click', showUploadModal);
    });

    // Activate global theme
    document.querySelectorAll('.btn-activate-global').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const name = this.dataset.name;

            Swal.fire({
                title: 'Activar tema',
                html: `<p>¿Deseas activar el tema <strong>${name}</strong>?</p><small class="text-muted">Tu sitio cambiará de apariencia inmediatamente.</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check me-1"></i> Sí, activar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('form-activate-global');
                    form.action = '{{ admin_url("themes/activate-global") }}/' + slug;
                    form.submit();
                }
            });
        });
    });

    // Activate custom theme
    document.querySelectorAll('.btn-activate-custom').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const name = this.dataset.name;

            Swal.fire({
                title: 'Activar tema personalizado',
                html: `<p>¿Deseas activar el tema <strong>${name}</strong>?</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check me-1"></i> Sí, activar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('form-activate-custom');
                    form.action = '{{ admin_url("themes/activate-custom") }}/' + slug;
                    form.submit();
                }
            });
        });
    });

    // Revalidate theme
    document.querySelectorAll('.btn-revalidate').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;

            Swal.fire({
                title: 'Revalidando tema...',
                html: 'Por favor espera mientras verificamos la seguridad del tema.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    const form = document.getElementById('form-revalidate');
                    form.action = '{{ admin_url("themes/revalidate") }}/' + slug;
                    form.submit();
                }
            });
        });
    });

    // Uninstall theme
    document.querySelectorAll('.btn-uninstall').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const name = this.dataset.name;

            Swal.fire({
                title: '¿Eliminar tema?',
                html: `<p>Vas a eliminar el tema <strong>${name}</strong>.</p><p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Esta acción no se puede deshacer.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('form-uninstall');
                    form.action = '{{ admin_url("themes/uninstall") }}/' + slug;
                    form.submit();
                }
            });
        });
    });
});

// Upload modal
function showUploadModal() {
    Swal.fire({
        title: '<i class="fas fa-cloud-upload-alt text-primary me-2"></i>Subir Tema',
        html: `
            <form id="upload-theme-form" action="{{ admin_url('themes/upload') }}" method="POST" enctype="multipart/form-data">
                {!! csrf_field() !!}
                <div class="alert alert-warning text-start small mb-3">
                    <i class="fas fa-shield-alt me-1"></i>
                    Todos los temas se validan automáticamente por seguridad.
                </div>
                <div class="mb-3 text-start">
                    <label class="form-label fw-semibold">Archivo ZIP del tema</label>
                    <input type="file" class="form-control" id="theme_zip" name="theme_zip" accept=".zip" required>
                    <small class="text-muted">Máximo 20MB</small>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-upload me-1"></i> Instalar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const fileInput = document.getElementById('theme_zip');
            if (!fileInput.files.length) {
                Swal.showValidationMessage('Selecciona un archivo ZIP');
                return false;
            }
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Instalando tema...',
                html: 'Por favor espera mientras se valida e instala el tema.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    document.getElementById('upload-theme-form').submit();
                }
            });
        }
    });
}

// Preview theme
function previewTheme(name, imagePath, description) {
    const imageHtml = imagePath
        ? `<img src="${imagePath}" class="img-fluid rounded mb-3" style="max-height: 300px; object-fit: contain;">`
        : `<div class="bg-light rounded d-flex flex-column align-items-center justify-content-center mb-3" style="height: 200px;">
               <i class="fas fa-image fa-4x text-muted mb-2"></i>
               <span class="text-muted">Sin vista previa disponible</span>
           </div>`;

    Swal.fire({
        title: name,
        html: `${imageHtml}<p class="text-muted">${description}</p>`,
        width: 600,
        showCloseButton: true,
        showConfirmButton: false
    });
}

// Show validation errors
function showValidationErrors(errors) {
    let errorList = '<ul class="text-start text-danger small mb-0">';
    errors.forEach(error => {
        errorList += `<li>${error}</li>`;
    });
    errorList += '</ul>';

    Swal.fire({
        title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i>Errores de Validación',
        html: errorList,
        icon: 'error',
        confirmButtonColor: '#667eea'
    });
}
</script>
@endsection
