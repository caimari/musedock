@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- Navegación y botón añadir post --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb">
        <a href="{{ route('blog.posts.index') }}">Posts</a> <span class="mx-2">/</span> <span>{{ e($post->title ?? 'Editando...') }}</span>
      </div>
      <a href="{{ route('blog.posts.create') }}" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i> {{ __('blog.post.add_post') }}</a>
    </div>

    {{-- Script para SweetAlert2 --}}
    @if (session('success'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(__('common.success')) !!}, text: {!! json_encode(session('success')) !!}, confirmButtonColor: '#3085d6' }); }); </script>
    @endif
    @if (session('error'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(__('common.error')) !!}, text: {!! json_encode(session('error')) !!}, confirmButtonColor: '#d33' }); }); </script>
    @endif

    <form method="POST" action="{{ route('blog.posts.update', ['id' => $post->id]) }}" id="postForm" enctype="multipart/form-data">
      @method('PUT')
      @csrf

      <div class="row">
        {{-- Columna izquierda (Principal) --}}
        <div class="col-md-9">
          {{-- Card Contenido Principal --}}
          <div class="card mb-4">
            <div class="card-body">
              {{-- Título --}}
              <div class="mb-3">
                <input type="text" class="form-control form-control-lg @error('title') is-invalid @enderror" name="title" id="title-input" value="{{ old('title', $post->title) }}" placeholder="{{ __('blog.post.write_title') }}" required>
                @error('title')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Slug --}}
              <div class="mb-3">
                <label class="form-label mb-1">{{ __('blog.post.slug') }}</label>
                <div class="input-group">
                  <input type="text" class="form-control @error('slug') is-invalid @enderror" name="slug" id="slug-input" value="{{ old('slug', $post->slug) }}" required readonly>
                  <button type="button" class="btn btn-outline-secondary" id="toggle-slug-edit" title="Editar Slug">
                    <i class="bi bi-lock"></i>
                  </button>
                  @error('slug')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <small class="text-muted mt-1 d-inline-block">
                  URL: <a href="{{ config('app.url') }}/blog/{{ $post->slug }}" target="_blank">{{ config('app.url') }}/blog/{{ $post->slug }}</a>
                </small>
                <span id="slug-check-result" class="ms-3 fw-bold"></span>
              </div>

              {{-- Extracto --}}
              <div class="mb-3">
                <label for="excerpt" class="form-label">{{ __('blog.post.excerpt') }}</label>
                <textarea class="form-control @error('excerpt') is-invalid @enderror" name="excerpt" id="excerpt" rows="3" placeholder="{{ __('blog.post.excerpt_placeholder') }}">{{ old('excerpt', $post->excerpt) }}</textarea>
                @error('excerpt')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.post.excerpt_help') }}</small>
              </div>

              {{-- Editor TinyMCE --}}
              <div class="mb-3">
                <label for="content-editor" class="form-label">{{ __('blog.post.content') }}</label>
                <textarea id="content-editor" name="content" class="@error('content') is-invalid @enderror">{{ old('content', $post->content) }}</textarea>
                @error('content')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>

          {{-- Card Traducciones --}}
          <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong>Traducciones</strong>
              <span class="badge bg-secondary">Idioma base: <span id="base-locale-name">{{ getAvailableLocales()[$post->base_locale] ?? e($post->base_locale) }}</span></span>
            </div>
            <div class="card-body">
              <div class="d-flex flex-wrap gap-2" id="translations-container">
                @foreach ($locales as $code => $name)
                  @if($code !== ($post->base_locale ?? config('app.locale', 'es')))
                    <a href="{{ route('blog.posts.translation.edit', ['id' => $post->id, 'locale' => $code]) }}" class="btn btn-sm translation-btn {{ isset($translatedLocales[$code]) ? 'btn-outline-success' : 'btn-outline-secondary' }}" data-locale="{{ $code }}">
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
          @include('partials._seo_fields', ['Page' => $post])

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
                  <option value="draft" @selected(old('status', $post->status) === 'draft')>{{ __('blog.post.draft') }}</option>
                  <option value="published" @selected(old('status', $post->status) === 'published')>{{ __('blog.post.published') }}</option>
                </select>
                @error('status')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Visibilidad --}}
              <div class="mb-3">
                <label class="form-label">{{ __('blog.post.visibility') }}</label>
                <select class="form-select @error('visibility') is-invalid @enderror" name="visibility" id="visibility-select">
                  <option value="public" @selected(old('visibility', $post->visibility ?? 'public') === 'public')>{{ __('blog.post.public') }}</option>
                  <option value="private" @selected(old('visibility', $post->visibility ?? 'public') === 'private')>{{ __('blog.post.private') }}</option>
                  <option value="password" @selected(old('visibility', $post->visibility ?? 'public') === 'password')>{{ __('blog.post.password_protected') }}</option>
                </select>
                @error('visibility')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Contraseña (solo si visibility es password) --}}
              <div class="mb-3" id="password-field" style="display: {{ old('visibility', $post->visibility) === 'password' ? 'block' : 'none' }};">
                <label class="form-label">{{ __('blog.post.password') }}</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" value="{{ old('password') }}" placeholder="Dejar vacío para mantener">
                @error('password')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Fecha Pub --}}
              <div class="mb-3">
                <label class="form-label">{{ __('blog.post.published_at') }}</label>
                <input type="datetime-local" class="form-control @error('published_at') is-invalid @enderror" name="published_at" id="published_at"
                       value="{{ old('published_at', $post->published_at ? ($post->published_at instanceof \DateTimeInterface ? $post->published_at->format('Y-m-d\TH:i') : '') : '') }}">
                @error('published_at')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Dejar vacío para usar fecha actual.</small>
              </div>

              {{-- Idioma Base --}}
              <div class="mb-3">
                <label class="form-label">{{ __('blog.post.base_language') }}</label>
                <select class="form-select @error('base_locale') is-invalid @enderror" name="base_locale" id="base-locale-select">
                  @php $currentLocale = old('base_locale', $post->base_locale); @endphp
                  @foreach (getAvailableLocales() as $code => $label)
                    <option value="{{ $code }}" @selected($currentLocale === $code)>{{ $label }}</option>
                  @endforeach
                </select>
                @error('base_locale')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('common.update') }}</button>
              </div>
            </div>
          </div>

          {{-- Card Imagen Destacada --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.post.featured_image') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="featured_image" class="form-label">{{ __('blog.post.image_url') }}</label>
                <input type="text" class="form-control @error('featured_image') is-invalid @enderror" name="featured_image" id="featured_image" value="{{ old('featured_image', $post->featured_image) }}" placeholder="{{ __('blog.post.image_placeholder') }}">
                @error('featured_image')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.post.image_help') }}</small>
              </div>
              <div id="featured-image-preview" class="mt-2">
                @if($post->featured_image)
                  <img src="{{ $post->featured_image }}" class="img-fluid rounded" alt="Preview">
                @endif
              </div>
            </div>
          </div>

          {{-- Card Opciones --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.post.options') }}</strong></div>
            <div class="card-body">
              {{-- Destacado --}}
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="featured" name="featured" @checked(old('featured', $post->featured))>
                <label class="form-check-label" for="featured">
                  {{ __('blog.post.featured_this_post') }}
                </label>
              </div>

              {{-- {{ __('blog.post.allow_comments') }} --}}
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="allow_comments" name="allow_comments" @checked(old('allow_comments', $post->allow_comments ?? true))>
                <label class="form-check-label" for="allow_comments">
                  {{ __('blog.post.allow_comments') }}
                </label>
              </div>
            </div>
          </div>

          {{-- Card Categorías --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.post.categories') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <select class="form-select @error('categories') is-invalid @enderror" name="categories[]" id="categories" multiple size="5">
                  @php
                    // Extraer IDs de categorías del post (usando PHP nativo)
                    $postCategories = $post->categories ?? [];
                    $selectedCategoryIds = array_map(function($cat) {
                        return is_object($cat) ? $cat->id : ($cat['id'] ?? null);
                    }, $postCategories);
                    $selectedCategoryIds = array_filter($selectedCategoryIds); // Eliminar nulls
                    $selectedCategories = old('categories', $selectedCategoryIds);
                  @endphp
                  @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(in_array($category->id, $selectedCategories))>
                      {{ str_repeat('— ', $category->depth ?? 0) }}{{ $category->name }}
                    </option>
                  @endforeach
                </select>
                @error('categories')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.post.select_multiple') }}</small>
              </div>
              <a href="{{ route('blog.categories.create') }}" class="btn btn-sm btn-outline-primary" target="_blank">+ {{ __('blog.category.new_category') }}</a>
            </div>
          </div>

          {{-- Card Etiquetas --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.post.tags') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <select class="form-select @error('tags') is-invalid @enderror" name="tags[]" id="tags" multiple size="5">
                  @php
                    // Extraer IDs de tags del post (usando PHP nativo)
                    $postTags = $post->tags ?? [];
                    $selectedTagIds = array_map(function($tag) {
                        return is_object($tag) ? $tag->id : ($tag['id'] ?? null);
                    }, $postTags);
                    $selectedTagIds = array_filter($selectedTagIds); // Eliminar nulls
                    $selectedTags = old('tags', $selectedTagIds);
                  @endphp
                  @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" @selected(in_array($tag->id, $selectedTags))>{{ $tag->name }}</option>
                  @endforeach
                </select>
                @error('tags')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.post.select_multiple') }}</small>
              </div>
              <a href="{{ route('blog.tags.create') }}" class="btn btn-sm btn-outline-primary" target="_blank">+ {{ __('blog.tag.new_tag') }}</a>
            </div>
          </div>

          {{-- Card Opciones de Cabecera --}}
          @include('partials._header_options', ['Page' => $post])

          {{-- Card Info Fechas --}}
          <div class="card mb-4">
            <div class="card-body p-2">
              <small class="text-muted d-block mb-1">
                <strong>Creado:</strong>
                {{ $post->created_at_formatted ?? ($post->created_at ? $post->created_at->format('d/m/Y H:i') : 'Desconocido') }}
              </small>
              <small class="text-muted d-block">
                <strong>Actualizado:</strong>
                {{ $post->updated_at_formatted ?? ($post->updated_at ? $post->updated_at->format('d/m/Y H:i') : 'Desconocido') }}
              </small>
            </div>
          </div>

          {{-- Card Eliminar --}}
          <div class="card mb-4">
            <div class="card-body text-center">
              <a href="javascript:void(0);" onclick="confirmDelete({{ $post->id }})" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-trash me-1"></i> Eliminar post
              </a>
            </div>
          </div>

        </div> {{-- Fin .col-md-3 --}}
      </div> {{-- Fin .row --}}
    </form>

    {{-- Script JS traducciones y funcionalidad --}}
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        // Elementos del DOM
        const baseLocaleSelect = document.getElementById('base-locale-select');
        const baseLocaleNameSpan = document.getElementById('base-locale-name');
        const translationsContainer = document.getElementById('translations-container');
        const postId = "{{ $post->id }}";
        const slugInput = document.getElementById('slug-input');
        const toggleSlugBtn = document.getElementById('toggle-slug-edit');
        const visibilitySelect = document.getElementById('visibility-select');
        const passwordField = document.getElementById('password-field');
        const featuredImageInput = document.getElementById('featured_image');
        const featuredImagePreview = document.getElementById('featured-image-preview');

        // Datos desde PHP
        const allLocales = {!! json_encode(getAvailableLocales() ?? []) !!};
        const translatedLocales = {!! json_encode($translatedLocales ?? []) !!};

        // Función para actualizar la UI de traducciones
        function updateTranslationsOptions(newBaseLocale) {
          if (!baseLocaleNameSpan || !translationsContainer || !allLocales) return;

          baseLocaleNameSpan.textContent = allLocales[newBaseLocale] || newBaseLocale.toUpperCase();
          translationsContainer.innerHTML = '';

          Object.keys(allLocales).forEach(code => {
            if (code !== newBaseLocale) {
              const name = allLocales[code];
              const isTranslated = translatedLocales.hasOwnProperty(code) && translatedLocales[code];
              const btn = document.createElement('a');
              const translationUrl = `{{ admin_path() }}/blog/posts/${postId}/translations/${code}`;

              btn.href = translationUrl;
              btn.className = `btn btn-sm translation-btn ${isTranslated ? 'btn-outline-success' : 'btn-outline-secondary'}`;
              btn.dataset.locale = code;
              btn.textContent = name;

              if (isTranslated) {
                const icon = document.createElement('i');
                icon.className = 'ms-1 fas fa-check-circle text-success';
                btn.appendChild(icon);
              }

              translationsContainer.appendChild(btn);
            }
          });
        }

        // Event listener para cambio de idioma base
        if (baseLocaleSelect) {
          baseLocaleSelect.addEventListener('change', function(event) {
            updateTranslationsOptions(event.target.value);
          });
        }

        // Toggle edición de slug
        if (toggleSlugBtn && slugInput) {
          toggleSlugBtn.addEventListener('click', function() {
            if (slugInput.readOnly) {
              slugInput.readOnly = false;
              this.querySelector('i').className = 'bi bi-unlock';
              slugInput.focus();
            } else {
              slugInput.readOnly = true;
              this.querySelector('i').className = 'bi bi-lock';
            }
          });
        }

        // Mostrar/ocultar campo de contraseña
        if (visibilitySelect && passwordField) {
          visibilitySelect.addEventListener('change', function() {
            passwordField.style.display = this.value === 'password' ? 'block' : 'none';
          });
        }

        // Preview de imagen destacada
        if (featuredImageInput && featuredImagePreview) {
          featuredImageInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url) {
              featuredImagePreview.innerHTML = `<img src="${url}" class="img-fluid rounded" alt="Preview" onerror="this.parentElement.innerHTML='<p class=text-danger>No se pudo cargar la imagen</p>'">`;
            } else {
              featuredImagePreview.innerHTML = '';
            }
          });
        }
      });

      // Función para confirmar eliminación
      function confirmDelete(postId) {
        Swal.fire({
          title: '¿Estás seguro?',
          text: 'Esta acción eliminará permanentemente este post y no se puede deshacer.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ admin_path() }}/blog/posts/${postId}`;
            form.innerHTML = `
              @csrf
              @method('DELETE')
            `;
            document.body.appendChild(form);
            form.submit();
          }
        });
      }
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
