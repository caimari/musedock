@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h2 class="mb-4">{{ $title }}</h2>
    @include('partials.alerts')

    <div class="d-flex justify-content-between align-items-center mb-3">
      <a href="{{ route('languages.create') }}" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i> Añadir nuevo idioma
      </a>
      <small class="text-muted">
        <i class="bi bi-arrows-move me-1"></i> Arrastra las filas para cambiar el orden
      </small>
    </div>

    <div class="card">
      <div class="card-body table-responsive p-0">
        <table class="table table-striped align-middle mb-0" id="languages-table">
          <thead>
            <tr>
              <th style="width: 40px;"></th>
              <th>Código</th>
              <th>Nombre</th>
              <th>Estado</th>
              @php
                $multiTenantEnabled = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
                if ($multiTenantEnabled === null) {
                    $multiTenantEnabled = setting('multi_tenant_enabled', false);
                }
              @endphp
              @if($multiTenantEnabled)
              <th>Tenant</th>
              @endif
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="sortable-languages">
            @foreach ($languages as $lang)
            <tr data-id="{{ $lang->id }}">
              <td class="drag-handle" style="cursor: grab;">
                <i class="bi bi-grip-vertical text-muted"></i>
              </td>
              <td><code>{{ $lang->code }}</code></td>
              <td>{{ $lang->name }}</td>
              <td>
                <form method="POST" action="{{ route('languages.toggle', $lang->id) }}" class="d-inline toggle-form">
                  {!! csrf_field() !!}
                  <button type="submit" class="btn btn-sm {{ $lang->active ? 'btn-success' : 'btn-outline-secondary' }}">
                    <i class="bi {{ $lang->active ? 'bi-check-circle' : 'bi-x-circle' }} me-1"></i>
                    {{ $lang->active ? 'Activo' : 'Inactivo' }}
                  </button>
                </form>
              </td>
              @if($multiTenantEnabled)
              <td>
                @if($lang->tenant_id)
                  <span class="badge bg-info">{{ $lang->tenant_domain ?? $lang->tenant_name ?? $lang->tenant_id }}</span>
                @else
                  <span class="badge bg-secondary">Global</span>
                @endif
              </td>
              @endif
              <td>
                <a href="{{ route('languages.edit', $lang->id) }}" class="btn btn-sm btn-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <form action="{{ route('languages.delete', $lang->id) }}" method="POST" class="d-inline delete-form">
                  {!! csrf_field() !!}
                  <button type="submit" class="btn btn-sm btn-danger">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información</h5>
      </div>
      <div class="card-body">
        <ul class="mb-0">
          <li><strong>Orden de idiomas:</strong> El orden define cómo aparecen en los selectores del front-end y del panel de administración.</li>
          <li><strong>Idiomas activos:</strong> Solo los idiomas activos se muestran en los selectores. Debe haber al menos un idioma activo.</li>
          <li><strong>Idioma único:</strong> Si solo hay un idioma activo, los selectores de idioma se ocultan automáticamente.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .sortable-ghost {
    opacity: 0.4;
    background: #c8ebfb !important;
  }
  .sortable-chosen {
    background: #f8f9fa !important;
  }
  .drag-handle:hover {
    cursor: grabbing;
  }
  #sortable-languages tr {
    transition: background-color 0.2s;
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const tbody = document.getElementById('sortable-languages');

  if (tbody && typeof Sortable !== 'undefined') {
    new Sortable(tbody, {
      handle: '.drag-handle',
      animation: 150,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      onEnd: function() {
        const rows = tbody.querySelectorAll('tr[data-id]');
        const order = Array.from(rows).map(row => row.dataset.id);

        fetch('{{ route("languages.update-order") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify({ order: order })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Show brief success feedback
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 end-0 p-3';
            toast.style.zIndex = '11';
            toast.innerHTML = `
              <div class="toast show" role="alert">
                <div class="toast-body bg-success text-white rounded">
                  <i class="bi bi-check-circle me-1"></i> Orden actualizado
                </div>
              </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
          }
        })
        .catch(error => console.error('Error:', error));
      }
    });
  }

  // Confirm delete
  document.querySelectorAll('.delete-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      if (!confirm('¿Seguro que quieres eliminar este idioma?')) {
        e.preventDefault();
      }
    });
  });
});
</script>
@endpush
