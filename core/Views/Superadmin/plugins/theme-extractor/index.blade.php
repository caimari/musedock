@extends('layouts.app')

@section('title', $title ?? 'Theme Extractor')

@section('content')
@include('partials.alerts-sweetalert2')

<div class="container-fluid" style="max-width: 1300px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-cloud-download me-2"></i>Theme Extractor</h2>
            <p class="text-muted mb-0">Extrae colores, fuentes y estilos de cualquier web para crear un skin MuseDock</p>
        </div>
        <a href="/musedock/themes" class="btn btn-outline-secondary btn-sm text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Volver a Temas
        </a>
    </div>

    {{-- Step 1: URL Input --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="/musedock/theme-extractor/extract" id="extractForm">
                {!! csrf_field() !!}
                <div class="row align-items-end">
                    <div class="col-lg-9 mb-3 mb-lg-0">
                        <label class="form-label fw-semibold">URL del sitio web</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-globe"></i></span>
                            <input type="url" name="url" class="form-control" placeholder="https://ejemplo.com" value="{{ $sourceUrl ?? '' }}" required>
                        </div>
                        <small class="text-muted">Introduce la URL de la web cuyo diseño quieres extraer</small>
                    </div>
                    <div class="col-lg-3">
                        <button type="submit" class="btn btn-primary btn-lg w-100" id="btnExtract">
                            <i class="bi bi-cloud-download me-1"></i> Extraer Diseño
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(!empty($extracted))
    {{-- Results --}}
    <form method="POST" action="/musedock/theme-extractor/save" id="saveForm">
        {!! csrf_field() !!}
        <input type="hidden" name="source_url" value="{{ $sourceUrl ?? '' }}">

        <div class="row">
            {{-- Left: Extracted Data --}}
            <div class="col-lg-7">
                {{-- Color Palette --}}
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-palette me-2 text-primary"></i>Colores Extraídos</h5>
                        <span class="badge bg-primary">{{ count($colors) }} colores</span>
                    </div>
                    <div class="card-body">
                        @php
                            $bgColors = array_filter($colors, fn($c) => $c['category'] === 'background');
                            $textColors = array_filter($colors, fn($c) => $c['category'] === 'text');
                            $accentColors = array_filter($colors, fn($c) => $c['category'] === 'accent');
                        @endphp

                        @if(!empty($accentColors))
                        <h6 class="text-muted small text-uppercase mb-2">Acentos / Marca</h6>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach(array_slice($accentColors, 0, 15) as $c)
                            <div class="te-color-swatch" data-color="{{ $c['hex'] }}" title="{{ $c['hex'] }} ({{ $c['count'] }}x)" style="background: {{ $c['hex'] }};">
                                <span class="te-color-label">{{ $c['hex'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        @if(!empty($bgColors))
                        <h6 class="text-muted small text-uppercase mb-2">Fondos</h6>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach(array_slice($bgColors, 0, 12) as $c)
                            <div class="te-color-swatch te-light" data-color="{{ $c['hex'] }}" title="{{ $c['hex'] }} ({{ $c['count'] }}x)" style="background: {{ $c['hex'] }};">
                                <span class="te-color-label">{{ $c['hex'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        @if(!empty($textColors))
                        <h6 class="text-muted small text-uppercase mb-2">Textos</h6>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach(array_slice($textColors, 0, 10) as $c)
                            <div class="te-color-swatch" data-color="{{ $c['hex'] }}" title="{{ $c['hex'] }} ({{ $c['count'] }}x)" style="background: {{ $c['hex'] }};">
                                <span class="te-color-label">{{ $c['hex'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Fonts --}}
                @if(!empty($fonts))
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-fonts me-2 text-primary"></i>Fuentes Detectadas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($fonts as $fontName => $count)
                            <div class="col-md-6 mb-2">
                                <div class="p-2 bg-light rounded d-flex justify-content-between align-items-center">
                                    <span style="font-family: '{{ $fontName }}', sans-serif; font-size: 15px;">{{ $fontName }}</span>
                                    <span class="badge bg-secondary">{{ $count }}x</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- CSS Variables --}}
                @if(!empty($cssVars))
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-code-slash me-2 text-primary"></i>Variables CSS</h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('cssVarsPanel').classList.toggle('d-none')">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="card-body d-none" id="cssVarsPanel">
                        <div style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                @foreach($cssVars as $varName => $varValue)
                                <tr>
                                    <td class="text-nowrap"><code>{{ $varName }}</code></td>
                                    <td>
                                        {{ mb_substr($varValue, 0, 60) }}
                                        @if(preg_match('/^#[0-9a-f]{3,8}$/i', trim($varValue)))
                                            <span class="d-inline-block" style="width:14px;height:14px;border-radius:3px;background:{{ trim($varValue) }};border:1px solid #ddd;vertical-align:middle;"></span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Background Images --}}
                @if(!empty($bgImages))
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-image me-2 text-primary"></i>Imagenes de Fondo</h5>
                        <span class="badge bg-primary">{{ count($bgImages) }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            @foreach($bgImages as $img)
                            <div class="col-4 col-md-3">
                                <div class="te-bg-thumb" title="{{ $img['url'] }}">
                                    <img src="{{ $img['url'] }}" alt="{{ $img['filename'] }}" loading="lazy" onerror="this.parentElement.style.display='none'">
                                    <small class="te-bg-filename">{{ mb_substr($img['filename'], 0, 20) }}</small>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- JS Sources --}}
                @if(!empty($jsSources))
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-filetype-js me-2 text-warning"></i>Scripts JS</h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('jsPanel').classList.toggle('d-none')">
                            <i class="bi bi-chevron-down"></i> {{ count($jsSources) }} archivos
                        </button>
                    </div>
                    <div class="card-body d-none" id="jsPanel">
                        <div style="max-height: 250px; overflow-y: auto;">
                            @foreach($jsSources as $js)
                            <div class="d-flex align-items-center gap-2 mb-1 p-1 {{ $js['is_library'] ? 'opacity-50' : '' }}" style="font-size: 12px;">
                                <i class="bi {{ $js['is_library'] ? 'bi-box-seam text-muted' : 'bi-file-code text-warning' }}"></i>
                                <span class="text-truncate flex-grow-1" title="{{ $js['url'] }}">{{ $js['filename'] }}</span>
                                @if($js['is_library'])<span class="badge bg-light text-muted">lib</span>@endif
                                <a href="{{ $js['url'] }}" target="_blank" class="text-muted" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- Custom CSS --}}
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-filetype-css me-2 text-primary"></i>CSS Personalizado</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">{{ number_format($rawCssLength / 1024, 1) }} KB de CSS detectados. Pega aqui CSS adicional o decoraciones especiales:</p>
                        <textarea name="custom_css" class="form-control font-monospace" rows="6" placeholder="/* CSS personalizado para el skin */"></textarea>
                    </div>
                </div>
            </div>

            {{-- Right: Mapping Panel --}}
            <div class="col-lg-5">
                <div class="card mb-4 sticky-top" style="top: 20px;">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-map me-2 text-success"></i>Mapeo de Colores</h5>
                    </div>
                    <div class="card-body" style="max-height: 70vh; overflow-y: auto;">
                        <p class="text-muted small mb-3">Asigna los colores extraidos a las variables del tema MuseDock. Haz clic en un color de la izquierda para copiarlo.</p>

                        @php
                            $mappingFields = [
                                'Topbar' => [
                                    'topbar_bg_color' => 'Fondo topbar',
                                    'topbar_text_color' => 'Texto topbar',
                                ],
                                'Header' => [
                                    'header_bg_color' => 'Fondo header',
                                    'header_link_color' => 'Color enlaces',
                                    'header_link_hover_color' => 'Color hover',
                                    'header_cta_bg_color' => 'Fondo botón CTA',
                                    'header_cta_text_color' => 'Texto botón CTA',
                                ],
                                'Hero' => [
                                    'hero_title_color' => 'Color título',
                                    'hero_overlay_color' => 'Color overlay',
                                ],
                                'Footer' => [
                                    'footer_bg_color' => 'Fondo footer',
                                    'footer_text_color' => 'Texto footer',
                                    'footer_heading_color' => 'Títulos footer',
                                    'footer_link_color' => 'Enlaces footer',
                                    'footer_link_hover_color' => 'Hover footer',
                                    'footer_icon_color' => 'Iconos footer',
                                ],
                                'Otros' => [
                                    'scroll_to_top_bg_color' => 'Botón scroll top',
                                ],
                            ];
                        @endphp

                        @foreach($mappingFields as $section => $fields)
                        <h6 class="text-uppercase text-muted small mt-3 mb-2 border-bottom pb-1">{{ $section }}</h6>
                        @foreach($fields as $key => $label)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="color" name="map_{{ $key }}" value="{{ $autoMapping[$key] ?? '#ffffff' }}" class="form-control form-control-color flex-shrink-0" style="width:38px;height:32px;" title="{{ $label }}">
                            <input type="text" class="form-control form-control-sm te-hex-input" value="{{ $autoMapping[$key] ?? '#ffffff' }}" placeholder="#000000" style="width:90px;font-size:12px;font-family:monospace;">
                            <span class="small text-muted flex-grow-1">{{ $label }}</span>
                        </div>
                        @endforeach
                        @endforeach
                    </div>

                    <div class="card-footer bg-white">
                        {{-- Skin metadata --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nombre del Skin</label>
                            <input type="text" name="skin_name" class="form-control" placeholder="Mi Skin Personalizado" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Descripcion</label>
                            <input type="text" name="skin_description" class="form-control form-control-sm" placeholder="Skin basado en..." value="Extraido de {{ $sourceUrl ?? '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Asignar a tenant (privado)</label>
                            <select name="tenant_id" class="form-select form-select-sm">
                                <option value="">Global (todos los tenants)</option>
                                @foreach($tenants ?? [] as $t)
                                    <option value="{{ $t['id'] }}">{{ $t['name'] }} ({{ $t['domain'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-save me-1"></i> Crear Skin
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>

<style>
.te-color-swatch {
    width: 56px; height: 56px; border-radius: 10px; cursor: pointer;
    display: flex; align-items: flex-end; justify-content: center;
    border: 2px solid transparent; transition: all 0.15s;
    position: relative; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.te-color-swatch:hover { transform: scale(1.1); border-color: #333; z-index: 2; }
.te-color-swatch.te-light { border-color: #e0e0e0; }
.te-color-swatch.te-light:hover { border-color: #333; }
.te-color-label {
    font-size: 8px; font-family: monospace; color: #fff;
    background: rgba(0,0,0,0.5); padding: 1px 4px; border-radius: 3px;
    margin-bottom: 3px; white-space: nowrap;
}
.te-light .te-color-label { color: #333; background: rgba(255,255,255,0.8); }
.te-color-swatch.selected { border-color: #0d6efd !important; box-shadow: 0 0 0 3px rgba(13,110,253,0.3); }
.te-bg-thumb {
    width: 100%; height: 80px; border-radius: 8px; overflow: hidden;
    background: #f0f2f5; position: relative; border: 1px solid #e5e7eb;
}
.te-bg-thumb img { width: 100%; height: 100%; object-fit: cover; }
.te-bg-filename {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: rgba(0,0,0,0.6); color: #fff; padding: 2px 4px;
    font-size: 9px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Loading state for extract button
    document.getElementById('extractForm')?.addEventListener('submit', function() {
        const btn = document.getElementById('btnExtract');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Extrayendo...';
        btn.disabled = true;
    });

    // Click color swatch to copy hex
    let lastClickedColor = '';
    document.querySelectorAll('.te-color-swatch').forEach(swatch => {
        swatch.addEventListener('click', function() {
            lastClickedColor = this.dataset.color;
            document.querySelectorAll('.te-color-swatch').forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');

            // Copy to clipboard
            navigator.clipboard?.writeText(lastClickedColor);

            // Flash feedback
            const label = this.querySelector('.te-color-label');
            const orig = label.textContent;
            label.textContent = 'Copiado!';
            setTimeout(() => label.textContent = orig, 800);
        });
    });

    // Sync color picker <-> hex input
    document.querySelectorAll('input[type="color"]').forEach(picker => {
        const hexInput = picker.nextElementSibling;
        if (!hexInput || !hexInput.classList.contains('te-hex-input')) return;

        picker.addEventListener('input', () => {
            hexInput.value = picker.value;
        });
        hexInput.addEventListener('change', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) {
                picker.value = hexInput.value;
                picker.name && (picker.value = hexInput.value);
            }
        });
    });
});
</script>
@endpush
@endsection
