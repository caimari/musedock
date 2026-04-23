@extends('layouts.app')

@section('title', $title . ' | ' . site_setting('site_name', 'IberoFilms'))
@section('description', 'Descubre películas del cine iberoamericano. Fichas editoriales con contexto, director, reparto y dónde verlas.')

@section('content')
<div class="container py-5 films-page">

  {{-- Header --}}
  <div class="text-center mb-4">
    <h1 class="display-5 fw-bold">{{ $title }}</h1>
    <p class="lead text-muted">Cine iberoamericano: fichas editoriales, directores y más</p>
  </div>

  {{-- Search bar --}}
  <div class="card mb-4 films-filter-card border-0 shadow-sm">
    <div class="card-body py-3 px-3">
      <form id="films-search-form" method="GET" action="/films">
        <div class="row g-2 align-items-end">
          <div class="col-md-3 col-6">
            <input type="text" name="q" id="film-q" value="{{ $search ?? '' }}" class="form-control films-input" placeholder="Título, director...">
          </div>
          <div class="col-md-2 col-6">
            <select name="genre" class="form-select films-input">
              <option value="">Género</option>
              @foreach($genres as $g)
                <option value="{{ $g->slug }}" {{ ($genreFilter ?? '') === $g->slug ? 'selected' : '' }}>{{ $g->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 col-4">
            <input type="number" name="year" value="{{ $yearFilter ?? '' }}" class="form-control films-input" placeholder="Año" min="1900" max="2030">
          </div>
          <div class="col-md-3 col-8">
            {{-- Custom multiselect dropdown for countries --}}
            @php $cf = $countryFilter ?? []; @endphp
            <div class="films-country-dropdown" id="country-dropdown">
              <button type="button" class="films-input films-country-btn w-100 text-start" id="country-toggle">
                <span id="country-label">{{ empty($cf) ? 'País' : count($cf) . ' países' }}</span>
                <svg class="films-country-arrow" width="12" height="12" viewBox="0 0 12 12"><path d="M3 5l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
              </button>
              <div class="films-country-panel" id="country-panel">
                @php
                  $countries = [
                    'ES'=>'España','MX'=>'México','AR'=>'Argentina','CO'=>'Colombia','CL'=>'Chile',
                    'PE'=>'Perú','UY'=>'Uruguay','PY'=>'Paraguay','BO'=>'Bolivia','EC'=>'Ecuador',
                    'VE'=>'Venezuela','CU'=>'Cuba','DO'=>'Rep. Dominicana','PR'=>'Puerto Rico',
                    'GT'=>'Guatemala','HN'=>'Honduras','SV'=>'El Salvador','NI'=>'Nicaragua',
                    'CR'=>'Costa Rica','PA'=>'Panamá','BR'=>'Brasil','PT'=>'Portugal'
                  ];
                @endphp
                @foreach($countries as $code => $name)
                  <label class="films-country-option">
                    <input type="checkbox" name="country[]" value="{{ $code }}" {{ in_array($code, $cf) ? 'checked' : '' }}>
                    <span>{{ $name }}</span>
                  </label>
                @endforeach
              </div>
            </div>
          </div>
          <div class="col-md-2 col-12">
            <button type="submit" class="btn films-input films-btn w-100">Buscar</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Local results --}}
  <p class="text-muted mb-3">{{ $pagination['total_posts'] ?? 0 }} películas en catálogo</p>

  @if(!empty($films) && count($films) > 0)
  <div class="row g-4" id="local-results">
    @foreach($films as $film)
    <div class="col-6 col-md-4 col-lg-3">
      <div class="card h-100 border-0 shadow-sm film-card">
        <a href="/films/{{ $film->slug }}" class="text-decoration-none">
          @if($film->poster_path)
            <img src="{{ film_poster_url($film->poster_path) }}" alt="{{ $film->title }}" class="card-img-top" style="aspect-ratio:2/3;object-fit:cover;">
          @else
            <div class="card-img-top bg-dark d-flex align-items-center justify-content-center text-muted" style="aspect-ratio:2/3;">
              <i class="bi bi-camera-reels" style="font-size:2.5rem;"></i>
            </div>
          @endif
          @if($film->tmdb_rating)
            <span class="position-absolute top-0 end-0 m-2 badge bg-warning text-dark fw-bold" style="font-size:0.75rem;">
              {{ number_format($film->tmdb_rating, 1) }}
            </span>
          @endif
          <div class="card-body p-2 pb-0">
            <h6 class="card-title mb-1 text-dark" style="font-size:0.9rem;">{{ $film->title }}</h6>
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted">{{ $film->year ?? '' }}</small>
              <small class="text-muted">{{ $film->director ?? '' }}</small>
            </div>
          </div>
        </a>
        @if(!empty($filmGenres[$film->id]))
          <div class="px-2 pb-2 pt-1">
            @foreach(array_slice($filmGenres[$film->id], 0, 3) as $g)
              <a href="/films/genero/{{ $g['slug'] }}" class="badge rounded-pill text-decoration-none" style="background:{{ $g['color'] ?? '#6c757d' }};font-size:0.65rem;color:#fff !important;">{{ $g['name'] }}</a>
            @endforeach
          </div>
        @endif
      </div>
    </div>
    @endforeach
  </div>

  @if(($pagination['total_pages'] ?? 1) > 1)
  <nav class="mt-5 d-flex justify-content-center">
    <ul class="pagination">
      @for($p = 1; $p <= $pagination['total_pages']; $p++)
        <li class="page-item {{ $p == $pagination['current_page'] ? 'active' : '' }}">
          <a class="page-link" href="/films?page={{ $p }}&q={{ urlencode($search ?? '') }}&genre={{ urlencode($genreFilter ?? '') }}&year={{ $yearFilter ?? '' }}">{{ $p }}</a>
        </li>
      @endfor
    </ul>
  </nav>
  @endif
  @endif

  {{-- TMDb results (only if API enabled) --}}
  @if($apiEnabled ?? false)
  <div id="tmdb-section" style="display:none;">
    <hr class="my-4">
    <h5 class="mb-3" style="color:var(--content-heading-color,#0f172a);">
      <i class="fas fa-globe me-1"></i> También en TMDb
      <small class="text-muted fw-normal ms-1" style="font-size:0.75rem;">click para añadir al catálogo</small>
    </h5>
    <div id="tmdb-results-grid" class="row g-3"></div>
    <div id="tmdb-loading" class="text-center py-3" style="display:none;">
      <div class="spinner-border spinner-border-sm text-muted"></div>
      <small class="text-muted ms-2">Buscando en TMDb...</small>
    </div>
    <div id="tmdb-pagination" class="d-flex justify-content-center align-items-center gap-2 mt-3" style="display:none;">
      <button type="button" id="tmdb-prev" style="background:transparent;color:#6c757d;border:1px solid #6c757d;padding:4px 14px;border-radius:4px;font-size:0.85rem;cursor:pointer;">&laquo; Anterior</button>
      <span id="tmdb-page-info" style="color:#888;font-size:0.82rem;"></span>
      <button type="button" id="tmdb-next" style="background:transparent;color:#6c757d;border:1px solid #6c757d;padding:4px 14px;border-radius:4px;font-size:0.85rem;cursor:pointer;">Siguiente &raquo;</button>
    </div>
  </div>
  @endif

  @if(empty($films) || count($films) === 0)
  <div class="text-center py-5 text-muted" id="no-results">
    <i class="bi bi-camera-reels" style="font-size:3rem;"></i>
    <p class="mt-3">No se encontraron películas.</p>
  </div>
  @endif

