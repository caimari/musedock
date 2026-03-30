@extends('layouts.app')

@section('title', $title)

@section('styles')
<style>
  .revision-row {
    cursor: pointer;
    transition: background-color 0.2s;
  }
  .revision-row:hover {
    background-color: #f8f9fa;
  }
  .revision-type-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
  }
  .compare-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
  }
</style>
@endsection

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botón Volver --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <div>
        <a href="{{ admin_url('blog') }}/posts/{{ $post->id }}/edit" class="btn btn-secondary me-2">
          <i class="bi bi-arrow-left"></i> Volver a editar
        </a>
        <a href="{{ admin_url('blog') }}/posts" class="btn btn-outline-secondary">
          <i class="bi bi-list"></i> Lista de posts
        </a>
      </div>
    </div>

    {{-- Alertas --}}
    @if (session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: 'Correcto',
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
            title: 'Error',
            text: {!! json_encode(session('error')) !!},
            confirmButtonColor: '#d33'
          });
        });
      </script>
    @endif

    {{-- Info del post --}}
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">{{ e($post->title) }}</h5>
        <p class="card-text text-muted">
          <strong>Total de revisiones:</strong> {{ count($revisions) }}
        </p>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <form method="POST" action="{{ admin_url('blog') }}/posts/{{ $post->id }}/revisions/bulk" id="bulkRevisionsForm" class="d-flex align-items-center gap-2">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <select name="action" class="form-select form-select-sm" id="bulkRevisionAction" style="width: auto;" required>
          <option value="">{{ __('blog.bulk.actions') }}</option>
          <option value="delete_selected">{{ __('blog.post.bulk_delete_selected_revisions') }}</option>
          <option value="delete_all">{{ __('blog.post.bulk_delete_all_revisions') }}</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm" id="bulkRevisionsApply" disabled>{{ __('common.apply') }}</button>
      </form>

      {{-- Botón comparar (solo visible si hay 2 seleccionadas) --}}
      <div id="compare-button-container" style="display: none;">
        <button type="button" class="btn btn-primary btn-sm" onclick="compareSelected()">
          <i class="bi bi-arrow-left-right"></i> Comparar revisiones seleccionadas
        </button>
      </div>
    </div>

    {{-- Tabla de revisiones --}}
    <div class="card">
      <div class="card-body">
        @if (empty($revisions))
          <p class="text-muted">No hay revisiones disponibles para este post.</p>
        @else
          <table class="table table-hover">
            <thead>
              <tr>
                <th style="width: 40px;">
                  <input type="checkbox" id="selectAllRevisions" class="form-check-input">
                </th>
                <th>Fecha y Hora</th>
                <th>Tipo</th>
                <th>Usuario</th>
                <th>Cambios</th>
                <th style="width: 200px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($revisions as $revision)
              <tr class="revision-row">
                <td>
                  <input type="checkbox"
                         class="compare-checkbox revision-checkbox form-check-input"
                         name="revision_ids[]"
                         value="{{ $revision->id }}"
                         form="bulkRevisionsForm"
                         data-revision-id="{{ $revision->id }}">
                </td>
                <td>
                  {{ date('d/m/Y H:i:s', strtotime($revision->created_at)) }}
                </td>
                <td>
                  @php
                    $badgeClass = 'secondary';
                    $typeLabel = $revision->revision_type;

                    switch ($revision->revision_type) {
                      case 'initial':
                        $badgeClass = 'success';
                        $typeLabel = 'Inicial';
                        break;
                      case 'manual':
                        $badgeClass = 'primary';
                        $typeLabel = 'Manual';
                        break;
                      case 'autosave':
                        $badgeClass = 'info';
                        $typeLabel = 'Autoguardado';
                        break;
                      case 'published':
                        $badgeClass = 'warning';
                        $typeLabel = 'Publicado';
                        break;
                      case 'restored':
                        $badgeClass = 'dark';
                        $typeLabel = 'Restaurado';
                        break;
                    }
                  @endphp
                  <span class="badge bg-{{ $badgeClass }} revision-type-badge">{{ $typeLabel }}</span>
                </td>
                <td>
                  {{ e($revision->user_name ?? 'Sistema') }}
                  <small class="text-muted d-block">{{ e($revision->user_type) }}</small>
                </td>
                <td>
                  @if ($revision->changes_summary)
                    {{ e($revision->changes_summary) }}
                  @else
                    <span class="text-muted">Sin descripción</span>
                  @endif
                </td>
                <td>
                  <div class="d-inline-flex align-items-center gap-2">
                    <a href="{{ admin_url('blog') }}/posts/{{ $post->id }}/revisions/{{ $revision->id }}/preview"
                       class="btn btn-outline-secondary btn-sm"
                       title="{{ __('pages.preview_revision') }}">
                      <i class="bi bi-eye"></i>
                    </a>
                    <form method="POST"
                          action="{{ admin_url('blog') }}/posts/{{ $post->id }}/revisions/{{ $revision->id }}/restore"
                          class="restore-revision-form"
                          data-post-id="{{ $post->id }}"
                          data-revision-id="{{ $revision->id }}">
                      <input type="hidden" name="_token" value="{{ csrf_token() }}">
                      <button type="submit" class="btn btn-outline-primary btn-sm" title="{{ __('pages.restore_revision') }}">
                        <i class="bi bi-arrow-counterclockwise"></i>
                      </button>
                    </form>
                    <form method="POST"
                          action="{{ admin_url('blog') }}/posts/{{ $post->id }}/revisions/{{ $revision->id }}/delete"
                          class="delete-revision-form"
                          data-post-id="{{ $post->id }}"
                          data-revision-id="{{ $revision->id }}">
                      <input type="hidden" name="_token" value="{{ csrf_token() }}">
                      <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('common.delete') }}">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>
    </div>

  </div>
