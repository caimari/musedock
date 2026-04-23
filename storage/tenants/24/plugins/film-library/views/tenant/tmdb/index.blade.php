@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-cloud-download me-2"></i>{{ $title }}</h2>
      <a href="{{ film_admin_url() }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver a Películas
      </a>
    </div>

    @if(!$hasApiKey)
    <div class="alert alert-warning">
      <i class="bi bi-exclamation-triangle me-1"></i>
      No hay API key de TMDb configurada.
      <a href="{{ film_admin_url('settings') }}" class="alert-link">Configurar ahora</a>.
    </div>
    @else

    {{-- Search + Filters --}}
    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-12 mb-1">
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" id="tmdb-search" class="form-control" placeholder="Buscar por título (opcional si usas filtros)..." autofocus>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-0">Género</label>
            <select id="tmdb-genre" class="form-select form-select-sm">
              <option value="">Todos</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-0">Año</label>
            <input type="number" id="tmdb-year" class="form-control form-control-sm" placeholder="2024" min="1900" max="2030">
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-0">País</label>
            <select id="tmdb-country" class="form-select form-select-sm">
              <option value="">Todos</option>
              <optgroup label="Iberoamérica">
                <option value="ES">España</option>
                <option value="MX">México</option>
                <option value="AR">Argentina</option>
                <option value="CO">Colombia</option>
                <option value="CL">Chile</option>
                <option value="PE">Perú</option>
                <option value="UY">Uruguay</option>
                <option value="PY">Paraguay</option>
                <option value="BO">Bolivia</option>
                <option value="EC">Ecuador</option>
                <option value="VE">Venezuela</option>
                <option value="CU">Cuba</option>
                <option value="DO">República Dominicana</option>
                <option value="PR">Puerto Rico</option>
                <option value="GT">Guatemala</option>
                <option value="HN">Honduras</option>
                <option value="SV">El Salvador</option>
                <option value="NI">Nicaragua</option>
                <option value="CR">Costa Rica</option>
                <option value="PA">Panamá</option>
                <option value="BR">Brasil</option>
                <option value="PT">Portugal</option>
              </optgroup>
              <optgroup label="Otros">
                <option value="US">Estados Unidos</option>
                <option value="GB">Reino Unido</option>
                <option value="FR">Francia</option>
                <option value="DE">Alemania</option>
                <option value="IT">Italia</option>
                <option value="JP">Japón</option>
                <option value="KR">Corea del Sur</option>
                <option value="IN">India</option>
                <option value="CN">China</option>
              </optgroup>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-0">Ordenar</label>
            <select id="tmdb-sort" class="form-select form-select-sm">
              <option value="popularity.desc">Más populares</option>
              <option value="vote_average.desc">Mejor valoradas</option>
              <option value="primary_release_date.desc">Más recientes</option>
              <option value="revenue.desc">Mayor recaudación</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="button" id="tmdb-search-btn" class="btn btn-primary btn-sm w-100">
              <i class="bi bi-search me-1"></i> Buscar
            </button>
          </div>
        </div>
      </div>
    </div>

    {{-- Bulk import bar (hidden until selection) --}}
    <div id="tmdb-bulk-bar" class="card mb-3 border-primary" style="display:none;">
      <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
          <input type="checkbox" id="tmdb-select-all" class="form-check-input me-2">
          <label for="tmdb-select-all" class="form-check-label fw-semibold">Seleccionar todas</label>
          <span id="tmdb-selected-count" class="badge bg-primary ms-2">0</span> seleccionadas
        </div>
        <button type="button" id="tmdb-bulk-import" class="btn btn-success btn-sm">
          <i class="bi bi-download me-1"></i> Importar seleccionadas
        </button>
      </div>
    </div>

    {{-- Import queue progress --}}
    <div id="tmdb-queue" class="card mb-3" style="display:none;">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-hourglass-split me-1"></i> Cola de importación</span>
        <span id="tmdb-queue-status" class="badge bg-info">0/0</span>
      </div>
      <div class="card-body p-0">
        <div id="tmdb-queue-list" style="max-height:300px;overflow-y:auto;"></div>
      </div>
      <div class="card-footer">
        <div class="progress" style="height:6px;">
          <div id="tmdb-queue-progress" class="progress-bar bg-success" style="width:0%"></div>
        </div>
      </div>
    </div>

    {{-- Results --}}
    <div id="tmdb-results" class="row g-3" style="display:none;"></div>
    <div id="tmdb-pagination" class="d-flex justify-content-center mt-3" style="display:none;"></div>
    <div id="tmdb-loading" class="text-center py-4" style="display:none;">
      <div class="spinner-border text-primary"></div>
      <p class="mt-2">Buscando en TMDb...</p>
    </div>
    <div id="tmdb-empty" class="text-center py-5 text-muted">
      <i class="bi bi-search" style="font-size:3rem;"></i>
      <p class="mt-2">Busca una película por título o usa los filtros para explorar.</p>
    </div>

    @endif
  </div>
