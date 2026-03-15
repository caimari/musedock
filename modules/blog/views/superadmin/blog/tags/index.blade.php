@extends('layouts.app')

@section('title', $title ?? 'Etiquetas')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botón Añadir Etiqueta --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title ?? __('blog.tags') }}</h2>
      @if (!empty($crossPublisherActive) && !empty($currentScope) && str_starts_with($currentScope, 'tenant:'))
        <a href="/musedock/blog/tags/create?tenant_id={{ substr($currentScope, 7) }}" class="btn btn-primary">
          <i class="bi bi-plus-lg me-1"></i> Crear etiqueta en {{ $scope['label'] ?? 'tenant' }}
        </a>
      @else
        <a href="{{ route('blog.tags.create') }}" class="btn btn-primary">{{ __('blog.tag.add_tag') }}</a>
      @endif
    </div>

    {{-- Filtro Cross-Publisher (solo si el plugin esta activo) --}}
    @if (!empty($crossPublisherActive))
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="/musedock/blog/tags" class="d-flex align-items-center gap-3 flex-wrap">
          @if (!empty($search))<input type="hidden" name="search" value="{{ $search }}">@endif
          <label class="form-label mb-0 fw-bold text-nowrap"><i class="bi bi-funnel me-1"></i> Filtrar por:</label>
          <select name="scope" class="form-select form-select-sm" style="width: auto; min-width: 280px;" onchange="this.form.submit()">
            <option value="mine" @if(($currentScope ?? 'mine') === 'mine') selected @endif>Mis etiquetas (Superadmin)</option>
            @foreach ($groups as $group)
              <optgroup label="{{ $group->name }} ({{ $group->member_count }} sitios)">
                <option value="group:{{ $group->id }}" @if(($currentScope ?? '') === "group:{$group->id}") selected @endif>
                  Todo el grupo: {{ $group->name }}
                </option>
                @foreach ($groupedTenants as $tenant)
                  @if ($tenant->group_id == $group->id)
                    <option value="tenant:{{ $tenant->id }}" @if(($currentScope ?? '') === "tenant:{$tenant->id}") selected @endif>
                      {{ $tenant->domain }}
                    </option>
                  @endif
                @endforeach
              </optgroup>
            @endforeach
          </select>
          @if (($currentScope ?? 'mine') !== 'mine')
            <span class="badge bg-info text-dark">{{ $scope['label'] ?? '' }}</span>
            <a href="/musedock/blog/tags" class="btn btn-sm btn-outline-secondary">Limpiar filtro</a>
          @endif
        </form>
      </div>
    </div>
    @endif

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

    {{-- Formulario de Búsqueda --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <form method="GET" action="{{ route('blog.tags.index') }}" class="d-flex align-items-center">
        @if (!empty($scope) && ($scope['mode'] ?? 'mine') !== 'mine')
          <input type="hidden" name="scope" value="{{ $_GET['scope'] ?? '' }}">
        @endif
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('blog.tag.search_placeholder') }}" class="form-control form-control-sm me-2" style="width: 250px;" id="search-input">
        <button type="submit" class="btn btn-outline-secondary btn-sm me-2">{{ __('common.search') }}</button>
        @if (!empty($search))
          <a href="{{ route('blog.tags.index') }}{{ !empty($scope) && ($scope['mode'] ?? 'mine') !== 'mine' ? '?scope=' . urlencode($_GET['scope'] ?? '') : '' }}" class="btn btn-outline-danger btn-sm">{{ __('common.clear_filter') }}</a>
        @endif
      </form>
    </div>

    {{-- Formulario Acciones en Lote --}}
    <form method="POST" action="{{ route('blog.tags.bulk') }}" id="bulkActionForm">
      @csrf
      <div class="card">
        <div class="card-body table-responsive p-0">
          @if (!empty($tags) && count($tags) > 0)
          <table class="table table-hover align-middle" id="tagsTable">
            <thead>
              <tr>
                <th style="width: 1%;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                <th>{{ __('blog.tag.name') }}</th>
                <th>{{ __('blog.tag.slug') }}</th>
                @if (!empty($scope) && ($scope['mode'] ?? 'mine') !== 'mine')
                  <th>Sitio</th>
                @endif
                <th>{{ __('blog.tag.description') }}</th>
                <th>{{ __('blog.tag.color') }}</th>
                <th>{{ __('blog.tag.posts') }}</th>
              </tr>
            </thead>
            <tbody>
            {{-- Bucle para mostrar las etiquetas --}}
            @foreach ($tags as $tag)
              <tr data-id="{{ $tag->id }}">
                <td><input type="checkbox" name="selected[]" value="{{ $tag->id }}" class="form-check-input select-item"></td>
                <td>
                  <strong>{{ e($tag->name) }}</strong>
                  <br>
                  <small>
                    <a href="{{ route('blog.tags.edit', ['id' => $tag->id]) }}">{{ __('common.edit') }}</a>
                     |
                    <a href="#" class="delete-tag-link" data-tag-id="{{ $tag->id }}" data-tag-name="{{ htmlspecialchars($tag->name, ENT_QUOTES, 'UTF-8') }}" style="color: #dc3545; text-decoration: none;">{{ __('common.delete') }}</a>
                  </small>
                </td>
                <td><small class="text-muted">{{ $tag->slug }}</small></td>
                @if (!empty($scope) && ($scope['mode'] ?? 'mine') !== 'mine')
                  <td>
                    @if (!empty($tenantMap[$tag->tenant_id]))
                      <small><a href="https://{{ $tenantMap[$tag->tenant_id]->domain }}" target="_blank">{{ $tenantMap[$tag->tenant_id]->domain }}</a></small>
                    @else
                      <small class="text-muted">—</small>
                    @endif
                  </td>
                @endif
                <td><small class="text-muted">{{ strlen($tag->description ?? '') > 50 ? substr($tag->description, 0, 50) . '...' : ($tag->description ?? '') }}</small></td>
                <td>
                  @if($tag->color)
                    <span class="badge" style="background-color: {{ $tag->color }}; color: {{ \Screenart\Musedock\Helpers\ColorHelper::getContrastColor($tag->color ?? '#000000') }};">
                      {{ $tag->color }}
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td><span class="badge bg-secondary">{{ $tag->post_count ?? 0 }}</span></td>
              </tr>
            @endforeach
            </tbody>
          </table>
          @else
            <div class="p-3 text-center">
              <p class="text-muted">{{ __('blog.tag.no_tags_found') }}</p>
               @if(empty($search))
                 <a href="{{ route('blog.tags.create') }}" class="btn btn-sm btn-primary">{{ __('blog.tag.create_first_tag') }}</a>
               @endif
            </div>
          @endif
        </div>
      </div>

      {{-- Acciones en lote y Paginación --}}
      <div class="d-flex justify-content-between align-items-center mt-3">
        @if (!empty($tags) && count($tags) > 0)
        <div class="d-flex">
          <select name="action" class="form-select form-select-sm me-2" id="bulkActionSelect" style="width: auto;" required>
            <option value="">{{ __('blog.bulk.actions') }}</option>
            <option value="delete">{{ __('blog.bulk.delete') }}</option>
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
    @if (!empty($tags) && count($tags) > 0)
      @foreach ($tags as $tag)
        <form method="POST" action="/musedock/blog/tags/{{ $tag->id }}" style="display: none;" id="delete-form-{{ $tag->id }}">
          @csrf
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
          text: {!! json_encode(__('blog.bulk.select_at_least_one_tag')) !!}
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
          text: {!! json_encode(__('blog.bulk.confirm_delete_tags', ['count' => ':count'])) !!}.replace(':count', selectedCount),
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
      } else {
        bulkForm.submit();
      }
    });
  }

  // Event listener para eliminar etiquetas (delegación de eventos)
  document.addEventListener('click', function(e) {
    const deleteLink = e.target.closest('.delete-tag-link');
    if (!deleteLink) return;

    e.preventDefault();

    const tagId = deleteLink.getAttribute('data-tag-id');
    const tagName = deleteLink.getAttribute('data-tag-name');

    Swal.fire({
      title: {!! json_encode(__('common.are_you_sure')) !!},
      html: {!! json_encode(__('blog.delete.confirm_delete_tag', ['name' => ':name'])) !!}.replace(':name', escapeHtml(tagName)),
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: {!! json_encode(__('blog.bulk.confirm_delete_yes')) !!},
      cancelButtonText: {!! json_encode(__('blog.bulk.confirm_cancel')) !!}
    }).then((result) => {
      if (result.isConfirmed) {
        // Buscar y enviar el formulario oculto
        const formId = `delete-form-${tagId}`;
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
});
</script>
@endpush