</div>

<style>
/* Uniform height for all inputs */
.films-input {
  height: 42px !important;
  font-size: 0.9rem !important;
  border-radius: 6px !important;
  border: 1px solid #d1d5db !important;
}
.films-input:focus {
  border-color: var(--header-link-hover-color, #ff5e15) !important;
  box-shadow: 0 0 0 0.2rem rgba(255,94,21,0.12) !important;
}
.films-btn {
  background: var(--header-link-hover-color, #ff5e15) !important;
  color: #fff !important;
  border: none !important;
  font-weight: 600 !important;
  cursor: pointer;
  transition: opacity 0.2s;
}
.films-btn:hover { opacity: 0.9; }
/* Country multiselect dropdown */
.films-country-dropdown { position: relative; }
.films-country-btn {
  background: #fff !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  cursor: pointer;
  color: #6b7280;
}
.films-country-arrow { transition: transform 0.2s; flex-shrink: 0; }
.films-country-dropdown.open .films-country-arrow { transform: rotate(180deg); }
.films-country-panel {
  display: none;
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  z-index: 100;
  max-height: 280px;
  overflow-y: auto;
  padding: 6px 0;
  margin-top: 2px;
}
.films-country-dropdown.open .films-country-panel { display: block; }
.films-country-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  cursor: pointer;
  font-size: 0.85rem;
  color: #374151;
  transition: background 0.1s;
  margin: 0;
}
.films-country-option:hover { background: #f3f4f6; }
.films-country-option input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--header-link-hover-color, #ff5e15);
  cursor: pointer;
  flex-shrink: 0;
}
.film-card { transition: transform 0.2s, box-shadow 0.2s; overflow: hidden; border-radius: 8px; }
.film-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; }
.films-page .page-item.active .page-link {
  background-color: var(--header-link-hover-color, #ff5e15);
  border-color: var(--header-link-hover-color, #ff5e15);
}
.films-page .page-link { color: var(--content-text-color, #334155); }
.films-page .page-link:hover { color: var(--header-link-hover-color, #ff5e15); }
/* TMDb result cards */
.tmdb-card {
  border-radius: 6px;
  overflow: hidden;
  transition: transform 0.2s;
  position: relative;
}
.tmdb-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.12) !important; }
.tmdb-card img { aspect-ratio: 2/3; object-fit: cover; width: 100%; }
.tmdb-badge-new {
  position: absolute; top: 6px; left: 6px;
  background: rgba(0,0,0,0.6); color: #fff;
  font-size: 0.55rem; padding: 2px 6px; border-radius: 3px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // ── Country multiselect dropdown ──
  var countryDD = document.getElementById('country-dropdown');
  var countryToggle = document.getElementById('country-toggle');
  var countryLabel = document.getElementById('country-label');
  if (countryDD && countryToggle) {
    countryToggle.addEventListener('click', function(e) {
      e.preventDefault();
      countryDD.classList.toggle('open');
    });
    // Close on click outside
    document.addEventListener('click', function(e) {
      if (!countryDD.contains(e.target)) countryDD.classList.remove('open');
    });
    // Update label on checkbox change
    countryDD.addEventListener('change', function() {
      var checked = countryDD.querySelectorAll('input[type="checkbox"]:checked');
      countryLabel.textContent = checked.length === 0 ? 'País' : checked.length + ' país' + (checked.length > 1 ? 'es' : '');
    });
  }

  var searchForm = document.getElementById('films-search-form');
  var qInput = document.getElementById('film-q');
  var tmdbSection = document.getElementById('tmdb-section');
  var tmdbGrid = document.getElementById('tmdb-results-grid');
  var tmdbLoading = document.getElementById('tmdb-loading');
  var posterBase = 'https://image.tmdb.org/t/p/';

  // Only run TMDb JS if the section exists (API enabled)
  if (tmdbSection && tmdbGrid) {

  window.filmTmdb = { page: 1, totalPages: 1, q: '', countries: [], year: '', cache: {} };

  function getSelectedCountries() {
    var checks = document.querySelectorAll('#country-panel input[type="checkbox"]:checked');
    return Array.from(checks).map(function(c) { return c.value; });
  }

  // After form submits and page loads, also search TMDb
  var query = qInput ? qInput.value.trim() : '';
  var countries = getSelectedCountries();
  if (query.length >= 2 || countries.length > 0) {
    searchTmdb(query, countries);
  }

  // Also search TMDb on form submit via AJAX (without blocking)
  if (searchForm) {
    searchForm.addEventListener('submit', function() {
      var q = qInput.value.trim();
      var cc = getSelectedCountries();
      if (q.length >= 2 || cc.length > 0) {
        setTimeout(function() { searchTmdb(q, cc); }, 100);
      }
    });
  }

  function searchTmdb(q, countries) {
    if (q !== undefined) window.filmTmdb.q = q || '';
    if (countries !== undefined) window.filmTmdb.countries = countries || [];
    var yearEl = document.querySelector('input[name="year"]');
    window.filmTmdb.year = (yearEl && yearEl.value) ? yearEl.value : '';

    tmdbSection.style.display = 'block';
    tmdbLoading.style.display = 'block';
    tmdbGrid.innerHTML = '';
    var pg = document.getElementById('tmdb-pagination');
    if (pg) pg.style.display = 'none';

    var p = [];
    p.push('page=' + window.filmTmdb.page);
    if (window.filmTmdb.q) p.push('q=' + encodeURIComponent(window.filmTmdb.q));
    if (window.filmTmdb.countries.length > 0) p.push('country=' + window.filmTmdb.countries.join(','));
    if (window.filmTmdb.year) p.push('year=' + window.filmTmdb.year);

    var url = '/films/tmdb-search?' + p.join('&');

    // Check cache
    if (window.filmTmdb.cache[url]) {
      tmdbLoading.style.display = 'none';
      renderTmdbResults(window.filmTmdb.cache[url]);
      return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function() {
      if (xhr.readyState !== 4) return;
      tmdbLoading.style.display = 'none';
      if (xhr.status !== 200) {
        tmdbGrid.innerHTML = '<div class="col-12 text-center text-muted py-3"><small>Error HTTP ' + xhr.status + '</small></div>';
        return;
      }
      try {
        var json = JSON.parse(xhr.responseText);
        window.filmTmdb.cache[url] = json;
        renderTmdbResults(json);
      } catch(e) {
        tmdbGrid.innerHTML = '<div class="col-12 text-center text-muted py-3"><small>Error al procesar respuesta</small></div>';
      }
    };
    xhr.onerror = function() {
      tmdbLoading.style.display = 'none';
      tmdbGrid.innerHTML = '<div class="col-12 text-center text-muted py-3"><small>Error de conexión</small></div>';
    };
    xhr.send();
  }

  function renderTmdbResults(json) {
    if (!json.success || !json.data || !json.data.results) {
      tmdbSection.style.display = 'none';
      return;
    }

    window.filmTmdb.totalPages = Math.min(json.data.total_pages || 1, 50);
    var totalResults = json.data.total_results || 0;
    var movies = json.data.results.filter(function(m) { return !m.already_imported; });

    if (movies.length === 0 && window.filmTmdb.page === 1) {
      tmdbSection.style.display = 'none';
      return;
    }

    tmdbGrid.innerHTML = '';
    movies.forEach(function(m) {
      var poster = m.poster_path ? posterBase + 'w185' + m.poster_path : '';
      var yr = m.release_date ? m.release_date.substring(0, 4) : '';
      var rating = m.vote_average ? m.vote_average.toFixed(1) : '';

      var card = '<div class="col-6 col-md-3 col-lg-2">' +
        '<a href="/films/ver/' + m.id + '" class="text-decoration-none">' +
        '<div class="card border-0 shadow-sm tmdb-card">' +
        (poster ? '<img src="' + poster + '" alt="" loading="lazy">' : '<div style="aspect-ratio:2/3;background:#f1f5f9;" class="d-flex align-items-center justify-content-center"><i class="bi bi-film text-muted" style="font-size:1.5rem;"></i></div>') +
        (rating ? '<span class="position-absolute top-0 end-0 m-1 badge bg-warning text-dark" style="font-size:0.6rem;">' + rating + '</span>' : '') +
        '<span class="tmdb-badge-new">TMDb</span>' +
        '<div class="p-1">' +
        '<p class="mb-0" style="font-size:0.75rem;font-weight:600;color:#333;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + m.title + '</p>' +
        '<small style="color:#999;font-size:0.68rem;">' + yr + '</small>' +
        '</div></div></a></div>';
      tmdbGrid.insertAdjacentHTML('beforeend', card);
    });

    // Show pagination
    var pg = document.getElementById('tmdb-pagination');
    var pgInfo = document.getElementById('tmdb-page-info');
    var pgPrev = document.getElementById('tmdb-prev');
    var pgNext = document.getElementById('tmdb-next');
    if (window.filmTmdb.totalPages > 1 && pg) {
      pg.style.display = 'flex';
      if (pgInfo) pgInfo.textContent = 'Página ' + window.filmTmdb.page + ' de ' + window.filmTmdb.totalPages + ' (' + totalResults.toLocaleString() + ' películas)';
      if (pgPrev) { pgPrev.disabled = window.filmTmdb.page <= 1; pgPrev.style.opacity = window.filmTmdb.page <= 1 ? '0.4' : '1'; }
      if (pgNext) { pgNext.disabled = window.filmTmdb.page >= window.filmTmdb.totalPages; pgNext.style.opacity = window.filmTmdb.page >= window.filmTmdb.totalPages ? '0.4' : '1'; }
    }

    // If no movies to show but there were results (all imported), show message
    if (movies.length === 0) {
      tmdbGrid.innerHTML = '<div class="col-12 text-center text-muted py-3"><small>Todas las películas de esta página ya están en el catálogo</small></div>';
    }
  }

  // Pagination buttons (use event delegation since elements are read dynamically)
  document.addEventListener('click', function(e) {
    if (e.target.closest('#tmdb-prev')) {
      if (window.filmTmdb.page > 1) {
        window.filmTmdb.page--;
        searchTmdb();
        document.getElementById('tmdb-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
    if (e.target.closest('#tmdb-next')) {
      if (window.filmTmdb.page < window.filmTmdb.totalPages) {
        window.filmTmdb.page++;
        searchTmdb();
        document.getElementById('tmdb-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  });

  } // end if tmdbSection
});
</script>

@include('films/partials/_image_fallback')
@endsection
