@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="bi bi-folder me-2"></i>{{ $title }}</h2>
      <a href="{{ festival_admin_url('categories/create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nueva Categoría
      </a>
    </div>

    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'success', title:'OK', text:{!! json_encode(session('success')) !!}, timer:3000 }); });</script>
    @endif

    <div class="card">
      <div class="card-body table-responsive p-0">
        @if(!empty($categories) && count($categories) > 0)
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Slug</th>
              <th>Color</th>
              <th>Festivales</th>
              <th style="width:120px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($categories as $cat)
            <tr>
              <td><strong>{{ e($cat->name) }}</strong></td>
              <td><code>{{ $cat->slug }}</code></td>
              <td>
                @if($cat->color)
                  <span class="badge" style="background:{{ $cat->color }}">{{ $cat->color }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>{{ $cat->festival_count ?? 0 }}</td>
              <td>
                <a href="{{ festival_admin_url('categories/' . $cat->id . '/edit') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <button type="button" class="btn btn-sm btn-outline-danger delete-category" data-id="{{ $cat->id }}" data-name="{{ e($cat->name) }}"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else
        <div class="p-4 text-center">
          <p class="text-muted">No hay categorías.</p>
          <a href="{{ festival_admin_url('categories/create') }}" class="btn btn-sm btn-primary">Crear primera categoría</a>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-category').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.id, name = this.dataset.name;
      Swal.fire({
        title: 'Eliminar categoría', text: '¿Eliminar "' + name + '"?', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Cancelar', confirmButtonText: 'Eliminar'
      }).then(r => {
        if (r.isConfirmed) {
          const f = document.createElement('form'); f.method = 'POST';
          f.action = '{{ festival_admin_url("categories") }}/' + id;
          f.innerHTML = '<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + '{{ csrf_token() }}' + '">';
          document.body.appendChild(f); f.submit();
        }
      });
    });
  });
});
</script>
@endpush
