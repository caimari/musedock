@extends('layouts.app')

@section('title', $title)

@section('styles')
<style>
  /* Estilos para la tabla ordenable */
  .sortable {
    cursor: pointer;
  }
  
  /* Selector de registros por página */
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

    {{-- Título y Botones de Acción --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <div class="d-flex gap-2">
        <a href="/musedock/pages/trash" class="btn btn-outline-danger" title="{{ __('pages.view_trash') }}">
          <i class="bi bi-trash me-1"></i> {{ __('pages.trash') }}
        </a>
        <a href="{{ route('pages.create') }}" class="btn btn-primary">
          <i class="bi bi-plus-lg me-1"></i> {{ __('pages.add_page') }}
        </a>
      </div>
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
      <form method="GET" action="{{ route('pages.index') }}" class="d-flex align-items-center">
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('pages.search_placeholder') }}" class="form-control form-control-sm me-2" style="width: 250px;" id="search-input">
        <button type="submit" class="btn btn-outline-secondary btn-sm me-2">{{ __('common.search') }}</button>
        @if (!empty($search))
          <a href="{{ route('pages.index') }}" class="btn btn-outline-danger btn-sm">{{ __('common.clear_filter') }}</a>
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

    {{-- Formulario Acciones en Lote --}}
    <form method="POST" action="{{ route('pages.bulk') }}" id="bulkActionForm">
      @csrf
      <div class="card">
        <div class="card-body table-responsive p-0">
          @if (!empty($pages) && count($pages) > 0)
          <table class="table table-hover align-middle" id="pagesTable">
            <thead>
              <tr>
                <th style="width: 1%;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                <th class="sortable" data-sort-col="1">{{ __('pages.title') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="2">{{ __('pages.author') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="3">{{ __('pages.base_language') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="4">{{ __('pages.status') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="5">{{ __('pages.template') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="6">{{ __('pages.publish_date') }} <i class="fas fa-sort-down text-primary ms-1"></i></th>
              </tr>
            </thead>
            <tbody>
            {{-- Bucle para mostrar las páginas --}}
            @foreach ($pages as $Page)
              @php
                // Obtenemos datos asociados
                $author = $authors[$Page->user_id] ?? null;
                $baseLang = $Page->base_locale ?? setting('language', 'es');
                $status = $Page->status === 'published' ? __('pages.published') : __('pages.draft');
                
                // Formato de fecha desde settings
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $dateTimeFormat = $dateFormat . ' ' . $timeFormat;
                
                // Datos de plantilla (convertir a string para evitar error de conversión)
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
                <td><input type="checkbox" name="selected[]" value="{{ $Page->id }}" class="form-check-input select-item"></td>
                <td>
                  <strong>{{ e($Page->title) }}</strong>
                  @if(isset($homepageId) && $Page->id === $homepageId)
                    <span class="badge rounded-pill bg-primary ms-1" style="font-size: 0.7em; vertical-align: middle;">{{ __('pages.homepage_badge') }}</span>
                  @endif
                  <br>
                  <small>
                    <a href="{{ route('pages.edit', ['id' => $Page->id]) }}">{{ __('common.edit') }}</a>
                    @if ($Page->status === 'published')
                       |
                      <a href="/p/{{ $Page->slug }}" target="_blank" rel="noopener noreferrer">{{ __('pages.view_page') }}</a>
                    @endif
                     |
                    <a href="#" class="delete-page-link" data-page-id="{{ $Page->id }}" data-page-title="{{ htmlspecialchars($Page->title, ENT_QUOTES, 'UTF-8') }}" style="color: #dc3545; text-decoration: none;">{{ __('common.delete') }}</a>
                  </small>
                </td>
                <td>{{ $author?->name ?? '—' }}</td>
                <td>{{ strtoupper($baseLang) }}</td>
                <td>
                  {{-- Estado --}}
                  <span class="badge {{ $Page->status === 'published' ? 'bg-success' : 'bg-secondary' }}">
                    {{ $status }}
                  </span>
                  
                  {{-- Badge Visibilidad --}}
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
              </tr>
            @endforeach
            </tbody>
          </table>
          @else
            <div class="p-3 text-center">
              <p class="text-muted">{{ __('pages.no_pages_found') }}</p>
               @if(empty($search))
                 <a href="{{ route('pages.create') }}" class="btn btn-sm btn-primary">{{ __('pages.create_first_page') }}</a>
               @endif
            </div>
          @endif
        </div>
      </div>

      {{-- Acciones en lote y Paginación --}}
      <div class="d-flex justify-content-between align-items-center mt-3">
        @if (!empty($pages) && count($pages) > 0)
        <div class="d-flex">
          <select name="action" class="form-select form-select-sm me-2" id="bulkActionSelect" style="width: auto;" required>
            <option value="">{{ __('pages.bulk_actions') }}</option>
            <option value="edit">{{ __('common.edit') }}</option>
            <option value="delete">{{ __('common.delete') }}</option>
            <option value="published">{{ __('pages.publish_selected') }}</option>
            <option value="draft">{{ __('pages.move_to_draft') }}</option>
            <option value="public">{{ __('pages.make_public') }}</option>
            <option value="private">{{ __('pages.make_private') }}</option>
            <option value="members">{{ __('pages.members_only') }}</option>
          </select>
          <button class="btn btn-secondary btn-sm" type="submit" id="applyBulkAction" disabled>{{ __('common.apply') }}</button>
        </div>
        @else
         <div></div>
        @endif
        @if (!empty($pagination) && isset($pagination['last_page']) && $pagination['last_page'] > 1)
            {{-- Paginación (pasando query y tamaño) --}}
           {!! pagination_links($pagination, http_build_query(request()->except('page')), 'sm') !!}
        @endif
      </div>
    </form>

    {{-- Formularios de eliminación (fuera de la tabla para evitar html fixup del navegador) --}}
    @if (!empty($pages) && count($pages) > 0)
      @foreach ($pages as $Page)
        <form method="POST" action="{{ route('pages.destroy', ['id' => $Page->id]) }}" style="display: none;" id="delete-form-{{ $Page->id }}">
          {!! csrf_field() !!}
          @method('DELETE')
        </form>
      @endforeach
    @endif

  </div> {{-- fin container-fluid --}}
