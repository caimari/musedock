@extends('layouts.app')

@section('title', $ad ? 'Editar anuncio' : 'Crear anuncio')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ $ad ? 'Editar anuncio' : 'Crear anuncio' }}</h2>
            <a href="/musedock/ads" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>

        @include('partials.alerts-sweetalert2')

        <form method="POST"
              action="/musedock/ads{{ $ad ? '/' . $ad->id . '/update' : '' }}"
              id="ad-form">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">

            <div class="row">
                {{-- Main column --}}
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Datos del anuncio</strong></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="{{ $ad->name ?? '' }}" required placeholder="Ej: Banner principal cabecera">
                            </div>

                            <div class="mb-3">
                                <label for="slot_slug" class="form-label">Espacio publicitario <span class="text-danger">*</span></label>
                                <select class="form-select" id="slot_slug" name="slot_slug" required>
                                    <option value="">-- Seleccionar espacio --</option>
                                    @foreach($slots as $slot)
                                        <option value="{{ $slot->slug }}"
                                                data-description="{{ e($slot->description ?? '') }}"
                                                data-size="{{ e($slot->default_width ?? '') }}"
                                                {{ ($ad->slot_slug ?? '') === $slot->slug ? 'selected' : '' }}>
                                            {{ e($slot->name) }} ({{ $slot->default_width ?? '—' }})
                                        </option>
                                    @endforeach
                                </select>
                                <div id="slot-info" class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de anuncio <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ad_type" id="ad_type_image"
                                               value="image" {{ (!$ad || ($ad->ad_type ?? 'image') === 'image') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ad_type_image">
                                            <i class="bi bi-image me-1"></i> Imagen + enlace
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ad_type" id="ad_type_html"
                                               value="html" {{ ($ad->ad_type ?? '') === 'html' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ad_type_html">
                                            <i class="bi bi-code-slash me-1"></i> HTML/JavaScript personalizado
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Image fields --}}
                    <div class="card mb-3" id="image-fields">
                        <div class="card-header"><strong>Imagen y enlace</strong></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="image_url" class="form-label">URL de la imagen</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="image_url" name="image_url"
                                           value="{{ $ad->image_url ?? '' }}" placeholder="https://...">
                                </div>
                                <small class="text-muted">Pega la URL de la imagen directamente.</small>
                                <div id="image-preview" class="mt-2">
                                    <img src="{{ e($ad->image_url ?? '') }}" alt="Preview" id="image-preview-img"
                                         style="max-width:300px;max-height:150px;{{ empty($ad->image_url) ? 'display:none;' : '' }}" class="border rounded">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="link_url" class="form-label">URL de destino</label>
                                <input type="url" class="form-control" id="link_url" name="link_url"
                                       value="{{ $ad->link_url ?? '' }}" placeholder="https://...">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="link_target" class="form-label">Abrir enlace en</label>
                                    <select class="form-select" id="link_target" name="link_target">
                                        <option value="_blank" {{ ($ad->link_target ?? '_blank') === '_blank' ? 'selected' : '' }}>Nueva ventana (_blank)</option>
                                        <option value="_self" {{ ($ad->link_target ?? '') === '_self' ? 'selected' : '' }}>Misma ventana (_self)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="alt_text" class="form-label">Texto alternativo (alt)</label>
                                    <input type="text" class="form-control" id="alt_text" name="alt_text"
                                           value="{{ $ad->alt_text ?? '' }}" placeholder="Descripci&oacute;n de la imagen">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- HTML fields --}}
                    <div class="card mb-3" id="html-fields" style="display:none;">
                        <div class="card-header"><strong>C&oacute;digo HTML/JavaScript</strong></div>
                        <div class="card-body">
                            <div class="mb-0">
                                <label for="html_content" class="form-label">C&oacute;digo del anuncio</label>
                                <textarea class="form-control font-monospace" id="html_content" name="html_content"
                                          rows="12" placeholder="Pega aqu&iacute; el c&oacute;digo HTML, JavaScript o el tag de tu red publicitaria..."
                                          style="font-size: 0.85rem;">{{ $ad->html_content ?? '' }}</textarea>
                                <div class="form-text">Acepta HTML, CSS inline y JavaScript (ej: Google AdSense, tags de redes publicitarias).</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Programaci&oacute;n</strong></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="starts_at" class="form-label">Fecha de inicio</label>
                                <input type="datetime-local" class="form-control" id="starts_at" name="starts_at"
                                       value="{{ $ad && $ad->starts_at ? date('Y-m-d\TH:i', strtotime($ad->starts_at)) : '' }}">
                                <div class="form-text">Dejar vac&iacute;o para empezar inmediatamente.</div>
                            </div>
                            <div class="mb-3">
                                <label for="ends_at" class="form-label">Fecha de fin</label>
                                <input type="datetime-local" class="form-control" id="ends_at" name="ends_at"
                                       value="{{ $ad && $ad->ends_at ? date('Y-m-d\TH:i', strtotime($ad->ends_at)) : '' }}">
                                <div class="form-text">Dejar vac&iacute;o para que no caduque.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3" id="repeat-card" style="display:none;">
                        <div class="card-header"><strong>Repetici&oacute;n</strong></div>
                        <div class="card-body">
                            <div class="mb-0">
                                <label for="repeat_every" class="form-label">Mostrar cada N posts/p&aacute;rrafos</label>
                                <input type="number" class="form-control" id="repeat_every" name="repeat_every"
                                       value="{{ $ad->repeat_every ?? '' }}" min="1" placeholder="Ej: 4">
                                <div class="form-text">Solo aplica a espacios in-feed e in-article.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><strong>Opciones</strong></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Prioridad</label>
                                <input type="number" class="form-control" id="priority" name="priority"
                                       value="{{ $ad->priority ?? 0 }}" min="0">
                                <div class="form-text">Mayor n&uacute;mero = mayor prioridad. Si hay varios anuncios en el mismo espacio, se muestra el de mayor prioridad.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                       {{ (!$ad || ($ad->is_active ?? false)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Anuncio activo</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg me-1"></i> Guardar anuncio
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const adTypeRadios = document.querySelectorAll('input[name="ad_type"]');
    const imageFields = document.getElementById('image-fields');
    const htmlFields = document.getElementById('html-fields');
    const slotSelect = document.getElementById('slot_slug');
    const slotInfo = document.getElementById('slot-info');
    const repeatCard = document.getElementById('repeat-card');
    const imageUrlInput = document.getElementById('image_url');
    const imagePreviewImg = document.getElementById('image-preview-img');

    function toggleAdType() {
        const selected = document.querySelector('input[name="ad_type"]:checked').value;
        if (selected === 'image') {
            imageFields.style.display = '';
            htmlFields.style.display = 'none';
        } else {
            imageFields.style.display = 'none';
            htmlFields.style.display = '';
        }
    }

    function toggleRepeatCard() {
        const slug = slotSelect.value;
        const showRepeat = slug === 'in-feed' || slug === 'in-article';
        repeatCard.style.display = showRepeat ? '' : 'none';
    }

    function updateSlotInfo() {
        const option = slotSelect.options[slotSelect.selectedIndex];
        const desc = option ? option.getAttribute('data-description') : '';
        const size = option ? option.getAttribute('data-size') : '';
        let html = '';
        if (desc) html += desc;
        if (size) html += (html ? ' — ' : '') + '<strong>Tamaño recomendado: ' + size + ' px</strong>';
        slotInfo.innerHTML = html;
    }

    function updateImagePreview() {
        const url = imageUrlInput.value.trim();
        if (url) {
            imagePreviewImg.src = url;
            imagePreviewImg.style.display = '';
        } else {
            imagePreviewImg.style.display = 'none';
        }
    }

    adTypeRadios.forEach(r => r.addEventListener('change', toggleAdType));
    slotSelect.addEventListener('change', function() {
        toggleRepeatCard();
        updateSlotInfo();
    });
    imageUrlInput.addEventListener('input', updateImagePreview);
    imageUrlInput.addEventListener('change', updateImagePreview);

    // Initial state
    toggleAdType();
    toggleRepeatCard();
    updateSlotInfo();
});
</script>
@endpush
