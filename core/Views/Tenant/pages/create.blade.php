@extends('layouts.app')

@section('title', $title) {{-- $title viene del controlador: 'Crear Página' o 'Editar Página' --}}

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- El action y method cambian si es editar --}}
    <form method="POST" action="{{ isset($Page) && $Page->id ? route('tenant.pages.update', ['id' => $Page->id]) : route('tenant.pages.store') }}" id="pageForm" enctype="multipart/form-data">
      {!! csrf_field() !!}
      {{-- Añadir campo _method para PUT en la edición --}}
      @if(isset($Page) && $Page->id)
        @method('PUT')
      @endif

      <div class="row">
        {{-- Columna izquierda --}}
        <div class="col-md-9">
          {{-- Card Contenido Principal --}}
          <div class="card mb-4">
            <div class="card-body">
              {{-- Input Título --}}
              <div class="mb-3">
                 {{-- La variable $Page viene del controlador --}}
                <input type="text" class="form-control form-control-lg" name="title" id="title-input"
                  value="{{ old('title', $Page->title ?? '') }}"
                  placeholder="{{ __('pages.enter_title') }}" required>
              </div>

              {{-- Input Slug --}}
              <div class="mb-3">
                <label class="form-label">{{ __('pages.slug') }}
                    {{-- El span para el resultado del check AJAX --}}
                    <span id="slug-check-result" class="ms-2 {{ old('slug_error') ? 'text-danger' : 'text-success' }} fw-bold"></span>
                    {{-- Icono de info para páginas legales (siempre visible) --}}
                    <a href="#" onclick="showLegalPagesInfo(); return false;"
                       class="ms-2 text-muted" style="font-size: 13px; text-decoration:none;"
                       title="{{ __('pages.legal_pages_info_title') }}">
                        <i class="bi bi-info-circle-fill"></i>
                        <span style="font-size:12px; font-weight:normal;">{{ __('pages.legal_pages_label') }}</span>
                    </a>
                </label>
                <div class="input-group"> {{-- Grupo para añadir botón de bloqueo/desbloqueo si es edición --}}
                    <input type="text" class="form-control" name="slug" id="slug-input"
                      value="{{ old('slug', $Page->slug ?? '') }}" required {{ isset($Page) && $Page->id ? 'readonly' : '' }}>
                    {{-- Botón para desbloquear edición de slug (solo en modo edición) --}}
                    @if(isset($Page) && $Page->id)
                    <button class="btn btn-outline-secondary" type="button" id="toggle-slug-edit" title="{{ __('pages.edit_slug') }}">
                        <i class="bi bi-lock"></i> {{-- Icono de candado (puedes usar FontAwesome, etc.) --}}
                    </button>
                    @endif
                </div>
                <small class="text-muted">
                  @php
                      $__prefix = $Page->getPrefix() ?? 'p';
                      $__host = $baseUrl ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
                      $__slugBase = $__prefix !== '' ? "{$__host}/{$__prefix}" : $__host;
                  @endphp
                  URL: {{ $__slugBase }}/<span id="slug-preview">{{ old('slug', $Page->slug ?? '') }}</span>
                </small>

                {{-- Aviso legal slug --}}
                <div id="legal-slug-notice" class="alert alert-info d-flex align-items-start gap-2 mt-2 mb-0 py-2 px-3" style="display:none !important; font-size: 13px;">
                    <i class="bi bi-shield-check fs-5 mt-1 flex-shrink-0"></i>
                    <div>
                        <strong>{{ __('pages.legal_notice_detected_title') }}</strong>
                        {{ __('pages.legal_notice_detected_text') }}
                        <a href="#" onclick="showLegalPagesInfo(); return false;" class="alert-link ms-1">{{ __('pages.legal_notice_more_info') }}</a>
                    </div>
                </div>
              </div>

              {{-- Editor TinyMCE --}}
              <div class="mb-3" id="editor-wrapper">
                <label for="content-editor" class="form-label">{{ __('pages.content') }}</label>
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
                <textarea name="content" id="content-editor" style="display:none !important;">{{ old('content', $Page->content ?? '') }}</textarea>
              </div>
            </div>
          </div> {{-- Fin Card Contenido Principal --}}

          {{-- === INCLUIR EL PARCIAL DE SEO === --}}
          @include('partials._seo_fields', ['Page' => $Page])
          {{-- Pasamos la variable $Page explícitamente por claridad, aunque ya estaría en el scope --}}
          {{-- =================================== --}}

        </div> {{-- Fin .col-md-9 --}}

        {{-- Columna derecha --}}
        <div class="col-md-3">
         {{-- Card Publicar --}}
