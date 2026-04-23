@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-camera-reels me-2"></i>{{ $title }}</h2>
      <a href="{{ film_admin_url() }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
    </div>

    @if(session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          Swal.fire({ icon: 'success', title: 'OK', text: {!! json_encode(session('success')) !!}, timer: 3000 });
        });
      </script>
    @endif

    <form method="POST"
          action="{{ $isEdit ? film_admin_url($film->id) : film_admin_url() }}"
          enctype="multipart/form-data">
      @csrf
      @if($isEdit)
        <input type="hidden" name="_method" value="PUT">
      @endif

      <div class="row">
        {{-- Main content --}}
        <div class="col-lg-8">
          {{-- Basic info --}}
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i> Información básica</div>
            <div class="card-body">
              <div class="row mb-3">
                <div class="col-md-8">
                  <label class="form-label">Título <span class="text-danger">*</span></label>
                  <input type="text" name="title" class="form-control" value="{{ $film->title ?? '' }}" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Año</label>
                  <input type="number" name="year" class="form-control" value="{{ $film->year ?? '' }}" min="1888" max="2100">
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Título original</label>
                  <input type="text" name="original_title" class="form-control" value="{{ $film->original_title ?? '' }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Director</label>
                  <input type="text" name="director" class="form-control" value="{{ $film->director ?? '' }}">
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="form-label">Duración (min)</label>
                  <input type="number" name="runtime" class="form-control" value="{{ $film->runtime ?? '' }}">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Idioma original</label>
                  <input type="text" name="original_language" class="form-control" value="{{ $film->original_language ?? '' }}">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Países de producción</label>
                  <input type="text" name="production_countries" class="form-control" value="{{ $film->production_countries ?? '' }}" placeholder="Separados por coma">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Tagline</label>
                <input type="text" name="tagline" class="form-control" value="{{ $film->tagline ?? '' }}">
              </div>
            </div>
          </div>

          {{-- Editorial content --}}
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-pen me-1"></i> Contenido editorial</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Sinopsis editorial <small class="text-muted">(tu versión, la que posiciona en SEO)</small></label>
                <textarea name="synopsis_editorial" class="form-control" rows="4">{{ $film->synopsis_editorial ?? '' }}</textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Contenido editorial <small class="text-muted">(análisis, opinión, por qué importa)</small></label>
                <textarea name="editorial_content" class="form-control" rows="6">{{ $film->editorial_content ?? '' }}</textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Contexto editorial <small class="text-muted">(festivales, premios, contexto iberoamericano)</small></label>
                <textarea name="editorial_context" class="form-control" rows="3">{{ $film->editorial_context ?? '' }}</textarea>
              </div>
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="form-label">Puntuación editorial (0-10)</label>
                  <input type="number" name="editorial_rating" class="form-control" value="{{ $film->editorial_rating ?? '' }}" min="0" max="10" step="0.5">
                </div>
                <div class="col-md-8">
                  <label class="form-label">URL del tráiler</label>
                  <input type="url" name="trailer_url" class="form-control" value="{{ $film->trailer_url ?? '' }}" placeholder="https://www.youtube.com/watch?v=...">
                </div>
              </div>
            </div>
          </div>

          {{-- Watch Providers --}}
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-tv me-1"></i> Dónde verla</div>
            <div class="card-body">
              <div id="watch-providers-list">
                @php $providers = $isEdit ? json_decode($film->watch_providers_json ?? '[]', true) : []; @endphp
                @if(!empty($providers))
                  @foreach($providers as $i => $p)
                  <div class="row g-2 mb-2 wp-row">
                    <div class="col-md-5">
                      <input type="text" name="wp_name[]" class="form-control form-control-sm" value="{{ $p['name'] ?? '' }}" placeholder="Nombre (ej: Netflix)">
                    </div>
                    <div class="col-md-4">
                      <select name="wp_type[]" class="form-select form-select-sm">
                        <option value="Suscripción" {{ ($p['type'] ?? '') === 'Suscripción' ? 'selected' : '' }}>Suscripción</option>
                        <option value="Alquiler" {{ ($p['type'] ?? '') === 'Alquiler' ? 'selected' : '' }}>Alquiler</option>
                        <option value="Compra" {{ ($p['type'] ?? '') === 'Compra' ? 'selected' : '' }}>Compra</option>
                        <option value="Gratis" {{ ($p['type'] ?? '') === 'Gratis' ? 'selected' : '' }}>Gratis</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <input type="text" name="wp_logo[]" class="form-control form-control-sm" value="{{ $p['logo_path'] ?? '' }}" placeholder="Logo path">
                    </div>
                    <div class="col-md-1">
                      <button type="button" class="btn btn-sm btn-outline-danger wp-remove"><i class="bi bi-x"></i></button>
                    </div>
                  </div>
                  @endforeach
                @endif
              </div>
              <button type="button" id="wp-add" class="btn btn-sm btn-outline-secondary mt-1">
                <i class="bi bi-plus me-1"></i> Añadir plataforma
              </button>
              <input type="hidden" name="watch_providers_json" id="watch-providers-json" value="{{ $film->watch_providers_json ?? '[]' }}">
            </div>
          </div>

          <script>
          document.addEventListener('DOMContentLoaded', function() {
            var wpList = document.getElementById('watch-providers-list');
            document.getElementById('wp-add').addEventListener('click', function() {
              var row = document.createElement('div');
              row.className = 'row g-2 mb-2 wp-row';
              row.innerHTML = '<div class="col-md-5"><input type="text" name="wp_name[]" class="form-control form-control-sm" placeholder="Nombre (ej: Netflix)"></div>' +
                '<div class="col-md-4"><select name="wp_type[]" class="form-select form-select-sm"><option value="Suscripción">Suscripción</option><option value="Alquiler">Alquiler</option><option value="Compra">Compra</option><option value="Gratis">Gratis</option></select></div>' +
                '<div class="col-md-2"><input type="text" name="wp_logo[]" class="form-control form-control-sm" placeholder="Logo path"></div>' +
                '<div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger wp-remove"><i class="bi bi-x"></i></button></div>';
              wpList.appendChild(row);
            });
            wpList.addEventListener('click', function(e) {
              if (e.target.closest('.wp-remove')) e.target.closest('.wp-row').remove();
            });
            // Before submit: build JSON from fields
            document.querySelector('form').addEventListener('submit', function() {
              var names = document.querySelectorAll('input[name="wp_name[]"]');
              var types = document.querySelectorAll('select[name="wp_type[]"]');
              var logos = document.querySelectorAll('input[name="wp_logo[]"]');
              var arr = [];
              names.forEach(function(n, i) {
                if (n.value.trim()) arr.push({ name: n.value.trim(), type: types[i].value, logo_path: logos[i].value.trim() });
              });
              document.getElementById('watch-providers-json').value = JSON.stringify(arr);
            });
          });
          </script>

          {{-- TMDb data (read-only reference) --}}
          @if($isEdit && !empty($film->synopsis_tmdb))
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-database me-1"></i> Datos TMDb (referencia)</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label text-muted">Sinopsis TMDb</label>
                <div class="border rounded p-2 bg-light small">{{ $film->synopsis_tmdb }}</div>
              </div>
              <div class="row">
                <div class="col-md-3">
                  <small class="text-muted">TMDb ID:</small> {{ $film->tmdb_id ?? '—' }}
                </div>
                <div class="col-md-3">
                  <small class="text-muted">IMDb:</small> {{ $film->imdb_id ?? '—' }}
                </div>
                <div class="col-md-3">
                  <small class="text-muted">Rating TMDb:</small> {{ $film->tmdb_rating ?? '—' }}
                </div>
                <div class="col-md-3">
                  <small class="text-muted">Votos:</small> {{ $film->tmdb_vote_count ?? '—' }}
                </div>
              </div>
            </div>
          </div>
          @endif

          {{-- SEO --}}
          <div class="card mb-3">
            <div class="card-header"><i class="bi bi-search me-1"></i> SEO</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">SEO Title</label>
                <input type="text" name="seo_title" class="form-control" value="{{ $film->seo_title ?? '' }}" maxlength="70">
              </div>
              <div class="mb-3">
                <label class="form-label">SEO Description</label>
                <textarea name="seo_description" class="form-control" rows="2" maxlength="160">{{ $film->seo_description ?? '' }}</textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">SEO Keywords</label>
                <input type="text" name="seo_keywords" class="form-control" value="{{ $film->seo_keywords ?? '' }}">
              </div>
              <div class="form-check">
                <input type="hidden" name="noindex" value="0">
                <input type="checkbox" name="noindex" value="1" class="form-check-input" id="noindex" {{ !empty($film->noindex) ? 'checked' : '' }}>
                <label class="form-check-label" for="noindex">noindex (no indexar en Google)</label>
              </div>
            </div>
          </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
          {{-- Publish box --}}
          <div class="card mb-3">
            <div class="card-header">Publicar</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                  @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ ($film->status ?? 'draft') === $key ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="featured" value="0">
                <input type="checkbox" name="featured" value="1" class="form-check-input" id="featured" {{ !empty($film->featured) ? 'checked' : '' }}>
                <label class="form-check-label" for="featured">Destacada</label>
              </div>
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-lg me-1"></i> {{ $isEdit ? 'Guardar cambios' : 'Crear película' }}
              </button>
            </div>
          </div>

          {{-- Poster --}}
          <div class="card mb-3">
            <div class="card-header">Póster</div>
            <div class="card-body text-center">
              @if(!empty($film->poster_path))
                <img src="{{ film_poster_url($film->poster_path) }}" alt="{{ $film->title ?? '' }}" class="img-fluid rounded mb-2" style="max-height:300px;">
              @else
                <div class="border rounded p-4 text-muted mb-2">
                  <i class="bi bi-image" style="font-size:2rem;"></i>
                  <p class="mb-0 small">Sin póster</p>
                </div>
              @endif
              <input type="text" name="poster_path" class="form-control form-control-sm" value="{{ $film->poster_path ?? '' }}" placeholder="Path del póster TMDb">
            </div>
          </div>

          {{-- Genres --}}
          <div class="card mb-3">
            <div class="card-header">Géneros</div>
            <div class="card-body">
              @if(!empty($genres))
                @foreach($genres as $genre)
                  <div class="form-check">
                    <input type="checkbox" name="genres[]" value="{{ $genre->id }}" class="form-check-input"
                           id="genre-{{ $genre->id }}"
                           {{ in_array($genre->id, $selectedGenres ?? []) ? 'checked' : '' }}>
                    <label class="form-check-label" for="genre-{{ $genre->id }}">{{ $genre->name }}</label>
                  </div>
                @endforeach
              @else
                <p class="text-muted small mb-0">No hay géneros. <a href="{{ film_admin_url('genres/create') }}">Crear uno</a>.</p>
              @endif
            </div>
          </div>

          {{-- Links --}}
          @if($isEdit)
          <div class="card mb-3">
            <div class="card-header">Enlaces</div>
            <div class="card-body">
              @if($film->tmdb_id)
                <a href="https://www.themoviedb.org/movie/{{ $film->tmdb_id }}" target="_blank" class="d-block mb-1">
                  <i class="bi bi-box-arrow-up-right me-1"></i> Ver en TMDb
                </a>
              @endif
              @if($film->imdb_id)
                <a href="https://www.imdb.com/title/{{ $film->imdb_id }}" target="_blank" class="d-block mb-1">
                  <i class="bi bi-box-arrow-up-right me-1"></i> Ver en IMDb
                </a>
              @endif
              @if($film->status === 'published')
                <a href="/films/{{ $film->slug }}" target="_blank" class="d-block">
                  <i class="bi bi-eye me-1"></i> Ver ficha pública
                </a>
              @endif
            </div>
          </div>
          @endif
        </div>
      </div>
    </form>

  </div>
</div>
@endsection
