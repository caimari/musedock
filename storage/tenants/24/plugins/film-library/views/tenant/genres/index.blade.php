@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-tags me-2"></i>{{ $title }}</h2>
      <a href="{{ film_admin_url('genres/create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Crear Género
      </a>
    </div>

    @if(session('success'))
      <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', text: {!! json_encode(session('success')) !!}, timer: 3000 }); });</script>
    @endif

    <div class="card">
      <div class="card-body table-responsive p-0">
        @if(!empty($genres) && count($genres) > 0)
        <table class="table table-hover table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Slug</th>
              <th>TMDb ID</th>
              <th>Color</th>
              <th>Películas</th>
              <th style="width:120px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($genres as $genre)
            <tr>
              <td class="fw-semibold">{{ $genre->name }}</td>
              <td><code>{{ $genre->slug }}</code></td>
              <td>{{ $genre->tmdb_id ?? '—' }}</td>
              <td><span class="badge" style="background-color:{{ $genre->color ?? '#6c757d' }}">{{ $genre->color ?? '#6c757d' }}</span></td>
              <td>{{ $genre->film_count ?? 0 }}</td>
              <td>
                <a href="{{ film_admin_url('genres/' . $genre->id . '/edit') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="{{ $genre->id }}" data-name="{{ $genre->name }}"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else
          <div class="text-center py-5 text-muted">
            <i class="bi bi-tags" style="font-size:3rem;"></i>
            <p class="mt-2">No hay géneros. Los géneros se crean automáticamente al importar películas de TMDb.</p>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-delete').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.dataset.id, name = this.dataset.name;
      Swal.fire({
        title: 'Eliminar género',
        text: '¿Eliminar "' + name + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar'
      }).then(function(result) {
        if (result.isConfirmed) {
          var form = document.createElement('form');
          form.method = 'POST';
          form.action = '{{ film_admin_url("genres") }}/' + id;
          form.innerHTML = '@csrf<input type="hidden" name="_method" value="DELETE">';
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
  });
});
</script>
@endsection