<div class="card mb-4">
   <div class="card-header"><strong>{{ __('pages.publish') }}</strong></div>
   <div class="card-body">
      {{-- Estado --}}
      <div class="mb-3">
        <label class="form-label">{{ __('pages.status') }}</label>
        <select class="form-select" name="status">
          {{-- Valor por defecto 'published' si es nueva página --}}
          <option value="draft" @selected(old('status', $Page->status ?? 'published') === 'draft')>{{ __('pages.draft') }}</option>
          <option value="published" @selected(old('status', $Page->status ?? 'published') === 'published')>{{ __('pages.published') }}</option>
        </select>
      </div>

      {{-- Visibilidad --}}
      <div class="mb-3">
        <label class="form-label">{{ __('pages.visibility') }}</label>
        <select class="form-select" name="visibility">
          <option value="public" @selected(old('visibility', $Page->visibility ?? 'public') === 'public')>{{ __('pages.visibility_public') }}</option>
          <option value="private" @selected(old('visibility', $Page->visibility ?? 'public') === 'private')>{{ __('pages.visibility_private') }}</option>
          <option value="members" @selected(old('visibility', $Page->visibility ?? 'public') === 'members')>{{ __('pages.visibility_members') }}</option>
        </select>
        <small class="text-muted">{{ __('pages.visibility_help') }}</small>
      </div>

      {{-- Fecha de Publicación --}}
      <div class="mb-3">
          <label for="published_at" class="form-label">{{ __('pages.publish_on') }}</label>
          <input type="datetime-local" class="form-control" id="published_at" name="published_at"
                 value="{{ old('published_at', $Page->published_at ? \Carbon\Carbon::parse($Page->published_at)->format('Y-m-d\TH:i') : '') }}">
          <small class="text-muted">{{ __('pages.publish_immediately_help') }}</small>
      </div>

      {{-- Mostrar fechas si estamos editando --}}
      @if(isset($Page) && $Page->id)
          <div class="mb-2">
              <small class="text-muted" title="{{ $Page->created_at }}">{{ __('pages.created') }}: {{ $Page->getFormattedDate('created_at') }}</small>
          </div>
          <div class="mb-3">
              <small class="text-muted" title="{{ $Page->updated_at }}">{{ __('pages.last_updated') }}: {{ $Page->getFormattedDate('updated_at') }}</small>
          </div>
      @endif

      {{-- Idioma Base --}}
      <div class="mb-3">
          <label for="base_locale" class="form-label">{{ __('pages.base_language') }}</label>
          <select class="form-select" id="base-locale-select" name="base_locale">
              @php $currentLocale = old('base_locale', $Page->base_locale ?? tenant_setting('default_lang', 'es')); @endphp
              @foreach (getAvailableLocales() as $code => $name)
                  <option value="{{ $code }}" @selected($currentLocale == $code)>{{ $name }}</option>
              @endforeach
          </select>
      </div>

      <div class="d-grid">
         <button type="submit" class="btn btn-primary">
            {{ isset($Page) && $Page->id ? __('common.update') : __('pages.publish') }}
         </button>
      </div>
   </div>
