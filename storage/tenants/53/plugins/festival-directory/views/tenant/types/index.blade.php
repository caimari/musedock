@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="bi bi-collection me-2"></i>{{ $title }}</h2>
      <a href="{{ festival_admin_url('types/create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nuevo Tipo
      </a>
    </div>

    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'success', title:'OK', text:{!! json_encode(session('success')) !!}, timer:3000 }); });</script>
    @endif

    <div class="card">
      <div class="card-body table-responsive p-0">
        @if(!empty($types) && count($types) > 0)
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:50px">Icono</th>
              <th>Nombre</th>
              <th>Slug</th>
              <th>Color</th>
              <th>Festivales</th>
              <th>Orden</th>
              <th style="width:120px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($types as $type)
            <tr>
              <td>
                @if($type->icon)
                  <i class="bi {{ $type->icon }}" style="font-size:1.3rem;{{ $type->color ? 'color:'.$type->color : '' }}"></i>
                @else
                  <i class="bi bi-circle text-muted"></i>
                @endif
              </td>
              <td><strong>{{ e($type->name) }}</strong>
                @if($type->description)
                  <br><small class="text-muted">{{ mb_substr($type->description, 0, 60) }}{{ mb_strlen($type->description) > 60 ? '...' : '' }}</small>
                @endif
              </td>
              <td><code>{{ $type->slug }}</code></td>
              <td>
                @if($type->color)
                  <span class="badge" style="background:{{ $type->color }}">{{ $type->color }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>{{ $type->festival_count ?? 0 }}</td>
              <td>{{ $type->sort_order ?? 0 }}</td>
              <td>
                <a href="{{ festival_admin_url('types/' . $type->id . '/edit') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <button type="button" class="btn btn-sm btn-outline-danger delete-type" data-id="{{ $type->id }}" data-name="{{ e($type->name) }}"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else
        <div class="p-4 text-center">
          <p class="text-muted">No hay tipos definidos.</p>
          <a href="{{ festival_admin_url('types/create') }}" class="btn btn-sm btn-primary">Crear primer tipo</a>
        </div>
        @endif
      </div>
    </div>

    <div class="alert alert-info mt-3">
      <i class="bi bi-info-circle me-2"></i>
      Los tipos definen las categorías principales de festivales: cine, música, certámenes, premios, concursos, etc.
      Al crear o editar un festival, podrás seleccionar el tipo de esta lista.
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-type').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.id, name = this.dataset.name;
      Swal.fire({
        title: 'Eliminar tipo', text: '¿Eliminar "' + name + '"? Los festivales con este tipo no se eliminarán.', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Cancelar', confirmButtonText: 'Eliminar'
      }).then(r => {
        if (r.isConfirmed) {
          const f = document.createElement('form'); f.method = 'POST';
          f.action = '{{ festival_admin_url("types") }}/' + id;
          f.innerHTML = '<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + '{{ csrf_token() }}' + '">';
          document.body.appendChild(f); f.submit();
        }
      });
    });
  });
});
</script>
@endpush
