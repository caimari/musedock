@extends('layouts.app')
@section('title', $title)

@section('styles')
<style>
  .revision-row { cursor: pointer; transition: background-color .2s; }
  .revision-row:hover { background-color: #f8f9fa; }
  .revision-type-badge { font-size: .75rem; padding: .25rem .5rem; }
  .compare-checkbox { width: 20px; height: 20px; cursor: pointer; }
</style>
@endsection

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <div>
        <a href="/musedock/pages/{{ $page->id }}/edit" class="btn btn-secondary me-2">
          <i class="bi bi-arrow-left"></i> Volver a editar
        </a>
        <a href="/musedock/pages" class="btn btn-outline-secondary">
          <i class="bi bi-list"></i> Lista de páginas
        </a>
      </div>
    </div>

    @if (session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({ icon: 'success', title: 'Correcto', text: {!! json_encode(session('success')) !!}, confirmButtonColor: '#3085d6' });
        });
      </script>
    @endif
    @if (session('error'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({ icon: 'error', title: 'Error', text: {!! json_encode(session('error')) !!}, confirmButtonColor: '#d33' });
        });
      </script>
    @endif

    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">{{ e($page->title) }}</h5>
        <p class="card-text text-muted"><strong>Total de revisiones:</strong> {{ count($revisions) }}</p>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <form method="POST" action="/musedock/pages/{{ $page->id }}/revisions/bulk" id="bulkRevisionsForm" class="d-flex align-items-center gap-2">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <select name="action" class="form-select form-select-sm" id="bulkRevisionAction" style="width: auto;" required>
          <option value="">{{ __('pages.bulk_actions') }}</option>
          <option value="delete_selected">{{ __('pages.bulk_delete_selected_revisions') }}</option>
          <option value="delete_all">{{ __('pages.bulk_delete_all_revisions') }}</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm" id="bulkRevisionsApply" disabled>{{ __('common.apply') }}</button>
      </form>

      <div id="compare-button-container" style="display:none;">
        <button type="button" class="btn btn-primary btn-sm" onclick="compareSelected()">
          <i class="bi bi-arrow-left-right"></i> Comparar revisiones seleccionadas
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        @if(empty($revisions))
          <p class="text-muted">No hay revisiones disponibles para esta página.</p>
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
                  <td>{{ date('d/m/Y H:i:s', strtotime($revision->created_at)) }}</td>
                  <td>
                    @php
                      $badges = [
                        'initial' => ['success', 'Inicial'],
                        'manual' => ['primary', 'Manual'],
                        'autosave' => ['info', 'Autoguardado'],
                        'published' => ['warning', 'Publicado'],
                        'restored' => ['dark', 'Restaurado'],
                      ];
                      [$class, $label] = $badges[$revision->revision_type] ?? ['secondary', $revision->revision_type];
                    @endphp
                    <span class="badge bg-{{ $class }} revision-type-badge">{{ $label }}</span>
                  </td>
                  <td>
                    {{ e($revision->user_name ?? 'Sistema') }}
                    <small class="text-muted d-block">{{ e($revision->user_type) }}</small>
                  </td>
                  <td>{!! e($revision->changes_summary) ?: '<span class="text-muted">Sin descripción</span>' !!}</td>
                  <td>
                    <div class="d-inline-flex align-items-center gap-2">
                      <a href="/musedock/pages/{{ $page->id }}/revisions/{{ $revision->id }}/preview" class="btn btn-outline-secondary btn-sm" title="Vista previa">
                        <i class="bi bi-eye"></i>
                      </a>
                      <button type="button"
                              class="btn btn-outline-primary btn-sm btn-restore"
                              title="Restaurar"
                              data-revision-id="{{ $revision->id }}"
                              data-revision-date="{{ date('d/m/Y H:i', strtotime($revision->created_at)) }}">
                        <i class="bi bi-arrow-counterclockwise"></i>
                      </button>
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
  document.getElementById('compare-button-container').style.display = selectedRevisions.length === 2 ? 'block' : 'none';
}

function compareSelected() {
  if (selectedRevisions.length === 2) {
    window.location.href = `/musedock/pages/{{ $page->id }}/revisions/${selectedRevisions[0]}/compare/${selectedRevisions[1]}`;
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

  revisionCheckboxes.forEach(cb => cb.addEventListener('change', updateBulkUi));
  if (actionSelect) actionSelect.addEventListener('change', updateBulkUi);
  updateBulkUi();

  if (bulkForm) {
    bulkForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const action = actionSelect ? actionSelect.value : '';
      const selectedCount = document.querySelectorAll('.revision-checkbox:checked').length;

      if (!action) {
        Swal.fire({
          icon: 'warning',
          title: {!! json_encode(__('pages.error_select_action_and_items')) !!},
          text: {!! json_encode(__('pages.error_select_action_and_items')) !!}
        });
        return false;
      }

      if (action === 'delete_selected' && selectedCount === 0) {
        Swal.fire({
          icon: 'warning',
          title: {!! json_encode(__('pages.bulk_actions')) !!},
          text: {!! json_encode(__('pages.bulk_revision_select_at_least_one')) !!}
        });
        return false;
      }

      const confirmText = (action === 'delete_all')
        ? {!! json_encode(__('pages.confirm_bulk_delete_revisions_all')) !!}
        : {!! json_encode(__('pages.confirm_bulk_delete_revisions_selected', ['count' => ':count'])) !!}.replace(':count', selectedCount);

      Swal.fire({
        title: {!! json_encode(__('common.are_you_sure')) !!},
        text: confirmText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: {!! json_encode(__('common.delete')) !!},
        cancelButtonText: {!! json_encode(__('common.cancel')) !!}
      }).then((result) => {
        if (result.isConfirmed) {
          bulkForm.submit();
        }
      });
    });
  }

  // SweetAlert2 para restaurar revisión (existente)
  document.querySelectorAll('.btn-restore').forEach(btn => {
    btn.addEventListener('click', function() {
      const revisionId = this.dataset.revisionId;
      const revisionDate = this.dataset.revisionDate;

      Swal.fire({
        title: '¿Restaurar versión?',
        html: `<p>La página volverá al estado del <strong>${revisionDate}</strong>.</p><p class="text-muted"><small>Se creará una nueva revisión con el estado actual antes de restaurar.</small></p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar',
        cancelButtonText: 'Cancelar',
        focusCancel: true
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '/musedock/pages/{{ $page->id }}/revisions/' + revisionId + '/restore';
          const csrfInput = document.createElement('input');
          csrfInput.type = 'hidden';
          csrfInput.name = '_token';
          csrfInput.value = '{{ csrf_token() }}';
          form.appendChild(csrfInput);
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
  });
});
</script>
@endsection