</div>

@if($hasApiKey)
<script>
document.addEventListener('DOMContentLoaded', function() {
  var searchInput = document.getElementById('tmdb-search');
  var searchBtn = document.getElementById('tmdb-search-btn');
  var genreSelect = document.getElementById('tmdb-genre');
  var yearInput = document.getElementById('tmdb-year');
  var countrySelect = document.getElementById('tmdb-country');
  var sortSelect = document.getElementById('tmdb-sort');
  var resultsDiv = document.getElementById('tmdb-results');
  var paginationDiv = document.getElementById('tmdb-pagination');
  var loadingDiv = document.getElementById('tmdb-loading');
  var emptyDiv = document.getElementById('tmdb-empty');
  var bulkBar = document.getElementById('tmdb-bulk-bar');
  var selectAllCb = document.getElementById('tmdb-select-all');
  var selectedCount = document.getElementById('tmdb-selected-count');
  var bulkImportBtn = document.getElementById('tmdb-bulk-import');
  var queueCard = document.getElementById('tmdb-queue');
  var queueList = document.getElementById('tmdb-queue-list');
  var queueStatus = document.getElementById('tmdb-queue-status');
  var queueProgress = document.getElementById('tmdb-queue-progress');
  var posterBase = 'https://image.tmdb.org/t/p/';
  var currentPage = 1;
  var lastParams = {};
  var selectedMovies = {}; // { tmdbId: { id, title, poster_path, year } }

  var apiUrl = '{{ film_admin_url("tmdb/search") }}';

  function apiFetch(params) {
    var qs = Object.keys(params).map(function(k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');
    return fetch(apiUrl + '?' + qs, { credentials: 'same-origin' })
      .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        var ct = r.headers.get('content-type') || '';
        if (!ct.includes('json')) {
          return r.text().then(function(t) { throw new Error('Respuesta no JSON'); });
        }
        return r.json();
      });
  }

  // Load genres
  apiFetch({ action: 'genres' }).then(function(json) {
    if (json.success && json.genres) {
      json.genres.forEach(function(g) {
        var opt = document.createElement('option');
        opt.value = g.id;
        opt.textContent = g.name;
        genreSelect.appendChild(opt);
      });
    }
  }).catch(function() {});

  function updateSelectedCount() {
    var count = Object.keys(selectedMovies).length;
    selectedCount.textContent = count;
    bulkBar.style.display = count > 0 || resultsDiv.style.display === 'flex' ? 'block' : 'none';
    bulkImportBtn.disabled = count === 0;
  }

  // Select all
  selectAllCb.addEventListener('change', function() {
    var checked = this.checked;
    document.querySelectorAll('.tmdb-cb').forEach(function(cb) {
      cb.checked = checked;
      var id = cb.dataset.id;
      if (checked && !cb.disabled) {
        selectedMovies[id] = JSON.parse(cb.dataset.movie);
      } else {
        delete selectedMovies[id];
      }
    });
    updateSelectedCount();
  });

  function doSearch(page) {
    currentPage = page || 1;
    var q = searchInput.value.trim();
    var genre = genreSelect.value;
    var year = yearInput.value;
    var country = countrySelect.value;
    var sort = sortSelect.value;
    var hasFilters = genre || year || country;

    if (!q && !hasFilters) {
      Swal.fire('', 'Escribe un título o selecciona al menos un filtro.', 'info');
      return;
    }

    var params = { page: currentPage };
    if (q) params.q = q;
    if (genre) params.genre = genre;
    if (year) params.year = year;
    if (country) params.country = country;
    if (sort && !q) params.sort = sort;

    lastParams = params;
    fetchResults(params);
  }

  function fetchResults(params) {
    loadingDiv.style.display = 'block';
    resultsDiv.style.display = 'none';
    paginationDiv.style.display = 'none';
    emptyDiv.style.display = 'none';
    selectAllCb.checked = false;

    apiFetch(params)
      .then(function(json) {
        loadingDiv.style.display = 'none';
        if (!json.success) { Swal.fire('Error', json.error, 'error'); return; }

        var movies = json.data.results || [];
        var totalPages = Math.min(json.data.total_pages || 1, 500);
        var totalResults = json.data.total_results || 0;

        if (movies.length === 0) {
          emptyDiv.style.display = 'block';
          emptyDiv.innerHTML = '<i class="bi bi-search" style="font-size:3rem;"></i><p class="mt-2">No se encontraron resultados.</p>';
          bulkBar.style.display = 'none';
          return;
        }

        resultsDiv.innerHTML = '<div class="col-12 mb-1"><small class="text-muted">' + totalResults.toLocaleString() + ' resultados — página ' + currentPage + ' de ' + totalPages + '</small></div>';
        movies.forEach(function(m) {
          var poster = m.poster_path ? posterBase + 'w154' + m.poster_path : '';
          var year = m.release_date ? m.release_date.substring(0, 4) : '';
          var rating = m.vote_average ? m.vote_average.toFixed(1) : '';
          var imported = m.already_imported;
          var isSelected = selectedMovies[m.id] !== undefined;
          var movieData = JSON.stringify({ id: m.id, title: m.title, poster_path: m.poster_path || '', year: year }).replace(/"/g, '&quot;');

          var card = '<div class="col-xl-2 col-lg-2 col-md-3 col-sm-4 col-4">' +
            '<div class="card h-100" style="border-radius:6px;overflow:hidden;position:relative;">' +
            // Checkbox
            '<div style="position:absolute;top:4px;left:4px;z-index:2;">' +
            '<input type="checkbox" class="form-check-input tmdb-cb" data-id="' + m.id + '" data-movie="' + movieData + '"' +
            (imported ? ' disabled title="Ya importada"' : '') +
            (isSelected ? ' checked' : '') +
            ' style="width:20px;height:20px;cursor:pointer;">' +
            '</div>' +
            // Rating
            (rating ? '<span class="badge bg-warning text-dark" style="font-size:0.6rem;position:absolute;top:4px;right:4px;">' + rating + '</span>' : '') +
            // Poster (clickable for preview)
            '<div class="tmdb-preview" data-id="' + m.id + '" style="cursor:pointer;">' +
            (poster ? '<img src="' + poster + '" class="card-img-top" alt="" style="aspect-ratio:2/3;object-fit:cover;">' : '<div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="aspect-ratio:2/3;"><i class="bi bi-film" style="font-size:1.5rem;color:#ccc;"></i></div>') +
            '</div>' +
            '<div class="card-body p-1">' +
            '<p class="card-title mb-0" style="font-size:0.75rem;line-height:1.2;font-weight:600;">' + m.title +
            (imported ? ' <span class="badge bg-success" style="font-size:0.55rem;">Importada</span>' : '') +
            '</p>' +
            '<small class="text-muted" style="font-size:0.7rem;">' + year + '</small>' +
            '</div></div></div>';
          resultsDiv.insertAdjacentHTML('beforeend', card);
        });
        resultsDiv.style.display = 'flex';
        bulkBar.style.display = 'block';

        // Pagination
        if (totalPages > 1) {
          var pHtml = '';
          if (currentPage > 1) pHtml += '<button class="btn btn-sm btn-outline-primary me-1 tmdb-page" data-page="' + (currentPage - 1) + '"><i class="bi bi-chevron-left"></i> Anterior</button>';
          pHtml += '<span class="btn btn-sm btn-primary disabled me-1">' + currentPage + ' / ' + totalPages + '</span>';
          if (currentPage < totalPages) pHtml += '<button class="btn btn-sm btn-outline-primary tmdb-page" data-page="' + (currentPage + 1) + '">Siguiente <i class="bi bi-chevron-right"></i></button>';
          paginationDiv.innerHTML = pHtml;
          paginationDiv.style.display = 'flex';
        }

        // Bind checkbox changes
        document.querySelectorAll('.tmdb-cb').forEach(function(cb) {
          cb.addEventListener('change', function() {
            if (this.checked) {
              selectedMovies[this.dataset.id] = JSON.parse(this.dataset.movie);
            } else {
              delete selectedMovies[this.dataset.id];
            }
            updateSelectedCount();
          });
        });

        // Bind preview clicks (on poster, not checkbox)
        document.querySelectorAll('.tmdb-preview').forEach(function(el) {
          el.addEventListener('click', function() { showPreview(this.dataset.id); });
        });

        // Bind pagination
        document.querySelectorAll('.tmdb-page').forEach(function(el) {
          el.addEventListener('click', function() {
            currentPage = parseInt(this.dataset.page);
            lastParams.page = currentPage;
            fetchResults(lastParams);
            window.scrollTo(0, 0);
          });
        });

        updateSelectedCount();
      })
      .catch(function(err) {
        loadingDiv.style.display = 'none';
        Swal.fire('Error', 'Error: ' + err.message, 'error');
      });
  }

  searchBtn.addEventListener('click', function() { doSearch(1); });
  searchInput.addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); doSearch(1); } });

  // ========== BULK IMPORT ==========
  bulkImportBtn.addEventListener('click', function() {
    var ids = Object.keys(selectedMovies);
    if (ids.length === 0) return;

    Swal.fire({
      title: 'Importar ' + ids.length + ' películas',
      html: 'Se importarán una a una con una pausa de 1.5s entre cada una para respetar los límites de la API de TMDb.<br><br><strong>¿Continuar?</strong>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Importar todas',
      confirmButtonColor: '#198754',
      cancelButtonText: 'Cancelar'
    }).then(function(result) {
      if (result.isConfirmed) startBulkImport(ids);
    });
  });

  function startBulkImport(ids) {
    queueCard.style.display = 'block';
    queueList.innerHTML = '';
    var total = ids.length;
    var done = 0;
    var success = 0;
    var errors = 0;

    // Build queue list
    ids.forEach(function(id) {
      var m = selectedMovies[id];
      var row = document.createElement('div');
      row.id = 'q-' + id;
      row.className = 'px-3 py-2 border-bottom d-flex align-items-center justify-content-between';
      row.innerHTML = '<div class="d-flex align-items-center">' +
        (m.poster_path ? '<img src="' + posterBase + 'w45' + m.poster_path + '" class="rounded me-2" style="height:35px;">' : '<div class="rounded bg-light me-2 d-flex align-items-center justify-content-center" style="width:24px;height:35px;"><i class="bi bi-film text-muted"></i></div>') +
        '<div><small class="fw-semibold">' + m.title + '</small> <small class="text-muted">(' + (m.year || '') + ')</small></div></div>' +
        '<span class="q-status"><span class="badge bg-secondary">En cola</span></span>';
      queueList.appendChild(row);
    });

    queueStatus.textContent = '0/' + total;
    queueProgress.style.width = '0%';

    // Process one by one with delay
    function processNext(index) {
      if (index >= ids.length) {
        // Done
        queueStatus.textContent = done + '/' + total;
        queueStatus.className = 'badge bg-success';
        Swal.fire({
          icon: 'success',
          title: 'Importación completada',
          html: '<strong>' + success + '</strong> importadas correctamente' + (errors > 0 ? '<br><strong>' + errors + '</strong> con errores o ya importadas' : ''),
          confirmButtonText: 'Ver películas',
          confirmButtonColor: '#0d6efd',
          showCancelButton: true,
          cancelButtonText: 'Cerrar'
        }).then(function(r) {
          if (r.isConfirmed) window.location.href = '{{ film_admin_url() }}';
        });
        selectedMovies = {};
        updateSelectedCount();
        return;
      }

      var id = ids[index];
      var statusEl = document.querySelector('#q-' + id + ' .q-status');
      statusEl.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div>';

      // Scroll to current item
      var row = document.getElementById('q-' + id);
      if (row) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

      apiFetch({ action: 'import', tmdb_id: id })
        .then(function(json) {
          done++;
          if (json.success) {
            success++;
            statusEl.innerHTML = '<span class="badge bg-success"><i class="bi bi-check"></i> OK</span>';
          } else {
            errors++;
            statusEl.innerHTML = '<span class="badge bg-warning text-dark" title="' + (json.error || '') + '"><i class="bi bi-exclamation-triangle"></i> ' + (json.film_id ? 'Ya existe' : 'Error') + '</span>';
          }
        })
        .catch(function() {
          done++;
          errors++;
          statusEl.innerHTML = '<span class="badge bg-danger"><i class="bi bi-x"></i> Error</span>';
        })
        .finally(function() {
          queueStatus.textContent = done + '/' + total;
          queueProgress.style.width = Math.round((done / total) * 100) + '%';

          // Wait 1.5s before next (rate limiting)
          setTimeout(function() { processNext(index + 1); }, 1500);
        });
    }

    processNext(0);
  }

  // ========== PREVIEW ==========
  function showPreview(tmdbId) {
    Swal.fire({
      title: 'Cargando...', html: '<div class="spinner-border text-primary"></div>',
      showConfirmButton: false, allowOutsideClick: false, width: '800px'
    });

    apiFetch({ action: 'preview', id: tmdbId })
      .then(function(json) {
        if (!json.success) { Swal.fire('Error', json.error || 'Error', 'error'); return; }
        var d = json.data;
        var poster = d.poster_path ? posterBase + 'w342' + d.poster_path : '';
        var genres = (d.genres || []).map(function(g) { return '<span class="badge bg-secondary me-1">' + g.name + '</span>'; }).join('');
        var cast = (d._cast || []).map(function(a) { return a.name; }).join(', ');
        var providers = (d._watch_providers || []).map(function(p) { return p.name + ' (' + p.type + ')'; }).join(', ');
        var year = d.release_date ? d.release_date.substring(0, 4) : '';

        var html = '<div style="text-align:left;"><div style="display:flex;gap:20px;flex-wrap:wrap;">' +
          (poster ? '<div style="flex:0 0 200px;text-align:center;"><img src="' + poster + '" style="width:100%;border-radius:8px;"></div>' : '') +
          '<div style="flex:1;min-width:250px;">' +
          '<h4 style="margin:0 0 5px;">' + d.title + (year ? ' <small style="color:#888;">(' + year + ')</small>' : '') + '</h4>' +
          (d.original_title && d.original_title !== d.title ? '<p style="color:#999;margin:0 0 8px;font-size:0.85em;">' + d.original_title + '</p>' : '') +
          (d.tagline ? '<p style="color:#888;font-style:italic;margin:0 0 10px;">' + d.tagline + '</p>' : '') +
          '<p style="font-size:0.9em;line-height:1.5;">' + (d.overview || 'Sin sinopsis') + '</p>' +
          '<div style="margin-bottom:10px;">' + genres + '</div>' +
          '<table style="width:100%;font-size:0.85em;border-collapse:collapse;">' +
          '<tr><td style="padding:4px 10px 4px 0;font-weight:bold;width:110px;">Director</td><td style="padding:4px 0;">' + (d._director || '—') + '</td></tr>' +
          '<tr><td style="padding:4px 10px 4px 0;font-weight:bold;vertical-align:top;">Reparto</td><td style="padding:4px 0;">' + (cast || '—') + '</td></tr>' +
          '<tr><td style="padding:4px 10px 4px 0;font-weight:bold;">Duración</td><td style="padding:4px 0;">' + (d.runtime ? d.runtime + ' min' : '—') + '</td></tr>' +
          '<tr><td style="padding:4px 10px 4px 0;font-weight:bold;">Rating</td><td style="padding:4px 0;">' + (d.vote_average ? '<span style="background:#f5c518;color:#000;padding:2px 8px;border-radius:4px;font-weight:bold;">' + d.vote_average.toFixed(1) + '</span> /10 (' + d.vote_count + ' votos)' : '—') + '</td></tr>' +
          (d._trailer ? '<tr><td style="padding:4px 10px 4px 0;font-weight:bold;">Tráiler</td><td style="padding:4px 0;"><a href="' + d._trailer + '" target="_blank">Ver en YouTube</a></td></tr>' : '') +
          (providers ? '<tr><td style="padding:4px 10px 4px 0;font-weight:bold;vertical-align:top;">Dónde verla</td><td style="padding:4px 0;">' + providers + '</td></tr>' : '') +
          '</table></div></div></div>';

        Swal.fire({
          title: '', html: html, width: '800px',
          showCancelButton: true,
          confirmButtonText: '<i class="bi bi-download me-1"></i> Importar',
          confirmButtonColor: '#198754',
          cancelButtonText: 'Cerrar',
          customClass: { popup: 'text-start' }
        }).then(function(result) {
          if (result.isConfirmed) doSingleImport(tmdbId);
        });
      })
      .catch(function(err) { Swal.fire('Error', 'Error: ' + err.message, 'error'); });
  }

  function doSingleImport(tmdbId) {
    Swal.fire({
      title: 'Importando...', html: '<div class="spinner-border text-success"></div>',
      showConfirmButton: false, allowOutsideClick: false
    });
    apiFetch({ action: 'import', tmdb_id: tmdbId })
      .then(function(json) {
        if (json.success) {
          Swal.fire({
            icon: 'success', title: 'Importada', html: json.message,
            confirmButtonText: 'Editar ahora', confirmButtonColor: '#0d6efd',
            showCancelButton: true, cancelButtonText: 'Seguir importando'
          }).then(function(r) { if (r.isConfirmed) window.location.href = json.edit_url; });
        } else {
          Swal.fire({ icon: json.film_id ? 'info' : 'error', title: json.film_id ? 'Ya importada' : 'Error', text: json.error });
        }
      })
      .catch(function(err) { Swal.fire('Error', 'Error: ' + err.message, 'error'); });
  }
});
</script>
@endif
@endsection
