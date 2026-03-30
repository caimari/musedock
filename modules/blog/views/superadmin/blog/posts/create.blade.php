@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- Navegación --}}
    @php
        $backUrl = !empty($targetTenant)
            ? route('blog.posts.index') . '?scope=tenant:' . $targetTenant->id
            : route('blog.posts.index');
        $backLabel = !empty($targetTenant)
            ? ($targetTenant->domain ?? $targetTenant->name)
            : __('blog.posts');
    @endphp
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb">
        <a href="{{ route('blog.posts.index') }}">{{ __('blog.posts') }}</a>
        @if(!empty($targetTenant))
            <span class="mx-2">/</span>
            <a href="{{ $backUrl }}">{{ $backLabel }}</a>
        @endif
        <span class="mx-2">/</span>
        <span>{{ __('blog.post.new_post') }}</span>
      </div>
      <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i> {{ $backLabel }}
      </a>
    </div>

    {{-- Script para SweetAlert2 Toast --}}
    @if (session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: {!! json_encode(session('success')) !!},
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
              toast.addEventListener('mouseenter', Swal.stopTimer);
              toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
          });
        });
      </script>
    @endif
    @if (session('error'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'error',
            title: {!! json_encode(session('error')) !!},
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
              toast.addEventListener('mouseenter', Swal.stopTimer);
              toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
          });
        });
      </script>
    @endif

    {{-- Alerta de post para tenant --}}
    @if(!empty($targetTenant))
      <div class="alert alert-info d-flex align-items-center mb-3">
        <i class="bi bi-globe me-2 fs-5"></i>
        <div>
          <strong>Creando post para:</strong> <a href="https://{{ $targetTenant->domain }}" target="_blank">{{ $targetTenant->domain }}</a>
          <small class="text-muted d-block">Las categorías y etiquetas corresponden a este sitio.</small>
        </div>
      </div>
    @endif

    <form method="POST" action="{{ route('blog.posts.store') }}" id="postForm" enctype="multipart/form-data">
      @csrf
      <input type="hidden" id="post-tenant-id" value="{{ $targetTenantId ?? '' }}">
      @if(!empty($targetTenantId))
        <input type="hidden" name="target_tenant_id" value="{{ $targetTenantId }}">
      @endif

      <div class="row">
        {{-- Columna izquierda (Principal) --}}
        <div class="col-md-9">
          {{-- Card Contenido Principal --}}
          <div class="card mb-4">
            <div class="card-body">
              {{-- Título --}}
              <div class="mb-3">
                <input type="text" class="form-control form-control-lg @error('title') is-invalid @enderror" name="title" id="title-input" value="{{ old('title') }}" placeholder="{{ __('blog.post.write_title') }}" required>
                @error('title')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Slug --}}
              <div class="mb-3">
                <label class="form-label mb-1">{{ __('blog.post.slug') }}</label>
                <div class="input-group">
                  <input type="text" class="form-control @error('slug') is-invalid @enderror" name="slug" id="slug-input" value="{{ old('slug') }}" required>
                  @error('slug')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                @php
                  if (!empty($targetTenant)) {
                      $createSlugBase = 'https://' . $targetTenant->domain . ($targetTenantPrefix ? '/' . $targetTenantPrefix : '') . '/';
                  } else {
                      $createSlugBase = config('app.url') . '/blog/';
                  }
                @endphp
                <small class="text-muted mt-1 d-inline-block">
                  URL: {{ $createSlugBase }}<span id="slug-preview">{{ old('slug') }}</span>
                </small>
                <span id="slug-check-result" class="ms-3 fw-bold"></span>
              </div>

              {{-- Extracto --}}
              <div class="mb-3">
                <label for="excerpt" class="form-label">{{ __('blog.post.excerpt') }}</label>
                <textarea class="form-control @error('excerpt') is-invalid @enderror" name="excerpt" id="excerpt" rows="3" placeholder="{{ __('blog.post.excerpt_placeholder') }}">{{ old('excerpt') }}</textarea>
                @error('excerpt')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.post.excerpt_help') }}</small>
              </div>

              {{-- Editor TinyMCE --}}
              <div class="mb-3">
                <label for="content-editor" class="form-label">{{ __('blog.post.content') }}</label>
                <textarea id="content-editor" name="content" class="@error('content') is-invalid @enderror" style="display:none !important;">{{ old('content') }}</textarea>
                @error('content')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>

          {{-- Card SEO --}}
          @include('partials._seo_fields', ['Page' => (object)['seo_title' => old('seo_title'), 'seo_description' => old('seo_description'), 'seo_keywords' => old('seo_keywords'), 'canonical_url' => old('canonical_url'), 'robots_directive' => old('robots_directive', 'index,follow'), 'seo_image' => old('seo_image'), 'twitter_title' => old('twitter_title'), 'twitter_description' => old('twitter_description'), 'twitter_image' => old('twitter_image')]])

        </div> {{-- Fin .col-md-9 --}}

        {{-- Sidebar derecha --}}
        <div class="col-md-3">
          {{-- Card Publicar --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.post.publish') }}</strong></div>
            <div class="card-body">
              {{-- Estado --}}
              <div class="mb-3">
                <label class="form-label">{{ __('blog.post.status') }}</label>
                <select class="form-select @error('status') is-invalid @enderror" name="status" id="status-select">
                  <option value="draft" @selected(old('status') === 'draft')>{{ __('blog.post.draft') }}</option>
                  <option value="published" @selected(old('status', 'published') === 'published')>{{ __('blog.post.published') }}</option>
                </select>
                @error('status')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Visibilidad --}}
              <div class="mb-3">
                <label class="form-label">{{ __('blog.post.visibility') }}</label>
                <select class="form-select @error('visibility') is-invalid @enderror" name="visibility" id="visibility-select">
                  <option value="public" @selected(old('visibility', 'public') === 'public')>{{ __('blog.post.public') }}</option>
                  <option value="private" @selected(old('visibility') === 'private')>{{ __('blog.post.private') }}</option>
                  <option value="password" @selected(old('visibility') === 'password')>{{ __('blog.post.password_protected') }}</option>
                </select>
                @error('visibility')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Contraseña (solo si visibility es password) --}}
              <div class="mb-3" id="password-field" style="display: none;">
                <label class="form-label">{{ __('blog.post.password') }}</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" value="{{ old('password') }}">
                @error('password')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Fecha Pub --}}
              <div class="mb-3">
                <label class="form-label">{{ __('blog.post.published_at') }}</label>
                <input type="datetime-local" class="form-control @error('published_at') is-invalid @enderror" name="published_at" id="published_at" value="{{ old('published_at') }}">
                @error('published_at')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.post.published_immediately') }}</small>
              </div>

              {{-- Idioma Base --}}
              <div class="mb-3">
                <label class="form-label">{{ __('blog.post.base_language') }}</label>
                <select class="form-select @error('base_locale') is-invalid @enderror" name="base_locale" id="base-locale-select">
                  @php $currentLocale = old('base_locale', config('app.locale', 'es')); @endphp
                  @foreach (getAvailableLocales() as $code => $label)
                    <option value="{{ $code }}" @selected($currentLocale === $code)>{{ $label }}</option>
                  @endforeach
                </select>
                @error('base_locale')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('blog.post.create_post') }}</button>
              </div>
            </div>
          </div>

          {{-- Card Imagen Destacada --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.post.featured_image') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="featured_image" class="form-label">{{ __('blog.post.image_url') }}</label>
                <div class="input-group">
                  <input type="text" class="form-control @error('featured_image') is-invalid @enderror" name="featured_image" id="featured_image" value="{{ old('featured_image') }}" placeholder="{{ __('blog.post.image_placeholder') }}">
                  <button type="button" class="btn btn-outline-primary" id="select-featured-image-btn">
                    <i class="bi bi-image"></i> {{ __('blog.post.select_image') }}
                  </button>
                  @if(function_exists('aiimage_is_active') && aiimage_is_active())
                  <button type="button" class="btn btn-outline-success ai-image-trigger" data-target="featured_image" data-preview="featured-image-preview" title="Generar imagen con IA">
                    <i class="bi bi-stars"></i>
                  </button>
                  @endif
                  @error('featured_image')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <small class="text-muted d-block mt-1">{{ __('blog.post.image_help') }}</small>
                <small class="text-info d-block mt-1">
                  <i class="bi bi-info-circle"></i> {{ __('blog.post.recommended_resolution') }}
                </small>
              </div>
              <div id="featured-image-preview" class="mt-2"></div>

              {{-- Checkbox para ocultar imagen --}}
              <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" value="1" id="hide_featured_image" name="hide_featured_image" @checked(old('hide_featured_image'))>
                <label class="form-check-label" for="hide_featured_image">
                  {{ __('blog.post.hide_featured_image') }}
                </label>
                <small class="text-muted d-block">{{ __('blog.post.hide_featured_image_help') }}</small>
              </div>
            </div>
          </div>

          {{-- Card Opciones --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.post.options') }}</strong></div>
            <div class="card-body">
              {{-- Tipo de post --}}
              <div class="mb-3">
                @php $__postType = old('post_type', 'post') ?: 'post'; @endphp
                <label for="post_type" class="form-label">{{ __('blog.post.post_type') }}</label>
                <select class="form-select" id="post_type" name="post_type">
                  <option value="post" {{ $__postType === 'post' ? 'selected' : '' }}>{{ __('blog.post.type_post') }}</option>
                  <option value="brief" {{ $__postType === 'brief' ? 'selected' : '' }}>{{ __('blog.post.type_brief') }}</option>
                </select>
              </div>

              {{-- Destacado --}}
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="featured" name="featured" @checked(old('featured'))>
                <label class="form-check-label" for="featured">
                  {{ __('blog.post.featured_this_post') }}
                </label>
              </div>

              {{-- Permitir comentarios --}}
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="allow_comments" name="allow_comments" @checked(old('allow_comments', true))>
                <label class="form-check-label" for="allow_comments">
                  {{ __('blog.post.allow_comments') }}
                </label>
              </div>
            </div>
          </div>

          {{-- Card Plantilla --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.post.template') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="template_select" class="form-label">{{ __('blog.post.template') }}</label>
                @php $__isSidebarStruct = function_exists('is_sidebar_structure') && is_sidebar_structure(); @endphp
                <select class="form-select" id="template_select" name="template" {{ $__isSidebarStruct ? 'disabled' : '' }}>
                  @foreach ($availableTemplates as $filename => $displayName)
                    <option value="{{ $filename }}" @if(old('template', $currentTemplate) === $filename) selected @endif>
                      {{ $displayName }}
                    </option>
                  @endforeach
                </select>
                @if($__isSidebarStruct)
                <input type="hidden" name="template" value="page">
                <div class="alert alert-info py-1 px-2 mt-2 mb-0" style="font-size:0.75rem;">
                    <i class="bi bi-layout-sidebar me-1"></i> La estructura <strong>Sidebar</strong> fuerza ancho completo. La plantilla seleccionada no se aplica.
                </div>
                @else
                <small class="text-muted">{{ __('blog.post.template_help') }}</small>
                @endif
              </div>
            </div>
          </div>

          {{-- Card Categorías y Tags con IA --}}
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong>{{ __('blog.post.categories') }} & {{ __('blog.post.tags') }}</strong>
              <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" id="btn-paste-taxonomy" title="Pegar categorías y tags manualmente">
                  <i class="bi bi-clipboard-plus"></i> Pegar
                </button>
                <button type="button" class="btn btn-outline-primary" id="btn-ai-taxonomy" title="Sugerir categorías y tags con IA">
                  <i class="bi bi-magic"></i> Auto IA
                </button>
              </div>
            </div>
            <div class="card-body">
              {{-- Categorías --}}
              <label class="form-label fw-semibold">{{ __('blog.post.categories') }}</label>
              <div class="mb-3">
                <select class="form-select @error('categories') is-invalid @enderror" name="categories[]" id="categories" multiple size="5">
                  @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(in_array($category->id, old('categories', [])))>
                      {{ str_repeat('— ', $category->depth ?? 0) }}{{ $category->name }}
                    </option>
                  @endforeach
                </select>
                @error('categories')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.post.select_multiple') }}</small>
              </div>
              <a href="{{ route('blog.categories.create') }}" class="btn btn-sm btn-outline-primary mb-3" target="_blank">+ {{ __('blog.category.new_category') }}</a>

              <hr>

              {{-- Tags --}}
              <label class="form-label fw-semibold">{{ __('blog.post.tags') }}</label>
              <div class="mb-3">
                <select class="form-select @error('tags') is-invalid @enderror" name="tags[]" id="tags" multiple size="5">
                  @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" @selected(in_array($tag->id, old('tags', [])))>{{ $tag->name }}</option>
                  @endforeach
                </select>
                @error('tags')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.post.select_multiple') }}</small>
              </div>
              <a href="{{ route('blog.tags.create') }}" class="btn btn-sm btn-outline-primary" target="_blank">+ {{ __('blog.tag.new_tag') }}</a>

              {{-- AI results area --}}
              <div id="ai-taxonomy-results" class="mt-3" style="display:none;"></div>
            </div>
          </div>

          {{-- Card Opciones de Cabecera --}}
          @include('partials._header_options', ['context' => 'blog_post', 'Page' => (object)['show_hero' => old('show_hero', 0), 'hero_title' => old('hero_title'), 'hero_subtitle' => old('hero_subtitle'), 'hero_image' => old('hero_image'), 'hero_height' => old('hero_height', 'medium'), 'hero_overlay' => old('hero_overlay', '0.3'), 'hero_text_color' => old('hero_text_color', 'white'), 'hide_title' => old('hide_title', 1)]])

          {{-- Card Cancelar --}}
          <div class="card">
            <div class="card-body text-center">
              <a href="{{ route('blog.posts.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('common.cancel') }}</a>
            </div>
          </div>
        </div> {{-- Fin .col-md-3 --}}
      </div> {{-- Fin .row --}}
    </form>

  </div> {{-- Fin .container-fluid --}}
</div> {{-- Fin .app-content --}}

{{-- Incluir el script de TinyMCE --}}
@include('partials._tinymce')

@if(function_exists('aiimage_is_active') && aiimage_is_active())
<script src="/assets/modules/aiimage/js/ai-image-generator.js"></script>
@endif
@endsection

{{-- Scripts específicos de la página --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const titleInput = document.getElementById('title-input');
  const slugInput = document.getElementById('slug-input');
  const slugPreview = document.getElementById('slug-preview');
  const visibilitySelect = document.getElementById('visibility-select');
  const passwordField = document.getElementById('password-field');
  const featuredImageInput = document.getElementById('featured_image');
  const featuredImagePreview = document.getElementById('featured-image-preview');

  // Auto-generar slug desde título
  if (titleInput && slugInput) {
    titleInput.addEventListener('input', function() {
      const slug = generateSlug(this.value);
      slugInput.value = slug;
      if (slugPreview) {
        slugPreview.textContent = slug;
      }
    });
  }

  // Función para generar slug
  function generateSlug(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-');
  }

  // Mostrar/ocultar campo de contraseña según visibilidad
  if (visibilitySelect && passwordField) {
    visibilitySelect.addEventListener('change', function() {
      if (this.value === 'password') {
        passwordField.style.display = 'block';
      } else {
        passwordField.style.display = 'none';
      }
    });

    // Verificar estado inicial
    if (visibilitySelect.value === 'password') {
      passwordField.style.display = 'block';
    }
  }

  // Preview de imagen destacada
  if (featuredImageInput && featuredImagePreview) {
    featuredImageInput.addEventListener('input', function() {
      const url = this.value.trim();
      if (url) {
        featuredImagePreview.innerHTML = `<img src="${url}" class="img-fluid rounded" alt="Preview" style="max-height: 200px; object-fit: cover;" onerror="this.parentElement.innerHTML='<p class=text-danger><i class=bi bi-exclamation-triangle></i> {!! addslashes(__('blog.post.image_load_error')) !!}</p>'">`;
      } else {
        featuredImagePreview.innerHTML = '';
      }
    });

    // Preview inicial si hay valor
    if (featuredImageInput.value.trim()) {
      const url = featuredImageInput.value.trim();
      featuredImagePreview.innerHTML = `<img src="${url}" class="img-fluid rounded" alt="Preview" style="max-height: 200px; object-fit: cover;" onerror="this.parentElement.innerHTML='<p class=text-danger><i class=bi bi-exclamation-triangle></i> {!! addslashes(__('blog.post.image_load_error')) !!}</p>'">`;
    }
  }

  // Botón para abrir el gestor de medios
  const selectFeaturedImageBtn = document.getElementById('select-featured-image-btn');
  if (selectFeaturedImageBtn) {
    selectFeaturedImageBtn.addEventListener('click', function() {
      // Verificar si el Media Manager está disponible en el momento del clic
      if (typeof window.openMediaManagerForTinyMCE === 'function') {
        window.openMediaManagerForTinyMCE(function(url, meta) {
          // Callback cuando se selecciona una imagen
          if (featuredImageInput) {
            featuredImageInput.value = url;
            // Disparar evento input para actualizar el preview
            featuredImageInput.dispatchEvent(new Event('input'));
          }
        }, featuredImageInput.value, { filetype: 'image' });
      } else {
        // Mostrar alerta si el Media Manager no está disponible
        alert('El gestor de medios aún no está disponible. Por favor, espera un momento e intenta de nuevo.');
        console.error('window.openMediaManagerForTinyMCE no está disponible');
      }
    });
  }

  // --- AI Auto-Taxonomy ---
  @include('Blog::partials._ai_taxonomy_script')
});
</script>
@endpush

@include('Blog::partials._slug_scripts')
