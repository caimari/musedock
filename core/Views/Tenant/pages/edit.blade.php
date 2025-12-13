@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <!-- Navegación y botón añadir página -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb">
        <a href="{{ route('pages.index') }}">{{ __('pages.pages') }}</a> <span class="mx-2">/</span> <span>{{ e($Page->title ?? __('pages.editing')) }}</span>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ admin_url('pages') }}/{{ $Page->id }}/revisions" class="btn btn-sm btn-outline-secondary" title="{{ __('pages.view_revisions') }}">
          <i class="bi bi-clock-history me-1"></i> {{ __('pages.revisions') }} @if(isset($Page->revision_count) && $Page->revision_count > 0)({{ $Page->revision_count }})@endif
        </a>
        <a href="{{ admin_url('pages') }}/trash" class="btn btn-sm btn-outline-danger" title="{{ __('pages.view_trash') }}">
          <i class="bi bi-trash me-1"></i> {{ __('pages.trash') }}
        </a>
        <a href="{{ route('pages.create') }}" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i> {{ __('pages.add_page') }}</a>
      </div>
    </div>

    {{-- Script para SweetAlert2 (MANTENER ESTO) --}}
    {{-- 🔒 SECURITY: JSON encoding con flags de escape para prevenir XSS --}}
    @if (session('success'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: 'Correcto', text: <?php echo json_encode(session('success'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, confirmButtonColor: '#3085d6' }); }); </script>
    @endif
    @if (session('error'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: 'Error', text: <?php echo json_encode(session('error'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, confirmButtonColor: '#d33' }); }); </script>
    @endif
    {{-- Fin Scripts SweetAlert2 --}}

    <form method="POST" action="{{ route('pages.update', ['id' => $Page->id]) }}" id="pageForm" enctype="multipart/form-data">
      @method('PUT') {!! csrf_field() !!}
      <div class="row">
        {{-- Columna izquierda (Principal) --}}
        <div class="col-md-9">
          {{-- Card Contenido Principal --}}
          <div class="card mb-4"> 
            <div class="card-body"> {{-- Título, Slug, Editor TinyMCE ... --}}
              {{-- Título --}} 
              <div class="mb-3">
                <input type="text" class="form-control form-control-lg" name="title" id="title-input" value="{{ old('title', $Page->title) }}" placeholder="{{ __('pages.enter_title') }}" required>
              </div>
              {{-- Slug --}}
              <div class="mb-3">
                <label class="form-label mb-1">{{ __('pages.slug') }}</label> 
                <div class="input-group"> 
                  <input type="text" class="form-control" name="slug" id="slug-input" value="{{ old('slug', $Page->slug) }}" required readonly> 
                  <button type="button" class="btn btn-outline-secondary" id="toggle-slug-edit" title="{{ __('pages.edit_slug') }}">
                    <i class="bi bi-lock"></i>
                  </button> 
                </div> 
                <small class="text-muted mt-1 d-inline-block"> 
                  URL: <a href="{{ $Page->getPublicUrl() }}" target="_blank">{{ $Page->getPublicUrl() }}</a> 
                </small> 
                <span id="slug-check-result" class="ms-3 fw-bold"></span> 
              </div>
              {{-- Editor TinyMCE --}}
              <div class="mb-3" id="editor-wrapper">
                {{-- Skeleton Loader - se muestra mientras TinyMCE carga --}}
                <div id="tinymce-skeleton" class="tinymce-skeleton">
                  <div class="tinymce-skeleton-toolbar">
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-separator"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-separator"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-separator"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-btn"></div>
                    <div class="tinymce-skeleton-btn"></div>
                  </div>
                  <div class="tinymce-skeleton-content">
                    <div class="tinymce-skeleton-line"></div>
                    <div class="tinymce-skeleton-line"></div>
                    <div class="tinymce-skeleton-line"></div>
                    <div class="tinymce-skeleton-line"></div>
                    <div class="tinymce-skeleton-line"></div>
                    <div class="tinymce-skeleton-line"></div>
                  </div>
                </div>
                <textarea id="content-editor" name="content" style="display:none !important;">{{ old('content', $Page->content) }}</textarea>
              </div>
            </div> 
          </div>

          {{-- Card Traducciones --}}
          <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong>{{ __('pages.translations') }}</strong>
              <span class="badge bg-secondary">{{ __('pages.base_language') }}: <span id="base-locale-name">{{ getAvailableLocales()[$Page->base_locale] ?? e($Page->base_locale) }}</span></span>
            </div> 
            <div class="card-body"> 
              <div class="d-flex flex-wrap gap-2" id="translations-container"> 
                @foreach ($locales as $code => $name) 
                  @if($code !== ($Page->base_locale ?? config('app.locale', 'es'))) 
                    <a href="{{ route('pages.translation.edit', ['id' => $Page->id, 'locale' => $code]) }}" class="btn btn-sm translation-btn {{ isset($translatedLocales[$code]) ? 'btn-outline-success' : 'btn-outline-secondary' }}" data-locale="{{ $code }}"> 
                      {{ $name }} 
                      @if (isset($translatedLocales[$code])) 
                        <i class="ms-1 fas fa-check-circle text-success"></i> 
                      @endif 
                    </a> 
                  @endif 
                @endforeach 
              </div> 
            </div> 
          </div>

          {{-- Card SEO --}}
          @include('partials._seo_fields', ['Page' => $Page])

        </div> {{-- Fin .col-md-9 --}}

        {{-- Sidebar derecha --}}
        <div class="col-md-3">
          {{-- Card Publicar --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('pages.publish') }}</strong></div>
            <div class="card-body">
              {{-- Estado --}}
              <div class="mb-3">
                <label class="form-label">{{ __('pages.status') }}</label>
                <select class="form-select" name="status" id="status-select">
                  <option value="draft" @selected(old('status', $Page->status) === 'draft')>{{ __('pages.draft') }}</option>
                  <option value="published" @selected(old('status', $Page->status) === 'published')>{{ __('pages.published') }}</option>
                </select>
              </div>

              {{-- Visibilidad (NUEVO) --}}
              <div class="mb-3">
                <label class="form-label">{{ __('pages.visibility') }}</label>
                <select class="form-select" name="visibility" id="visibility-select">
                  <option value="public" @selected(old('visibility', $Page->visibility ?? 'public') === 'public')>{{ __('pages.visibility_public') }}</option>
                  <option value="private" @selected(old('visibility', $Page->visibility ?? 'public') === 'private')>{{ __('pages.visibility_private') }}</option>
                  <option value="members" @selected(old('visibility', $Page->visibility ?? 'public') === 'members')>{{ __('pages.visibility_members') }}</option>
                </select>
                <small class="text-muted">{{ __('pages.visibility_help') }}</small>
              </div>

              {{-- Fecha Pub --}}
              <div class="mb-3">
                <label class="form-label">{{ __('pages.publish_date') }}</label>
                <input type="datetime-local" class="form-control" name="published_at" id="published_at"
                       value="{{ old('published_at', $Page->published_at ? ($Page->published_at instanceof \DateTimeInterface ? $Page->published_at->format('Y-m-d\TH:i') : '') : '') }}">
                <small class="text-muted">{{ __('pages.publish_immediately_help') }}</small>
              </div>

              {{-- Idioma Base --}}
              <div class="mb-3">
                <label class="form-label">{{ __('pages.base_language') }}</label>
                <select class="form-select" name="base_locale" id="base-locale-select">
                  @php $currentLocale = old('base_locale', $Page->base_locale); @endphp
                  @foreach (getAvailableLocales() as $code => $label)
                    <option value="{{ $code }}" @selected($currentLocale === $code)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                  {{ isset($Page) && $Page->id ? __('common.update') : __('pages.publish') }}
                </button>
              </div>
            </div>
          </div>

          {{-- === CHECKBOX PÁGINA DE INICIO (Verificar esta línea) === --}}
          <hr>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_homepage_checkbox" name="is_homepage" role="switch"
                   {{-- Asegúrate que $Page->is_homepage sea BOOLEAN(true) para que @checked funcione --}}
                   @checked(old('is_homepage', $Page->is_homepage ?? false))
                   @disabled($Page->status !== 'published')>
            <label class="form-check-label" for="is_homepage_checkbox">
              {{ __('pages.set_as_homepage') }}
            </label>
             @if($Page->status !== 'published')
               <small class="d-block text-muted mt-1">{{ __('pages.only_published_pages') }}</small>
             @endif
          </div>
          {{-- ======================================================= --}}

          <input type="hidden" name="prefix" value="{{ $Page->getPrefix() ?? 'p' }}">
          
   

          {{-- === NUEVO: INCLUIR CARD DE OPCIONES DE CABECERA === --}}
          @include('partials._header_options', ['Page' => $Page])
          {{-- ======================================================= --}}

          {{-- Card Info Fechas --}}
          <div class="card mb-4">
            <div class="card-body p-2">
              {{-- === USAR LAS VARIABLES FORMATEADAS DESDE EL CONTROLADOR === --}}
              <small class="text-muted d-block mb-1">
                <strong>{{ __('pages.created') }}:</strong>
                {{-- Estas variables las prepara el controlador edit() --}}
                {{ $Page->created_at_formatted ?? __('common.unknown') }}
              </small>
              <small class="text-muted d-block">
                <strong>{{ __('pages.last_updated') }}:</strong>
                {{ $Page->updated_at_formatted ?? __('common.unknown') }}
              </small>
              {{-- ==================================================== --}}
            </div>
          </div> {{-- Fin Card Info Fechas --}}
			
          {{-- === Selección de plantilla === --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('pages.page_template') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="page_template_select" class="form-label">{{ __('pages.template') }}</label>
                <select class="form-select" id="page_template_select" name="page_template">
                  @foreach ($availableTemplates as $filename => $displayName)
                    <option value="{{ $filename }}" @if(old('page_template', $currentPageTemplate) === $filename) selected @endif>
                      {{ $displayName }}
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">{{ __('pages.template_help_edit') }}</small>
              </div>
            </div>
          </div>
          {{-- === FIN Selección de plantilla === --}}

          {{-- Card Eliminar --}}
          <div class="card mb-4"> 
            <div class="card-body text-center">
              <a href="javascript:void(0);" onclick="confirmDelete({{ $Page->id }})" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-trash me-1"></i> {{ __('pages.delete_page') }}
              </a>
            </div> 
          </div>

        </div> {{-- Fin .col-md-3 --}}
      </div> {{-- Fin .row --}}
    </form>


    {{-- Script JS traducciones --}}
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        // --- Elementos del DOM ---
        const baseLocaleSelect = document.getElementById('base-locale-select');
        const baseLocaleNameSpan = document.getElementById('base-locale-name'); // El <span> dentro del badge
        const translationsContainer = document.getElementById('translations-container');
        const pageId = "{{ $Page->id }}"; // ID de la página actual
        const isHomepageCheckbox = document.getElementById('is_homepage_checkbox');
        const isCurrentlyHomepage = {{ $Page->is_homepage ? 'true' : 'false' }};

        // --- Datos desde PHP ---
        // 🔒 SECURITY: JSON encoding con flags de escape para prevenir XSS
        const allLocales = <?php echo json_encode(getAvailableLocales() ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const translatedLocales = <?php echo json_encode($translatedLocales ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // --- Función para actualizar la UI ---
        function updateTranslationsOptions(newBaseLocale) {
          // Salir si falta algún elemento esencial
          if (!baseLocaleNameSpan || !translationsContainer || !allLocales) {
              console.error("Error: Elementos necesarios para actualizar traducciones no encontrados.");
              return;
          }

          // 1. Actualizar el nombre del idioma base en el badge
          baseLocaleNameSpan.textContent = allLocales[newBaseLocale] || newBaseLocale.toUpperCase();

          // 2. Limpiar los botones de traducción actuales
          translationsContainer.innerHTML = '';

          // 3. Regenerar los botones para los idiomas que NO son el nuevo base
          Object.keys(allLocales).forEach(code => {
            if (code !== newBaseLocale) {
              const name = allLocales[code]; // Nombre del idioma
              const isTranslated = translatedLocales.hasOwnProperty(code) && translatedLocales[code]; // Verifica si existe traducción

              const btn = document.createElement('a');

              // Construir la URL dinámicamente usando la función route() de JS si la tienes, o manualmente
              // Nota: route('pages.translation.edit', ...) se ejecuta en PHP, no aquí. Necesitamos la URL base.
              // Asumiremos una estructura de URL fija o necesitaremos pasar la URL base desde PHP.
              const translationUrl = `{{ admin_url('pages') }}/${pageId}/translations/${code}`; // AJUSTA ESTA URL si es diferente

              btn.href = translationUrl;
              btn.className = `btn btn-sm translation-btn ${isTranslated ? 'btn-outline-success' : 'btn-outline-secondary'}`;
              btn.dataset.locale = code;
              btn.textContent = name; // Establecer el nombre del idioma

              if (isTranslated) {
                // Añadir el icono de check si ya está traducido
                const icon = document.createElement('i');
                icon.className = 'ms-1 fas fa-check-circle text-success';
                btn.appendChild(icon); // Añadir el icono después del texto
              }

              translationsContainer.appendChild(btn); // Añadir el botón al contenedor
            }
          });
        }

        // --- Añadir el Event Listener ---
        if (baseLocaleSelect) {
            // console.log("Añadiendo listener a baseLocaleSelect"); // Debug
            baseLocaleSelect.addEventListener('change', function(event) {
                // console.log("Idioma base cambiado a:", event.target.value); // Debug
                updateTranslationsOptions(event.target.value); // Llama a la función con el nuevo código de idioma
            });
        } else {
            console.error("Error: Select #base-locale-select no encontrado.");
        }

        // --- (Opcional) Llamada inicial por si los datos PHP no coinciden al cargar ---
        // Si sospechas que el estado inicial podría estar mal, puedes descomentar esto:
        // if (baseLocaleSelect) {
        //     updateTranslationsOptions(baseLocaleSelect.value);
        // }

        // --- Debug: Verificar datos iniciales ---
        // console.log("Página ID:", pageId);
        // console.log("Todos los locales:", allLocales);
        // console.log("Locales traducidos:", translatedLocales);

        // === MODAL DE CONFIRMACIÓN PARA PÁGINA DE INICIO ===
        if (isHomepageCheckbox) {
          // Guardar el estado original para detectar cambios
          let initialHomepageState = isCurrentlyHomepage;

          isHomepageCheckbox.addEventListener('change', function(e) {
            const isNowChecked = this.checked;
            const isChangingState = initialHomepageState !== isNowChecked;

            if (!isChangingState) {
              // Si no hay cambio real, permitir sin confirmar
              return;
            }

            e.preventDefault();

            if (isNowChecked && !isCurrentlyHomepage) {
              // Intentando marcar como página de inicio (pero no lo es actualmente)
              Swal.fire({
                title: '{{ __('pages.set_homepage_title') }}',
                html: '{{ __('pages.set_homepage_message') }}',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '{{ __('common.yes_set') }}',
                cancelButtonText: '{{ __('common.cancel') }}'
              }).then((result) => {
                if (result.isConfirmed) {
                  isHomepageCheckbox.checked = true;
                  initialHomepageState = true;
                } else {
                  isHomepageCheckbox.checked = false;
                }
              });
            } else if (!isNowChecked && isCurrentlyHomepage) {
              // Intentando desmarcar como página de inicio
              Swal.fire({
                title: '{{ __('pages.unset_homepage_title') }}',
                html: '{{ __('pages.unset_homepage_message') }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '{{ __('common.yes_unset') }}',
                cancelButtonText: '{{ __('common.cancel') }}'
              }).then((result) => {
                if (result.isConfirmed) {
                  isHomepageCheckbox.checked = false;
                  initialHomepageState = false;
                } else {
                  isHomepageCheckbox.checked = true;
                }
              });
            }
          });
        }
        // ===================================================

      }); // Fin DOMContentLoaded
    </script>

  </div> {{-- Fin .container-fluid --}}
</div> {{-- Fin .app-content --}}

{{-- Scripts Apilados --}}
@push('scripts')
  @include('partials._page_scripts', ['isEdit' => true])
@endpush

{{-- TinyMCE --}}
@include('partials._tinymce')

@endsection