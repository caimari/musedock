@extends('layouts.app')
@section('title', $title)

@section('styles')
<style>
.source-card { cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
.source-card:hover { border-color: #0d6efd; transform: translateY(-2px); }
.source-card.selected { border-color: #0d6efd; background: #f0f7ff; }
.source-card.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
#results-table tbody tr.imported { opacity: 0.5; background: #f0fdf4; }
#results-table tbody tr:hover:not(.imported) { background: #f8f9fa; }
.fest-logo { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; background: #f1f1f1; }
</style>
@endsection

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2><i class="bi bi-cloud-download me-2"></i>{{ $title }}</h2>
        <small class="text-muted">Importa festivales desde directorios externos</small>
      </div>
      <a href="{{ festival_admin_url() }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Festivales</a>
    </div>

    {{-- Source Selection --}}
    <div class="row mb-4 g-3">
      @foreach($sources as $key => $source)
      <div class="col-md-3">
        <div class="card source-card {{ !$source['enabled'] ? 'disabled' : '' }}" data-source="{{ $key }}">
          <div class="card-body text-center py-3">
            <i class="bi {{ $source['icon'] }}" style="font-size:1.5rem;color:{{ $source['enabled'] ? '#0d6efd' : '#adb5bd' }}"></i>
            <h6 class="mt-2 mb-0">{{ $source['name'] }}</h6>
            @if(!$source['enabled'])
              <small class="text-danger">No disponible</small>
            @endif
          </div>
        </div>
      </div>
      @endforeach
    </div>

    {{-- Results Panel --}}
    <div id="search-panel" style="display:none">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
          <div>
            <strong><span id="source-name"></span></strong>
            <span id="results-info" class="text-muted ms-2" style="font-size:0.85rem"></span>
          </div>
          <button id="btn-load-more" class="btn btn-sm btn-outline-primary" style="display:none">
            <i class="bi bi-arrow-down-circle me-1"></i> Cargar siguiente página
          </button>
        </div>

        <div class="card-body p-0">
          {{-- Loading --}}
          <div id="results-loading" class="text-center py-4" style="display:none">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2 mb-0">Conectando con el directorio externo...</p>
          </div>

          {{-- Table --}}
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="results-table" style="display:none">
              <thead class="table-light">
                <tr>
                  <th style="width:40px"></th>
                  <th>Festival</th>
                  <th style="width:100px" class="text-center">Estado</th>
                  <th style="width:180px" class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody id="results-body"></tbody>
            </table>
          </div>

          {{-- Empty --}}
          <div id="results-empty" class="text-center py-5" style="display:none">
            <i class="bi bi-inbox" style="font-size:2.5rem;color:#dee2e6"></i>
            <p class="text-muted mt-2">No se encontraron festivales.</p>
          </div>
        </div>

        {{-- Footer with load more --}}
        <div class="card-footer d-flex justify-content-between align-items-center py-2" id="results-footer" style="display:none">
          <small class="text-muted" id="results-footer-info"></small>
          <button id="btn-load-more-bottom" class="btn btn-sm btn-primary" style="display:none">
            <i class="bi bi-arrow-down-circle me-1"></i> Cargar siguiente página
          </button>
        </div>
      </div>

      {{-- How it works --}}
      <div class="alert alert-light border mt-3" style="font-size:0.85rem">
        <strong><i class="bi bi-info-circle me-1"></i> ¿Cómo funciona?</strong>
        <ol class="mb-0 mt-1 ps-3">
          <li>Al seleccionar una fuente, <strong>tu servidor</strong> se conecta al directorio externo y descarga la lista de festivales.</li>
          <li>Los resultados se muestran en tu navegador. Puedes <strong>previsualizar</strong> los datos antes de importar.</li>
          <li>Al importar, tu servidor obtiene los datos completos (país, fechas, contacto, redes...) y crea el festival como <strong>borrador</strong>.</li>
          <li>"Cargar siguiente página" descarga la siguiente tanda del directorio (el orden lo determina la fuente).</li>
        </ol>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  let currentSource = null;
  let currentPage = 0;
  let totalLoaded = 0;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

  // Source selection
  document.querySelectorAll('.source-card:not(.disabled)').forEach(card => {
    card.addEventListener('click', function() {
      document.querySelectorAll('.source-card').forEach(c => c.classList.remove('selected'));
      this.classList.add('selected');
      currentSource = this.dataset.source;
      currentPage = 0;
      totalLoaded = 0;
      document.getElementById('source-name').textContent = this.querySelector('h6').textContent;
      document.getElementById('search-panel').style.display = 'block';
      document.getElementById('results-body').innerHTML = '';
      document.getElementById('results-table').style.display = 'none';
      loadMore();
    });
  });

  // Load more buttons (header + footer)
  document.getElementById('btn-load-more').addEventListener('click', loadMore);
  document.getElementById('btn-load-more-bottom').addEventListener('click', loadMore);

  function loadMore() {
    currentPage++;
    const loading = document.getElementById('results-loading');
    const btnMore = document.getElementById('btn-load-more');
    const btnMoreBottom = document.getElementById('btn-load-more-bottom');

    loading.style.display = 'block';
    btnMore.style.display = 'none';
    btnMoreBottom.style.display = 'none';

    fetch(`{{ festival_admin_url('scraper/search') }}?source=${currentSource}&page=${currentPage}`)
      .then(r => r.json())
      .then(data => {
        loading.style.display = 'none';

        if (data.error) {
          Swal.fire({ icon: 'error', title: 'Error', text: data.error });
          return;
        }

        if (data.festivals.length === 0 && currentPage === 1) {
          document.getElementById('results-empty').style.display = 'block';
          return;
        }

        document.getElementById('results-empty').style.display = 'none';
        document.getElementById('results-table').style.display = 'table';
        document.getElementById('results-footer').style.display = 'flex';

        const tbody = document.getElementById('results-body');
        data.festivals.forEach(fest => {
          tbody.insertAdjacentHTML('beforeend', renderRow(fest));
        });

        totalLoaded += data.festivals.length;

        if (data.hasMore) {
          btnMore.style.display = 'inline-block';
          btnMoreBottom.style.display = 'inline-block';
        }

        const totalText = data.total ? ` de ${data.total}` : '';
        document.getElementById('results-info').textContent = `${totalLoaded}${totalText} (pág. ${currentPage})`;
        document.getElementById('results-footer-info').textContent = `${totalLoaded}${totalText} cargados — página ${currentPage}`;

        bindImportButtons();
      })
      .catch(err => {
        loading.style.display = 'none';
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
      });
  }

  function renderRow(fest) {
    const imported = fest.already_imported;
    const trClass = imported ? ' class="imported"' : '';

    let statusBadge = '';
    if (imported && fest.duplicate) {
      const tip = fest.duplicate.match_type === 'fuzzy'
        ? `Similar (${fest.duplicate.similarity}%) a "${escHtml(fest.duplicate.name)}"`
        : 'Ya importado';
      statusBadge = `<span class="badge bg-success" title="${tip}"><i class="bi bi-check-lg"></i> Existente</span>`;
    } else {
      statusBadge = '<span class="badge bg-light text-dark border">Nuevo</span>';
    }

    let actions = '';
    if (imported) {
      actions = '<span class="text-muted small">Ya en tu base de datos</span>';
    } else {
      actions = `
        <button class="btn btn-sm btn-outline-primary btn-preview" data-source="${fest.source}" data-id="${fest.source_id}" data-name="${escHtml(fest.name)}" title="Previsualizar datos">
          <i class="bi bi-eye"></i>
        </button>
        <button class="btn btn-sm btn-success btn-quick-import" data-source="${fest.source}" data-id="${fest.source_id}" data-name="${escHtml(fest.name)}" title="Importar como borrador">
          <i class="bi bi-download me-1"></i> Importar
        </button>`;
    }

    // Extra info line (deadline, genre, prize, country)
    const extras = [];
    if (fest.date_text) extras.push(`<i class="bi bi-calendar-event me-1"></i>${fest.date_text}`);
    if (fest.genre) extras.push(`<i class="bi bi-tag me-1"></i>${escHtml(fest.genre)}`);
    if (fest.prize) extras.push(`<i class="bi bi-trophy me-1"></i>${escHtml(fest.prize.substring(0, 60))}`);
    if (fest.country) extras.push(`<i class="bi bi-geo-alt me-1"></i>${escHtml(fest.country)}`);
    const extrasHtml = extras.length > 0
      ? `<br><small class="text-muted">${extras.join(' &middot; ')}</small>`
      : '';

    // Logo or icon fallback
    const logoHtml = fest.logo
      ? `<img src="${fest.logo}" class="fest-logo" alt="" onerror="this.style.display='none'">`
      : `<div class="fest-logo d-flex align-items-center justify-content-center" style="background:#f1f1f1"><i class="bi ${fest.source === 'escritores' ? 'bi-book' : 'bi-film'} text-muted"></i></div>`;

    const detailLink = fest.festhome_url || fest.detail_url || '#';
    const sourceName = fest.source === 'escritores' ? 'escritores.org' : fest.source;

    return `<tr${trClass} id="result-${fest.source_id}">
      <td>${logoHtml}</td>
      <td>
        <strong style="font-size:0.9rem">${escHtml(fest.name)}</strong>${extrasHtml}
        <br><a href="${detailLink}" target="_blank" rel="noopener" class="text-muted small text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i>Ver en ${escHtml(sourceName)}</a>
      </td>
      <td class="text-center">${statusBadge}</td>
      <td class="text-end">${actions}</td>
    </tr>`;
  }

  function bindImportButtons() {
    // Preview
    document.querySelectorAll('.btn-preview:not(.bound)').forEach(btn => {
      btn.classList.add('bound');
      btn.addEventListener('click', function() {
        const source = this.dataset.source;
        const id = this.dataset.id;
        const name = this.dataset.name;

        Swal.fire({
          title: 'Obteniendo datos...',
          html: '<div class="spinner-border text-primary"></div><p class="mt-2 text-muted">' + escHtml(name) + '</p>',
          showConfirmButton: false, allowOutsideClick: false,
        });

        fetch(`{{ festival_admin_url('scraper/detail') }}?source=${source}&id=${id}`)
          .then(r => r.json())
          .then(data => {
            if (data.error) { Swal.fire({ icon: 'error', title: 'Error', text: data.error }); return; }

            const f = data.festival;
            const cats = (f.auto_categories || []).join(', ') || '—';
            const tags = (f.auto_tags || []).join(', ') || '—';

            Swal.fire({
              title: f.name || name,
              width: '720px',
              html: `<div class="text-start" style="max-height:450px;overflow-y:auto;">
                <table class="table table-sm table-striped" style="font-size:0.85rem">
                  <tbody>
                    ${f.country ? `<tr><th style="width:140px">País</th><td>${escHtml(f.country)}</td></tr>` : ''}
                    ${f.city ? `<tr><th>Ciudad</th><td>${escHtml(f.city)}</td></tr>` : ''}
                    ${f.venue ? `<tr><th>Sede</th><td>${escHtml(f.venue)}</td></tr>` : ''}
                    ${f.start_date ? `<tr><th>Fechas</th><td>${f.start_date}${f.end_date ? ' — ' + f.end_date : ''}</td></tr>` : ''}
                    ${f.deadline_date ? `<tr><th>Deadline</th><td>${f.deadline_date}</td></tr>` : ''}
                    ${f.website_url ? `<tr><th>Web</th><td><a href="${f.website_url}" target="_blank">${f.website_url}</a></td></tr>` : ''}
                    ${f.email ? `<tr><th>Email</th><td>${f.email}</td></tr>` : ''}
                    ${f.phone ? `<tr><th>Teléfono</th><td>${f.phone}</td></tr>` : ''}
                    ${f.social_facebook ? `<tr><th>Facebook</th><td><a href="${f.social_facebook}" target="_blank">Link</a></td></tr>` : ''}
                    ${f.social_instagram ? `<tr><th>Instagram</th><td><a href="${f.social_instagram}" target="_blank">Link</a></td></tr>` : ''}
                    ${f.social_twitter ? `<tr><th>Twitter/X</th><td><a href="${f.social_twitter}" target="_blank">Link</a></td></tr>` : ''}
                    ${f.submission_festhome_url ? `<tr><th>Festhome</th><td><a href="${f.submission_festhome_url}" target="_blank">Link</a></td></tr>` : ''}
                    <tr><th>Categorías auto</th><td><small class="text-muted">${cats}</small></td></tr>
                    <tr><th>Tags auto</th><td><small class="text-muted">${tags}</small></td></tr>
                  </tbody>
                </table>
                ${f.description ? `<div class="border-top pt-2 mt-2"><p style="font-size:0.82rem;color:#555">${escHtml(f.description).substring(0, 600)}${(f.description||'').length > 600 ? '...' : ''}</p></div>` : ''}
              </div>`,
              showCancelButton: true,
              confirmButtonText: '<i class="bi bi-download me-1"></i> Importar',
              cancelButtonText: 'Cancelar',
              confirmButtonColor: '#198754',
            }).then(result => {
              if (result.isConfirmed) doImport(f);
            });
          })
          .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message }));
      });
    });

    // Quick import
    document.querySelectorAll('.btn-quick-import:not(.bound)').forEach(btn => {
      btn.classList.add('bound');
      btn.addEventListener('click', function() {
        const source = this.dataset.source;
        const id = this.dataset.id;
        const name = this.dataset.name;
        const btnEl = this;

        Swal.fire({
          title: 'Importar festival',
          text: '"' + name + '" — Se creará como borrador.',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Importar',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#198754',
        }).then(result => {
          if (!result.isConfirmed) return;

          btnEl.disabled = true;
          btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

          fetch(`{{ festival_admin_url('scraper/detail') }}?source=${source}&id=${id}`)
            .then(r => r.json())
            .then(data => {
              if (data.error || !data.festival) {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Sin datos.' });
                btnEl.disabled = false; btnEl.innerHTML = '<i class="bi bi-download me-1"></i> Importar';
                return;
              }
              doImport(data.festival, btnEl);
            })
            .catch(err => {
              Swal.fire({ icon: 'error', title: 'Error', text: err.message });
              btnEl.disabled = false; btnEl.innerHTML = '<i class="bi bi-download me-1"></i> Importar';
            });
        });
      });
    });
  }

  function doImport(festData, btnEl, extraParams) {
    const body = new URLSearchParams();
    Object.keys(festData).forEach(key => {
      const val = festData[key];
      if (val !== null && val !== undefined && !Array.isArray(val)) {
        body.append(key, val);
      }
    });
    // Send auto arrays as comma-separated
    if (festData.auto_categories) body.append('auto_categories', festData.auto_categories.join(','));
    if (festData.auto_tags) body.append('auto_tags', festData.auto_tags.join(','));
    body.append('_token', csrfToken);
    if (extraParams) Object.keys(extraParams).forEach(k => body.append(k, extraParams[k]));

    fetch('{{ festival_admin_url("scraper/import") }}', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body,
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        Swal.fire({
          icon: data.merged ? 'info' : 'success',
          title: data.merged ? 'Link añadido' : 'Importado',
          html: data.message + '<br><a href="' + data.edit_url + '" class="btn btn-sm btn-primary mt-2">Editar festival</a>',
        });
        // Mark row
        const row = document.getElementById('result-' + festData.source_id);
        if (row) {
          row.classList.add('imported');
          const actionsCell = row.querySelector('td:last-child');
          if (actionsCell) actionsCell.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>' + (data.merged ? 'Vinculado' : 'Importado') + '</span>';
          const statusCell = row.querySelector('td:nth-child(3)');
          if (statusCell) statusCell.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-lg"></i></span>';
        }
      } else if (data.duplicate) {
        Swal.fire({
          title: 'Festival similar encontrado',
          html: `<p>${data.message}</p><p class="small text-muted">Puedes <strong>vincular</strong> el link al existente o <strong>importar igualmente</strong>.</p>`,
          icon: 'warning',
          showDenyButton: true, showCancelButton: true,
          confirmButtonText: '<i class="bi bi-link-45deg me-1"></i> Vincular link',
          denyButtonText: '<i class="bi bi-plus-lg me-1"></i> Importar nuevo',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#0d6efd', denyButtonColor: '#198754',
        }).then(result => {
          if (result.isConfirmed) doImport(festData, btnEl, { merge_into: data.existing.id });
          else if (result.isDenied) doImport(festData, btnEl, { force_import: '1' });
          else if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="bi bi-download me-1"></i> Importar'; }
        });
      } else {
        Swal.fire({ icon: 'warning', title: 'No importado', text: data.error || 'Error.' });
        if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="bi bi-download me-1"></i> Importar'; }
      }
    })
    .catch(err => {
      Swal.fire({ icon: 'error', title: 'Error', text: err.message });
      if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="bi bi-download me-1"></i> Importar'; }
    });
  }

  function escHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }
});
</script>
@endpush
