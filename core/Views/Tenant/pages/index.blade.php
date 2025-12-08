@extends('layouts.app')

@section('title', $title)

@section('styles')
<style>
  .sortable {
    cursor: pointer;
  }

  .pages-per-page-selector {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
  }
  .pages-per-page-selector label {
    margin-right: 0.5rem;
    margin-bottom: 0;
    font-size: 0.875rem;
  }
  .pages-per-page-selector select {
    width: auto;
  }
</style>
@endsection

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botón Añadir Página --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <a href="{{ route('tenant.pages.create') }}" class="btn btn-primary">{{ __('pages.add_page') }}</a>
    </div>

    {{-- Alertas con SweetAlert2 --}}
    @if (session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: '{{ __('common.correct') }}',
            text: <?php echo json_encode(session('success'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            confirmButtonColor: '#3085d6'
          });
        });
      </script>
    @endif
    @if (session('error'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'error',
            title: '{{ __('common.error') }}',
            text: <?php echo json_encode(session('error'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            confirmButtonColor: '#d33'
          });
        });
      </script>
    @endif

    {{-- Formulario de Búsqueda y Selector de registros por página --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <form method="GET" action="{{ route('tenant.pages.index') }}" class="d-flex align-items-center">
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('pages.search_placeholder') }}" class="form-control form-control-sm me-2" style="width: 250px;" id="search-input">
        <button type="submit" class="btn btn-outline-secondary btn-sm me-2">{{ __('common.search') }}</button>
        @if (!empty($search))
          <a href="{{ route('tenant.pages.index') }}" class="btn btn-outline-danger btn-sm">{{ __('common.clear_filter') }}</a>
        @endif
      </form>

      <div class="pages-per-page-selector">
        <label for="perPage">{{ __('common.show') }}:</label>
        <select class="form-select form-select-sm" id="perPage">
          <option value="10" {{ (isset($_GET['perPage']) && $_GET['perPage'] == 10) ? 'selected' : '' }}>10</option>
          <option value="25" {{ (isset($_GET['perPage']) && $_GET['perPage'] == 25) ? 'selected' : '' }}>25</option>
          <option value="50" {{ (isset($_GET['perPage']) && $_GET['perPage'] == 50) ? 'selected' : '' }}>50</option>
          <option value="100" {{ (isset($_GET['perPage']) && $_GET['perPage'] == 100) ? 'selected' : '' }}>100</option>
          <option value="-1" {{ (isset($_GET['perPage']) && $_GET['perPage'] == -1) ? 'selected' : '' }}>{{ __('common.all') }}</option>
        </select>
      </div>
    </div>

    <div class="card">
      <div class="card-body table-responsive p-0">
        @if (!empty($pages) && count($pages) > 0)
        <table class="table table-hover align-middle" id="pagesTable">
          <thead>
            <tr>
              <th class="sortable" data-sort-col="1">{{ __('pages.title') }} <i class="fas fa-sort text-muted ms-1"></i></th>
              <th class="sortable" data-sort-col="2">{{ __('pages.author') }} <i class="fas fa-sort text-muted ms-1"></i></th>
              <th class="sortable" data-sort-col="3">{{ __('pages.base_language') }} <i class="fas fa-sort text-muted ms-1"></i></th>
              <th class="sortable" data-sort-col="4">{{ __('pages.status') }} <i class="fas fa-sort text-muted ms-1"></i></th>
              <th class="sortable" data-sort-col="5">{{ __('pages.template') }} <i class="fas fa-sort text-muted ms-1"></i></th>
              <th class="sortable" data-sort-col="6">{{ __('pages.publish_date') }} <i class="fas fa-sort-down text-primary ms-1"></i></th>
              <th style="width: 100px;">{{ __('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
          @foreach ($pages as $Page)
            @php
              $author = $authors[$Page->user_id] ?? null;
              $baseLang = $Page->base_locale ?? setting('language', 'es');
              $status = $Page->status === 'published' ? __('pages.published') : __('pages.draft');

              $dateFormat = setting('date_format', 'd/m/Y');
              $timeFormat = setting('time_format', 'H:i');
              $dateTimeFormat = $dateFormat . ' ' . $timeFormat;

              $templateName = '';
              try {
                if (isset($Page->meta) && isset($Page->meta->page_template)) {
                  $templateName = (string) $Page->meta->page_template;
                } else {
                  $templateName = \Screenart\Musedock\Models\PageMeta::getMeta($Page->id, 'page_template', 'page.blade.php');
                  $templateName = (string) $templateName;
                }
              } catch (\Exception $e) {
                $templateName = 'page.blade.php';
              }

              $availableTemplates = get_page_templates();
              $templateLabel = isset($availableTemplates[$templateName]) ? (string) $availableTemplates[$templateName] : $templateName;
            @endphp
            <tr data-id="{{ $Page->id }}">
              <td>
                <strong>{{ e($Page->title) }}</strong>
                @if(isset($homepageId) && $Page->id === $homepageId)
                  <span class="badge rounded-pill bg-primary ms-1" style="font-size: 0.7em; vertical-align: middle;">{{ __('pages.homepage_badge') }}</span>
                @endif
                <br>
                <small>
                  <a href="{{ route('tenant.pages.edit', ['id' => $Page->id]) }}">{{ __('common.edit') }}</a>
                  @if ($Page->status === 'published')
                     |
                    <a href="/p/{{ $Page->slug }}" target="_blank" rel="noopener noreferrer">{{ __('pages.view_page') }}</a>
                  @endif
                </small>
              </td>
              <td>{{ $author?->name ?? '—' }}</td>
              <td>{{ strtoupper($baseLang) }}</td>
              <td>
                <span class="badge {{ $Page->status === 'published' ? 'bg-success' : 'bg-secondary' }}">
                  {{ $status }}
                </span>

                @if($Page->visibility === 'private')
                  <span class="badge bg-danger ms-1">{{ __('pages.private') }}</span>
                @endif

                @if($Page->visibility === 'members')
                  <span class="badge bg-info ms-1">{{ __('pages.members') }}</span>
                @endif
              </td>
              <td><small class="text-muted">{{ $templateLabel }}</small></td>
              <td data-date="{{ $Page->published_at ? $Page->published_at->format('Y-m-d H:i:s') : ($Page->created_at ? $Page->created_at->format('Y-m-d H:i:s') : '') }}">
                {{ $Page->published_at ? $Page->published_at->format($dateTimeFormat) : ($Page->created_at ? $Page->created_at->format($dateTimeFormat) : '—') }}
              </td>
              <td>
                <form method="POST" action="{{ route('tenant.pages.delete', ['id' => $Page->id]) }}" id="delete-form-{{ $Page->id }}" style="display: inline;">
                  {!! csrf_field() !!}
                  @method('DELETE')
                  <button type="button" onclick="confirmDelete({{ $Page->id }})" class="btn btn-sm btn-danger" title="{{ __('common.delete') }}">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
        @else
          <div class="p-3 text-center">
            <p class="text-muted">{{ __('pages.no_pages_found') }}</p>
             @if(empty($search))
               <a href="{{ route('tenant.pages.create') }}" class="btn btn-sm btn-primary">{{ __('pages.create_first_page') }}</a>
             @endif
          </div>
        @endif
      </div>
    </div>

    {{-- Paginación --}}
    @if (!empty($pagination) && isset($pagination['last_page']) && $pagination['last_page'] > 1)
      <div class="d-flex justify-content-end mt-3">
        {!! pagination_links($pagination, http_build_query(request()->except('page')), 'sm') !!}
      </div>
    @endif

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Función de confirmación para eliminar una página
  window.confirmDelete = function(id) {
    Swal.fire({
      title: '{{ __('pages.confirm_delete_title') }}',
      text: "{{ __('pages.confirm_delete_message') }}",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: '{{ __('common.yes_delete') }}',
      cancelButtonText: '{{ __('common.cancel') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        document.getElementById('delete-form-' + id).submit();
      }
    });
    return false;
  }

  // Selector de registros por página
  const perPageSelect = document.getElementById('perPage');
  if (perPageSelect) {
    perPageSelect.addEventListener('change', function() {
      const currentUrl = new URL(window.location.href);
      currentUrl.searchParams.set('perPage', this.value);
      window.location.href = currentUrl.toString();
    });
  }

  // Ordenación de tabla
  const table = document.getElementById('pagesTable');
  if (table) {
    const headers = table.querySelectorAll('th.sortable');
    headers.forEach(header => {
      header.addEventListener('click', function() {
        const column = this.getAttribute('data-sort-col');
        sortTable(table, parseInt(column), this);
      });
    });

    // Ordenar por fecha al cargar (columna 6)
    const dateHeader = table.querySelector('th.sortable[data-sort-col="6"]');
    if (dateHeader) {
      setTimeout(function() {
        dateHeader.click();
      }, 100);
    }
  }

  // Función para ordenar la tabla
  function sortTable(table, columnIndex, header) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const icon = header.querySelector('i');

    const isAsc = icon.classList.contains('fa-sort') || icon.classList.contains('fa-sort-down');

    table.querySelectorAll('th.sortable i').forEach(i => {
      i.className = 'fas fa-sort text-muted ms-1';
    });

    if (isAsc) {
      icon.className = 'fas fa-sort-up text-primary ms-1';
    } else {
      icon.className = 'fas fa-sort-down text-primary ms-1';
    }

    rows.sort((a, b) => {
      let aValue, bValue;

      if (columnIndex === 1) {
        aValue = a.querySelector('td:nth-child(1) strong').textContent.trim();
        bValue = b.querySelector('td:nth-child(1) strong').textContent.trim();
      } else if (columnIndex === 6) {
        aValue = a.querySelector('td:nth-child(6)').getAttribute('data-date') || '';
        bValue = b.querySelector('td:nth-child(6)').getAttribute('data-date') || '';
      } else {
        aValue = a.querySelector(`td:nth-child(${columnIndex})`).textContent.trim();
        bValue = b.querySelector(`td:nth-child(${columnIndex})`).textContent.trim();
      }

      if (columnIndex === 6) {
        if (aValue < bValue) return isAsc ? 1 : -1;
        if (aValue > bValue) return isAsc ? -1 : 1;
      } else {
        if (aValue < bValue) return isAsc ? -1 : 1;
        if (aValue > bValue) return isAsc ? 1 : -1;
      }
      return 0;
    });

    rows.forEach(row => tbody.appendChild(row));
  }
});
</script>
@endpush
