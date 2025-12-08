@extends('layouts.app')

{{-- Título dinámico basado en si es creación o edición --}}
@section('title', isset($translation->id) ? "Editar Traducción ({$localeName})" : "Crear Traducción ({$localeName})")

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Breadcrumbs / Navegación --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="breadcrumb">
          <a href="{{ route('pages.index') }}">Páginas</a>
          <span class="mx-2">/</span>
          <a href="{{ route('pages.edit', ['id' => $Page->id]) }}">{{ e($Page->title) }}</a>
          <span class="mx-2">/</span>
          {{-- Usamos el título dinámico --}}
          <span>{{ isset($translation->id) ? "Editar Traducción ({$localeName})" : "Crear Traducción ({$localeName})" }}</span>
        </div>
         <a href="{{ route('pages.edit', ['id' => $Page->id]) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Volver a Página Base
         </a>
    </div>

    {{-- Mensajes de alerta (si usas un sistema de flash messages) --}}
    @include('partials.alerts')

    <form method="POST" action="{{ route('pages.translation.update', ['id' => $Page->id, 'locale' => $locale]) }}" id="translationForm">
        {{-- Método PUT es más semántico para updates, pero POST funciona si tu controlador usa $_POST --}}
        {{-- Si tu router soporta PUT/PATCH y tu controlador usa un Request object, usa @method('PUT') --}}
        {{-- @method('PUT') --}}
        {!! csrf_field() !!}

        {{-- Es buena práctica incluir estos aunque el controlador los fuerce, por claridad --}}
        <input type="hidden" name="page_id" value="{{ $Page->id }}">
        <input type="hidden" name="locale" value="{{ $locale }}">

        <div class="row">
            {{-- Columna Principal (Contenido + SEO) --}}
            <div class="col-md-9">
                {{-- Card Contenido Principal Traducido --}}
                <div class="card mb-4">
                    <div class="card-header">
                        Contenido Principal ({{ $localeName }})
                    </div>
                    <div class="card-body">
                        {{-- Título Traducido --}}
                        <div class="mb-3">
                            <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg @error('title') is-invalid @enderror" id="title" name="title"
                                   value="{{ old('title', $translation->title ?? '') }}" required>
                            {{-- Mostrar error de validación si existe --}}
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Contenido Traducido (TinyMCE) --}}
                        <div class="mb-3">
                            <label for="content-editor" class="form-label">Contenido</label>
                            {{-- Añadir clase is-invalid si hay error en content --}}
                            <textarea id="content-editor" name="content" class="form-control @error('content') is-invalid @enderror" style="visibility: hidden; height: 600px;">{{ old('content', $translation->content ?? '') }}</textarea>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>{{-- Fin card contenido --}}

                {{-- Card SEO Específico del Idioma --}}
                <div class="card mb-4"> {{-- Añadido mb-4 --}}
                    <div class="card-header">
                        Optimización SEO ({{ $localeName }}) <small class="text-muted">- Opcional</small>
                    </div>
                    <div class="card-body">
                         <p class="text-muted small mb-3">Define cómo quieres que esta traducción aparezca en buscadores. Si se dejan vacíos, se podrían usar los valores de la página base.</p>

                         {{-- SEO Title --}}
                         <div class="mb-3">
                           <label for="seo_title" class="form-label">Título SEO</label>
                           <input type="text" class="form-control @error('seo_title') is-invalid @enderror" id="seo_title" name="seo_title" value="{{ old('seo_title', $translation->seo_title ?? '') }}" placeholder="Título para buscadores en {{ $localeName }}">
                            @error('seo_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                         </div>

                         {{-- SEO Description --}}
                         <div class="mb-3">
                           <label for="seo_description" class="form-label">Descripción SEO</label>
                           <textarea class="form-control @error('seo_description') is-invalid @enderror" id="seo_description" name="seo_description" rows="3" placeholder="Descripción corta para buscadores en {{ $localeName }}">{{ old('seo_description', $translation->seo_description ?? '') }}</textarea>
                            @error('seo_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                         </div>

                         {{-- SEO Keywords --}}
                         <div class="mb-3">
                           <label for="seo_keywords" class="form-label">Palabras Clave SEO</label>
                           <input type="text" class="form-control @error('seo_keywords') is-invalid @enderror" id="seo_keywords" name="seo_keywords" value="{{ old('seo_keywords', $translation->seo_keywords ?? '') }}" placeholder="Palabras clave en {{ $localeName }}, separadas por comas">
                           @error('seo_keywords') <div class="invalid-feedback">{{ $message }}</div> @enderror
                         </div>

                         {{-- Canonical URL --}}
                         <div class="mb-3">
                           <label for="canonical_url" class="form-label">URL Canónica</label>
                           <input type="url" class="form-control @error('canonical_url') is-invalid @enderror" id="canonical_url" name="canonical_url" value="{{ old('canonical_url', $translation->canonical_url ?? '') }}" placeholder="URL preferida si es contenido duplicado">
                           <small class="text-muted">Normalmente es la misma para todas las traducciones.</small>
                           @error('canonical_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                         </div>

                         {{-- Robots Directive --}}
                         <div class="mb-3">
                           <label for="robots_directive" class="form-label">Directiva para Robots</label>
                           <select class="form-select @error('robots_directive') is-invalid @enderror" id="robots_directive" name="robots_directive">
                              @php $currentRobots = old('robots_directive', $translation->robots_directive ?? null); @endphp
                              <option value="" @selected($currentRobots === null)>Usar valor de página base o sistema</option>
                              <option value="index,follow" @selected($currentRobots === 'index,follow')>Indexar y Seguir</option>
                              {{-- ... otras opciones ... --}}
                              <option value="noindex,nofollow" @selected($currentRobots === 'noindex,nofollow')>No Indexar y No Seguir</option>
                           </select>
                           <small class="text-muted">Normalmente es la misma para todas las traducciones.</small>
                            @error('robots_directive') <div class="invalid-feedback">{{ $message }}</div> @enderror
                         </div>

                          {{-- SEO Image --}}
                          <div class="mb-3">
                              <label for="seo_image" class="form-label">Imagen SEO / Open Graph</label>
                              <input type="text" class="form-control @error('seo_image') is-invalid @enderror" id="seo_image" name="seo_image" value="{{ old('seo_image', $translation->seo_image ?? '') }}" placeholder="URL de la imagen principal para compartir">
                               <small class="text-muted">Normalmente es la misma para todas las traducciones.</small>
                               @error('seo_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                          </div>

                          <hr>
                          <h6>Twitter Cards ({{ $localeName }}) <small class="text-muted">- Opcional</small></h6>

                           {{-- Twitter Title --}}
                          <div class="mb-3">
                              <label for="twitter_title" class="form-label">Título para Twitter</label>
                              <input type="text" class="form-control @error('twitter_title') is-invalid @enderror" id="twitter_title" name="twitter_title" value="{{ old('twitter_title', $translation->twitter_title ?? '') }}">
                              @error('twitter_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                          </div>

                          {{-- Twitter Description --}}
                          <div class="mb-3">
                              <label for="twitter_description" class="form-label">Descripción para Twitter</label>
                              <textarea class="form-control @error('twitter_description') is-invalid @enderror" id="twitter_description" name="twitter_description" rows="2">{{ old('twitter_description', $translation->twitter_description ?? '') }}</textarea>
                              @error('twitter_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                          </div>

                          {{-- Twitter Image --}}
                          <div class="mb-3">
                              <label for="twitter_image" class="form-label">Imagen para Twitter</label>
                              <input type="text" class="form-control @error('twitter_image') is-invalid @enderror" id="twitter_image" name="twitter_image" value="{{ old('twitter_image', $translation->twitter_image ?? '') }}" placeholder="URL de imagen específica para Twitter">
                              <small class="text-muted">Normalmente es la misma para todas las traducciones.</small>
                              @error('twitter_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                          </div>

                    </div>{{-- Fin card-body SEO --}}
                </div>{{-- Fin card SEO --}}

            </div> {{-- Fin col-md-9 --}}

            {{-- Columna Derecha (Acciones) --}}
            <div class="col-md-3">
                <div class="card sticky-top" style="top: 1rem;">
                     <div class="card-header">
                         Acciones
                     </div>
                     <div class="card-body">
                        <p><span class="badge bg-primary">Idioma: {{ $localeName }}</span></p>
                        <p><small class="text-muted">Página base: {{ e($Page->title) }}</small></p>
                        <div class="d-grid gap-2">
                             <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> {{ isset($translation->id) ? 'Actualizar' : 'Crear' }} Traducción
                             </button>
                             <a href="{{ route('pages.edit', ['id' => $Page->id]) }}" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                             </a>
                         </div>
                        {{-- Mostrar fechas si la traducción existe --}}
                        @if(isset($translation->id))
                         <hr>
                         <small class="text-muted d-block mb-1" title="{{ $translation->created_at }}">
                           <strong>Traducido:</strong>
                           {{ $translation->created_at ? \Carbon\Carbon::parse($translation->created_at)->diffForHumans() : 'N/A' }}
                         </small>
                         <small class="text-muted d-block" title="{{ $translation->updated_at }}">
                           <strong>Actualizado:</strong>
                           {{ $translation->updated_at ? \Carbon\Carbon::parse($translation->updated_at)->diffForHumans() : 'N/A' }}
                         </small>
                        @endif
                     </div>
                 </div>
            </div> {{-- Fin col-md-3 --}}
        </div> {{-- Fin .row --}}
    </form>

  </div> {{-- Fin container-fluid --}}
</div> {{-- Fin app-content --}}

{{-- Incluir el script de TinyMCE DESPUÉS del textarea --}}
@include('partials._tinymce') 

@endsection

{{-- Scripts específicos de la página --}}
@push('scripts')
{{-- Incluimos el partial con el JavaScript existente --}}
@include('partials._page_scripts', ['isEdit' => isset($translation->id)]) {{-- Pasamos 'isEdit' al script --}}
@endpush