@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Ajustes de lectura</h3>
  </div>

  <div class="card-body">
    <form method="POST" action="{{ route('settings.reading.update') }}">
      {!! csrf_field() !!}

      <!-- Tu página de inicio muestra -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Tu página de inicio muestra</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="show_on_front" id="show_posts" value="posts"
                @if(($settings['show_on_front'] ?? 'posts') === 'posts') checked @endif>
              <label class="form-check-label" for="show_posts">
                <strong>Tus últimas entradas</strong>
                <small class="d-block text-muted">Muestra los posts más recientes del blog en la página de inicio</small>
              </label>
            </div>
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="show_on_front" id="show_page" value="page"
                @if(($settings['show_on_front'] ?? 'posts') === 'page') checked @endif>
              <label class="form-check-label" for="show_page">
                <strong>Una página estática</strong> (seleccionar abajo)
                <small class="d-block text-muted">Muestra una página específica como página de inicio</small>
              </label>
            </div>
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="show_on_front" id="show_post" value="post"
                @if(($settings['show_on_front'] ?? 'posts') === 'post') checked @endif>
              <label class="form-check-label" for="show_post">
                <strong>Un post estático</strong> (seleccionar abajo)
                <small class="d-block text-muted">Muestra un post específico del blog como página de inicio</small>
              </label>
            </div>
          </div>

          <div class="row mt-4" id="static-page-selector" style="display: {{ ($settings['show_on_front'] ?? 'posts') === 'page' ? 'block' : 'none' }};">
            <div class="col-12">
              <div class="mb-3">
                <label class="form-label">Página de inicio:</label>
                <select name="page_on_front" class="form-select">
                  <option value="">— Elegir —</option>
                  @foreach($pages as $page)
                    <option value="{{ $page['id'] }}" @if(($settings['page_on_front'] ?? '') == $page['id']) selected @endif>
                      {{ !empty(trim($page['title'] ?? '')) ? $page['title'] : ($page['slug'] ?? '(Sin título)') }}
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">Página que se mostrará como inicio</small>
              </div>
            </div>
          </div>

          <div class="row mt-4" id="static-post-selector" style="display: {{ ($settings['show_on_front'] ?? 'posts') === 'post' ? 'block' : 'none' }};">
            <div class="col-12">
              <div class="mb-3">
                <label class="form-label">Post de inicio:</label>
                <select name="post_on_front" class="form-select">
                  <option value="">— Elegir —</option>
                  @foreach($blog_posts as $post)
                    <option value="{{ $post['id'] }}" @if(($settings['post_on_front'] ?? '') == $post['id']) selected @endif>
                      {{ $post['title'] }}
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">Post del blog que se mostrará como inicio</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Número de entradas -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Número de entradas a mostrar</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Número máximo de entradas a mostrar en el sitio</label>
                <div class="input-group">
                  <input type="number" name="posts_per_page" class="form-control" value="{{ $settings['posts_per_page'] ?? '10' }}" min="1" max="100">
                  <span class="input-group-text">entradas</span>
                </div>
                <small class="text-muted">Controla cuántos posts se muestran por página en el blog</small>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Número máximo de entradas a mostrar en el feed</label>
                <div class="input-group">
                  <input type="number" name="posts_per_rss" class="form-control" value="{{ $settings['posts_per_rss'] ?? '10' }}" min="1" max="50">
                  <span class="input-group-text">elementos</span>
                </div>
                <small class="text-muted">Número de entradas en el feed RSS</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Estructura de URLs del blog -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Estructura de URLs del blog</h5>
        </div>
        <div class="card-body">
          @php
            $currentBlogPrefix = $settings['blog_url_prefix'] ?? 'blog';
            $hasBlogPrefix = ($currentBlogPrefix !== '');
          @endphp

          <div class="mb-3">
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="blog_url_mode" id="blog_url_with_prefix" value="prefix"
                @if($hasBlogPrefix) checked @endif>
              <label class="form-check-label" for="blog_url_with_prefix">
                <strong>Con prefijo</strong>
                <small class="d-block text-muted">Los posts se acceden con un prefijo en la URL (ej: /blog/mi-post)</small>
              </label>
            </div>

            <div id="blog-prefix-input" class="ms-4 mb-3" style="display: {{ $hasBlogPrefix ? 'block' : 'none' }};">
              <label class="form-label">Nombre del prefijo</label>
              <div class="input-group" style="max-width: 400px;">
                <span class="input-group-text">/</span>
                <input type="text" name="blog_url_prefix" id="blog_url_prefix_input" class="form-control"
                  value="{{ $currentBlogPrefix }}" placeholder="blog"
                  pattern="[a-z0-9\-]+" title="Solo letras minúsculas, números y guiones">
                <span class="input-group-text">/mi-post</span>
              </div>
              <small class="text-muted">Solo letras minúsculas, números y guiones. Ejemplos: blog, noticias, articulos</small>
            </div>
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="blog_url_mode" id="blog_url_no_prefix" value="none"
                @if(!$hasBlogPrefix) checked @endif>
              <label class="form-check-label" for="blog_url_no_prefix">
                <strong>Sin prefijo</strong>
                <small class="d-block text-muted">Los posts se acceden directamente desde la raíz (ej: /mi-post)</small>
              </label>
            </div>
          </div>

          <div class="alert alert-info mb-0 mt-3">
            <i class="bi bi-eye"></i> <strong>Vista previa:</strong>
            <span id="blog-url-preview">{{ $_SERVER['HTTP_HOST'] ?? 'dominio.com' }}/<span id="blog-prefix-preview">{{ $hasBlogPrefix ? $currentBlogPrefix . '/' : '' }}</span>ejemplo-de-post</span>
          </div>
        </div>
      </div>

      <!-- Estructura de URLs de Páginas -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Estructura de URLs de Páginas</h5>
        </div>
        <div class="card-body">
          @php
            $currentPagePrefix = $settings['page_url_prefix'] ?? 'p';
            $hasPagePrefix = ($currentPagePrefix !== '');
          @endphp

          <div class="mb-3">
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="page_url_mode" id="page_url_with_prefix" value="prefix"
                @if($hasPagePrefix) checked @endif>
              <label class="form-check-label" for="page_url_with_prefix">
                <strong>Con prefijo</strong>
                <small class="d-block text-muted">Las páginas se acceden con un prefijo en la URL (ej: /p/nosotros)</small>
              </label>
            </div>

            <div id="page-prefix-input" class="ms-4 mb-3" style="display: {{ $hasPagePrefix ? 'block' : 'none' }};">
              <label class="form-label">Nombre del prefijo</label>
              <div class="input-group" style="max-width: 400px;">
                <span class="input-group-text">/</span>
                <input type="text" name="page_url_prefix" id="page_url_prefix_input" class="form-control"
                  value="{{ $currentPagePrefix }}" placeholder="p"
                  pattern="[a-z0-9\-]+" title="Solo letras minúsculas, números y guiones">
                <span class="input-group-text">/mi-pagina</span>
              </div>
              <small class="text-muted">Solo letras minúsculas, números y guiones. Ejemplos: p, paginas, pages</small>
            </div>
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="page_url_mode" id="page_url_no_prefix" value="none"
                @if(!$hasPagePrefix) checked @endif>
              <label class="form-check-label" for="page_url_no_prefix">
                <strong>Sin prefijo</strong>
                <small class="d-block text-muted">Las páginas se acceden directamente desde la raíz (ej: /nosotros)</small>
              </label>
            </div>
          </div>

          <div class="alert alert-info mb-0 mt-3">
            <i class="bi bi-eye"></i> <strong>Vista previa:</strong>
            <span id="page-url-preview">{{ $_SERVER['HTTP_HOST'] ?? 'dominio.com' }}/<span id="page-prefix-preview">{{ $hasPagePrefix ? $currentPagePrefix . '/' : '' }}</span>nosotros</span>
          </div>
        </div>
      </div>

      <!-- Visibilidad en motores de búsqueda -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Visibilidad en los motores de búsqueda</h5>
        </div>
        <div class="card-body">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="blog_public" id="blog_public" value="0"
              @if(($settings['blog_public'] ?? '1') == '0') checked @endif>
            <label class="form-check-label" for="blog_public">
              <strong>Pedir a los motores de búsqueda que no indexen este sitio</strong>
            </label>
          </div>
          <small class="text-muted d-block mt-2">
            <i class="bi bi-info-circle"></i> Depende de los motores de búsqueda atender esta petición o no.
          </small>
        </div>
      </div>

      <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save"></i> Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ─── Blog URL prefix ───