</div> {{-- fin app-content --}}
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Referencia al checkbox "seleccionar todo"
  const selectAllCheckbox = document.getElementById('selectAll');
  
  // Referencias a todos los checkboxes individuales
  const checkboxes = document.querySelectorAll('.select-item');
  
  // Evento para seleccionar/deseleccionar todos
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
      checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
      });
      
      // Actualizar estado del botón
      updateButtonState();
    });
  }
  
  // Evento para cada checkbox individual
  checkboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      // Verificar si todos están seleccionados
      const allChecked = Array.from(checkboxes).every(cb => cb.checked);
      const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
      
      // Actualizar checkbox "seleccionar todo"
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = anyChecked && !allChecked;
      }
      
      // Actualizar estado del botón
      updateButtonState();
    });
  });
  
  // Referencia al formulario y botón de acción
  const bulkForm = document.getElementById('bulkActionForm');
  const actionSelect = document.getElementById('bulkActionSelect');
  const actionButton = document.getElementById('applyBulkAction');
  
  // Función para actualizar estado del botón
  function updateButtonState() {
    const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
    if (actionButton) {
      actionButton.disabled = !anyChecked;
    }
  }
  
  // Inicializar estado del botón
  updateButtonState();
  
  // Manejar envío del formulario
  if (bulkForm) {
    bulkForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Verificar que haya elementos seleccionados
      const selectedCount = document.querySelectorAll('.select-item:checked').length;
      if (selectedCount === 0) {
        Swal.fire({
          icon: 'warning',
          title: '{{ __('pages.selection_required') }}',
          text: '{{ __('pages.select_at_least_one') }}'
        });
        return false;
      }
      
      // Verificar que haya una acción seleccionada
      const action = actionSelect.value;
      if (!action) {
        Swal.fire({
          icon: 'warning',
          title: '{{ __('pages.action_required') }}',
          text: '{{ __('pages.select_action') }}'
        });
        return false;
      }
      
      // Para acciones que requieren confirmación
      if (action === 'delete') {
        Swal.fire({
          title: '{{ __('pages.confirm_delete_title') }}',
          text: `{{ __('pages.confirm_bulk_delete_message') }} ${selectedCount} {{ __('pages.pages_count') }}`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: '{{ __('common.yes_delete') }}',
          cancelButtonText: '{{ __('common.cancel') }}'
        }).then((result) => {
          if (result.isConfirmed) {
            bulkForm.submit();
          }
        });
      } else if (action === 'published' || action === 'draft' || 
                action === 'public' || action === 'private' || action === 'members') {
        // Acciones que cambian estados
        const actionLabels = {
          'published': 'publicar',
          'draft': 'pasar a borrador',
          'public': 'hacer públicas',
          'private': 'hacer privadas',
          'members': 'restringir a miembros'
        };
        
        Swal.fire({
          title: '¿Confirmar cambio?',
          text: `Vas a ${actionLabels[action]} ${selectedCount} página(s).`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Sí, continuar',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            bulkForm.submit();
          }
        });
      } else {
        // Otras acciones que no necesitan confirmación
        bulkForm.submit();
      }
    });
  }
  
  // Event listener para eliminar páginas (delegación de eventos)
  document.addEventListener('click', function(e) {
    const deleteLink = e.target.closest('.delete-page-link');
    if (!deleteLink) return;

    e.preventDefault();

    const pageId = deleteLink.getAttribute('data-page-id');
    const pageTitle = deleteLink.getAttribute('data-page-title');

    console.log('[DELETE] Enlace clickeado. ID:', pageId, 'Título:', pageTitle);

    Swal.fire({
      title: '{{ __('pages.confirm_delete_title') }}',
      html: `{{ __('pages.confirm_delete_page_message') }} <strong>"${escapeHtml(pageTitle)}"</strong>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: '{{ __('common.yes_delete') }}',
      cancelButtonText: '{{ __('common.cancel') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        console.log('[DELETE] Confirmado. Eliminando página ID:', pageId);

        // Buscar y enviar el formulario oculto
        const formId = `delete-form-${pageId}`;
        const form = document.getElementById(formId);

        if (!form) {
          console.error('[DELETE] Formulario no encontrado:', formId);
          Swal.fire({
            icon: 'error',
            title: '{{ __('common.error') }}',
            text: '{{ __('pages.form_not_found') }}'
          });
          return;
        }

        console.log('[DELETE] Formulario encontrado, enviando...');
        form.submit();
      }
    });
  });

  // Función auxiliar para escapar HTML
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
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
    
    // Determinar la dirección de ordenación
    const isAsc = icon.classList.contains('fa-sort') || icon.classList.contains('fa-sort-down');
    
    // Resetear todos los iconos
    table.querySelectorAll('th.sortable i').forEach(i => {
      i.className = 'fas fa-sort text-muted ms-1';
    });
    
    // Actualizar icono de ordenación
    if (isAsc) {
      icon.className = 'fas fa-sort-up text-primary ms-1';
    } else {
      icon.className = 'fas fa-sort-down text-primary ms-1';
    }
    
    // Ordenar las filas
    rows.sort((a, b) => {
      let aValue, bValue;
      
      // Obtener los valores según la columna
      if (columnIndex === 1) { // Título
        aValue = a.querySelector('td:nth-child(2) strong').textContent.trim();
        bValue = b.querySelector('td:nth-child(2) strong').textContent.trim();
      } else if (columnIndex === 6) { // Fecha (usamos el atributo data-date)
        aValue = a.querySelector('td:nth-child(7)').getAttribute('data-date') || '';
        bValue = b.querySelector('td:nth-child(7)').getAttribute('data-date') || '';
      } else {
        aValue = a.querySelector(`td:nth-child(${columnIndex+1})`).textContent.trim();
        bValue = b.querySelector(`td:nth-child(${columnIndex+1})`).textContent.trim();
      }
      
      // Comparar valores (orden descendente para fecha)
      if (columnIndex === 6) {
        if (aValue < bValue) return isAsc ? 1 : -1;
        if (aValue > bValue) return isAsc ? -1 : 1;
      } else {
        if (aValue < bValue) return isAsc ? -1 : 1;
        if (aValue > bValue) return isAsc ? 1 : -1;
      }
      return 0;
    });
    
    // Reordenar el DOM
    rows.forEach(row => tbody.appendChild(row));
  }
});
</script>
@endpush