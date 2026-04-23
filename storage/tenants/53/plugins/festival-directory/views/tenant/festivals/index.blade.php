@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0"><i class="bi bi-film me-2"></i>{{ $title }}</h2>
        <small class="text-muted">{{ $pagination['total'] ?? 0 }} festivales en total</small>
      </div>
      <div class="d-flex gap-2">
        @if(($pendingClaims ?? 0) > 0)
          <a href="{{ festival_admin_url('claims') }}" class="btn btn-outline-warning">
            <i class="bi bi-shield-check me-1"></i> {{ $pendingClaims }} Claims pendientes
          </a>
        @endif
        <a href="{{ festival_admin_url('scraper') }}" class="btn btn-outline-primary">
          <i class="bi bi-cloud-download me-1"></i> Importar
        </a>
        <a href="{{ festival_admin_url('create') }}" class="btn btn-primary">
          <i class="bi bi-plus-lg me-1"></i> Crear Festival
        </a>
      </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="{{ festival_admin_url() }}" class="d-flex align-items-center gap-2 flex-wrap">
          <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar festival..."
                 class="form-control form-control-sm" style="width: 220px;">
          <select name="status" class="form-select form-select-sm" style="width: 150px;">
            <option value="">Todos los estados</option>
            @foreach($statuses as $key => $label)
              <option value="{{ $key }}" {{ ($statusFilter ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          <select name="type" class="form-select form-select-sm" style="width: 170px;">
            <option value="">Todos los tipos</option>
            @foreach($types as $key => $label)
              <option value="{{ $key }}" {{ ($typeFilter ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-search"></i> Filtrar
          </button>
          @if(!empty($search) || !empty($statusFilter) || !empty($typeFilter))
            <a href="{{ festival_admin_url() }}" class="btn btn-outline-danger btn-sm">
              <i class="bi bi-x-lg"></i> Limpiar
            </a>
          @endif
        </form>
      </div>
    </div>

    {{-- Flash messages via SweetAlert2 --}}
    @if(session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          Swal.fire({ icon: 'success', title: 'OK', text: {!! json_encode(session('success')) !!}, confirmButtonColor: '#3085d6', timer: 3000 });
        });
      </script>
    @endif
    @if(session('error'))
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          Swal.fire({ icon: 'error', title: 'Error', html: {!! json_encode(session('error')) !!}, confirmButtonColor: '#d33' });
        });
      </script>
    @endif

    {{-- Table --}}
    <form method="POST" action="{{ festival_admin_url('bulk') }}" id="bulkActionForm">
      @csrf
      <div class="card">
        <div class="card-body table-responsive p-0">
          @if(!empty($festivals) && count($festivals) > 0)
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="width:1%"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                <th style="width:50px"></th>
                <th>
                  <a href="{{ festival_admin_url() }}?orderby=name&order={{ $orderBy === 'name' && $order === 'ASC' ? 'DESC' : 'ASC' }}&search={{ $search }}&status={{ $statusFilter }}&type={{ $typeFilter }}">
                    Nombre {!! $orderBy === 'name' ? ($order === 'ASC' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>') : '' !!}
                  </a>
                </th>
                <th>País / Ciudad</th>
                <th>Tipo</th>
                <th>Categorías</th>
                <th>
                  <a href="{{ festival_admin_url() }}?orderby=status&order={{ $orderBy === 'status' && $order === 'ASC' ? 'DESC' : 'ASC' }}&search={{ $search }}">
                    Estado {!! $orderBy === 'status' ? ($order === 'ASC' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>') : '' !!}
                  </a>
                </th>
                <th>
                  <a href="{{ festival_admin_url() }}?orderby=view_count&order={{ $orderBy === 'view_count' && $order === 'ASC' ? 'DESC' : 'ASC' }}&search={{ $search }}">
                    Visitas {!! $orderBy === 'view_count' ? ($order === 'ASC' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>') : '' !!}
                  </a>
                </th>
              </tr>
            </thead>
            <tbody>
              @foreach($festivals as $festival)
              <tr>
                <td><input type="checkbox" name="selected[]" value="{{ $festival->id }}" class="form-check-input select-item"></td>
                <td>
                  @if($festival->logo)
                    <img src="{{ $festival->logo }}" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:6px;">
                  @else
                    <div style="width:36px;height:36px;background:#e9ecef;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                      <i class="bi bi-film text-muted"></i>
                    </div>
                  @endif
                </td>
                <td>
                  <strong>{{ e($festival->name) }}</strong>
                  @if($festival->featured)
                    <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">Destacado</span>
                  @endif
                  @if($festival->status === 'verified' || $festival->status === 'claimed')
                    <i class="bi bi-patch-check-fill text-primary ms-1" title="Verificado"></i>
                  @endif
                  <br>
                  <small>
                    <a href="{{ festival_admin_url($festival->id . '/edit') }}">Editar</a>
                    @if($festival->status === 'published' || $festival->status === 'verified' || $festival->status === 'claimed')
                      | <a href="{{ festival_url($festival->slug) }}" target="_blank">Ver</a>
                    @endif
                    | <a href="#" class="text-danger delete-festival" data-id="{{ $festival->id }}" data-name="{{ e($festival->name) }}">Eliminar</a>
                  </small>
                </td>
                <td>
                  <span>{{ $festival->country }}</span>
                  @if($festival->city)
                    <br><small class="text-muted">{{ $festival->city }}</small>
                  @endif
                </td>
                <td><span class="text-muted" style="font-size:0.8rem">{{ $types[$festival->type] ?? $festival->type }}</span></td>
                <td>
                  @if(isset($festivalCategories[$festival->id]))
                    @foreach($festivalCategories[$festival->id] as $cat)
                      <span class="badge rounded-pill" style="background:{{ $cat['color'] ?? '#6c757d' }};font-size:0.7rem">{{ $cat['name'] }}</span>
                    @endforeach
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  @php
                    $statusColors = ['draft'=>'secondary','published'=>'success','verified'=>'primary','claimed'=>'info','suspended'=>'danger'];
                    $sc = $statusColors[$festival->status] ?? 'secondary';
                  @endphp
                  <span class="badge bg-{{ $sc }}">{{ $statuses[$festival->status] ?? $festival->status }}</span>
                </td>
                <td class="text-muted">{{ number_format($festival->view_count ?? 0) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @else
            <div class="p-4 text-center">
              <i class="bi bi-film" style="font-size:3rem;color:#dee2e6"></i>
              <p class="text-muted mt-2">No se encontraron festivales.</p>
              <a href="{{ festival_admin_url('create') }}" class="btn btn-sm btn-primary">Crear el primer festival</a>
            </div>
          @endif
        </div>
      </div>

      {{-- Bulk + Pagination --}}
      @if(!empty($festivals) && count($festivals) > 0)
      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="d-flex gap-2">
          <select name="action" class="form-select form-select-sm" style="width:auto" id="bulkAction">
            <option value="">Acciones masivas</option>
            <option value="published">Publicar</option>
            <option value="draft">Borrador</option>
            <option value="delete">Eliminar</option>
          </select>
          <button type="submit" class="btn btn-secondary btn-sm" id="applyBulk" disabled>Aplicar</button>
        </div>
        @if(isset($pagination) && $pagination['last_page'] > 1)
          {!! pagination_links($pagination, http_build_query(array_filter(['search' => $search, 'status' => $statusFilter, 'type' => $typeFilter, 'orderby' => $orderBy, 'order' => $order])), 'sm') !!}
        @endif
      </div>
      @endif
    </form>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Select all
  const selectAll = document.getElementById('selectAll');
  const items = document.querySelectorAll('.select-item');
  const bulkAction = document.getElementById('bulkAction');
  const applyBulk = document.getElementById('applyBulk');

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      items.forEach(cb => cb.checked = this.checked);
      toggleBulk();
    });
  }

  items.forEach(cb => cb.addEventListener('change', toggleBulk));
  if (bulkAction) bulkAction.addEventListener('change', toggleBulk);

  function toggleBulk() {
    const hasSelected = [...items].some(cb => cb.checked);
    const hasAction = bulkAction && bulkAction.value !== '';
    if (applyBulk) applyBulk.disabled = !(hasSelected && hasAction);
  }

  // Delete single
  document.querySelectorAll('.delete-festival').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const id = this.dataset.id;
      const name = this.dataset.name;
      Swal.fire({
        title: 'Eliminar festival',
        text: '¿Seguro que quieres eliminar "' + name + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '{{ festival_admin_url() }}/' + id;
          form.innerHTML = '<input type="hidden" name="_method" value="DELETE">' +
                           '<input type="hidden" name="_token" value="' + document.querySelector('input[name=_token]').value + '">';
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
  });

  // Bulk confirm
  const bulkForm = document.getElementById('bulkActionForm');
  if (bulkForm) {
    bulkForm.addEventListener('submit', function(e) {
      const action = bulkAction ? bulkAction.value : '';
      if (action === 'delete') {
        e.preventDefault();
        Swal.fire({
          title: 'Eliminar festivales',
          text: '¿Seguro que quieres eliminar los festivales seleccionados?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonText: 'Cancelar',
          confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
          if (result.isConfirmed) bulkForm.submit();
        });
      }
    });
  }
});
</script>
@endpush
