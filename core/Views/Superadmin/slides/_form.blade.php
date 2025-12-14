@csrf
@php
    $mediaModuleActive = is_module_active('media-manager');
@endphp

{{-- Campo oculto para saber a qué slider pertenece --}}
@if(isset($sliderId))
    <input type="hidden" name="slider_id" value="{{ $sliderId }}">
@endif

<div class="row">
    <div class="col-md-8">
        @php
            // Lista de tipografías (valores CSS para font-family)
            $fontOptions = [
                '' => '— Heredar del slider/tema —',
                "'Playfair Display', serif" => 'Playfair Display',
                "'Montserrat', sans-serif" => 'Montserrat',
                "'Roboto', sans-serif" => 'Roboto',
                "'Open Sans', sans-serif" => 'Open Sans',
                "'Lato', sans-serif" => 'Lato',
                "'Poppins', sans-serif" => 'Poppins',
                "'Oswald', sans-serif" => 'Oswald',
                "'Raleway', sans-serif" => 'Raleway',
            ];
        @endphp

        {{-- URL de la Imagen --}}
        <div class="mb-3">
            <label for="image_url" class="form-label">URL de la Imagen <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="text" class="form-control @error('image_url') is-invalid @enderror" id="image_url" name="image_url"
                      value="{{ old('image_url', $slide->image_url ?? '') }}" required placeholder="https://...">
                @if($mediaModuleActive)
                <button type="button" class="md-button md-primary open-media-modal-button"
                        id="btn-media-manager"
                        data-input-target="#image_url"
                        data-preview-target="#image_preview"
                        title="Abrir Gestor de Medios">
                    <i class="fas fa-photo-video"></i>
                </button>
                @else
                <button type="button" class="btn btn-secondary" disabled title="Módulo Media Manager desactivado">
                    <i class="fas fa-photo-video"></i>
                </button>
                @endif
            </div>
            @if($mediaModuleActive)
                <small class="text-muted">Pega la URL completa o usa el gestor de medios.</small>
            @else
                <small class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    El módulo Media Manager está desactivado. Pega la URL de la imagen directamente.
                    <a href="/musedock/modules">Activar módulo</a>
                </small>
            @endif
            @error('image_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Resto de campos del formulario --}}
        {{-- Título --}}
        <div class="mb-3">
            <label for="title" class="form-label">Título (Opcional)</label>
            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                   value="{{ old('title', $slide->title ?? '') }}">
            <small class="text-muted">Texto principal que puede superponerse a la imagen.</small>
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="title_color" class="form-label">Color del título (Opcional)</label>
            <input type="color" class="form-control form-control-color @error('title_color') is-invalid @enderror"
                   id="title_color" name="title_color" value="{{ old('title_color', $slide->title_color ?? '#ffffff') }}">
            <small class="text-muted">Si lo defines aquí, tiene prioridad sobre el color global del slider.</small>
            @error('title_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Descripción --}}
        <div class="mb-3">
            <label for="description" class="form-label">Descripción (Opcional)</label>
            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $slide->description ?? '') }}</textarea>
            <small class="text-muted">Texto adicional corto.</small>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="description_color" class="form-label">Color del subtítulo (Opcional)</label>
            <input type="color" class="form-control form-control-color @error('description_color') is-invalid @enderror"
                   id="description_color" name="description_color" value="{{ old('description_color', $slide->description_color ?? '#ffffff') }}">
            <small class="text-muted">Si lo defines aquí, tiene prioridad sobre el color global del slider.</small>
            @error('description_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Enlace --}}
        <div class="mb-3">
            <label for="link_url" class="form-label">Enlace (Opcional)</label>
            <input type="url" class="form-control @error('link_url') is-invalid @enderror" id="link_url" name="link_url"
                   value="{{ old('link_url', $slide->link_url ?? '') }}" placeholder="https://...">
            <small class="text-muted">URL a la que dirigirá al hacer clic en la diapositiva.</small>
            @error('link_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label for="link_target" class="form-label">Abrir enlace</label>
            @php $selectedTarget = old('link_target', $slide->link_target ?? '_self'); @endphp
            <select class="form-select" id="link_target" name="link_target">
                <option value="_self" @selected($selectedTarget === '_self')>En la misma pestaña</option>
                <option value="_blank" @selected($selectedTarget === '_blank')>En una pestaña nueva</option>
            </select>
        </div>

        {{-- Botón (opcional) --}}
        <div class="mb-3">
            <label for="link_text" class="form-label">Texto del botón (Opcional)</label>
            <input type="text" class="form-control @error('link_text') is-invalid @enderror" id="link_text" name="link_text"
                   value="{{ old('link_text', $slide->link_text ?? '') }}" placeholder="Ej: Leer más">
            <small class="text-muted">Si defines un texto y hay enlace, se mostrará un botón dentro de la diapositiva.</small>
            @error('link_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        {{-- Botón 2 (opcional) --}}
        <div class="mb-3">
            <label for="link2_url" class="form-label">Enlace 2 (Opcional)</label>
            <input type="url" class="form-control @error('link2_url') is-invalid @enderror" id="link2_url" name="link2_url"
                   value="{{ old('link2_url', $slide->link2_url ?? '') }}" placeholder="https://...">
            <small class="text-muted">URL del segundo botón.</small>
            @error('link2_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label for="link2_target" class="form-label">Abrir enlace 2</label>
            @php $selectedTarget2 = old('link2_target', $slide->link2_target ?? '_self'); @endphp
            <select class="form-select" id="link2_target" name="link2_target">
                <option value="_self" @selected($selectedTarget2 === '_self')>En la misma pestaña</option>
                <option value="_blank" @selected($selectedTarget2 === '_blank')>En una pestaña nueva</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="link2_text" class="form-label">Texto del botón 2 (Opcional)</label>
            <input type="text" class="form-control @error('link2_text') is-invalid @enderror" id="link2_text" name="link2_text"
                   value="{{ old('link2_text', $slide->link2_text ?? '') }}" placeholder="Ej: Contactar">
            <small class="text-muted">Si defines texto y enlace 2, se mostrará un segundo botón en paralelo.</small>
            @error('link2_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-check mb-2">
            <input type="hidden" name="button_custom" value="0">
            <input class="form-check-input" type="checkbox" value="1" id="button_custom" name="button_custom"
                   @checked(old('button_custom', (int)($slide->button_custom ?? 0)) == 1)>
            <label class="form-check-label" for="button_custom">Personalizar colores del botón</label>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="button_bg_color" class="form-label">Botón: fondo</label>
                    <input type="color" class="form-control form-control-color @error('button_bg_color') is-invalid @enderror"
                           id="button_bg_color" name="button_bg_color" value="{{ old('button_bg_color', $slide->button_bg_color ?? '#1d4ed8') }}">
                    @error('button_bg_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="button_text_color" class="form-label">Botón: texto</label>
                    <input type="color" class="form-control form-control-color @error('button_text_color') is-invalid @enderror"
                           id="button_text_color" name="button_text_color" value="{{ old('button_text_color', $slide->button_text_color ?? '#ffffff') }}">
                    @error('button_text_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="button_border_color" class="form-label">Botón: borde</label>
                    <input type="color" class="form-control form-control-color @error('button_border_color') is-invalid @enderror"
                           id="button_border_color" name="button_border_color" value="{{ old('button_border_color', $slide->button_border_color ?? '#ffffff') }}">
                    @error('button_border_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="form-check mb-2">
            <input type="hidden" name="button2_custom" value="0">
            <input class="form-check-input" type="checkbox" value="1" id="button2_custom" name="button2_custom"
                   @checked(old('button2_custom', (int)($slide->button2_custom ?? 0)) == 1)>
            <label class="form-check-label" for="button2_custom">Personalizar colores del botón 2</label>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="button2_bg_color" class="form-label">Botón 2: fondo</label>
                    <input type="color" class="form-control form-control-color @error('button2_bg_color') is-invalid @enderror"
                           id="button2_bg_color" name="button2_bg_color" value="{{ old('button2_bg_color', $slide->button2_bg_color ?? '#0ea5e9') }}">
                    @error('button2_bg_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="button2_text_color" class="form-label">Botón 2: texto</label>
                    <input type="color" class="form-control form-control-color @error('button2_text_color') is-invalid @enderror"
                           id="button2_text_color" name="button2_text_color" value="{{ old('button2_text_color', $slide->button2_text_color ?? '#ffffff') }}">
                    @error('button2_text_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="button2_border_color" class="form-label">Botón 2: borde</label>
                    <input type="color" class="form-control form-control-color @error('button2_border_color') is-invalid @enderror"
                           id="button2_border_color" name="button2_border_color" value="{{ old('button2_border_color', $slide->button2_border_color ?? '#ffffff') }}">
                    @error('button2_border_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="button_shape" class="form-label">Forma de los botones</label>
            @php $selectedShape = old('button_shape', $slide->button_shape ?? 'rounded'); @endphp
            <select class="form-select" id="button_shape" name="button_shape">
                <option value="rounded" @selected($selectedShape === 'rounded')>Redondeado (pill)</option>
                <option value="square" @selected($selectedShape === 'square')>Cuadrado (esquinas)</option>
            </select>
            <small class="text-muted">Aplica a ambos botones de la diapositiva.</small>
        </div>

        {{-- Tipografía y estilo --}}
        <div class="mt-2">
            <div class="form-check mb-2">
                <input type="hidden" name="title_bold" value="0">
                <input class="form-check-input" type="checkbox" value="1" id="title_bold" name="title_bold"
                       @checked(old('title_bold', $slide->exists ? (int)($slide->title_bold ?? 1) : 1) == 1)>
                <label class="form-check-label" for="title_bold">Título en negrita (bold)</label>
            </div>

            <div class="mb-3">
                <label for="title_font" class="form-label">Tipografía del título (Opcional)</label>
                <select class="form-select @error('title_font') is-invalid @enderror" id="title_font" name="title_font">
                    @php $selectedTitleFont = old('title_font', $slide->title_font ?? ''); @endphp
                    @foreach($fontOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string)$selectedTitleFont === (string)$value)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="text-muted">Si eliges una tipografía aquí, tiene prioridad sobre la del slider.</small>
                @error('title_font') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="description_font" class="form-label">Tipografía del subtítulo (Opcional)</label>
                <select class="form-select @error('description_font') is-invalid @enderror" id="description_font" name="description_font">
                    @php $selectedDescFont = old('description_font', $slide->description_font ?? ''); @endphp
                    @foreach($fontOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string)$selectedDescFont === (string)$value)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="text-muted">Si eliges una tipografía aquí, tiene prioridad sobre la del slider.</small>
                @error('description_font') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    <div class="col-md-4">
        {{-- Previsualización Imagen --}}
        <div class="mb-3">
            <label class="form-label">Previsualización</label>
            <img id="image_preview" src="{{ old('image_url', $slide->image_url ?? '/assets/superadmin/img/placeholder-image.png') }}" alt="Previsualización" class="img-fluid rounded border" style="max-height: 150px; width: 100%; object-fit: cover;">
        </div>

        {{-- Activo --}}
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" role="switch"
                   @checked(old('is_active', $slide->is_active ?? true))>
            <label class="form-check-label" for="is_active">Activo</label>
            <small class="d-block text-muted">Desmarca para ocultar esta diapositiva.</small>
        </div>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">
        {{ $slide->exists ? 'Actualizar Diapositiva' : 'Guardar Diapositiva' }}
    </button>
    <a href="{{ route('sliders.edit', ['id' => $slide->slider_id ?? $sliderId]) }}" class="btn btn-secondary">Cancelar</a>
</div>

{{-- Script para actualizar preview al escribir URL manualmente --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageUrlInput = document.getElementById('image_url');
    const imagePreview = document.getElementById('image_preview');

    if (imageUrlInput && imagePreview) {
        imageUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url) {
                imagePreview.src = url;
            } else {
                imagePreview.src = '/assets/superadmin/img/placeholder-image.png';
            }
        });

        // Manejar error de carga de imagen
        imagePreview.addEventListener('error', function() {
            this.src = '/assets/superadmin/img/placeholder-image.png';
        });
    }
});
</script>

@if($mediaModuleActive)
{{-- CONFIGURACIÓN GLOBAL PARA MEDIA MANAGER --}}
<script>
// Configuración global del gestor de medios (DEBE ESTAR ANTES DE CARGAR CUALQUIER JS)
window.MediaManagerConfig = {
    uploadUrl: '{{ route("superadmin.media.upload") }}',
    dataUrl: '{{ route("superadmin.media.data") }}',
    deleteUrlTemplate: '{{ route("superadmin.media.delete", ["id" => ":id"]) }}',
    detailsUrlTemplate: '{{ route("superadmin.media.details", ["id" => ":id"]) }}',
    updateUrlTemplate: '{{ route("superadmin.media.update", ["id" => ":id"]) }}',
    foldersStructureUrl: '{{ route("superadmin.media.folders.structure") }}',
    csrfToken: '{{ csrf_token() }}',
    currentDisk: 'media',
    availableDisks: {
        'media': { name: 'Local (Seguro)', icon: 'bi-hdd' },
        'local': { name: 'Local (Legacy)', icon: 'bi-folder' }
    }
};
// Fallback si las rutas no se resuelven
if (window.MediaManagerConfig.foldersStructureUrl.includes('#ruta-no-encontrada')) {
    window.MediaManagerConfig.foldersStructureUrl = '/musedock/media/folders/structure';
}
if (window.MediaManagerConfig.uploadUrl.includes('#ruta-no-encontrada')) {
    window.MediaManagerConfig.uploadUrl = '/musedock/media/upload';
}
if (window.MediaManagerConfig.dataUrl.includes('#ruta-no-encontrada')) {
    window.MediaManagerConfig.dataUrl = '/musedock/media/data';
}
if (window.MediaManagerConfig.deleteUrlTemplate.includes('#ruta-no-encontrada')) {
    window.MediaManagerConfig.deleteUrlTemplate = '/musedock/media/:id/delete';
}
if (window.MediaManagerConfig.detailsUrlTemplate.includes('#ruta-no-encontrada')) {
    window.MediaManagerConfig.detailsUrlTemplate = '/musedock/media/:id/details';
}
if (window.MediaManagerConfig.updateUrlTemplate.includes('#ruta-no-encontrada')) {
    window.MediaManagerConfig.updateUrlTemplate = '/musedock/media/:id/update';
}
</script>

{{-- ESTRUCTURA DEL MODAL PERSONALIZADO --}}
<div id="md-media-manager" class="md-modal-overlay">
    <div class="md-modal">
        <div class="md-modal-header">
            <h3>Gestor de Medios</h3>
            <div class="md-modal-tabs">
                <button type="button" class="md-tab active" data-tab="library">Biblioteca</button>
                <button type="button" class="md-tab" data-tab="upload">Subir Archivos</button>
            </div>
            {{-- Selector de Disco --}}
            <div class="md-disk-selector">
                <select id="md-disk-select" class="md-disk-dropdown" title="Seleccionar almacenamiento">
                    <option value="media" selected>📁 Local (Seguro)</option>
                    <option value="local">📂 Local (Legacy)</option>
                </select>
            </div>
            <button type="button" class="md-close">&times;</button>
        </div>

        <div class="md-modal-content">
            <!-- Pestaña de Biblioteca -->
            <div class="md-tab-content active" id="library-tab">
                <div class="md-library-layout">
                    <!-- Panel de Carpetas -->
                    <div class="md-folders-panel" id="md-folders-panel">
                        <div class="md-folders-header">
                            <span>Carpetas</span>
                        </div>
                        <div class="md-folders-tree" id="md-folders-tree">
                            <div class="md-folders-loading">Cargando...</div>
                        </div>
                    </div>

                    <!-- Panel de Archivos -->
                    <div class="md-files-panel">
                        <div class="md-search-bar">
                            <div class="md-current-folder" id="md-current-folder">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                                <span>Raíz</span>
                            </div>
                            <div class="md-search-container">
                                <input type="text" id="md-search-input" placeholder="Buscar medios...">
                                <button type="button" id="md-search-button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                </button>
                            </div>
                            <!-- Filtro de tipo eliminado - mostramos todos los archivos -->
                        </div>

                        <div class="md-grid-container">
                            <div class="md-loader">
                                <div class="md-spinner"></div>
                                <p>Cargando medios...</p>
                            </div>
                            <div class="md-grid" id="md-media-grid"></div>
                        </div>

                        <div class="md-pagination" id="md-pagination"></div>
                    </div>
                </div>
            </div>

            <!-- Pestaña de Subida -->
            <div class="md-tab-content" id="upload-tab">
                <div class="md-upload-folder-indicator" id="md-upload-folder-indicator">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#ffc107"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                    <span>Subir a: <strong id="md-upload-folder-name">Raíz</strong></span>
                </div>
                <div class="md-dropzone" id="md-dropzone">
                    <div class="md-dropzone-content">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <h3>Arrastra archivos aquí</h3>
                        <p>o</p>
                        <button type="button" class="md-button" id="md-browse-button">Seleccionar archivos</button>
                        <input type="file" id="md-file-input" multiple style="display: none;">
                        <p class="md-small">Formatos permitidos: JPG, PNG, GIF, PDF, DOC, XLS. Máx 10MB.</p>
                    </div>
                </div>

                <div class="md-upload-progress" style="display: none;">
                    <div class="md-progress-container">
                        <div class="md-progress-bar"></div>
                    </div>
                    <p class="md-progress-status">Subiendo archivos...</p>
                </div>

                <div class="md-upload-results" style="display: none;">
                    <h4>Archivos subidos</h4>
                    <div class="md-uploaded-files"></div>
                </div>
            </div>
        </div>

        <div class="md-modal-footer">
            <div class="md-file-details" style="display: none;">
                <div class="md-preview">
                    <img id="md-selected-preview" src="" alt="">
                </div>
                <div class="md-details">
                    <p class="md-filename" id="md-selected-filename"></p>
                    <p class="md-dimensions" id="md-selected-dimensions"></p>
                </div>
            </div>
            <div class="md-actions">
                <button type="button" class="md-button md-secondary md-cancel">Cancelar</button>
                <button type="button" class="md-button md-primary md-select" disabled>Seleccionar</button>
            </div>
        </div>
    </div>
</div>

{{-- ESTILOS DEL MEDIA MANAGER --}}
<style>
/* Variables para colores y tamaños consistentes */
:root {
    --md-primary: #2271b1;
    --md-primary-hover: #135e96;
    --md-secondary: #f0f0f1;
    --md-secondary-hover: #dcdcde;
    --md-danger: #d63638;
    --md-danger-hover: #b32d2e;
    --md-text: #2c3338;
    --md-text-light: #646970;
    --md-border: #c3c4c7;
    --md-focus: rgba(34, 113, 177, 0.2);
    --md-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    --md-animation: all 0.2s ease;
}

/* Reset básico */
.md-modal-overlay *,
.md-modal-overlay *:before,
.md-modal-overlay *:after {
    box-sizing: border-box;
}

/* Overlay para el modal */
.md-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    overflow: hidden;
    padding: 20px;
}

.md-modal-overlay.active {
    display: flex;
    animation: mdFadeIn 0.3s ease;
}

/* Modal principal */
.md-modal {
    background-color: white;
    border-radius: 4px;
    overflow: hidden;
    width: 100%;
    max-width: 900px;
    height: 80vh;
    max-height: 600px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    animation: mdSlideIn 0.3s ease;
}

/* Cabecera del modal */
.md-modal-header {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--md-border);
    background-color: #fff;
}

.md-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--md-text);
    flex: 0 0 auto;
}

.md-modal-tabs {
    display: flex;
    margin-left: 20px;
    flex: 1;
}

/* Selector de Disco */
.md-disk-selector {
    margin-left: auto;
    margin-right: 15px;
}

.md-disk-dropdown {
    padding: 6px 12px;
    border: 1px solid var(--md-border);
    border-radius: 4px;
    background: #fff;
    font-size: 13px;
    cursor: pointer;
    color: var(--md-text);
}

.md-disk-dropdown:focus {
    outline: none;
    border-color: var(--md-primary);
    box-shadow: 0 0 0 2px var(--md-focus);
}

.md-tab {
    background: none;
    border: none;
    padding: 10px 15px;
    font-size: 14px;
    color: var(--md-text-light);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: var(--md-animation);
}

.md-tab:hover {
    color: var(--md-primary);
}

.md-tab.active {
    color: var(--md-primary);
    border-bottom-color: var(--md-primary);
}

.md-close {
    background: none;
    border: none;
    font-size: 24px;
    line-height: 1;
    color: var(--md-text-light);
    cursor: pointer;
    transition: var(--md-animation);
}

.md-close:hover {
    color: var(--md-danger);
}

/* Contenido del modal */
.md-modal-content {
    flex: 1;
    overflow: hidden;
    position: relative;
}

.md-tab-content {
    display: none;
    height: 100%;
    overflow: auto;
}

.md-tab-content.active {
    display: flex;
    flex-direction: column;
}

/* Layout de biblioteca con carpetas */
.md-library-layout {
    display: flex;
    height: 100%;
    overflow: hidden;
}

.md-folders-panel {
    width: 200px;
    min-width: 200px;
    border-right: 1px solid var(--md-border);
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
}

.md-folders-header {
    padding: 12px 15px;
    font-weight: 600;
    font-size: 13px;
    color: var(--md-text);
    border-bottom: 1px solid var(--md-border);
    background: #fff;
}

.md-folders-tree {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.md-folders-loading {
    text-align: center;
    color: var(--md-text-light);
    font-size: 13px;
    padding: 20px;
}

.md-folder-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 13px;
    color: var(--md-text);
    transition: background-color 0.15s;
}

.md-folder-item:hover {
    background-color: #e9ecef;
}

.md-folder-item.active {
    background-color: var(--md-primary);
    color: white;
}

.md-folder-item.active svg {
    fill: white;
}

.md-folder-item svg {
    width: 16px;
    height: 16px;
    fill: #ffc107;
    flex-shrink: 0;
}

.md-folder-item span {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.md-folder-children {
    margin-left: 20px;
    border-left: 1px solid var(--md-border);
    padding-left: 5px;
}

.md-files-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

.md-current-folder {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #fff;
    border: 1px solid var(--md-border);
    border-radius: 4px;
    font-size: 13px;
    color: var(--md-text);
}

.md-current-folder svg {
    fill: #ffc107;
}

/* Pie del modal */
.md-modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 20px;
    border-top: 1px solid var(--md-border);
    background-color: #f8f9fa;
}

/* Barra de búsqueda */
.md-search-bar {
    padding: 15px 20px;
    display: flex;
    gap: 10px;
    background-color: #f8f9fa;
    border-bottom: 1px solid var(--md-border);
}

.md-search-container {
    flex: 1;
    display: flex;
    position: relative;
}

.md-search-container input {
    flex: 1;
    padding: 8px 15px;
    border: 1px solid var(--md-border);
    border-radius: 4px;
    font-size: 14px;
    transition: var(--md-animation);
}

.md-search-container input:focus {
    outline: none;
    border-color: var(--md-primary);
    box-shadow: 0 0 0 2px var(--md-focus);
}

.md-search-container button {
    position: absolute;
    right: 0;
    top: 0;
    height: 100%;
    width: 40px;
    background: none;
    border: none;
    color: var(--md-text-light);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.md-filter {
    width: 180px;
}

.md-filter select {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--md-border);
    border-radius: 4px;
    background-color: white;
    font-size: 14px;
    color: var(--md-text);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='5' viewBox='0 0 10 5'%3E%3Cpath d='M0 0l5 5 5-5z' fill='%23646970'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 25px;
    transition: var(--md-animation);
}

.md-filter select:focus {
    outline: none;
    border-color: var(--md-primary);
    box-shadow: 0 0 0 2px var(--md-focus);
}

/* Contenedor de cuadrícula */
.md-grid-container {
    flex: 1;
    overflow: auto;
    padding: 20px;
    position: relative;
}

/* Cuadrícula de medios */
.md-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

/* Elemento de medio */
.md-item {
    position: relative;
    cursor: pointer;
    border-radius: 4px;
    overflow: hidden;
    transition: var(--md-animation);
    border: 2px solid transparent;
}

.md-item:hover {
    transform: translateY(-3px);
    box-shadow: var(--md-shadow);
}

.md-item.selected {
    border-color: var(--md-primary);
    box-shadow: 0 0 0 2px var(--md-focus);
}

.md-item-thumbnail {
    height: 150px;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.md-item-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.md-item-filename {
    padding: 8px;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background-color: white;
}

.md-item-actions {
    position: absolute;
    top: 5px;
    right: 5px;
    display: none;
    gap: 5px;
}

.md-item:hover .md-item-actions {
    display: flex;
}

.md-item-action {
    width: 28px;
    height: 28px;
    border-radius: 3px;
    background-color: rgba(255, 255, 255, 0.9);
    border: 1px solid var(--md-border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--md-animation);
}

.md-item-action:hover {
    background-color: white;
}

.md-item-action.delete:hover {
    color: var(--md-danger);
}

.md-item-action.edit:hover {
    color: var(--md-primary);
}

/* Iconos para documentos */
.md-file-icon {
    width: 48px;
    height: 48px;
    color: var(--md-text-light);
}

/* Paginación */
.md-pagination {
    display: flex;
    justify-content: center;
    padding: 15px 0;
    border-top: 1px solid var(--md-border);
}

.md-pagination ul {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
}

.md-pagination li {
    margin: 0 2px;
}

.md-pagination button {
    min-width: 30px;
    height: 30px;
    border-radius: 3px;
    border: 1px solid var(--md-border);
    background-color: white;
    color: var(--md-text);
    font-size: 14px;
    cursor: pointer;
    transition: var(--md-animation);
    padding: 0 6px;
}

.md-pagination button:hover {
    background-color: var(--md-secondary);
}

.md-pagination button.active {
    background-color: var(--md-primary);
    color: white;
    border-color: var(--md-primary);
}

.md-pagination button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Indicador de carpeta destino para upload */
.md-upload-folder-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid var(--md-border);
    font-size: 13px;
    color: var(--md-text);
}

.md-upload-folder-indicator strong {
    color: var(--md-primary);
}

/* Zona de drop para subida */
.md-dropzone {
    flex: 1;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.md-dropzone-content {
    width: 100%;
    max-width: 500px;
    height: 300px;
    border: 2px dashed var(--md-border);
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    text-align: center;
    transition: var(--md-animation);
}

.md-dropzone-content svg {
    margin-bottom: 15px;
    color: var(--md-text-light);
}

.md-dropzone-content h3 {
    margin: 0 0 10px;
    font-size: 18px;
    font-weight: 500;
    color: var(--md-text);
}

.md-dropzone-content p {
    margin: 5px 0;
    color: var(--md-text-light);
}

.md-dropzone.dragover .md-dropzone-content {
    background-color: rgba(34, 113, 177, 0.05);
    border-color: var(--md-primary);
}

.md-small {
    font-size: 12px;
    margin-top: 15px;
    color: var(--md-text-light);
}

/* Barra de progreso */
.md-upload-progress {
    padding: 20px;
    margin-top: 20px;
}

.md-progress-container {
    height: 8px;
    background-color: var(--md-secondary);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.md-progress-bar {
    height: 100%;
    background-color: var(--md-primary);
    width: 0%;
    transition: width 0.3s ease;
}

.md-progress-status {
    font-size: 14px;
    color: var(--md-text-light);
    text-align: center;
    margin: 0;
}

/* Resultados de subida */
.md-upload-results {
    padding: 20px;
    border-top: 1px solid var(--md-border);
    margin-top: 20px;
}

.md-upload-results h4 {
    margin: 0 0 15px;
    font-size: 16px;
    font-weight: 500;
    color: var(--md-text);
}

.md-uploaded-files {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
}

/* Panel de detalles del archivo seleccionado */
.md-file-details {
    display: flex;
    align-items: center;
    gap: 15px;
}

.md-preview {
    width: 60px;
    height: 60px;
    border-radius: 3px;
    overflow: hidden;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.md-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.md-details p {
    margin: 0;
}

.md-filename {
    font-weight: 500;
    color: var(--md-text);
    font-size: 14px;
}

.md-dimensions {
    color: var(--md-text-light);
    font-size: 12px;
}

/* Botones */
.md-button {
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--md-animation);
    background-color: var(--md-secondary);
    color: var(--md-text);
    border: 1px solid var(--md-border);
}

.md-button:hover {
    background-color: var(--md-secondary-hover);
}

.md-button.md-primary {
    background-color: var(--md-primary);
    color: white;
    border-color: var(--md-primary);
}

.md-button.md-primary:hover {
    background-color: var(--md-primary-hover);
}

.md-button.md-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.md-actions {
    display: flex;
    gap: 10px;
}

/* Loader */
.md-loader {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background-color: rgba(255, 255, 255, 0.8);
    z-index: 1;
}

.md-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(34, 113, 177, 0.2);
    border-top-color: var(--md-primary);
    border-radius: 50%;
    animation: mdSpin 0.8s linear infinite;
    margin-bottom: 10px;
}

/* Animaciones */
@keyframes mdFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes mdSlideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes mdSpin {
    to { transform: rotate(360deg); }
}

/* Utilidades */
.md-hidden {
    display: none !important;
}
</style>

{{-- MEDIA MANAGER PERSONALIZADO --}}

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========================================================
    // SECTION 1: REFERENCIAS A ELEMENTOS
    // ========================================================
    // Referencias al modal principal
    const modalOverlay = document.getElementById('md-media-manager');
    const closeBtn = modalOverlay.querySelector('.md-close');
    const cancelBtn = modalOverlay.querySelector('.md-cancel');

    // Referencias a las pestañas
    const tabs = modalOverlay.querySelectorAll('.md-tab');
    const tabContents = modalOverlay.querySelectorAll('.md-tab-content');

    // Referencias a la biblioteca
    const mediaGrid = document.getElementById('md-media-grid');
    const searchInput = document.getElementById('md-search-input');
    const searchButton = document.getElementById('md-search-button');
    const filterSelect = document.getElementById('md-filter-select');
    const paginationContainer = document.getElementById('md-pagination');
    const loader = modalOverlay.querySelector('.md-loader');

    // Referencias a la pestaña de subida
    const dropzone = document.getElementById('md-dropzone');
    const dropzoneContent = dropzone.querySelector('.md-dropzone-content');
    const fileInput = document.getElementById('md-file-input');
    const browseButton = document.getElementById('md-browse-button');
    const progressContainer = modalOverlay.querySelector('.md-upload-progress');
    const progressBar = modalOverlay.querySelector('.md-progress-bar');
    const progressStatus = modalOverlay.querySelector('.md-progress-status');
    const uploadResults = modalOverlay.querySelector('.md-upload-results');
    const uploadedFilesContainer = modalOverlay.querySelector('.md-uploaded-files');

    // Referencias a la selección y acciones
    const selectButton = modalOverlay.querySelector('.md-select');
    const fileDetailsContainer = modalOverlay.querySelector('.md-file-details');
    const selectedPreview = document.getElementById('md-selected-preview');
    const selectedFilename = document.getElementById('md-selected-filename');
    const selectedDimensions = document.getElementById('md-selected-dimensions');

    // Referencias a carpetas
    const foldersTree = document.getElementById('md-folders-tree');
    const currentFolderDisplay = document.getElementById('md-current-folder');

    // Referencias al selector de disco
    const diskSelector = document.getElementById('md-disk-select');

    // ========================================================
    // SECTION 2: VARIABLES DE ESTADO
    // ========================================================
    let currentPage = 1;
    let searchTerm = '';
    let filterType = 'all';
    let currentFolderId = null; // null = Root
    let currentFolderName = 'Raíz';
    let selectedMediaId = null;
    let selectedMediaData = null;
    let targetInputElement = null;
    let targetPreviewElement = null;
    let currentDisk = 'media'; // Disco actual seleccionado

    // ========================================================
    // SECTION 3: CONFIGURACIÓN
    // ========================================================
    // Verificar que MediaManagerConfig esté definido
    if (!window.MediaManagerConfig) {
        console.error('Error crítico: MediaManagerConfig no está definido. El gestor de medios no funcionará correctamente.');
        return;
    }

    const uploadUrl = window.MediaManagerConfig.uploadUrl;
    const dataUrl = window.MediaManagerConfig.dataUrl;
    const deleteUrlBase = window.MediaManagerConfig.deleteUrlTemplate;
    const updateUrlBase = window.MediaManagerConfig.updateUrlTemplate;
    const foldersStructureUrl = window.MediaManagerConfig.foldersStructureUrl;
    const csrfToken = window.MediaManagerConfig.csrfToken;

    // ========================================================
    // SECTION 4: FUNCIONES PRINCIPALES
    // ========================================================

    // Función para abrir el modal
    function openMediaManager(inputSelector, previewSelector) {
        // Configurar objetivos
        targetInputElement = document.querySelector(inputSelector);
        targetPreviewElement = document.querySelector(previewSelector);
        selectedMediaId = null;
        selectedMediaData = null;

        // Resetear carpeta actual
        currentFolderId = null;
        currentFolderName = 'Raíz';
        updateCurrentFolderDisplay();

        // Desactivar botón de selección
        if (selectButton) {
            selectButton.disabled = true;
        }

        // Ocultar panel de detalles
        if (fileDetailsContainer) {
            fileDetailsContainer.style.display = 'none';
        }

        // Restablecer pestañas
        showTab('library');

        // Mostrar modal
        modalOverlay.classList.add('active');

        // Cargar carpetas y medios
        loadFolders();
        loadMedia(1);
    }

    // Función para cerrar el modal
    function closeMediaManager() {
        modalOverlay.classList.remove('active');
    }

    // Función para cargar medios
    function loadMedia(page = 1, search = '', filter = 'all') {
        if (!mediaGrid) return;

        // Actualizar estado
        currentPage = page;
        searchTerm = search;
        filterType = filter;

        // Mostrar cargador
        loader.style.display = 'flex';
        mediaGrid.innerHTML = '';

        if (paginationContainer) {
            paginationContainer.innerHTML = '';
        }

        // Construir URL con parámetros
        const url = new URL(dataUrl, window.location.origin);
        url.searchParams.append('page', page);
        if (search) url.searchParams.append('search', search);
        if (filter && filter !== 'all') url.searchParams.append('type', filter);
        // Añadir folder_id si está seleccionada una carpeta
        if (currentFolderId) url.searchParams.append('folder_id', currentFolderId);
        // Añadir disk si está seleccionado
        if (currentDisk) url.searchParams.append('disk', currentDisk);

        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error ${response.status}`);
                return response.json();
            })
            .then(data => {
                // Ocultar cargador
                loader.style.display = 'none';

                if (data.success && data.media && data.media.length > 0) {
                    // Crear elementos para cada medio
                    data.media.forEach(item => {
                        const mediaItem = createMediaItem(item);
                        mediaGrid.appendChild(mediaItem);
                    });

                    // Renderizar paginación
                    if (paginationContainer && data.pagination) {
                        renderPagination(data.pagination);
                    }
                } else if (data.success) {
                    mediaGrid.innerHTML = '<div class="md-empty">No se encontraron medios</div>';
                } else {
                    mediaGrid.innerHTML = '<div class="md-error">Error al cargar medios</div>';
                    console.error('Error loading media:', data.message);
                }
            })
            .catch(error => {
                loader.style.display = 'none';
                mediaGrid.innerHTML = '<div class="md-error">Error de conexión</div>';
                console.error('Error fetching media:', error);
            });
    }

    // Función para cargar la estructura de carpetas
    function loadFolders() {
        if (!foldersTree || !foldersStructureUrl) return;

        foldersTree.innerHTML = '<div class="md-folders-loading">Cargando...</div>';

        // Construir URL con parámetro de disco
        const folderUrl = new URL(foldersStructureUrl, window.location.origin);
        if (currentDisk) {
            folderUrl.searchParams.append('disk', currentDisk);
        }

        fetch(folderUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.folders) {
                    renderFolderTree(data.folders);
                } else {
                    foldersTree.innerHTML = '<div class="md-folders-loading">Sin carpetas</div>';
                }
            })
            .catch(error => {
                console.error('Error loading folders:', error);
                foldersTree.innerHTML = '<div class="md-folders-loading">Error al cargar</div>';
            });
    }

    // Función para renderizar el árbol de carpetas
    function renderFolderTree(folders) {
        let html = '';

        // Encontrar la carpeta raíz (path === '/' o parent_id === null)
        const rootFolder = folders.find(f => f.path === '/' || f.parent_id === null);
        const otherFolders = folders.filter(f => f.path !== '/' && f.parent_id !== null);

        // Construir árbol jerárquico
        function buildTree(parentId) {
            return folders
                .filter(f => f.parent_id === parentId)
                .map(f => ({
                    ...f,
                    children: buildTree(f.id)
                }));
        }

        // Carpeta raíz (usar la de la BD si existe, sino mostrar virtual)
        if (rootFolder) {
            // La raíz de la BD - mostrar como "Raíz" pero usar su ID
            const isRootActive = !currentFolderId || currentFolderId === rootFolder.id;
            html += `<div class="md-folder-item ${isRootActive ? 'active' : ''}" data-folder-id="${rootFolder.id}" data-folder-name="Raíz">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                <span>Raíz</span>
            </div>`;

            // Construir árbol desde las subcarpetas de root
            const tree = buildTree(rootFolder.id);

            // Función recursiva para renderizar carpetas
            function renderFolder(folder, level = 0) {
                const isActive = currentFolderId == folder.id;
                let folderHtml = `<div class="md-folder-item ${isActive ? 'active' : ''}" data-folder-id="${folder.id}" data-folder-name="${escapeHtml(folder.name)}" style="padding-left: ${10 + (level + 1) * 15}px">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                    <span>${escapeHtml(folder.name)}</span>
                </div>`;

                if (folder.children && folder.children.length > 0) {
                    folder.children.forEach(child => {
                        folderHtml += renderFolder(child, level + 1);
                    });
                }

                return folderHtml;
            }

            // Renderizar subcarpetas
            tree.forEach(folder => {
                html += renderFolder(folder);
            });
        } else {
            // No hay carpeta raíz en BD - mostrar raíz virtual
            html += `<div class="md-folder-item ${!currentFolderId ? 'active' : ''}" data-folder-id="" data-folder-name="Raíz">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                <span>Raíz</span>
            </div>`;
        }

        foldersTree.innerHTML = html;

        // Añadir eventos de click a las carpetas
        foldersTree.querySelectorAll('.md-folder-item').forEach(item => {
            item.addEventListener('click', function() {
                const folderId = this.dataset.folderId;
                const folderName = this.dataset.folderName;

                // Actualizar estado
                currentFolderId = folderId ? parseInt(folderId) : null;
                currentFolderName = folderName;

                // Actualizar clases activas
                foldersTree.querySelectorAll('.md-folder-item').forEach(f => f.classList.remove('active'));
                this.classList.add('active');

                // Actualizar display de carpeta actual
                updateCurrentFolderDisplay();

                // Recargar medios con la nueva carpeta
                loadMedia(1, searchTerm, filterType);
            });
        });
    }

    // Función para actualizar el display de la carpeta actual
    function updateCurrentFolderDisplay() {
        if (currentFolderDisplay) {
            currentFolderDisplay.querySelector('span').textContent = currentFolderName;
        }
        // Actualizar también el indicador de carpeta en la pestaña de upload
        const uploadFolderName = document.getElementById('md-upload-folder-name');
        if (uploadFolderName) {
            uploadFolderName.textContent = currentFolderName;
        }
    }

    // Función para crear elementos de media
    function createMediaItem(item) {
        const div = document.createElement('div');
        div.className = 'md-item';
        div.dataset.id = item.id;
        div.dataset.url = item.url;
        div.dataset.filename = item.filename;

        if (item.width && item.height) {
            div.dataset.dimensions = `${item.width} × ${item.height}`;
        } else {
            div.dataset.dimensions = '';
        }

        const thumbnailUrl = item.thumbnail_url || item.url;
        const isImage = item.mime_type && item.mime_type.startsWith('image/');

        div.innerHTML = `
            <div class="md-item-thumbnail">
                ${isImage ?
                    `<img src="${escapeHtml(thumbnailUrl)}" alt="${escapeHtml(item.filename)}" loading="lazy">` :
                    `<svg class="md-file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>`
                }
            </div>
            <div class="md-item-filename">${escapeHtml(item.filename)}</div>
            <div class="md-item-actions">
                <button class="md-item-action delete" title="Eliminar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </button>
                <button class="md-item-action edit" title="Editar metadatos">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                </button>
            </div>
        `;

        // Evento de selección
        div.addEventListener('click', function(e) {
            // No seleccionar si se hizo clic en un botón de acción
            if (e.target.closest('.md-item-action')) return;

            // Deseleccionar item anterior
            const previousSelected = mediaGrid.querySelector('.md-item.selected');
            if (previousSelected) {
                previousSelected.classList.remove('selected');
            }

            // Seleccionar actual
            this.classList.add('selected');
            selectedMediaId = this.dataset.id;

            // Guardar datos para su uso posterior
            selectedMediaData = {
                id: this.dataset.id,
                url: this.dataset.url,
                filename: this.dataset.filename,
                dimensions: this.dataset.dimensions
            };

            // Actualizar panel de detalles
            updateFileDetails();

            // Habilitar botón de selección
            selectButton.disabled = false;
        });

        // Eventos para botones de acción
        const deleteBtn = div.querySelector('.delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                handleDeleteMedia(item.id);
            });
        }

        const editBtn = div.querySelector('.edit');
        if (editBtn) {
            editBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                handleEditMedia(item);
            });
        }

        return div;
    }

    // Función para manejar la eliminación de medios
    function handleDeleteMedia(mediaId) {
        if (!mediaId) return;

        if (confirm('¿Estás seguro de que quieres eliminar este archivo? Esta acción no se puede deshacer.')) {
            const deleteUrl = deleteUrlBase.replace(':id', mediaId);

            fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({'_csrf': csrfToken})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar mensaje
                    showNotification('Archivo eliminado correctamente', 'success');

                    // Recargar biblioteca
                    loadMedia(currentPage, searchTerm, filterType);

                    // Si era el elemento seleccionado, deseleccionar
                    if (selectedMediaId === mediaId) {
                        selectedMediaId = null;
                        selectedMediaData = null;
                        selectButton.disabled = true;
                        updateFileDetails();
                    }
                } else {
                    showNotification(data.message || 'Error al eliminar el archivo', 'error');
                }
            })
            .catch(error => {
                console.error("Error deleting media:", error);
                showNotification('Error de red al eliminar', 'error');
            });
        }
    }

    // Función para manejar la edición de medios
    function handleEditMedia(mediaItem) {
        // Implementar modal de edición de metadatos si se requiere
        console.log('Edit media:', mediaItem);
        alert('Funcionalidad de edición en desarrollo');
    }

    // Función para actualizar el panel de detalles
    function updateFileDetails() {
        if (fileDetailsContainer) {
            if (selectedMediaData) {
                // Mostrar panel
                fileDetailsContainer.style.display = 'flex';

                // Actualizar contenido
                if (selectedPreview) {
                    selectedPreview.src = selectedMediaData.url;
                }

                if (selectedFilename) {
                    selectedFilename.textContent = selectedMediaData.filename;
                }

                if (selectedDimensions) {
                    selectedDimensions.textContent = selectedMediaData.dimensions || '';
                }
            } else {
                // Ocultar panel si no hay selección
                fileDetailsContainer.style.display = 'none';
            }
        }
    }

    // Función para renderizar paginación
    function renderPagination(pagination) {
        if (!pagination || !paginationContainer) return;

        // Si solo hay una página, no mostrar paginación
        if (pagination.last_page <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        // Crear contenedor de paginación
        const ul = document.createElement('ul');

        // Botón anterior
        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '&laquo;';
        prevBtn.className = pagination.current_page === 1 ? 'disabled' : '';
        prevBtn.addEventListener('click', function() {
            if (pagination.current_page > 1) {
                loadMedia(pagination.current_page - 1, searchTerm, filterType);
            }
        });

        const prevLi = document.createElement('li');
        prevLi.appendChild(prevBtn);
        ul.appendChild(prevLi);

        // Calcular rango de páginas a mostrar
        let startPage = Math.max(1, pagination.current_page - 2);
        let endPage = Math.min(pagination.last_page, startPage + 4);

        // Ajustar si estamos cerca del final
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        // Primera página si no está incluida en el rango
        if (startPage > 1) {
            const pageBtn = document.createElement('button');
            pageBtn.textContent = '1';
            pageBtn.addEventListener('click', function() {
                loadMedia(1, searchTerm, filterType);
            });

            const pageLi = document.createElement('li');
            pageLi.appendChild(pageBtn);
            ul.appendChild(pageLi);

            // Añadir elipsis si es necesario
            if (startPage > 2) {
                const ellipsis = document.createElement('li');
                ellipsis.textContent = '...';
                ellipsis.className = 'ellipsis';
                ul.appendChild(ellipsis);
            }
        }

        // Páginas numeradas
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.textContent = i;
            if (i === pagination.current_page) {
                pageBtn.className = 'active';
            }

            pageBtn.addEventListener('click', function() {
                if (i !== pagination.current_page) {
                    loadMedia(i, searchTerm, filterType);
                }
            });

            const pageLi = document.createElement('li');
            pageLi.appendChild(pageBtn);
            ul.appendChild(pageLi);
        }

        // Última página si no está incluida
        if (endPage < pagination.last_page) {
            // Añadir elipsis si es necesario
            if (endPage < pagination.last_page - 1) {
                const ellipsis = document.createElement('li');
                ellipsis.textContent = '...';
                ellipsis.className = 'ellipsis';
                ul.appendChild(ellipsis);
            }

            const pageBtn = document.createElement('button');
            pageBtn.textContent = pagination.last_page;
            pageBtn.addEventListener('click', function() {
                loadMedia(pagination.last_page, searchTerm, filterType);
            });

            const pageLi = document.createElement('li');
            pageLi.appendChild(pageBtn);
            ul.appendChild(pageLi);
        }

        // Botón siguiente
        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = '&raquo;';
        nextBtn.className = pagination.current_page === pagination.last_page ? 'disabled' : '';
        nextBtn.addEventListener('click', function() {
            if (pagination.current_page < pagination.last_page) {
                loadMedia(pagination.current_page + 1, searchTerm, filterType);
            }
        });

        const nextLi = document.createElement('li');
        nextLi.appendChild(nextBtn);
        ul.appendChild(nextLi);

        // Actualizar el contenedor
        paginationContainer.innerHTML = '';
        paginationContainer.appendChild(ul);
    }

    // Función para manejar la subida de archivos
    function handleFilesUpload(files) {
        if (!files || !files.length) return;

        // Mostrar la barra de progreso
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressStatus.textContent = `Subiendo ${files.length} archivo(s)...`;

        // Preparar FormData
        const formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            formData.append('file[]', files[i]);
        }

        // Añadir CSRF token
        if (csrfToken) {
            formData.append('_csrf', csrfToken);
        }

        // Añadir folder_id si hay una carpeta seleccionada
        if (currentFolderId) {
            formData.append('folder_id', currentFolderId);
        }

        // Añadir disk actual
        if (currentDisk) {
            formData.append('disk', currentDisk);
        }

        // Crear y configurar la petición XHR
        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        // Evento de progreso
        xhr.upload.onprogress = function(event) {
            if (event.lengthComputable) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressStatus.textContent = `Subiendo... ${percentComplete}%`;
            }
        };

        // Evento de finalización
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);

                    if (response.success) {
                        // Actualizar estado
                        progressStatus.textContent = 'Archivos subidos correctamente';

                        // Mostrar resultados
                        uploadResults.style.display = 'block';

                        // Limpiar contenedor anterior
                        uploadedFilesContainer.innerHTML = '';

                        // Manejar tanto respuesta de archivo único como múltiple
                        if (response.files && Array.isArray(response.files)) {
                            response.files.forEach(file => {
                                addUploadedFileItem(file);
                            });

                            showNotification(`${response.files.length} archivos subidos correctamente`, 'success');
                        } else if (response.media) {
                            addUploadedFileItem(response.media);
                            showNotification('Archivo subido correctamente', 'success');
                        }

                        // Volver a cargar la biblioteca después de un breve retraso
                        setTimeout(() => {
                            showTab('library');
                            loadMedia(1);
                        }, 1500);
                    } else {
                        progressStatus.textContent = 'Error al subir: ' + (response.message || 'Respuesta inválida');
                        showNotification('Error: ' + (response.message || 'Ocurrió un error al subir los archivos'), 'error');
                    }
                } catch (e) {
                    progressStatus.textContent = 'Error procesando respuesta del servidor';
                    showNotification('Error procesando respuesta del servidor', 'error');
                    console.error('Error parsing upload response:', e, xhr.responseText);
                }
            } else {
                progressStatus.textContent = `Error de subida: ${xhr.status} ${xhr.statusText}`;
                showNotification(`Error de subida: ${xhr.status} ${xhr.statusText}`, 'error');
            }
        };

        // Error de red
        xhr.onerror = function() {
            progressStatus.textContent = 'Error de red durante la subida';
            showNotification('Error de red durante la subida', 'error');
        };

        // Enviar petición
        xhr.send(formData);
    }

    // Función para añadir un elemento de archivo subido
    function addUploadedFileItem(file) {
        if (!uploadedFilesContainer) return;

        const div = document.createElement('div');
        div.className = 'md-uploaded-item';

        const isImage = file.mime_type && file.mime_type.startsWith('image/');
        const thumbnailUrl = file.thumbnail_url || file.url;

        div.innerHTML = `
            <div class="md-uploaded-thumbnail">
                ${isImage ?
                    `<img src="${escapeHtml(thumbnailUrl)}" alt="${escapeHtml(file.filename)}">` :
                    `<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>`
                }
            </div>
            <div class="md-uploaded-filename">${escapeHtml(file.filename)}</div>
        `;

        uploadedFilesContainer.appendChild(div);
    }

    // Función para mostrar una pestaña
    function showTab(tabId) {
        // Desactivar todas las pestañas
        tabs.forEach(tab => {
            tab.classList.remove('active');
        });

        // Ocultar todos los contenidos
        tabContents.forEach(content => {
            content.classList.remove('active');
        });

        // Activar la pestaña seleccionada
        const selectedTab = Array.from(tabs).find(tab => tab.dataset.tab === tabId);
        if (selectedTab) {
            selectedTab.classList.add('active');
        }

        // Mostrar el contenido seleccionado
        const selectedContent = document.getElementById(tabId + '-tab');
        if (selectedContent) {
            selectedContent.classList.add('active');
        }

        // Si es la pestaña de subida, restablecer
        if (tabId === 'upload') {
            // Ocultar resultados anteriores
            uploadResults.style.display = 'none';
            progressContainer.style.display = 'none';
            fileInput.value = '';
        }
    }

    // ========================================================
    // SECTION 5: UTILIDADES
    // ========================================================

    // Función para escapar HTML
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Función para mostrar notificaciones
    function showNotification(message, type = 'info') {
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `md-notification md-${type}`;
        notification.innerHTML = `
            <div class="md-notification-content">
                <span>${message}</span>
                <button class="md-notification-close">&times;</button>
            </div>
        `;

        // Añadir al DOM
        document.body.appendChild(notification);

        // Mostrar con animación
        setTimeout(() => {
            notification.classList.add('md-show');
        }, 10);

        // Configurar cierre automático
        setTimeout(() => {
            notification.classList.remove('md-show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);

        // Evento de cierre manual
        const closeBtn = notification.querySelector('.md-notification-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                notification.classList.remove('md-show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            });
        }
    }

    // ========================================================
    // SECTION 6: EVENTOS
    // ========================================================

    // Evento para cambio de pestañas
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            if (tabId) {
                showTab(tabId);
            }
        });
    });

    // Eventos para cerrar el modal
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMediaManager);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeMediaManager);
    }

    // Evento para la búsqueda
    if (searchButton && searchInput) {
        searchButton.addEventListener('click', function() {
            loadMedia(1, searchInput.value, filterType);
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadMedia(1, this.value, filterType);
            }
        });
    }

    // Evento para el filtro
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            loadMedia(1, searchTerm, this.value);
        });
    }

    // Evento para el selector de disco
    if (diskSelector) {
        diskSelector.addEventListener('change', function() {
            currentDisk = this.value;
            // Actualizar config global
            if (window.MediaManagerConfig) {
                window.MediaManagerConfig.currentDisk = currentDisk;
            }
            // Resetear carpeta al cambiar de disco
            currentFolderId = null;
            currentFolderName = 'Raíz';
            updateCurrentFolderDisplay();
            // Recargar carpetas y medios para el nuevo disco
            loadFolders();
            loadMedia(1, searchTerm, filterType);
        });
    }

    // Eventos para subida de archivos
    if (browseButton && fileInput) {
        browseButton.addEventListener('click', function() {
            fileInput.click();
        });

        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                handleFilesUpload(this.files);
            }
        });
    }

    // Configuración de drag and drop
    if (dropzone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, function() {
                dropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function() {
                dropzone.classList.remove('dragover');
            }, false);
        });

        dropzone.addEventListener('drop', function(e) {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                handleFilesUpload(e.dataTransfer.files);
            }
        }, false);
    }

    // Evento para seleccionar un medio
    if (selectButton) {
        selectButton.addEventListener('click', function() {
            if (selectedMediaData && targetInputElement) {
                // Actualizar campo de entrada
                targetInputElement.value = selectedMediaData.url;

                // Actualizar vista previa si existe
                if (targetPreviewElement) {
                    targetPreviewElement.src = selectedMediaData.url;
                }

                // Cerrar modal
                closeMediaManager();
            }
        });
    }

    // Evitar que el clic dentro del modal cierre el modal
    modalOverlay.querySelector('.md-modal').addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Cerrar modal al hacer clic en el overlay
    modalOverlay.addEventListener('click', closeMediaManager);

    // ========================================================
    // SECTION 7: INICIALIZACIÓN Y EXPOSICIÓN GLOBAL
    // ========================================================

    // Añadir estilos de notificación si no existen
    if (!document.getElementById('md-notification-styles')) {
        const notificationStyles = document.createElement('style');
        notificationStyles.id = 'md-notification-styles';
        notificationStyles.textContent = `
        .md-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            z-index: 99999;
            max-width: 350px;
        }

        .md-notification.md-show {
            transform: translateX(0);
        }

        .md-notification-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background-color: white;
            color: var(--md-text);
        }

        .md-notification.md-success .md-notification-content {
            border-left: 4px solid #4caf50;
        }

        .md-notification.md-error .md-notification-content {
            border-left: 4px solid #f44336;
        }

        .md-notification.md-info .md-notification-content {
            border-left: 4px solid #2196f3;
        }

        .md-notification-close {
            background: none;
            border: none;
            font-size: 18px;
            color: var(--md-text-light);
            cursor: pointer;
            margin-left: 10px;
        }
        `;
        document.head.appendChild(notificationStyles);
    }

    // Exponer función para abrir el modal desde botones
    document.body.addEventListener('click', function(e) {
        const button = e.target.closest('.open-media-modal-button');
        if (button) {
            e.preventDefault();

            // Obtener selectores de destino
            const inputTarget = button.dataset.inputTarget;
            const previewTarget = button.dataset.previewTarget;

            // Abrir modal
            openMediaManager(inputTarget, previewTarget);
        }
    });

    // Exponer función globalmente
    window.openMediaManager = function(options = {}) {
        // Configurar objetivos
        const inputSelector = options.inputSelector || null;
        const previewSelector = options.previewSelector || null;

        openMediaManager(inputSelector, previewSelector);
    };
});
</script>
@endif
