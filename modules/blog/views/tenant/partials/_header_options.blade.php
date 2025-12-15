{{-- views/partials/_header_options.blade.php --}}
<div class="card mb-4">
    <div class="card-header"><strong>Opciones de Cabecera</strong></div>
    <div class="card-body">
        {{-- Lógica para determinar la imagen a mostrar --}}
        @php
            $sliderImage = $Page->slider_image ?? '';
            $imagePath = '';
            $isCustom = false;
            
            if (!empty($sliderImage)) {
                $isCustom = strpos($sliderImage, 'themes/default') === false;
                // No añadimos 'assets/' al inicio si ya existe una imagen personalizada
                $imagePath = $isCustom ? $sliderImage : 'themes/default/img/hero/contact_hero.jpg';
            } else {
                $imagePath = 'themes/default/img/hero/contact_hero.jpg';
            }
        @endphp
        
        {{-- Activar/desactivar la cabecera --}}
        <div class="mb-3 form-check">
            <input type="hidden" name="show_slider" value="0">
            <input type="checkbox" class="form-check-input" id="show-slider" name="show_slider" value="1"
                   {{ old('show_slider', $Page->show_slider ?? 0) == 1 ? 'checked' : '' }}>
            <label class="form-check-label" for="show-slider">Mostrar cabecera con imagen</label>
        </div>

        {{-- Imagen de fondo --}}
        <div class="mb-3" id="slider-image-container" style="{{ old('show_slider', $Page->show_slider ?? 0) == 1 ? '' : 'display: none;' }}">
            <label for="slider-image" class="form-label">Imagen de fondo</label>
            
            <div class="image-preview-container mb-3">
                <div class="slider-preview" id="current-image-preview">
                    <img src="{{ asset($imagePath) }}" 
                         alt="Vista previa de la imagen de cabecera" 
                         class="img-fluid img-thumbnail mb-2" 
                         style="max-height: 150px; max-width: 100%; {{ $isCustom ? '' : 'opacity: 0.7;' }}"
                         id="preview-image-display"
                         data-path="{{ $imagePath }}"
                         data-is-custom="{{ $isCustom ? 'true' : 'false' }}"
                         data-original-value="{{ $sliderImage }}">
                    
                    @if($isCustom)
                        <div class="mt-1 d-flex" id="custom-image-controls">
                            <button type="button" class="btn btn-sm btn-outline-danger me-2" id="remove-slider-image">
                                <i class="bi bi-x-circle"></i> Eliminar imagen
                            </button>
                            <small class="text-muted align-self-center">
                                (Imagen personalizada)
                            </small>
                        </div>
                    @else
                        <div class="mt-1" id="default-image-notice">
                            <small class="text-muted d-block">(Imagen predeterminada)</small>
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="mb-3">
                <label for="slider-image" class="form-label">Subir nueva imagen</label>
                <input type="file" class="form-control" id="slider-image" name="slider_image" accept="image/*">
                <input type="hidden" name="current_slider_image" value="{{ $sliderImage }}" id="current-slider-image-input">
                <input type="hidden" name="remove_slider_image" id="remove-slider-image-flag" value="0">
                <small class="text-muted">Recomendado: 1920x400px. La imagen se adaptará automáticamente a estas dimensiones.</small>
            </div>
        </div>

        {{-- Título personalizado para la cabecera (opcional) --}}
        <div class="mb-3" id="slider-title-container" style="{{ old('show_slider', $Page->show_slider ?? 0) == 1 ? '' : 'display: none;' }}">
            <label for="slider-title" class="form-label">Título para la cabecera <small class="text-muted">(opcional)</small></label>
            <input type="text" class="form-control" id="slider-title" name="slider_title" 
                   value="{{ old('slider_title', $Page->slider_title ?? '') }}" 
                   placeholder="Dejar vacío para usar el título de la página">
        </div>

        {{-- Contenido personalizado para la cabecera (opcional) --}}
        <div class="mb-3" id="slider-content-container" style="{{ old('show_slider', $Page->show_slider ?? 0) == 1 ? '' : 'display: none;' }}">
            <label for="slider-content" class="form-label">Subtítulo <small class="text-muted">(opcional)</small></label>
            <textarea class="form-control" id="slider-content" name="slider_content" rows="2" 
                      placeholder="Texto adicional para mostrar debajo del título principal">{{ old('slider_content', $Page->slider_content ?? '') }}</textarea>
            <small class="text-muted">Texto breve que aparecerá debajo del título principal.</small>
        </div>

        {{-- Ocultar título H1 en el contenido principal --}}
        <div class="mb-3 form-check mt-4">
            <input type="hidden" name="hide_title" value="0">
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
    const removeSliderImageBtn = document.getElementById('remove-slider-image');
    const removeSliderImageFlag = document.getElementById('remove-slider-image-flag');
    const sliderImageInput = document.getElementById('slider-image');
    const currentImagePreview = document.getElementById('current-image-preview');
    const previewImageDisplay = document.getElementById('preview-image-display');
    const currentSliderImageInput = document.getElementById('current-slider-image-input');
    
    // Logs para depuración detallada
    console.log('Estado inicial:');
    console.log('- Valor de current_slider_image:', currentSliderImageInput ? currentSliderImageInput.value : 'no encontrado');
    if (previewImageDisplay) {
        console.log('- Valor original de slider_image:', previewImageDisplay.getAttribute('data-original-value'));
        console.log('- Ruta de la imagen mostrada:', previewImageDisplay.getAttribute('data-path'));
        console.log('- ¿Es imagen personalizada?:', previewImageDisplay.getAttribute('data-is-custom'));
        console.log('- URL completa de la imagen:', previewImageDisplay.src);
    }
    
    // Comprobar si estamos detectando correctamente la imagen personalizada
    if (currentSliderImageInput && currentSliderImageInput.value) {
        console.log('- Análisis de imagen personalizada:');
        console.log('  * Valor slider_image:', currentSliderImageInput.value);
        console.log('  * Está vacío:', !currentSliderImageInput.value);
        console.log('  * Contiene themes/default:', currentSliderImageInput.value.indexOf('themes/default') !== -1);
        console.log('  * DEBERÍA ser imagen personalizada:', !!currentSliderImageInput.value && currentSliderImageInput.value.indexOf('themes/default') === -1);
    }
    
    // Función para mostrar/ocultar los campos del slider según checkbox
    function toggleSliderOptions() {
        const showSlider = showSliderCheckbox.checked;
        sliderImageContainer.style.display = showSlider ? 'block' : 'none';
        sliderTitleContainer.style.display = showSlider ? 'block' : 'none';
        sliderContentContainer.style.display = showSlider ? 'block' : 'none';
    }
    
    // Event listeners
    showSliderCheckbox.addEventListener('change', toggleSliderOptions);
    
