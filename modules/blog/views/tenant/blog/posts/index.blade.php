@extends('layouts.app')

@section('title', $title)

@section('styles')
<style>
  /* Estilos para la tabla ordenable */
  .sortable {
    cursor: pointer;
  }

  /* Selector de registros por página */
  .posts-per-page-selector {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
  }
  .posts-per-page-selector label {
    margin-right: 0.5rem;
    margin-bottom: 0;
    font-size: 0.875rem;
  }
  .posts-per-page-selector select {
    width: auto;
  }
</style>
@endsection

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botón Añadir Post --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <a href="{{ route('tenant.blog.posts.create') }}" class="btn btn-primary">{{ __('blog.post.add_post') }}</a>
    </div>

    {{-- Alertas con SweetAlert2 --}}
    @if (session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: {!! json_encode(__('common.success')) !!},
            text: {!! json_encode(session('success')) !!},
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
            title: {!! json_encode(__('common.error')) !!},
            text: {!! json_encode(session('error')) !!},
            confirmButtonColor: '#d33'
          });
        });
      </script>
    @endif

    {{-- Formulario de Búsqueda y Selector de registros por página --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <form method="GET" action="{{ route('tenant.blog.posts.index') }}" class="d-flex align-items-center">
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('blog.post.search_placeholder') }}" class="form-control form-control-sm me-2" style="width: 250px;" id="search-input">
        <button type="submit" class="btn btn-outline-secondary btn-sm me-2">{{ __('common.search') }}</button>
        @if (!empty($search))
          <a href="{{ route('tenant.blog.posts.index') }}" class="btn btn-outline-danger btn-sm">{{ __('common.clear_filter') }}</a>
        @endif
      </form>

      <div class="posts-per-page-selector">
        <label for="perPage">{{ __('common.show') }}</label>
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
    <form method="POST" action="{{ route('tenant.blog.posts.bulk') }}" id="bulkActionForm">
      @csrf
      <div class="card">
        <div class="card-body table-responsive p-0">
          @if (!empty($posts) && count($posts) > 0)
          <table class="table table-hover align-middle" id="postsTable">
            <thead>
              <tr>
                <th style="width: 1%;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                <th class="sortable" data-sort-col="1">{{ __('blog.post.title') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="2">{{ __('blog.post.author') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="3">{{ __('blog.post.categories') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="4">{{ __('blog.post.status') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="5">{{ __('blog.post.language') }} <i class="fas fa-sort text-muted ms-1"></i></th>
                <th class="sortable" data-sort-col="6">{{ __('blog.post.published_at') }} <i class="fas fa-sort-down text-primary ms-1"></i></th>
              </tr>
            </thead>
            <tbody>
            {{-- Bucle para mostrar los posts --}}
            @foreach ($posts as $post)
              @php
                // Obtenemos datos asociados
                $author = $authors[$post->user_id] ?? null;
                $baseLang = $post->base_locale ?? setting('language', 'es');
                $status = $post->status === 'published' ? __('blog.post.published') : __('blog.post.draft');

                // Formato de fecha desde settings
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $dateTimeFormat = $dateFormat . ' ' . $timeFormat;

                // Obtener categorías del post (usando PHP nativo, no Laravel Collection)
                $postCategories = $post->categories ?? [];
                // Extraer nombres de las primeras 2 categorías
                $categoryNamesArray = array_slice(array_map(function($cat) {
                    return is_object($cat) ? $cat->name : ($cat['name'] ?? '');
                }, $postCategories), 0, 2);
                $categoryNames = implode(', ', $categoryNamesArray);
                if (count($postCategories) > 2) {
                  $categoryNames .= ' +' . (count($postCategories) - 2);
                }
              @endphp
              <tr data-id="{{ $post->id }}">
                <td><input type="checkbox" name="selected[]" value="{{ $post->id }}" class="form-check-input select-item"></td>
                <td>
                  <strong>{{ e($post->title) }}</strong>
                  @if($post->featured)
                    <span class="badge rounded-pill bg-warning text-dark ms-1" style="font-size: 0.7em; vertical-align: middle;">{{ __('blog.post.featured') }}</span>
                  @endif
                  @if(!$post->allow_comments)
                    <span class="badge rounded-pill bg-secondary ms-1" style="font-size: 0.7em; vertical-align: middle;"><i class="bi bi-chat-slash"></i></span>
                  @endif
                  <br>
                  <small>
                    <a href="{{ route('tenant.blog.posts.edit', ['id' => $post->id]) }}">{{ __('common.edit') }}</a>
                    @if ($post->status === 'published')
                       |
                      <a href="/blog/{{ $post->slug }}" target="_blank" rel="noopener noreferrer">{{ __('blog.post.view_post') }}</a>
                    @endif
                     |
                    <a href="#" class="delete-post-link" data-post-id="{{ $post->id }}" data-post-title="{{ htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8') }}" style="color: #dc3545; text-decoration: none;">{{ __('common.delete') }}</a>
                  </small>
                </td>
                <td>{{ $author?->name ?? '—' }}</td>
                <td><small class="text-muted">{{ $categoryNames ?: '—' }}</small></td>
                <td>
                  {{-- Estado --}}
                  <span class="badge {{ $post->status === 'published' ? 'bg-success' : 'bg-secondary' }}">
                    {{ $status }}
                  </span>

                  {{-- Badge Visibilidad --}}
                  @if($post->visibility === 'private')
                    <span class="badge bg-danger ms-1">{{ __('blog.post.private') }}</span>
                  @endif

                  @if($post->visibility === 'password')
                    <span class="badge bg-warning text-dark ms-1">{{ __('blog.post.protected') }}</span>
                  @endif
                </td>
                <td>{{ strtoupper($baseLang) }}</td>
                <td data-date="{{ $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : ($post->created_at ? $post->created_at->format('Y-m-d H:i:s') : '') }}">
                  {{ $post->published_at ? $post->published_at->format($dateTimeFormat) : ($post->created_at ? $post->created_at->format($dateTimeFormat) : '—') }}
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
          @else
            <div class="p-3 text-center">
              <p class="text-muted">{{ __('blog.post.no_posts_found') }}</p>
               @if(empty($search))
                 <a href="{{ route('tenant.blog.posts.create') }}" class="btn btn-sm btn-primary">{{ __('blog.post.create_first_post') }}</a>
               @endif
            </div>
          @endif
        </div>
      </div>

      {{-- Acciones en lote y Paginación --}}
      <div class="d-flex justify-content-between align-items-center mt-3">
        @if (!empty($posts) && count($posts) > 0)
        <div class="d-flex">
          <select name="action" class="form-select form-select-sm me-2" id="bulkActionSelect" style="width: auto;" required>
            <option value="">{{ __('blog.bulk.actions') }}</option>
            <option value="edit">{{ __('blog.bulk.edit') }}</option>
            <option value="delete">{{ __('blog.bulk.delete') }}</option>
            <option value="published">{{ __('blog.bulk.publish_selected') }}</option>
            <option value="draft">{{ __('blog.bulk.set_as_draft') }}</option>
            <option value="public">{{ __('blog.bulk.make_public') }}</option>
            <option value="private">{{ __('blog.bulk.make_private') }}</option>
            <option value="featured">{{ __('blog.bulk.mark_as_featured') }}</option>
            <option value="unfeatured">{{ __('blog.bulk.remove_featured') }}</option>
          </select>
          <button class="btn btn-secondary btn-sm" type="submit" id="applyBulkAction" disabled>{{ __('common.apply') }}</button>
        </div>
        @else
         <div></div>
        @endif
        @if (!empty($pagination) && isset($pagination['last_page']) && $pagination['last_page'] > 1)
            {{-- Paginación --}}
           {!! pagination_links($pagination, http_build_query(request()->except('page')), 'sm') !!}
        @endif
      </div>
    </form>

    {{-- Formularios de eliminación --}}
    @if (!empty($posts) && count($posts) > 0)
      @foreach ($posts as $post)
        <form method="POST" action="{{ route('tenant.blog.posts.destroy', ['id' => $post->id]) }}" style="display: none;" id="delete-form-{{ $post->id }}">
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
          title: {!! json_encode(__('blog.bulk.selection_required')) !!},
          text: {!! json_encode(__('blog.bulk.select_at_least_one_post')) !!}
        });
        return false;
      }

      // Verificar que haya una acción seleccionada
      const action = actionSelect.value;
      if (!action) {
        Swal.fire({
          icon: 'warning',
          title: {!! json_encode(__('blog.bulk.action_required')) !!},
          text: {!! json_encode(__('blog.bulk.select_action')) !!}
        });
        return false;
      }

      // Para acciones que requieren confirmación
      if (action === 'delete') {
        Swal.fire({
          title: {!! json_encode(__('common.are_you_sure')) !!},
          text: {!! json_encode(__('blog.bulk.confirm_delete_posts', ['count' => '${selectedCount}'])) !!}.replace(':count', selectedCount),
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: {!! json_encode(__('blog.bulk.confirm_delete_yes')) !!},
          cancelButtonText: {!! json_encode(__('blog.bulk.confirm_cancel')) !!}
        }).then((result) => {
          if (result.isConfirmed) {
            bulkForm.submit();
          }
        });
      } else if (action === 'published' || action === 'draft' ||
                action === 'public' || action === 'private' ||
                action === 'featured' || action === 'unfeatured') {
        // Acciones que cambian estados
        const actionLabels = {
          'published': {!! json_encode(__('blog.bulk.confirm_action_publish', ['count' => ':count'])) !!},
          'draft': {!! json_encode(__('blog.bulk.confirm_action_draft', ['count' => ':count'])) !!},
          'public': {!! json_encode(__('blog.bulk.confirm_action_public', ['count' => ':count'])) !!},
          'private': {!! json_encode(__('blog.bulk.confirm_action_private', ['count' => ':count'])) !!},
          'featured': {!! json_encode(__('blog.bulk.confirm_action_featured', ['count' => ':count'])) !!},
          'unfeatured': {!! json_encode(__('blog.bulk.confirm_action_unfeatured', ['count' => ':count'])) !!}
        };

        Swal.fire({
          title: {!! json_encode(__('blog.bulk.confirm_change')) !!},
          text: actionLabels[action].replace(':count', selectedCount),
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#6c757d',
          confirmButtonText: {!! json_encode(__('blog.bulk.confirm_yes_continue')) !!},
          cancelButtonText: {!! json_encode(__('blog.bulk.confirm_cancel')) !!}
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

  // Event listener para eliminar posts (delegación de eventos)
  document.addEventListener('click', function(e) {
    const deleteLink = e.target.closest('.delete-post-link');
    if (!deleteLink) return;

    e.preventDefault();

    const postId = deleteLink.getAttribute('data-post-id');
    const postTitle = deleteLink.getAttribute('data-post-title');

    Swal.fire({
      title: {!! json_encode(__('common.are_you_sure')) !!},
      html: {!! json_encode(__('blog.delete.confirm_delete_post', ['title' => ':title'])) !!}.replace(':title', escapeHtml(postTitle)),
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: {!! json_encode(__('blog.bulk.confirm_delete_yes')) !!},
      cancelButtonText: {!! json_encode(__('blog.bulk.confirm_cancel')) !!}
    }).then((result) => {
      if (result.isConfirmed) {
        // Buscar y enviar el formulario oculto
        const formId = `delete-form-${postId}`;
        const form = document.getElementById(formId);

        if (!form) {
          Swal.fire({
            icon: 'error',
            title: {!! json_encode(__('common.error')) !!},
            text: {!! json_encode(__('blog.delete.form_not_found')) !!}
          });
          return;
        }

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
  const table = document.getElementById('postsTable');
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
