@extends('layouts.app')

@section('title', $title) {{-- $title viene del controlador: 'Crear Página' o 'Editar Página' --}}

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- El action y method cambian si es editar --}}
    <form method="POST" action="{{ isset($Page) && $Page->id ? route('pages.update', ['id' => $Page->id]) : route('pages.store') }}" id="pageForm" enctype="multipart/form-data">
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
                  {{-- Usamos getPrefix() del modelo si existe, si no 'p'. $baseUrl viene del controlador --}}
                  URL: {{ $baseUrl ?? ($_SERVER['HTTP_HOST'] ?? 'localhost') }}/{{ $Page->getPrefix() ?? 'p' }}/<span id="slug-preview">{{ old('slug', $Page->slug ?? '') }}</span>
                   {{-- Podrías añadir un campo para el prefijo si fuera editable --}}
                   {{-- <input type="hidden" name="prefix" value="{{ $Page->getPrefix() ?? 'p' }}"> --}}
                </small>
              </div>

              {{-- Textarea para TinyMCE --}}
              <div class="mb-3">
                <label for="content-editor" class="form-label">{{ __('pages.content') }}</label>
                <textarea name="content" id="content-editor" class="form-control"
                  style="visibility: hidden; height: 600px;">{{ old('content', $Page->content ?? '') }}</textarea>
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
              @php $currentLocale = old('base_locale', $Page->base_locale ?? config('app.locale', 'es')); @endphp
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
                      <select class="form-select" id="page_template_select" name="page_template">
                          @foreach($availableTemplates as $filename => $displayName)
                              <option value="{{ $filename }}"
                                      @selected(old('page_template', $currentPageTemplate ?? 'page.blade.php') === $filename)>
                                  {{ $displayName }}
                              </option>
                          @endforeach
                      </select>
                      <small class="text-muted">{{ __('pages.template_help') }}</small>
                  </div>
              </div>
          </div>
          {{-- === FIN Card Plantilla === --}}

          {{-- Card Cancelar --}}
           <div class="card">
             <div class="card-body text-center">
               <a href="{{ route('pages.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('common.cancel') }}</a>
             </div>
           </div>
        </div> {{-- Fin .col-md-3 --}}
      </div> {{-- Fin .row --}}
    </form>
  </div> {{-- Fin .container-fluid --}}
</div> {{-- Fin .app-content --}}

{{-- Incluir el script de TinyMCE DESPUÉS del textarea --}}
@include('partials._tinymce') {{-- Asegúrate que la ruta a tu partial TinyMCE es correcta --}}

@endsection

{{-- Scripts específicos de la página --}}
@push('scripts')
{{-- Incluimos el partial con el JavaScript existente --}}
@include('partials._page_scripts', ['isEdit' => isset($Page) && $Page->id]) {{-- Pasamos 'isEdit' al script --}}
@endpush