document.querySelectorAll('input[name="blog_url_mode"]').forEach(radio => {
  radio.addEventListener('change', function() {
    const prefixInput = document.getElementById('blog-prefix-input');
    const prefixPreview = document.getElementById('blog-prefix-preview');
    const inputField = document.getElementById('blog_url_prefix_input');

    if (this.value === 'prefix') {
      prefixInput.style.display = 'block';
      const val = inputField.value.trim() || 'blog';
      prefixPreview.textContent = val + '/';
    } else {
      prefixInput.style.display = 'none';
      prefixPreview.textContent = '';
    }
  });
});

const blogPrefixField = document.getElementById('blog_url_prefix_input');
if (blogPrefixField) {
  blogPrefixField.addEventListener('input', function() {
    const val = this.value.replace(/[^a-z0-9\-]/g, '').trim();
    this.value = val;
    document.getElementById('blog-prefix-preview').textContent = val ? val + '/' : '';
  });
}

// ─── Page URL prefix ───
document.querySelectorAll('input[name="page_url_mode"]').forEach(radio => {
  radio.addEventListener('change', function() {
    const prefixInput = document.getElementById('page-prefix-input');
    const prefixPreview = document.getElementById('page-prefix-preview');
    const inputField = document.getElementById('page_url_prefix_input');

    if (this.value === 'prefix') {
      prefixInput.style.display = 'block';
      const val = inputField.value.trim() || 'p';
      prefixPreview.textContent = val + '/';
    } else {
      prefixInput.style.display = 'none';
      prefixPreview.textContent = '';
    }
  });
});

const pagePrefixField = document.getElementById('page_url_prefix_input');
if (pagePrefixField) {
  pagePrefixField.addEventListener('input', function() {
    const val = this.value.replace(/[^a-z0-9\-]/g, '').trim();
    this.value = val;
    document.getElementById('page-prefix-preview').textContent = val ? val + '/' : '';
  });
}

// Mostrar/ocultar selectores según la opción seleccionada
document.querySelectorAll('input[name="show_on_front"]').forEach(radio => {
  radio.addEventListener('change', function() {
    const pageSelector = document.getElementById('static-page-selector');
    const postSelector = document.getElementById('static-post-selector');

    // Ocultar ambos por defecto
    pageSelector.style.display = 'none';
    postSelector.style.display = 'none';

    // Mostrar el selector correspondiente
    if (this.value === 'page') {
      pageSelector.style.display = 'block';
    } else if (this.value === 'post') {
      postSelector.style.display = 'block';
    }
  });
});
</script>
@endsection