</div> {{-- Fin Card Publicar --}}
          
          {{-- === NUEVO: INCLUIR EL PARCIAL DE OPCIONES DE CABECERA === --}}
          @include('partials._header_options', ['Page' => $Page])
          {{-- ======================================================= --}}

          {{-- === NUEVO: Card Plantilla === --}}
          <div class="card mb-4">
              <div class="card-header"><strong>{{ __('pages.page_template') }}</strong></div>
              <div class="card-body">
                  <div class="mb-3">
                      <label for="page_template_select" class="form-label">{{ __('pages.template') }}</label>
                      @php $__isSidebarStruct = function_exists('is_sidebar_structure') && is_sidebar_structure(); @endphp
                      <select class="form-select" id="page_template_select" name="page_template" {{ $__isSidebarStruct ? 'disabled' : '' }}>
                          @foreach($availableTemplates as $filename => $displayName)
                              <option value="{{ $filename }}"
                                      @selected(old('page_template', $currentPageTemplate ?? 'page.blade.php') === $filename)>
                                  {{ $displayName }}
                              </option>
                          @endforeach
                      </select>
                @if($__isSidebarStruct)
                <input type="hidden" name="page_template" value="page.blade.php">
                <div class="alert alert-info py-1 px-2 mt-2 mb-0" style="font-size:0.75rem;">
                          <i class="bi bi-layout-sidebar me-1"></i> {!! __('pages.sidebar_structure_template_notice') !!}
                      </div>
                      @else
                      <small class="text-muted">{{ __('pages.template_help') }}</small>
                      @endif
                  </div>
              </div>
          </div>
          {{-- === FIN Card Plantilla === --}}

          {{-- Card Cancelar --}}
           <div class="card">
             <div class="card-body text-center">
               <a href="{{ route('tenant.pages.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('common.cancel') }}</a>
             </div>
           </div>
        </div> {{-- Fin .col-md-3 --}}
      </div> {{-- Fin .row --}}
    </form>
  </div> {{-- Fin .container-fluid --}}
</div> {{-- Fin .app-content --}}

{{-- Incluir el script de TinyMCE DESPUÉS del textarea --}}
@include('partials._tinymce') {{-- Asegúrate que la ruta a tu partial TinyMCE es correcta --}}

@if(function_exists('aiimage_is_active') && aiimage_is_active())
<script src="/assets/modules/aiimage/js/ai-image-generator.js"></script>
@endif

@endsection

{{-- Scripts específicos de la página --}}
@push('scripts')
{{-- Incluimos el partial con el JavaScript existente --}}
@include('partials._page_scripts', ['isEdit' => isset($Page) && $Page->id]) {{-- Pasamos 'isEdit' al script --}}

<script>
const _legalPagesHtml = {!! json_encode(__('pages.legal_pages_modal_html')) !!};
const _legalPagesTitle = {!! json_encode(__('pages.legal_pages_modal_title')) !!};
const _legalPagesBtn   = {!! json_encode(__('pages.legal_pages_modal_button')) !!};

function showLegalPagesInfo() {
    Swal.fire({
        title: _legalPagesTitle,
        html: _legalPagesHtml,
        icon: 'info',
        confirmButtonText: _legalPagesBtn,
        confirmButtonColor: '#4f46e5',
        width: 560,
        customClass: { htmlContainer: 'text-start' }
    });
}

(function () {
    // Slugs que activan el aviso legal
    const legalSlugs = [
        'aviso-legal', 'legal', 'aviso_legal',
        'privacy', 'privacidad', 'politica-de-privacidad', 'politica-privacidad',
        'cookie-policy', 'cookies', 'politica-de-cookies', 'politica-cookies',
        'terms-and-conditions', 'terminos', 'terms', 'terminos-y-condiciones',
        'terminos-condiciones', 'condiciones-de-uso'
    ];

    const slugInput = document.getElementById('slug-input');
    const notice    = document.getElementById('legal-slug-notice');

    function checkLegalSlug() {
        const val = (slugInput ? slugInput.value : '').trim().toLowerCase();
        if (legalSlugs.includes(val)) {
            notice.style.setProperty('display', 'flex', 'important');
        } else {
            notice.style.setProperty('display', 'none', 'important');
        }
    }

    if (slugInput && notice) {
        slugInput.addEventListener('input', checkLegalSlug);
        slugInput.addEventListener('change', checkLegalSlug);
        // Comprobar al cargar (modo edición con slug ya rellenado)
        checkLegalSlug();
    }
})();
</script>
@endpush
