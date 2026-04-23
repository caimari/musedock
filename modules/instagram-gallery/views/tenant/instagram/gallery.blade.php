@extends('layouts::app')

@section('title', __instagram('gallery.title') . ' - @' . $connection->username)

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-grid-3x3-gap-fill" style="font-size:1.35rem;color:#fff;"></i>
                </div>
                <div>
                    <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">{{ __instagram('gallery.title') }} — @{{ $connection->username }}</h3>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Configura layout y columnas, copia el shortcode y pégalo donde quieras.</p>
                </div>
            </div>
            <a href="/{{ $basePath ?? admin_path() }}/social-publisher" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef;color:#6c757d;text-decoration:none;">
                <i class="bi bi-arrow-left"></i>
                <span>{{ __instagram('common.back') }}</span>
            </a>
        </div>

        <div class="row">
            {{-- Configuración --}}
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-sliders"></i> Configuración</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Layout</label>
                            <select class="form-select" id="layoutSelect">
                                @foreach ($layouts as $key => $layout)
                                    <option value="{{ $key }}" {{ $defaultLayout === $key ? 'selected' : '' }}>{{ $layout['name'] }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted" id="layoutDescription"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Columnas</label>
                            <input type="number" class="form-control" id="columnsInput" min="1" max="6" value="{{ $defaultColumns }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Espacio (px)</label>
                            <input type="number" class="form-control" id="gapInput" min="0" max="50" value="{{ $defaultGap }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Límite de Posts</label>
                            <input type="number" class="form-control" id="limitInput" min="1" max="50" value="12">
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="form-label"><strong>{{ __instagram('gallery.shortcode') }}</strong></label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="shortcodeText" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyShortcode()">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">{{ __instagram('gallery.usage_info') }}</small>
                        </div>

                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle"></i>
                            <strong>Ejemplos de uso:</strong>
                            <ul class="mb-0 mt-2">
                                <li><code>[instagram connection={{ $connection->id }}]</code></li>
                                <li><code>[instagram connection={{ $connection->id }} layout="masonry"]</code></li>
                                <li><code>[instagram username="{{ $connection->username }}"]</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Vista Previa --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-eye"></i> {{ __instagram('gallery.preview') }}</h5>
                    </div>
                    <div class="card-body">
                        @if (empty($posts))
                            <div class="text-center py-5">
                                <i class="bi bi-image" style="font-size: 4rem; color: #ddd;"></i>
                                <h4 class="mt-3">{{ __instagram('gallery.no_preview') }}</h4>
                                <p class="text-muted mb-0" style="font-size:0.85rem;">
                                    Pulsa <strong>«Sincronizar Ahora»</strong> en la lista de conexiones para descargar tus posts.
                                </p>
                            </div>
                        @else
                            <div id="galleryPreview">
                                @php
                                    echo render_instagram_gallery_html(
                                        $connection,
                                        array_slice($posts, 0, 12),
                                        $defaultLayout,
                                        $defaultColumns,
                                        $defaultGap,
                                        [
                                            'show_caption' => true,
                                            'caption_length' => 100,
                                            'lazy_load' => true,
                                            'hover_effect' => 'zoom'
                                        ]
                                    );
                                @endphp
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const layoutDescriptions = @json($layouts);
    const connectionId = {{ $connection->id }};
    const defaultLayout = @json($defaultLayout);
    const defaultColumns = @json((string)$defaultColumns);
    const defaultGap = @json((string)$defaultGap);

    function updateShortcode() {
        const layout = document.getElementById('layoutSelect').value;
        const columns = document.getElementById('columnsInput').value;
        const gap = document.getElementById('gapInput').value;
        const limit = document.getElementById('limitInput').value;

        let shortcode = `[instagram connection=${connectionId}`;
        if (layout !== defaultLayout) shortcode += ` layout="${layout}"`;
        if (columns !== defaultColumns) shortcode += ` columns=${columns}`;
        if (gap !== defaultGap) shortcode += ` gap=${gap}`;
        if (limit !== '12') shortcode += ` limit=${limit}`;
        shortcode += ']';

        document.getElementById('shortcodeText').value = shortcode;

        const layoutDesc = layoutDescriptions[layout];
        if (layoutDesc && layoutDesc.description) {
            document.getElementById('layoutDescription').textContent = layoutDesc.description;
        }
    }

    function copyShortcode() {
        const shortcodeInput = document.getElementById('shortcodeText');
        navigator.clipboard.writeText(shortcodeInput.value).then(() => {
            Swal.fire({
                icon: 'success',
                title: @json(__instagram('gallery.shortcode_copied')),
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
        });
    }

    document.getElementById('layoutSelect').addEventListener('change', updateShortcode);
    document.getElementById('columnsInput').addEventListener('input', updateShortcode);
    document.getElementById('gapInput').addEventListener('input', updateShortcode);
    document.getElementById('limitInput').addEventListener('input', updateShortcode);
    updateShortcode();
</script>
@endpush
