@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-gear me-2"></i>{{ $title }}</h2>
      <a href="{{ film_admin_url() }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
    </div>

    @if(session('success'))
      <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', text: {!! json_encode(session('success')) !!}, timer: 3000 }); });</script>
    @endif

    <div class="row">
      <div class="col-lg-6">
        <form method="POST" action="{{ film_admin_url('settings') }}">
          @csrf

          {{-- API --}}
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-key me-1"></i> API de TMDb</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">TMDb API Key <span class="text-danger">*</span></label>
                <input type="text" name="tmdb_api_key" class="form-control" value="{{ $settings['tmdb_api_key'] ?? '' }}" placeholder="Tu API key">
                <small class="form-text text-muted">
                  Gratis en <a href="https://www.themoviedb.org/settings/api" target="_blank">themoviedb.org/settings/api</a>
                </small>
              </div>
              <div class="mb-3">
                <label class="form-label">Idioma TMDb</label>
                <select name="tmdb_language" class="form-select">
                  <option value="es-ES" {{ ($settings['tmdb_language'] ?? 'es-ES') === 'es-ES' ? 'selected' : '' }}>Español (España)</option>
                  <option value="es-MX" {{ ($settings['tmdb_language'] ?? '') === 'es-MX' ? 'selected' : '' }}>Español (México)</option>
                  <option value="en-US" {{ ($settings['tmdb_language'] ?? '') === 'en-US' ? 'selected' : '' }}>English (US)</option>
                  <option value="pt-BR" {{ ($settings['tmdb_language'] ?? '') === 'pt-BR' ? 'selected' : '' }}>Português (Brasil)</option>
                  <option value="pt-PT" {{ ($settings['tmdb_language'] ?? '') === 'pt-PT' ? 'selected' : '' }}>Português (Portugal)</option>
                  <option value="ca-ES" {{ ($settings['tmdb_language'] ?? '') === 'ca-ES' ? 'selected' : '' }}>Català</option>
                </select>
              </div>
            </div>
          </div>

          {{-- Images --}}
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-image me-1"></i> Imágenes</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Fuente de imágenes</label>
                <select name="image_source" class="form-select">
                  <option value="tmdb" {{ ($settings['image_source'] ?? 'tmdb') === 'tmdb' ? 'selected' : '' }}>
                    TMDb CDN (recomendado — cumple terms of service)
                  </option>
                  <option value="local_fallback" {{ ($settings['image_source'] ?? '') === 'local_fallback' ? 'selected' : '' }}>
                    Local con fallback a TMDb (usa copia local si existe, si no TMDb)
                  </option>
                  <option value="local" {{ ($settings['image_source'] ?? '') === 'local' ? 'selected' : '' }}>
                    Solo local (requiere haber descargado las imágenes)
                  </option>
                </select>
                <small class="form-text text-muted">
                  Las imágenes se descargan automáticamente como backup al importar una película.
                  Con "TMDb CDN" se sirven desde image.tmdb.org. Con "Local" se sirven desde tu servidor vía Cloudflare.
                </small>
              </div>
              <div class="mb-3">
                <label class="form-label">URL base de pósters</label>
                <input type="text" name="poster_base_url" class="form-control" value="{{ $settings['poster_base_url'] ?? 'https://image.tmdb.org/t/p/w500' }}">
              </div>
            </div>
          </div>

          {{-- API Control --}}
          <div class="card mb-3 {{ !($settings['api_enabled'] ?? 1) ? 'border-danger' : '' }}">
            <div class="card-header"><i class="bi bi-toggles me-1"></i> Control de API TMDb</div>
            <div class="card-body">
              <div class="form-check form-switch mb-2">
                <input type="hidden" name="api_enabled" value="0">
                <input type="checkbox" name="api_enabled" value="1" class="form-check-input" id="api_enabled"
                       {{ ($settings['api_enabled'] ?? 1) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="api_enabled">
                  API TMDb activa
                </label>
              </div>
              <small class="text-muted d-block mb-2">
                Cuando está <strong>desactivada</strong>: no se hacen llamadas a TMDb. Las búsquedas en el catálogo solo muestran películas locales.
                La importación desde admin, la búsqueda en TMDb del catálogo público, y la auto-importación al clickar filmografías quedan deshabilitadas.
                Las fichas ya importadas siguen funcionando con normalidad.
              </small>
              @if(!($settings['api_enabled'] ?? 1))
                <div class="alert alert-warning mb-0 py-2">
                  <i class="bi bi-exclamation-triangle me-1"></i> La API está desactivada. No se importarán películas nuevas.
                </div>
              @endif
            </div>
          </div>

          {{-- Frontend --}}
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-layout-text-window me-1"></i> Frontend</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Películas por página (catálogo)</label>
                <input type="number" name="films_per_page" class="form-control" value="{{ $settings['films_per_page'] ?? 12 }}" min="1" max="100">
              </div>
            </div>
          </div>

          {{-- Home carousel --}}
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-house me-1"></i> Cartelera en Home</div>
            <div class="card-body">
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="show_home_carousel" value="0">
                <input type="checkbox" name="show_home_carousel" value="1" class="form-check-input" id="show_home_carousel"
                       {{ ($settings['show_home_carousel'] ?? 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="show_home_carousel">
                  Mostrar cartelera de películas en la página de inicio
                </label>
              </div>
              <div class="mb-3">
                <label class="form-label">Título de la sección</label>
                <input type="text" name="home_carousel_title" class="form-control" value="{{ $settings['home_carousel_title'] ?? 'Cartelera' }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Número de películas a mostrar</label>
                <input type="number" name="home_carousel_count" class="form-control" value="{{ $settings['home_carousel_count'] ?? 12 }}" min="1" max="50">
              </div>
              <small class="text-muted">
                Muestra las últimas películas publicadas en la home, después de los artículos del blog y antes del footer.
                Se muestran en una fila horizontal con scroll.
              </small>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Guardar configuración
          </button>
        </form>
      </div>

      {{-- Info panel --}}
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header"><i class="bi bi-info-circle me-1"></i> Almacenamiento de imágenes</div>
          <div class="card-body small">
            <p>Al importar una película, el sistema descarga automáticamente una <strong>copia de backup</strong> del póster y backdrop a tu servidor:</p>
            <code>storage/tenants/24/films/images/posters/</code><br>
            <code>storage/tenants/24/films/images/backdrops/</code><br>
            <code>storage/tenants/24/films/images/people/</code>
            <hr>
            <p class="mb-1"><strong>TMDb CDN</strong> — Las imágenes se sirven desde <code>image.tmdb.org</code>. Rápido, cumple los terms de TMDb. Cloudflare las cachea como recurso externo.</p>
            <p class="mb-1"><strong>Local con fallback</strong> — Intenta servir la copia local (tu dominio, cacheada por Cloudflare como propia). Si no existe, usa TMDb.</p>
            <p class="mb-0"><strong>Solo local</strong> — Solo sirve imágenes locales. Si no se descargó, no muestra imagen.</p>
          </div>
        </div>

        @php
          $imgPath = dirname(__DIR__, 6) . '/storage/tenants/24/films/images';
          $posterCount = is_dir($imgPath . '/posters') ? count(glob($imgPath . '/posters/*')) : 0;
          $backdropCount = is_dir($imgPath . '/backdrops') ? count(glob($imgPath . '/backdrops/*')) : 0;
          $peopleCount = is_dir($imgPath . '/people') ? count(glob($imgPath . '/people/*')) : 0;

          $totalSize = 0;
          foreach (['posters', 'backdrops', 'people'] as $subdir) {
              $dir = $imgPath . '/' . $subdir;
              if (is_dir($dir)) {
                  foreach (glob($dir . '/*') as $f) { $totalSize += filesize($f); }
              }
          }
        @endphp
        <div class="card mt-3">
          <div class="card-header"><i class="bi bi-hdd me-1"></i> Estado del almacenamiento local</div>
          <div class="card-body">
            <table class="table table-sm mb-0">
              <tr><td>Pósters</td><td class="text-end">{{ $posterCount }} archivos</td></tr>
              <tr><td>Backdrops</td><td class="text-end">{{ $backdropCount }} archivos</td></tr>
              <tr><td>Fotos de personas</td><td class="text-end">{{ $peopleCount }} archivos</td></tr>
              <tr class="fw-bold"><td>Espacio total</td><td class="text-end">{{ number_format($totalSize / 1024 / 1024, 1) }} MB</td></tr>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
