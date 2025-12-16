{{-- views/partials/_header_options.blade.php --}}
{{-- Versión con Media Manager integrado para Blog --}}
<div class="card mb-4">
    <div class="card-header"><strong>Opciones de Cabecera</strong></div>
    <div class="card-body">
        {{-- Lógica para determinar la imagen a mostrar --}}
        @php
            $heroImage = $Page->hero_image ?? '';
            $defaultImage = 'themes/default/img/hero/contact_hero.jpg';

            // Determinar URL de la imagen
            if (!empty($heroImage)) {
                $isCustom = true;
                // Si es URL de media manager (/media/...) o URL completa, usar directamente
                if (str_starts_with($heroImage, '/media/') || str_starts_with($heroImage, 'http')) {
                    $imageUrl = $heroImage;
                } else {
                    // Ruta de assets local
                    $imageUrl = asset($heroImage);
                }
            } else {
                $isCustom = false;
                $imageUrl = asset($defaultImage);
            }
        @endphp

        {{-- Activar/desactivar la cabecera --}}
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="show-slider" name="show_hero" value="1"
                   {{ old('show_hero', $Page->show_hero ?? 0) == 1 ? 'checked' : '' }}>
            <label class="form-check-label" for="show-slider">Mostrar cabecera con imagen</label>
        </div>

        {{-- Imagen de fondo --}}
        <div class="mb-3" id="slider-image-container" style="{{ old('show_hero', $Page->show_hero ?? 0) == 1 ? '' : 'display: none;' }}">
            <label class="form-label">Imagen de fondo</label>

            {{-- Preview de la imagen actual --}}
            <div class="image-preview-container mb-3">
                <div class="slider-preview" id="current-image-preview">
                    <img src="{{ $imageUrl }}"
                         alt="Vista previa de la imagen de cabecera"
                         class="img-fluid img-thumbnail mb-2"
                         style="max-height: 200px; max-width: 100%; {{ $isCustom ? '' : 'opacity: 0.7;' }}"
                         id="header-image-preview">

                    <div class="mt-1" id="image-status">
                        @if($isCustom)
                            <span class="badge bg-success">Imagen personalizada</span>
                        @else
                            <span class="badge bg-secondary">Imagen predeterminada</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Input hidden para guardar la URL de la imagen --}}
            <input type="hidden" name="hero_image" id="header-image-input" value="{{ $heroImage }}">

            {{-- Botones de acción --}}
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary" id="select-header-image-btn">
                    <i class="bi bi-image"></i> Seleccionar imagen
                </button>

                @if($isCustom)
                <button type="button" class="btn btn-outline-danger" id="remove-header-image-btn">
                    <i class="bi bi-x-circle"></i> Eliminar imagen
                </button>
                @endif
            </div>

            <small class="text-muted d-block mt-2">
                <i class="bi bi-info-circle"></i> Recomendado: 1920x600px para blog.
            </small>
        </div>

        {{-- Título personalizado para la cabecera (opcional) --}}
        <div class="mb-3" id="slider-title-container" style="{{ old('show_hero', $Page->show_hero ?? 0) == 1 ? '' : 'display: none;' }}">
            <label for="slider-title" class="form-label">Título para la cabecera <small class="text-muted">(opcional)</small></label>
            <input type="text" class="form-control" id="slider-title" name="hero_title"
                   value="{{ old('hero_title', $Page->hero_title ?? '') }}"
                   placeholder="Dejar vacío para usar el título del post">
        </div>

        {{-- Contenido personalizado para la cabecera (opcional) --}}
        <div class="mb-3" id="slider-content-container" style="{{ old('show_hero', $Page->show_hero ?? 0) == 1 ? '' : 'display: none;' }}">
            <label for="slider-content" class="form-label">Subtítulo <small class="text-muted">(opcional)</small></label>
            <textarea class="form-control" id="slider-content" name="hero_content" rows="2"
                      placeholder="Texto adicional para mostrar debajo del título principal">{{ old('hero_content', $Page->hero_content ?? '') }}</textarea>
            <small class="text-muted">Texto breve que aparecerá debajo del título principal.</small>
        </div>

        {{-- Ocultar título H1 en el contenido principal --}}
        <div class="mb-3 form-check mt-4">
            <input type="checkbox" class="form-check-input" id="hide-title" name="hide_title" value="1"
                   {{ old('hide_title', $Page->hide_title ?? 0) == 1 ? 'checked' : '' }}>
            <label class="form-check-label" for="hide-title">Ocultar título H1 en el contenido principal</label>
            <small class="d-block text-muted">Útil cuando ya se muestra el título en la cabecera o en otro elemento de la página.</small>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos DOM
    const showSliderCheckbox = document.getElementById('show-slider');
    const sliderImageContainer = document.getElementById('slider-image-container');
    const sliderTitleContainer = document.getElementById('slider-title-container');
    const sliderContentContainer = document.getElementById('slider-content-container');
    const headerImageInput = document.getElementById('header-image-input');
    const headerImagePreview = document.getElementById('header-image-preview');
    const selectImageBtn = document.getElementById('select-header-image-btn');
    const removeImageBtn = document.getElementById('remove-header-image-btn');
    const imageStatus = document.getElementById('image-status');

    const defaultImageUrl = '{{ asset($defaultImage) }}';

    // Función para mostrar/ocultar los campos del slider según checkbox
    function toggleSliderOptions() {
        const showSlider = showSliderCheckbox.checked;
        sliderImageContainer.style.display = showSlider ? 'block' : 'none';
        sliderTitleContainer.style.display = showSlider ? 'block' : 'none';
        sliderContentContainer.style.display = showSlider ? 'block' : 'none';
    }

    // Event listener para checkbox
    if (showSliderCheckbox) {
        showSliderCheckbox.addEventListener('change', toggleSliderOptions);
    }

    // Seleccionar imagen desde Media Manager
    if (selectImageBtn) {
        selectImageBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Abriendo Media Manager para seleccionar imagen de cabecera...');

            if (typeof window.openMediaManagerForTinyMCE === 'function') {
                window.openMediaManagerForTinyMCE(function(url, meta) {
                    console.log('Imagen seleccionada:', url);

                    // Actualizar input hidden
                    if (headerImageInput) {
                        headerImageInput.value = url;
                    }

                    // Actualizar preview
                    if (headerImagePreview) {
                        headerImagePreview.src = url;
                        headerImagePreview.style.opacity = '1';
                    }

                    // Actualizar estado
                    if (imageStatus) {
                        imageStatus.innerHTML = '<span class="badge bg-success">Imagen personalizada</span>';
                    }

                    // Mostrar botón de eliminar si no existe
                    if (!removeImageBtn) {
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'btn btn-outline-danger';
                        removeBtn.id = 'remove-header-image-btn';
                        removeBtn.innerHTML = '<i class="bi bi-x-circle"></i> Eliminar imagen';
                        removeBtn.addEventListener('click', removeImage);
                        selectImageBtn.parentNode.appendChild(removeBtn);
                    }

                }, headerImageInput ? headerImageInput.value : '', { filetype: 'image' });
            } else {
                console.error('Media Manager no disponible');
                alert('El gestor de medios no está disponible. Por favor, recarga la página.');
            }
        });
    }

    // Función para eliminar imagen
    function removeImage(e) {
        e.preventDefault();
        console.log('Eliminando imagen de cabecera...');

        // Limpiar input hidden
        if (headerImageInput) {
            headerImageInput.value = '';
        }

        // Restaurar preview a imagen predeterminada
        if (headerImagePreview) {
            headerImagePreview.src = defaultImageUrl;
            headerImagePreview.style.opacity = '0.7';
        }

        // Actualizar estado
        if (imageStatus) {
            imageStatus.innerHTML = '<span class="badge bg-secondary">Imagen predeterminada</span>';
        }

        // Eliminar botón de eliminar
        const removeBtn = document.getElementById('remove-header-image-btn');
        if (removeBtn) {
            removeBtn.remove();
        }
    }

    // Event listener para botón eliminar
    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', removeImage);
    }

    // Inicializar estado
    toggleSliderOptions();
});
</script>
@endpush
