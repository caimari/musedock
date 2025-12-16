@extends('layouts.app')

@section('title', $title ?? __('themes.title'))

@push('styles')
<style>
/* Contenedor principal */
.themes-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Header */
.themes-header {
    margin-bottom: 2rem;
}
.themes-header h2 {
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 0.25rem;
}
.themes-header p {
    color: #6c757d;
    margin-bottom: 0;
}

/* Banner tema activo */
.active-theme-card {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
    border-radius: 16px;
    padding: 1.5rem;
    color: white;
    margin-bottom: 2rem;
}
.active-theme-card .icon-circle {
    width: 56px;
    height: 56px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.active-theme-card .theme-label {
    font-size: 0.85rem;
    opacity: 0.85;
}
.active-theme-card .theme-name {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}
.active-theme-card .badge {
    background: rgba(255,255,255,0.25);
    color: white;
    font-weight: 500;
}

/* Section header */
.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}
.section-header .icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.section-header .icon-box.official {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}
.section-header .icon-box.custom {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}
.section-header h4 {
    font-weight: 600;
    margin: 0;
    color: #1a1a2e;
}
.section-header small {
    color: #6c757d;
}

/* Theme cards - Grid style */
.theme-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.theme-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.2s ease;
    position: relative;
}
.theme-card:hover {
    border-color: #c7d2fe;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.1);
    transform: translateY(-2px);
}
.theme-card.is-active {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Active badge ribbon */
.theme-card .active-badge {
    position: absolute;
    top: 12px;
    right: -32px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 4px 40px;
    transform: rotate(45deg);
    letter-spacing: 0.5px;
    z-index: 5;
}

/* Theme preview area */
.theme-preview {
    height: 160px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.theme-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.theme-preview .placeholder-icon {
    font-size: 3rem;
    color: #94a3b8;
    margin-bottom: 0.5rem;
}
.theme-preview .placeholder-text {
    font-size: 0.75rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Theme info */
.theme-info {
    padding: 1.25rem;
}
.theme-info .theme-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}
.theme-info .theme-desc {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 0.75rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.theme-info .theme-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #94a3b8;
}
.theme-info .theme-meta i {
    margin-right: 4px;
}

/* Theme actions */
.theme-actions {
    padding: 0 1.25rem 1.25rem;
    display: flex;
    gap: 0.5rem;
}
.theme-actions .btn {
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
}
.theme-actions .btn-active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border: none;
    color: white;
}
.theme-actions .btn-activate {
    background: white;
    border: 1px solid #6366f1;
    color: #6366f1;
}
.theme-actions .btn-activate:hover {
    background: #6366f1;
    color: white;
}
.theme-actions .btn-icon {
    padding: 0.5rem 0.65rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
}
.theme-actions .btn-icon:hover {
    background: #e2e8f0;
    color: #475569;
}

/* Badges */
.badge-official {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    font-weight: 500;
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
}

/* Empty state */
.empty-state {
    background: #f8fafc;
    border: 2px dashed #e2e8f0;
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
}
.empty-state .empty-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}
.empty-state .empty-icon i {
    font-size: 2rem;
    color: #94a3b8;
}
.empty-state h5 {
    color: #475569;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.empty-state p {
    color: #94a3b8;
    margin-bottom: 1.5rem;
}