// Eliminar imagen
if (removeSliderImageBtn) {
    console.log('Botón eliminar imagen encontrado');
    removeSliderImageBtn.addEventListener('click', function() {
        console.log('Botón eliminar imagen pulsado');
        
        // Marcar como eliminada
        removeSliderImageFlag.value = '1';
        console.log('Flag de eliminación activado:', removeSliderImageFlag.value);
        
        // Cambiar la imagen a la predeterminada inmediatamente
        if (previewImageDisplay) {
            const baseUrl = window.location.origin;
            // Usamos una URL absoluta para asegurar que la imagen se cargue correctamente
            const defaultImagePath = baseUrl + '/themes/default/img/hero/contact_hero.jpg';
            
            // Precargamos la imagen para evitar que aparezca como "rota"
            const img = new Image();
            img.onload = function() {
                // Una vez cargada, la asignamos a la vista previa
                previewImageDisplay.src = defaultImagePath;
                previewImageDisplay.style.opacity = '0.7';
                previewImageDisplay.setAttribute('data-is-custom', 'false');
                console.log('Imagen cambiada a predeterminada:', previewImageDisplay.src);
            };
            img.onerror = function() {
                console.error('Error al cargar la imagen predeterminada');
                // Intentar con otra ruta alternativa si la primera falla
                previewImageDisplay.src = baseUrl + '/assets/themes/default/img/hero/contact_hero.jpg';
            };
            img.src = defaultImagePath;
        }
        
        // Eliminar los controles de imagen personalizada
        const customControls = document.getElementById('custom-image-controls');
        if (customControls) {
            customControls.remove();
            
            // Agregar texto de imagen predeterminada
            const defaultNotice = document.createElement('div');
            defaultNotice.id = 'default-image-notice';
            defaultNotice.innerHTML = '<small class="text-muted d-block">(Imagen predeterminada)</small>';
            currentImagePreview.appendChild(defaultNotice);
            
            console.log('Controles de imagen personalizada eliminados y texto predeterminado añadido');
        }
    });
} else {
    console.log('Botón de eliminar imagen no encontrado');

        
        // Verificar si debería haber un botón de eliminar
        if (previewImageDisplay && previewImageDisplay.getAttribute('data-is-custom') === 'true') {
            console.error('ERROR: Debería haber un botón de eliminar para una imagen personalizada, pero no se encontró');
            console.log('- Forzando recarga para corregir inconsistencia de estado...');
            
            // Opcional: forzar la recarga de la página si está en un estado inconsistente
            // window.location.reload();
        }
    }
    
    // Vista previa de imagen seleccionada
    if (sliderImageInput) {
        sliderImageInput.addEventListener('change', function() {
            console.log('Archivo seleccionado para subir:', this.files && this.files[0] ? this.files[0].name : 'ninguno');
            
            if (this.files && this.files[0]) {
                // Cancelar eliminación si se había marcado
                if (removeSliderImageFlag) {
                    removeSliderImageFlag.value = '0';
                    console.log('Flag de eliminación desactivado por selección de nueva imagen');
                }
                
                // Crear vista previa
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('Imagen cargada para previsualización');
                    
                    const previewContainer = document.createElement('div');
                    previewContainer.className = 'mt-3';
                    previewContainer.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Vista previa:</strong><br>
                            <img src="${e.target.result}" class="img-fluid img-thumbnail mt-2" style="max-height: 150px;">
                            <p class="mb-0 mt-2"><small>La imagen se guardará al actualizar la página</small></p>
                        </div>
                    `;
                    
                    // Agregar después del input
                    const existingPreview = sliderImageInput.nextElementSibling.nextElementSibling.nextElementSibling;
                    if (existingPreview && existingPreview.classList.contains('mt-3')) {
                        existingPreview.replaceWith(previewContainer);
                        console.log('Previsualización existente reemplazada');
                    } else {
                        const container = sliderImageInput.parentNode;
                        container.appendChild(previewContainer);
                        console.log('Nueva previsualización añadida');
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    } else {
        console.log('Input de selección de imagen no encontrado');
    }
    
    // Comprobar si la imagen se carga correctamente
    if (previewImageDisplay) {
        previewImageDisplay.addEventListener('load', function() {
            console.log('Imagen cargada correctamente:', this.src);
        });
        
        previewImageDisplay.addEventListener('error', function() {
            console.error('Error al cargar la imagen:', this.src);
            
            // Verificar si la URL tiene 'assets/' duplicado
            const src = this.src;
            if (src.includes('/assets/assets/')) {
                console.error('ERROR: URL de imagen con "assets/" duplicado. Corrigiendo...');
                // Corregir la URL de la imagen
                const correctedSrc = src.replace('/assets/assets/', '/assets/');
                this.src = correctedSrc;
                console.log('URL corregida:', correctedSrc);
                return;
            }
            
            // Intentar cargar la imagen predeterminada como fallback
            const baseUrl = window.location.origin;
            if (this.src !== baseUrl + '/themes/default/img/hero/contact_hero.jpg') {
                console.log('Intentando cargar imagen predeterminada como fallback');
                this.src = baseUrl + '/themes/default/img/hero/contact_hero.jpg';
                this.style.opacity = '0.7';
                
                // Actualizar controles si existen
                const customControls = document.getElementById('custom-image-controls');
                if (customControls) {
                    customControls.remove();
                    
                    // Agregar texto de imagen predeterminada
                    const defaultNotice = document.createElement('div');
                    defaultNotice.id = 'default-image-notice';
                    defaultNotice.innerHTML = '<small class="text-muted d-block">(Imagen predeterminada - original no encontrada)</small>';
                    currentImagePreview.appendChild(defaultNotice);
                    
                    console.log('Controles actualizados para mostrar imagen predeterminada');
                }
            }
        });
    }
    
    // Inicializar estado
    toggleSliderOptions();
});
</script>
@endpush