</div>

<script>
let selectedRevisions = [];

function toggleCompareButton() {
  const checkboxes = document.querySelectorAll('.compare-checkbox:checked');
  selectedRevisions = Array.from(checkboxes).map(cb => cb.getAttribute('data-revision-id'));

  const compareContainer = document.getElementById('compare-button-container');
  if (selectedRevisions.length === 2) {
    compareContainer.style.display = 'block';
  } else {
    compareContainer.style.display = 'none';
  }
}

function compareSelected() {
  if (selectedRevisions.length === 2) {
    window.location.href = `{{ admin_url('blog') }}/posts/{{ $post->id }}/revisions/${selectedRevisions[0]}/compare/${selectedRevisions[1]}`;
  }
}

document.addEventListener('DOMContentLoaded', function () {
  const selectAllCheckbox = document.getElementById('selectAllRevisions');
  const revisionCheckboxes = document.querySelectorAll('.revision-checkbox');
  const bulkForm = document.getElementById('bulkRevisionsForm');
  const actionSelect = document.getElementById('bulkRevisionAction');
  const applyButton = document.getElementById('bulkRevisionsApply');

  function updateBulkUi() {
    toggleCompareButton();

    const anyChecked = Array.from(revisionCheckboxes).some(cb => cb.checked);
    const allChecked = revisionCheckboxes.length > 0 && Array.from(revisionCheckboxes).every(cb => cb.checked);

    if (selectAllCheckbox) {
      selectAllCheckbox.checked = allChecked;
      selectAllCheckbox.indeterminate = anyChecked && !allChecked;
    }

    const action = actionSelect ? actionSelect.value : '';
    if (applyButton) {
      if (!action) {
        applyButton.disabled = true;
      } else if (action === 'delete_all') {
        applyButton.disabled = false;
      } else {
        applyButton.disabled = !anyChecked;
      }
    }
  }

  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function () {
      revisionCheckboxes.forEach(cb => { cb.checked = selectAllCheckbox.checked; });
      updateBulkUi();
    });
  }

  revisionCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateBulkUi);
  });

  if (actionSelect) {
    actionSelect.addEventListener('change', updateBulkUi);
  }

  updateBulkUi();

  if (bulkForm) {
    bulkForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const action = actionSelect ? actionSelect.value : '';
      const selectedCount = document.querySelectorAll('.revision-checkbox:checked').length;

      if (!action) {
        Swal.fire({
          icon: 'warning',
          title: {!! json_encode(__('blog.bulk.action_required')) !!},
          text: {!! json_encode(__('blog.bulk.select_action')) !!}
        });
        return false;
      }

      if (action === 'delete_selected' && selectedCount === 0) {
        Swal.fire({
          icon: 'warning',
          title: {!! json_encode(__('blog.bulk.selection_required')) !!},
          text: {!! json_encode(__('blog.post.bulk_revision_select_at_least_one')) !!}
        });
        return false;
      }

      const confirmText = (action === 'delete_all')
        ? {!! json_encode(__('blog.post.confirm_bulk_delete_revisions_all')) !!}
        : {!! json_encode(__('blog.post.confirm_bulk_delete_revisions_selected', ['count' => ':count'])) !!}.replace(':count', selectedCount);

      Swal.fire({
        title: {!! json_encode(__('common.are_you_sure')) !!},
        text: confirmText,
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
    });
  }

  document.querySelectorAll('.restore-revision-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      Swal.fire({
        icon: 'warning',
        title: {!! json_encode(__('blog.post.confirm_restore_revision_title')) !!},
        text: {!! json_encode(__('blog.post.confirm_restore_revision_text')) !!},
        showCancelButton: true,
        confirmButtonText: {!! json_encode(__('blog.post.confirm_restore_revision_yes')) !!},
        cancelButtonText: {!! json_encode(__('common.cancel')) !!},
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d'
      }).then(function (result) {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });

  document.querySelectorAll('.delete-revision-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      Swal.fire({
        icon: 'warning',
        title: {!! json_encode(__('blog.post.confirm_delete_revision_title')) !!},
        text: {!! json_encode(__('blog.post.confirm_delete_revision_text')) !!},
        showCancelButton: true,
        confirmButtonText: {!! json_encode(__('blog.post.confirm_delete_revision_yes')) !!},
        cancelButtonText: {!! json_encode(__('common.cancel')) !!},
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d'
      }).then(function (result) {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });
});
</script>
@endsection
