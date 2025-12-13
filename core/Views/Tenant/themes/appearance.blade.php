@extends('layouts.app')

@section('title', $title ?? 'Personalizar Apariencia')

@push('styles')
<style>
/* Fix breadcrumb numbered list override */
nav[aria-label="breadcrumb"] ol.breadcrumb {
    list-style: none !important;
    counter-reset: none !important;
    padding-left: 0 !important;
}
nav[aria-label="breadcrumb"] ol.breadcrumb li {
    list-style: none !important;
}
nav[aria-label="breadcrumb"] ol.breadcrumb li::before {
    content: none !important;
    counter-increment: none !important;
}
nav[aria-label="breadcrumb"] .breadcrumb-item + .breadcrumb-item::before {
    content: "/" !important;
    float: left;
    padding-right: 0.5rem;
    color: var(--tblr-breadcrumb-divider-color, #6c757d);
}
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <a href="/{{ admin_path() }}/themes"><i class="fas fa-palette me-1"></i>Temas</a>
                        </li>
                        <li class="breadcrumb-item active">Personalizar: {{ $theme['name'] ?? $slug }}</li>
                    </ol>
                </nav>
                <h2 class="mb-0"><i class="fas fa-paint-brush me-2"></i>{{ $title }}</h2>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary" id="btn-presets-header">
                    <i class="fas fa-bookmark me-1"></i> Presets
                </button>
                <button type="submit" form="appearanceForm" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Guardar Cambios
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Panel de opciones -->
            <div class="col-lg-8">
                @if($hasCustomOptions)
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Tienes personalizaciones activas para este tema.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @else
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    Estás usando los valores por defecto del tema. Personalízalo a tu gusto.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                <form method="POST" action="/{{ admin_path() }}/themes/appearance/{{ $slug }}/save" id="appearanceForm">
                    {!! csrf_field() !!}

                    @foreach ($optionsSchema as $sectionSlug => $section)
                        @if(($section['type'] ?? '') === 'section')
                        <div class="appearance-section card mb-4" id="section-{{ $sectionSlug }}">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    @switch($sectionSlug)
                                        @case('topbar')
                                            <i class="fas fa-grip-lines text-primary me-2"></i>
                                            @break
                                        @case('header')
                                            <i class="fas fa-heading text-primary me-2"></i>
                                            @break
                                        @case('footer')
                                            <i class="fas fa-shoe-prints text-primary me-2"></i>
                                            @break
                                        @case('custom_code')
                                            <i class="fas fa-code text-primary me-2"></i>
                                            @break
                                        @default
                                            <i class="fas fa-cog text-primary me-2"></i>
                                    @endswitch
                                    {{ $section['label'] ?? 'Sección' }}
                                </h5>
                            </div>
                            <div class="card-body">
                                @if(!empty($section['options']))
                                    <div class="row">
                                    @foreach ($section['options'] as $optionSlug => $option)
                                        @php
                                            $optionKey = $sectionSlug . '.' . $optionSlug;
                                            $savedValue = array_get_nested($savedOptions ?? [], $optionKey, $option['default'] ?? null);
                                            $currentValue = old('options.' . $optionKey, $savedValue);
                                            $colClass = in_array($option['type'] ?? '', ['code', 'textarea']) ? 'col-12' : 'col-md-6';
                                        @endphp

                                        <div class="{{ $colClass }} mb-3 option-group"
                                             id="group-{{ str_replace('.', '-', $optionKey) }}"
                                             @if(!empty($option['depends_on']))
                                                 data-depends-on="{{ $option['depends_on'] }}"
                                                 style="{{ array_check_dependency($savedOptions ?? [], $option['depends_on'] ?? null) ? '' : 'display: none;' }}"
                                             @endif>

                                            <label for="option-{{ str_replace('.', '-', $optionKey) }}" class="form-label fw-semibold">
                                                {{ $option['label'] ?? 'Opción' }}
                                            </label>

                                            @switch($option['type'] ?? 'text')
                                                @case('toggle')
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" value="1" role="switch"
                                                               id="option-{{ str_replace('.', '-', $optionKey) }}"
                                                               name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                               @checked($currentValue)
                                                               data-dependency-driver="true">
                                                        <label class="form-check-label" for="option-{{ str_replace('.', '-', $optionKey) }}">
                                                            {{ $currentValue ? 'Activado' : 'Desactivado' }}
                                                        </label>
                                                    </div>
                                                    <input type="hidden" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]" value="0" @if($currentValue) disabled @endif>
                                                    @break

                                                @case('color')
                                                    <div class="input-group">
                                                        <input type="color" class="form-control form-control-color"
                                                               id="option-{{ str_replace('.', '-', $optionKey) }}"
                                                               name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                               value="{{ $currentValue ?? '#ffffff' }}"
                                                               title="Elige un color">
                                                        <input type="text" class="form-control color-hex-input"
                                                               value="{{ $currentValue ?? '#ffffff' }}"
                                                               pattern="^#[0-9A-Fa-f]{6}$"
                                                               placeholder="#000000">
                                                    </div>
                                                    @break

                                                @case('text')
                                                @case('url')
                                                    <input type="{{ $option['type'] === 'url' ? 'url' : 'text' }}"
                                                           class="form-control"
                                                           id="option-{{ str_replace('.', '-', $optionKey) }}"
                                                           name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                           value="{{ $currentValue ?? '' }}"
                                                           placeholder="{{ $option['placeholder'] ?? '' }}">
                                                    @break

                                                @case('select')
                                                    <select class="form-select"
                                                            id="option-{{ str_replace('.', '-', $optionKey) }}"
                                                            name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                            @if($option['is_driver'] ?? false) data-dependency-driver="true" @endif>
                                                        @if(!empty($option['options']) && is_array($option['options']))
                                                            @foreach($option['options'] as $value => $label)
                                                                <option value="{{ $value }}" @selected($currentValue == $value)>
                                                                    {{ $label }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                    @break

                                                @case('textarea')
                                                    <textarea class="form-control"
                                                              id="option-{{ str_replace('.', '-', $optionKey) }}"
                                                              name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                              rows="{{ $option['rows'] ?? 3 }}">{{ $currentValue ?? '' }}</textarea>
                                                    @break

                                                @case('code')
                                                    <textarea class="form-control font-monospace code-editor"
                                                              id="option-{{ str_replace('.', '-', $optionKey) }}"
                                                              name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                              rows="8"
                                                              placeholder="Escribe tu código {{ $option['language'] ?? '' }} aquí..."
                                                              data-language="{{ $option['language'] ?? 'css' }}">{{ $currentValue ?? '' }}</textarea>
                                                    <small class="text-muted">
                                                        <i class="fas fa-code me-1"></i>{{ strtoupper($option['language'] ?? 'Código') }}
                                                    </small>
                                                    @break

                                                @case('image')
                                                    <div class="input-group">
                                                        <input type="text" class="form-control"
                                                               id="option-{{ str_replace('.', '-', $optionKey) }}"
                                                               name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                               value="{{ $currentValue ?? '' }}"
                                                               placeholder="URL de la imagen">
                                                        <button type="button" class="btn btn-outline-secondary btn-media-manager"
                                                                data-input-id="option-{{ str_replace('.', '-', $optionKey) }}">
                                                            <i class="fas fa-image"></i>
                                                        </button>
                                                    </div>
                                                    @if($currentValue)
                                                        <img src="{{ $currentValue }}" class="mt-2 img-thumbnail" style="max-height: 80px;">
                                                    @endif
                                                    @break

                                                @default
                                                    <input type="text" class="form-control"
                                                           name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                           value="{{ $currentValue ?? '' }}">
                                            @endswitch

                                            @if(!empty($option['description']))
                                                <small class="form-text text-muted">{{ $option['description'] }}</small>
                                            @endif
                                        </div>
                                    @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    @endforeach

                    <!-- Botones de acción -->
                    <div class="d-flex justify-content-between mb-4">
                        <button type="button" class="btn btn-outline-danger" id="btn-reset-defaults">
                            <i class="fas fa-undo me-1"></i> Restaurar valores por defecto
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-1"></i> Guardar Cambios
                        </button>
                    </div>
                </form>

                <!-- Form oculto para reset -->
                <form method="POST" action="/{{ admin_path() }}/themes/appearance/{{ $slug }}/reset" id="resetForm" style="display: none;">
                    {!! csrf_field() !!}
                </form>
            </div>

            <!-- Panel lateral - Preview y Presets -->
            <div class="col-lg-4">
                <!-- Preview del tema -->
                <div class="card mb-4 sticky-top" style="top: 20px;">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-eye me-2"></i>Vista previa</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="theme-preview-mini" id="themePreviewMini">
                            <!-- Topbar preview -->
                            <div class="preview-topbar" id="preview-topbar">
                                <small>Barra superior</small>
                            </div>
                            <!-- Header preview -->
                            <div class="preview-header" id="preview-header">
                                <span class="preview-logo">Logo</span>
                                <span class="preview-cta" id="preview-cta">CTA</span>
                            </div>
                            <!-- Content preview -->
                            <div class="preview-content">
                                <div class="preview-placeholder"></div>
                                <div class="preview-placeholder short"></div>
                            </div>
                            <!-- Footer preview -->
                            <div class="preview-footer" id="preview-footer">
                                <small>Footer</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-center">
                        <a href="/" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt me-1"></i> Ver sitio
                        </a>
                    </div>
                </div>

                <!-- Mis Presets -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-bookmark me-2"></i>Mis Presets</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-save-preset">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        @if(empty($presets))
                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Guarda tus configuraciones como presets para reutilizarlas.
                            </p>
                        @else
                            <div class="preset-list">
                                @foreach($presets as $preset)
                                <div class="preset-item d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                    <span class="preset-name">
                                        <i class="fas fa-palette me-2 text-muted"></i>
                                        {{ $preset['preset_name'] }}
                                    </span>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-success btn-load-preset"
                                                data-slug="{{ $preset['preset_slug'] }}" title="Aplicar">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-delete-preset"
                                                data-slug="{{ $preset['preset_slug'] }}"
                                                data-name="{{ $preset['preset_name'] }}" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex gap-2">
                            <a href="/{{ admin_path() }}/themes/appearance/{{ $slug }}/export" class="btn btn-sm btn-outline-secondary flex-grow-1">
                                <i class="fas fa-download me-1"></i> Exportar
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1" id="btn-import-preset">
                                <i class="fas fa-upload me-1"></i> Importar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Info del tema -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información del tema</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Nombre</td>
                                <td class="fw-semibold">{{ $theme['name'] ?? $slug }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Versión</td>
                                <td>{{ $theme['version'] ?? '1.0.0' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Autor</td>
                                <td>{{ $theme['author'] ?? 'Desconocido' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Form ocultos para presets -->
<form method="POST" action="/{{ admin_path() }}/themes/appearance/{{ $slug }}/preset/load/" id="loadPresetForm" style="display: none;">
    {!! csrf_field() !!}
</form>

<form method="POST" action="/{{ admin_path() }}/themes/appearance/{{ $slug }}/import" id="importPresetForm" enctype="multipart/form-data" style="display: none;">
    {!! csrf_field() !!}
    <input type="file" name="preset_file" id="presetFileInput" accept=".json">
</form>

<style>
.appearance-section .card-header {
    border-bottom: 2px solid #e9ecef;
}

.form-control-color {
    width: 60px;
    padding: 0.375rem;
}

.color-hex-input {
    font-family: monospace;
}

.code-editor {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 13px;
    line-height: 1.5;
    background-color: #1e1e1e;
    color: #d4d4d4;
    border-radius: 6px;
}

/* Preview mini */
.theme-preview-mini {
    background: #fff;
    border-radius: 4px;
    overflow: hidden;
    font-size: 10px;
}

.preview-topbar {
    background: var(--preview-topbar-bg, #1a2a40);
    color: var(--preview-topbar-text, #fff);
    padding: 4px 8px;
    text-align: center;
}

.preview-header {
    background: var(--preview-header-bg, #f8f9fa);
    padding: 8px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
}

.preview-logo {
    font-weight: bold;
    color: #333;
}

.preview-cta {
    background: var(--preview-cta-bg, #ff5e15);
    color: var(--preview-cta-text, #fff);
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 9px;
}

.preview-content {
    padding: 15px;
    min-height: 100px;
}

.preview-placeholder {
    background: #e9ecef;
    height: 15px;
    border-radius: 3px;
    margin-bottom: 8px;
}

.preview-placeholder.short {
    width: 60%;
}

.preview-footer {
    background: var(--preview-footer-bg, #f8fafe);
    color: var(--preview-footer-text, #333);
    padding: 10px;
    text-align: center;
    border-top: 1px solid #eee;
}

/* Preset list */
.preset-item {
    transition: all 0.2s;
}

.preset-item:hover {
    background-color: #e9ecef !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeSlug = '{{ $slug }}';
    const csrfToken = '{{ csrf_token() }}';
    const adminPath = '{{ admin_path() }}';

    // ==================== COLOR PICKERS ====================
    document.querySelectorAll('.form-control-color').forEach(colorInput => {
        const hexInput = colorInput.nextElementSibling;
        if (hexInput && hexInput.classList.contains('color-hex-input')) {
            colorInput.addEventListener('input', function() {
                hexInput.value = this.value;
                updatePreview();
            });

            hexInput.addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    colorInput.value = this.value;
                    updatePreview();
                }
            });
        }
    });

    // ==================== LIVE PREVIEW ====================
    function updatePreview() {
        const topbarBg = document.querySelector('[name="options[topbar][topbar_bg_color]"]');
        const topbarText = document.querySelector('[name="options[topbar][topbar_text_color]"]');
        if (topbarBg) document.documentElement.style.setProperty('--preview-topbar-bg', topbarBg.value);
        if (topbarText) document.documentElement.style.setProperty('--preview-topbar-text', topbarText.value);

        const headerBg = document.querySelector('[name="options[header][header_bg_color]"]');
        if (headerBg) document.documentElement.style.setProperty('--preview-header-bg', headerBg.value);

        const ctaBg = document.querySelector('[name="options[header][header_cta_bg_color]"]');
        const ctaText = document.querySelector('[name="options[header][header_cta_text_color]"]');
        if (ctaBg) document.documentElement.style.setProperty('--preview-cta-bg', ctaBg.value);
        if (ctaText) document.documentElement.style.setProperty('--preview-cta-text', ctaText.value);

        const footerBg = document.querySelector('[name="options[footer][footer_bg_color]"]');
        const footerText = document.querySelector('[name="options[footer][footer_text_color]"]');
        if (footerBg) document.documentElement.style.setProperty('--preview-footer-bg', footerBg.value);
        if (footerText) document.documentElement.style.setProperty('--preview-footer-text', footerText.value);
    }

    updatePreview();

    // ==================== DEPENDENCIES ====================
    function updateDependencies() {
        document.querySelectorAll('.option-group[data-depends-on]').forEach(group => {
            const dependsOn = group.dataset.dependsOn;
            const driverElement = document.querySelector(`[id*="option-${dependsOn.replace('.', '-')}"]`);

            if (driverElement) {
                let shouldShow = false;
                if (driverElement.type === 'checkbox') {
                    shouldShow = driverElement.checked;
                } else {
                    shouldShow = Boolean(driverElement.value) && driverElement.value !== '0';
                }

                group.style.display = shouldShow ? '' : 'none';
            }
        });
    }

    document.querySelectorAll('[data-dependency-driver="true"]').forEach(el => {
        el.addEventListener('change', updateDependencies);
    });
    updateDependencies();

    // Toggle labels
    document.querySelectorAll('input[type="checkbox"][role="switch"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const label = this.nextElementSibling;
            if (label) {
                label.textContent = this.checked ? 'Activado' : 'Desactivado';
            }

            const parent = this.closest('.form-check');
            if (parent && parent.nextElementSibling) {
                const hidden = parent.nextElementSibling;
                if (hidden.type === 'hidden') {
                    hidden.disabled = this.checked;
                }
            }
        });
    });

    // ==================== RESET ====================
    document.getElementById('btn-reset-defaults')?.addEventListener('click', function() {
        Swal.fire({
            title: '¿Restaurar valores por defecto?',
            html: '<p>Se eliminarán todas tus personalizaciones.</p><p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-undo me-1"></i> Sí, restaurar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('resetForm').submit();
            }
        });
    });

    // ==================== PRESETS MODAL (Header Button) ====================
    document.getElementById('btn-presets-header')?.addEventListener('click', function() {
        showPresetsModal();
    });

    function showPresetsModal() {
        @php
            $presetsJson = json_encode($presets ?? []);
        @endphp
        const presets = {!! $presetsJson !!};

        let presetsListHtml = '';
        if (presets.length === 0) {
            presetsListHtml = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-bookmark fa-3x mb-3 opacity-50"></i>
                    <p>No tienes presets guardados</p>
                    <small>Guarda tu configuración actual como preset para reutilizarla.</small>
                </div>
            `;
        } else {
            presetsListHtml = '<div class="list-group">';
            presets.forEach(preset => {
                presetsListHtml += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-palette me-2 text-muted"></i>${preset.preset_name}</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-success modal-load-preset" data-slug="${preset.preset_slug}" title="Aplicar">
                                <i class="fas fa-check"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger modal-delete-preset" data-slug="${preset.preset_slug}" data-name="${preset.preset_name}" title="Eliminar">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            presetsListHtml += '</div>';
        }

        Swal.fire({
            title: '<i class="fas fa-bookmark text-primary me-2"></i>Gestionar Presets',
            html: `
                <div class="text-start">
                    ${presetsListHtml}
                    <hr>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-success flex-grow-1" id="modal-new-preset">
                            <i class="fas fa-plus me-1"></i> Nuevo Preset
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="modal-import-preset">
                            <i class="fas fa-upload me-1"></i> Importar
                        </button>
                        <a href="/${adminPath}/themes/appearance/${themeSlug}/export" class="btn btn-outline-secondary">
                            <i class="fas fa-download me-1"></i> Exportar
                        </a>
                    </div>
                </div>
            `,
            width: 500,
            showConfirmButton: false,
            showCloseButton: true,
            didOpen: () => {
                // Load preset from modal
                document.querySelectorAll('.modal-load-preset').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const presetSlug = this.dataset.slug;
                        Swal.close();
                        loadPreset(presetSlug);
                    });
                });

                // Delete preset from modal
                document.querySelectorAll('.modal-delete-preset').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const presetSlug = this.dataset.slug;
                        const presetName = this.dataset.name;
                        Swal.close();
                        deletePreset(presetSlug, presetName);
                    });
                });

                // New preset from modal
                document.getElementById('modal-new-preset')?.addEventListener('click', function() {
                    Swal.close();
                    saveNewPreset();
                });

                // Import from modal
                document.getElementById('modal-import-preset')?.addEventListener('click', function() {
                    Swal.close();
                    document.getElementById('presetFileInput').click();
                });
            }
        });
    }

    // ==================== PRESET FUNCTIONS ====================

    function saveNewPreset() {
        Swal.fire({
            title: '<i class="fas fa-save text-success me-2"></i>Guardar Preset',
            html: `
                <p class="text-muted small">Guarda tu configuración actual para reutilizarla después.</p>
                <input type="text" id="preset-name-input" class="form-control" placeholder="Nombre del preset" autofocus>
            `,
            showCancelButton: true,
            confirmButtonColor: '#198754',
            confirmButtonText: '<i class="fas fa-save me-1"></i> Guardar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const name = document.getElementById('preset-name-input').value.trim();
                if (!name) {
                    Swal.showValidationMessage('Escribe un nombre para el preset');
                    return false;
                }
                return name;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Guardando...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch(`/${adminPath}/themes/appearance/${themeSlug}/preset/save`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `_csrf=${csrfToken}&preset_name=${encodeURIComponent(result.value)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Preset guardado',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'No se pudo guardar', 'error');
                    }
                })
                .catch(() => {
                    Swal.fire('Error', 'Error de conexión', 'error');
                });
            }
        });
    }

    function loadPreset(presetSlug) {
        Swal.fire({
            title: 'Aplicar preset',
            text: '¿Deseas aplicar este preset? Se sobrescribirán tus configuraciones actuales.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            confirmButtonText: '<i class="fas fa-check me-1"></i> Aplicar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('loadPresetForm');
                form.action = `/${adminPath}/themes/appearance/${themeSlug}/preset/load/${presetSlug}`;
                form.submit();
            }
        });
    }

    function deletePreset(presetSlug, presetName) {
        Swal.fire({
            title: '¿Eliminar preset?',
            html: `<p>Vas a eliminar <strong>${presetName}</strong></p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/${adminPath}/themes/appearance/${themeSlug}/preset/delete/${presetSlug}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `_csrf=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error, 'error');
                    }
                });
            }
        });
    }

    // ==================== SIDEBAR PRESETS BUTTONS ====================

    document.getElementById('btn-save-preset')?.addEventListener('click', saveNewPreset);

    document.querySelectorAll('.btn-load-preset').forEach(btn => {
        btn.addEventListener('click', function() {
            loadPreset(this.dataset.slug);
        });
    });

    document.querySelectorAll('.btn-delete-preset').forEach(btn => {
        btn.addEventListener('click', function() {
            deletePreset(this.dataset.slug, this.dataset.name);
        });
    });

    // ==================== IMPORT ====================
    document.getElementById('btn-import-preset')?.addEventListener('click', function() {
        Swal.fire({
            title: '<i class="fas fa-upload text-primary me-2"></i>Importar Preset',
            html: `
                <p class="text-muted small">Selecciona un archivo JSON exportado previamente.</p>
                <div class="mb-3">
                    <input type="file" class="form-control" id="swal-preset-file" accept=".json">
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#198754',
            confirmButtonText: '<i class="fas fa-upload me-1"></i> Importar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const fileInput = document.getElementById('swal-preset-file');
                if (!fileInput.files.length) {
                    Swal.showValidationMessage('Selecciona un archivo JSON');
                    return false;
                }
                return fileInput.files[0];
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const originalInput = document.getElementById('presetFileInput');
                const dt = new DataTransfer();
                dt.items.add(result.value);
                originalInput.files = dt.files;
                document.getElementById('importPresetForm').submit();
            }
        });
    });

    document.getElementById('presetFileInput')?.addEventListener('change', function() {
        if (this.files.length > 0) {
            Swal.fire({
                title: 'Importar preset',
                html: `<p>¿Importar <strong>${this.files[0].name}</strong>?</p><p class="text-muted small">Se aplicará inmediatamente.</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                confirmButtonText: '<i class="fas fa-upload me-1"></i> Importar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('importPresetForm').submit();
                } else {
                    this.value = '';
                }
            });
        }
    });
});
</script>
@endsection
