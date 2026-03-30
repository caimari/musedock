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
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #10b981;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    color: #1e293b;
    margin-bottom: 2rem;
}
.active-theme-card .icon-circle {
    width: 44px;
    height: 44px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #10b981;
}
.active-theme-card .theme-label {
    font-size: 0.8rem;
    color: #64748b;
}
.active-theme-card .theme-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
    color: #1e293b;
}
.active-theme-card .badge {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
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
    border-color: #93c5fd;
    box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.15);
}

/* Active badge ribbon */
.theme-card .active-badge {
    position: absolute;
    top: 12px;
    right: -32px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
    background: #10b981;
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

/* ==================== SKINS ==================== */
.skins-section {
    margin-top: 2.5rem;
    margin-bottom: 2rem;
}
.section-header .icon-box.skins {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
}
.skin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.25rem;
    align-items: stretch;
}
.skin-card {
    background: white;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.2s ease;
    position: relative;
    display: flex;
    flex-direction: column;
}
.skin-card:hover {
    border-color: #c4b5fd;
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.12);
    transform: translateY(-2px);
}
.skin-card.skin-active {
    border-color: #22c55e;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25);
}
.skin-card.skin-active:hover {
    border-color: #22c55e;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25), 0 6px 20px rgba(34, 197, 94, 0.12);
}
.skin-card .skin-preview {
    height: 130px;
    background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.skin-card .skin-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.skin-card .skin-preview .skin-placeholder {
    text-align: center;
}
.skin-card .skin-preview .skin-placeholder i {
    font-size: 2.5rem;
    color: #a78bfa;
    display: block;
    margin-bottom: 0.25rem;
}
.skin-card .skin-preview .skin-placeholder span {
    font-size: 0.7rem;
    color: #a78bfa;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.skin-card .skin-preview .skin-color-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 6px;
    display: flex;
}
.skin-card .skin-preview .skin-color-bar span {
    flex: 1;
}
.skin-card .skin-info {
    padding: 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.skin-card .skin-info .skin-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.15rem;
}
.skin-card .skin-info .skin-desc {
    font-size: 0.78rem;
    color: #94a3b8;
    margin-bottom: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.3;
}
.skin-card .skin-info .skin-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: auto;
}
.skin-card .skin-actions {
    padding: 0 1rem 1rem;
    display: flex;
    gap: 0.4rem;
}
.skin-card .skin-actions .btn {
    font-size: 0.8rem;
    padding: 0.4rem 0.75rem;
    border-radius: 8px;
}
.skin-card .skin-actions .btn-apply-skin {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    border: none;
    color: white;
    flex-grow: 1;
}
.skin-card .skin-actions .btn-apply-skin:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}
.skin-card .skin-actions .btn-skin-active {
    background: #22c55e;
    border: none;
    color: white;
    flex-grow: 1;
    cursor: default;
    pointer-events: none;
}
.skin-active-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #22c55e;
    color: white;
    font-size: 0.6rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    z-index: 2;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.skin-badge-global {
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed;
    font-weight: 500;
    font-size: 0.65rem;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
}
.skin-badge-own {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    font-weight: 500;
    font-size: 0.65rem;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
}