/* Responsive */
@media (max-width: 576px) {
    .theme-grid {
        grid-template-columns: 1fr;
    }
    .active-theme-card {
        text-align: center;
    }
    .active-theme-card .d-flex {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>
@endpush

@section('content')
<div class="themes-container">
    <!-- Header -->
    <div class="themes-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h2><i class="bi bi-palette2 me-2"></i>{{ __('themes.title') ?? 'Gestión de Temas' }}</h2>
            <p>{{ __('themes.subtitle') ?? 'Personaliza el aspecto de tu sitio web' }}</p>
        </div>
        <button class="btn btn-primary" id="btn-upload-theme">
            <i class="bi bi-cloud-upload me-2"></i>{{ __('themes.upload') ?? 'Subir Tema' }}
        </button>
    </div>

    <!-- Tema Activo Banner -->
    <div class="active-theme-card">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-circle">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="theme-label">{{ __('themes.current_theme') ?? 'Tema activo actualmente' }}</div>
                    <h5 class="theme-name">
                        @if($tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug'])
                            {{ $tenant['custom_theme_slug'] }}
                            <span class="badge ms-2">{{ __('themes.custom') ?? 'Personalizado' }}</span>
                        @else
                            {{ ucfirst($tenant['theme'] ?? 'default') }}
                            <span class="badge ms-2">{{ __('themes.official') ?? 'Oficial' }}</span>
                        @endif
                    </h5>
                </div>
            </div>
            <a href="/{{ admin_path() }}/themes/appearance/{{ $tenant['theme_type'] === 'custom' ? $tenant['custom_theme_slug'] : $tenant['theme'] }}" class="btn btn-light btn-sm">
                <i class="bi bi-brush me-1"></i>{{ __('themes.customize') ?? 'Personalizar' }}
            </a>
        </div>
    </div>

    <!-- Temas Oficiales -->
    <div class="themes-section mb-5">
        <div class="section-header">
            <div class="icon-box official">
                <i class="bi bi-award"></i>
            </div>
            <div class="flex-grow-1">
                <h4>{{ __('themes.official_themes') ?? 'Temas Oficiales' }}</h4>
                <small>{{ __('themes.official_desc') ?? 'Desarrollados por MuseDock, seguros y optimizados' }}</small>
            </div>
            <span class="badge bg-success">
                <i class="bi bi-shield-check me-1"></i>{{ __('themes.verified') ?? 'Verificados' }}
            </span>
        </div>

        @if(empty($globalThemes))
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-palette"></i>
                </div>
                <h5>{{ __('themes.no_official') ?? 'Sin temas disponibles' }}</h5>
                <p>{{ __('themes.no_official_desc') ?? 'No hay temas oficiales habilitados para tu cuenta.' }}</p>
            </div>
        @else
            <div class="theme-grid">
                @foreach($globalThemes as $theme)
                    @php
                        $isActive = $tenant['theme_type'] === 'global' && $tenant['theme'] === $theme['slug'];
                        $screenshotFile = $theme['screenshot'] ? APP_ROOT . "/themes/{$theme['slug']}/{$theme['screenshot']}" : null;
                        $screenshotPath = ($screenshotFile && file_exists($screenshotFile)) ? "/themes/{$theme['slug']}/{$theme['screenshot']}" : null;
                    @endphp
                    <div class="theme-card {{ $isActive ? 'is-active' : '' }}">
                        @if($isActive)
                            <div class="active-badge">{{ __('themes.active') ?? 'ACTIVO' }}</div>
                        @endif

                        <div class="theme-preview">
                            @if($screenshotPath)
                                <img src="{{ $screenshotPath }}" alt="{{ $theme['name'] }}" loading="lazy">
                            @else
                                <i class="bi bi-palette placeholder-icon"></i>
                                <span class="placeholder-text">{{ $theme['slug'] }}</span>
                            @endif
                        </div>

                        <div class="theme-info">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="theme-title">{{ $theme['name'] }}</span>
                                <span class="badge-official">
                                    <i class="bi bi-check-circle-fill me-1"></i>{{ __('themes.official') ?? 'Oficial' }}
                                </span>
                            </div>
                            <p class="theme-desc">{{ $theme['description'] ?: __('themes.default_desc') ?? 'Tema por defecto de MuseDock' }}</p>
                            <div class="theme-meta">
                                <span><i class="bi bi-person"></i>{{ $theme['author'] }}</span>
                                <span><i class="bi bi-tag"></i>v{{ $theme['version'] }}</span>
                            </div>
                        </div>

                        <div class="theme-actions">
                            @if($isActive)
                                <button class="btn btn-active flex-grow-1" disabled>
                                    <i class="bi bi-check2 me-1"></i>{{ __('themes.active_theme') ?? 'Tema Activo' }}
                                </button>
                                <a href="/{{ admin_path() }}/themes/appearance/{{ $theme['slug'] }}" class="btn btn-icon" title="{{ __('themes.customize') ?? 'Personalizar' }}">
                                    <i class="bi bi-brush"></i>
                                </a>
                                <a href="/{{ admin_path() }}/widgets" class="btn btn-icon" title="{{ __('themes.widgets') ?? 'Widgets' }}">
                                    <i class="bi bi-grid-3x3"></i>
                                </a>
                            @else
                                <button type="button" class="btn btn-activate flex-grow-1 btn-activate-global"
                                        data-slug="{{ $theme['slug'] }}"
                                        data-name="{{ $theme['name'] }}">
                                    <i class="bi bi-power me-1"></i>{{ __('themes.activate') ?? 'Activar' }}
                                </button>
                                <a href="/{{ admin_path() }}/themes/appearance/{{ $theme['slug'] }}" class="btn btn-icon" title="{{ __('themes.preview') ?? 'Ver opciones' }}">
                                    <i class="bi bi-eye"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Temas Personalizados -->
    <div class="themes-section">
        <div class="section-header">
            <div class="icon-box custom">
                <i class="bi bi-magic"></i>
            </div>
            <div class="flex-grow-1">
                <h4>{{ __('themes.custom_themes') ?? 'Mis Temas Personalizados' }}</h4>
                <small>{{ __('themes.custom_desc') ?? 'Temas exclusivos subidos por ti' }}</small>
            </div>
            <span class="badge bg-primary">{{ count($customThemes) }} {{ __('themes.themes_count') ?? 'tema(s)' }}</span>
        </div>

        @if(empty($customThemes))
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <h5>{{ __('themes.no_custom') ?? 'No tienes temas personalizados' }}</h5>
                <p>{{ __('themes.no_custom_desc') ?? 'Sube tu propio tema para darle un toque único a tu sitio web' }}</p>
                <button class="btn btn-primary" id="btn-upload-theme-empty">
                    <i class="bi bi-upload me-2"></i>{{ __('themes.upload_first') ?? 'Subir mi primer tema' }}
                </button>
            </div>
        @else
            <div class="theme-grid">
                @foreach($customThemes as $theme)
                    @php
                        $isActive = $tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug'] === $theme['slug'];
                        $screenshotFile = $theme['screenshot'] ? APP_ROOT . "/storage/tenants/{$tenantId}/themes/{$theme['slug']}/{$theme['screenshot']}" : null;
                        $screenshotPath = ($screenshotFile && file_exists($screenshotFile)) ? "/storage/tenants/{$tenantId}/themes/{$theme['slug']}/{$theme['screenshot']}" : null;
                    @endphp
                    <div class="theme-card {{ $isActive ? 'is-active' : '' }}">
                        @if($isActive)
                            <div class="active-badge">{{ __('themes.active') ?? 'ACTIVO' }}</div>
                        @endif

                        <div class="theme-preview">
                            @if($screenshotPath)
                                <img src="{{ $screenshotPath }}" alt="{{ $theme['name'] }}" loading="lazy">
                            @else
                                <i class="bi bi-palette placeholder-icon"></i>
                                <span class="placeholder-text">{{ $theme['slug'] }}</span>
                            @endif
                        </div>

                        <div class="theme-info">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="theme-title">{{ $theme['name'] }}</span>
                                @if($theme['validated'])
                                    <span class="badge-official" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                                        <i class="bi bi-shield-check me-1"></i>{{ $theme['security_score'] }}%
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger" style="font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 6px;">
                                        <i class="bi bi-exclamation-circle me-1"></i>{{ __('themes.not_safe') ?? 'No seguro' }}
                                    </span>
                                @endif
                            </div>
                            <p class="theme-desc">{{ $theme['description'] ?? __('themes.no_desc') ?? 'Sin descripción' }}</p>
                            <div class="theme-meta">
                                <span><i class="bi bi-person"></i>{{ $theme['author'] ?? __('themes.unknown') ?? 'Desconocido' }}</span>
                                <span><i class="bi bi-tag"></i>v{{ $theme['version'] }}</span>
                            </div>
                        </div>

                        <div class="theme-actions">
                            @if($isActive)
                                <button class="btn btn-active flex-grow-1" disabled>
                                    <i class="bi bi-check2 me-1"></i>{{ __('themes.active') ?? 'Activo' }}
                                </button>
                                <a href="/{{ admin_path() }}/widgets" class="btn btn-icon" title="{{ __('themes.widgets') ?? 'Widgets' }}">
                                    <i class="bi bi-sliders"></i>
                                </a>
                            @elseif($theme['validated'])
                                <button type="button" class="btn btn-activate flex-grow-1 btn-activate-custom"
                                        data-slug="{{ $theme['slug'] }}"
                                        data-name="{{ $theme['name'] }}">
                                    <i class="bi bi-power me-1"></i>{{ __('themes.activate') ?? 'Activar' }}
                                </button>
                            @else
                                <button class="btn btn-secondary flex-grow-1" disabled>
                                    <i class="bi bi-lock me-1"></i>{{ __('themes.not_available') ?? 'No Disponible' }}
                                </button>
                            @endif

                            <button type="button" class="btn btn-icon btn-revalidate" data-slug="{{ $theme['slug'] }}" title="{{ __('themes.revalidate') ?? 'Revalidar' }}">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>

                            @if(!$isActive)
                                <button type="button" class="btn btn-icon btn-uninstall" data-slug="{{ $theme['slug'] }}" data-name="{{ $theme['name'] }}" title="{{ __('themes.delete') ?? 'Eliminar' }}" style="color: #ef4444;">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<!-- Forms ocultos -->
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Upload theme modal
    document.querySelectorAll('#btn-upload-theme, #btn-upload-theme-empty').forEach(btn => {
        btn.addEventListener('click', showUploadModal);
    });

    // Activate global theme
    document.querySelectorAll('.btn-activate-global').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const name = this.dataset.name;

            Swal.fire({
                title: '<i class="bi bi-palette2 text-primary"></i>',
                html: `
                    <h5 class="mb-2">{!! json_encode(__('themes.activate_title') ?? 'Activar tema') !!}</h5>
                    <p class="text-muted mb-0">{!! json_encode(__('themes.activate_confirm') ?? '¿Deseas activar el tema') !!} <strong>${name}</strong>?</p>
                `,
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-lg me-1"></i> {!! json_encode(__('themes.yes_activate') ?? 'Sí, activar') !!}',
                cancelButtonText: '{!! json_encode(__('cancel') ?? 'Cancelar') !!}'
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
                title: '<i class="bi bi-magic text-primary"></i>',
                html: `
                    <h5 class="mb-2">{!! json_encode(__('themes.activate_custom') ?? 'Activar tema personalizado') !!}</h5>
                    <p class="text-muted mb-0">{!! json_encode(__('themes.activate_confirm') ?? '¿Deseas activar el tema') !!} <strong>${name}</strong>?</p>
                `,
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-lg me-1"></i> {!! json_encode(__('themes.yes_activate') ?? 'Sí, activar') !!}',
                cancelButtonText: '{!! json_encode(__('cancel') ?? 'Cancelar') !!}'
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
                title: '{!! json_encode(__('themes.revalidating') ?? 'Revalidando tema...') !!}',
                html: '{!! json_encode(__('themes.revalidating_desc') ?? 'Por favor espera mientras verificamos la seguridad del tema.') !!}',
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
                title: '<i class="bi bi-exclamation-triangle text-danger"></i>',
                html: `
                    <h5 class="mb-2 text-danger">{!! json_encode(__('themes.delete_title') ?? '¿Eliminar tema?') !!}</h5>
                    <p class="mb-1">{!! json_encode(__('themes.delete_confirm') ?? 'Vas a eliminar el tema') !!} <strong>${name}</strong>.</p>
                    <p class="text-danger small mb-0"><i class="bi bi-exclamation-circle me-1"></i>{!! json_encode(__('themes.delete_warning') ?? 'Esta acción no se puede deshacer.') !!}</p>
                `,
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3 me-1"></i> {!! json_encode(__('themes.yes_delete') ?? 'Sí, eliminar') !!}',
                cancelButtonText: '{!! json_encode(__('cancel') ?? 'Cancelar') !!}'
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

function showUploadModal() {
    Swal.fire({
        title: '<i class="bi bi-cloud-upload text-primary me-2"></i>{!! json_encode(__('themes.upload') ?? 'Subir Tema') !!}',
        html: `
            <form id="upload-theme-form" action="{{ admin_url('themes/upload') }}" method="POST" enctype="multipart/form-data">
                {!! csrf_field() !!}
                <div class="alert alert-warning text-start small mb-3 py-2">
                    <i class="bi bi-shield-check me-1"></i>
                    {!! json_encode(__('themes.security_notice') ?? 'Todos los temas se validan automáticamente por seguridad.') !!}
                </div>
                <div class="mb-3 text-start">
                    <label class="form-label fw-semibold">{!! json_encode(__('themes.zip_file') ?? 'Archivo ZIP del tema') !!}</label>
                    <input type="file" class="form-control" id="theme_zip" name="theme_zip" accept=".zip" required>
                    <small class="text-muted">{!! json_encode(__('themes.max_size') ?? 'Máximo 20MB') !!}</small>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-upload me-1"></i> {!! json_encode(__('themes.install') ?? 'Instalar') !!}',
        cancelButtonText: '{!! json_encode(__('cancel') ?? 'Cancelar') !!}',
        preConfirm: () => {
            const fileInput = document.getElementById('theme_zip');
            if (!fileInput.files.length) {
                Swal.showValidationMessage('{!! json_encode(__('themes.select_zip') ?? 'Selecciona un archivo ZIP') !!}');
                return false;
            }
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '{!! json_encode(__('themes.installing') ?? 'Instalando tema...') !!}',
                html: '{!! json_encode(__('themes.installing_desc') ?? 'Por favor espera mientras se valida e instala el tema.') !!}',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    document.getElementById('upload-theme-form').submit();
                }
            });
        }
    });
}
</script>
@endpush
