@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="bi bi-tags me-2"></i>{{ $title }}</h2>
      <a href="{{ festival_admin_url('tags/create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nuevo Tag
      </a>
    </div>

    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'success', title:'OK', text:{!! json_encode(session('success')) !!}, timer:3000 }); });</script>
    @endif

    <div class="card">
      <div class="card-body table-responsive p-0">
        @if(!empty($tags) && count($tags) > 0)
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Nombre</th><th>Slug</th><th>Festivales</th><th style="width:120px">Acciones</th></tr></thead>
          <tbody>
            @foreach($tags as $tag)
            <tr>
              <td><strong>{{ e($tag->name) }}</strong></td>
              <td><code>{{ $tag->slug }}</code></td>
              <td>{{ $tag->festival_count ?? 0 }}</td>
              <td>
                <a href="{{ festival_admin_url('tags/' . $tag->id . '/edit') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <button type="button" class="btn btn-sm btn-outline-danger delete-tag" data-id="{{ $tag->id }}" data-name="{{ e($tag->name) }}"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else
        <div class="p-4 text-center">
          <p class="text-muted">No hay tags.</p>
          <a href="{{ festival_admin_url('tags/create') }}" class="btn btn-sm btn-primary">Crear primer tag</a>
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
  document.querySelectorAll('.delete-tag').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.dataset.id, name = this.dataset.name;
      Swal.fire({
        title: 'Eliminar tag', text: '¿Eliminar "' + name + '"?', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Cancelar', confirmButtonText: 'Eliminar'
      }).then(r => {
        if (r.isConfirmed) {
          const f = document.createElement('form'); f.method = 'POST';
          f.action = '{{ festival_admin_url("tags") }}/' + id;
          f.innerHTML = '<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + '{{ csrf_token() }}' + '">';
          document.body.appendChild(f); f.submit();
        }
      });
    });
  });
});
</script>
@endpush