/* Upload skin card */
.skin-card-upload {
    background: #faf5ff;
    border: 2px dashed #d8b4fe;
    border-radius: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 240px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.skin-card-upload:hover {
    border-color: #a78bfa;
    background: #f3e8ff;
}
.skin-card-upload i {
    font-size: 2rem;
    color: #a78bfa;
    margin-bottom: 0.5rem;
}
.skin-card-upload span {
    font-size: 0.85rem;
    color: #7c3aed;
    font-weight: 500;
}
.skin-card-upload small {
    font-size: 0.7rem;
    color: #a78bfa;
    margin-top: 0.25rem;
}

/* Responsive */
@media (max-width: 576px) {
    .theme-grid {
        grid-template-columns: 1fr;
    }
    .skin-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
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
                                <a href="/{{ admin_path() }}/widgets/{{ $theme['slug'] }}" class="btn btn-icon" title="{{ __('themes.widgets') ?? 'Widgets' }}">
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

    <!-- Skins del Tema Activo -->
    <div class="skins-section">
        <div class="section-header">
            <div class="icon-box skins">
                <i class="bi bi-stars"></i>
            </div>
            <div class="flex-grow-1">
                <h4>{{ __('themes.skins') ?? 'Skins' }}</h4>
                <small>{{ __('themes.skins_desc') ?? 'Cambia la estética de tu tema con un solo click' }}</small>
            </div>
            <span class="badge" style="background: rgba(139, 92, 246, 0.1); color: #7c3aed;">
                {{ count($skins ?? []) }} {{ __('themes.available') ?? 'disponible(s)' }}
            </span>
        </div>

        <div class="skin-grid">
            @forelse($skins ?? [] as $skin)
                @php
                    $skinOptions = is_string($skin['options']) ? json_decode($skin['options'], true) : $skin['options'];
                    $isOwn = !empty($skin['tenant_id']) && $skin['tenant_id'] == $tenantId;
                    // Extract colors for the preview bar
                    $previewColors = [
                        $skinOptions['header']['header_bg_color'] ?? '#f8f9fa',
                        $skinOptions['topbar']['topbar_bg_color'] ?? '#1a2a40',
                        $skinOptions['header']['header_cta_bg_color'] ?? '#ff5e15',
                        $skinOptions['footer']['footer_bg_color'] ?? '#1a1a2e',
                    ];
                @endphp
                <div class="skin-card{{ ($activeSkinSlug ?? '') === $skin['slug'] ? ' skin-active' : '' }}">
                    @if(($activeSkinSlug ?? '') === $skin['slug'])
                        <span class="skin-active-badge"><i class="bi bi-check-circle me-1"></i>En uso</span>
                    @endif
                    <div class="skin-preview">
                        @if(!empty($skin['screenshot']) && !str_starts_with($skin['screenshot'], 'data:'))
                            <img src="{{ $skin['screenshot'] }}" alt="{{ $skin['name'] }}" loading="lazy">
                        @elseif(!empty($skin['screenshot']) && str_starts_with($skin['screenshot'], 'data:'))
                            <img src="{{ $skin['screenshot'] }}" alt="{{ $skin['name'] }}" loading="lazy">
                        @else
                            <div class="skin-placeholder">
                                <i class="bi bi-palette2"></i>
                                <span>{{ $skin['name'] }}</span>
                            </div>
                        @endif
                        <div class="skin-color-bar">
                            @foreach($previewColors as $color)
                                <span style="background-color: {{ $color }}"></span>
                            @endforeach
                        </div>
                    </div>

                    <div class="skin-info">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="skin-name">{{ $skin['name'] }}</span>
                            @if($isOwn)
                                <span class="skin-badge-own">{{ __('themes.own') ?? 'Propio' }}</span>
                            @else
                                <span class="skin-badge-global"><i class="bi bi-globe2 me-1"></i>{{ __('themes.official') ?? 'Oficial' }}</span>
                            @endif
                        </div>
                        @if(!empty($skin['description']))
                            <p class="skin-desc">{{ $skin['description'] }}</p>
                        @endif
                        <div class="skin-meta">
                            <span><i class="bi bi-person me-1"></i>{{ $skin['author'] ?? 'MuseDock' }}</span>
                            <span><i class="bi bi-download me-1"></i>{{ $skin['install_count'] ?? 0 }}</span>
                        </div>
                    </div>

                    <div class="skin-actions">
                        @if(($activeSkinSlug ?? '') === $skin['slug'])
                            <button type="button" class="btn btn-skin-active">
                                <i class="bi bi-check-lg me-1"></i>{{ __('themes.in_use') ?? 'En uso' }}
                            </button>
                        @else
                            <button type="button" class="btn btn-apply-skin"
                                    data-slug="{{ $skin['slug'] }}"
                                    data-name="{{ $skin['name'] }}">
                                <i class="bi bi-magic me-1"></i>{{ __('themes.apply') ?? 'Aplicar' }}
                            </button>
                        @endif
                        @if($isOwn)
                            <button type="button" class="btn btn-icon btn-delete-skin"
                                    data-slug="{{ $skin['slug'] }}"
                                    data-name="{{ $skin['name'] }}"
                                    title="{{ __('themes.delete') ?? 'Eliminar' }}"
                                    style="color: #ef4444; padding: 0.4rem 0.55rem;">
                                <i class="bi bi-trash3"></i>
                            </button>
                        @endif
                    </div>
                </div>
            @empty
            @endforelse

            <!-- Upload skin card -->
            <div class="skin-card-upload" id="btn-upload-skin">
                <i class="bi bi-cloud-upload"></i>
                <span>{{ __('themes.upload_skin') ?? 'Subir Skin' }}</span>
                <small>.skin.json</small>
            </div>
        </div>
    </div>

</div>

<!-- Forms ocultos -->
<form id="form-activate-global" action="" method="POST" style="display:none;">
    {!! csrf_field() !!}
</form>
<form id="form-apply-skin" action="" method="POST" style="display:none;">
    {!! csrf_field() !!}
</form>
<form id="form-delete-skin" action="" method="POST" style="display:none;">
    {!! csrf_field() !!}
</form>
<form id="form-upload-skin" action="{{ admin_url('themes/skins/upload') }}" method="POST" enctype="multipart/form-data" style="display:none;">
    {!! csrf_field() !!}
    <input type="file" name="skin_file" id="skin_file_input" accept=".json">
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
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

});

    // ==================== SKINS ====================

    // Apply skin
    document.querySelectorAll('.btn-apply-skin').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const name = this.dataset.name;

            Swal.fire({
                title: '<i class="bi bi-stars text-purple"></i>',
                html: `
                    <h5 class="mb-2">Aplicar Skin</h5>
                    <p class="text-muted mb-2">Se aplicará el skin <strong>${name}</strong> a tu tema actual.</p>
                    <div class="alert alert-info text-start small py-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Esto cambiará los colores, fuentes y estilos de tu tema. Puedes revertirlo desde la personalización del tema.
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-magic me-1"></i> Aplicar Skin',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Aplicando skin...',
                        html: 'Configurando colores, fuentes y estilos...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                            const form = document.getElementById('form-apply-skin');
                            form.action = '{{ admin_url("themes/skins/apply") }}/' + slug;
                            form.submit();
                        }
                    });
                }
            });
        });
    });

    // Delete skin
    document.querySelectorAll('.btn-delete-skin').forEach(btn => {
        btn.addEventListener('click', function() {
            const slug = this.dataset.slug;
            const name = this.dataset.name;

            Swal.fire({
                title: '<i class="bi bi-exclamation-triangle text-danger"></i>',
                html: `
                    <h5 class="mb-2 text-danger">¿Eliminar skin?</h5>
                    <p class="mb-0">Se eliminará el skin <strong>${name}</strong> permanentemente.</p>
                `,
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('form-delete-skin');
                    form.action = '{{ admin_url("themes/skins/delete") }}/' + slug;
                    form.submit();
                }
            });
        });
    });

    // Upload skin
    document.getElementById('btn-upload-skin').addEventListener('click', function() {
        Swal.fire({
            title: '<i class="bi bi-cloud-upload text-purple me-2"></i>Subir Skin',
            html: `
                <div class="alert alert-info text-start small mb-3 py-2">
                    <i class="bi bi-shield-check me-1"></i>
                    Los skins son seguros: solo contienen colores, fuentes y estilos (sin código ejecutable).
                </div>
                <div class="mb-3 text-start">
                    <label class="form-label fw-semibold">Archivo de Skin (.skin.json)</label>
                    <input type="file" class="form-control" id="skin_file_modal" accept=".json" required>
                    <small class="text-muted">Máximo 2MB. Formato .skin.json</small>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#7c3aed',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-upload me-1"></i> Instalar Skin',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const fileInput = document.getElementById('skin_file_modal');
                if (!fileInput.files.length) {
                    Swal.showValidationMessage('Selecciona un archivo .skin.json');
                    return false;
                }
                const fileName = fileInput.files[0].name;
                if (!fileName.endsWith('.json')) {
                    Swal.showValidationMessage('Solo se aceptan archivos .json');
                    return false;
                }
                return true;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const fileInput = document.getElementById('skin_file_modal');
                const realInput = document.getElementById('skin_file_input');

                // Transfer the file
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(fileInput.files[0]);
                realInput.files = dataTransfer.files;

                Swal.fire({
                    title: 'Instalando skin...',
                    html: 'Validando y guardando el skin...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        document.getElementById('form-upload-skin').submit();
                    }
                });
            }
        });
    });

</script>
@endpush
