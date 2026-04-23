@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0"><i class="bi bi-camera-reels me-2"></i>{{ $title }}</h2>
        <small class="text-muted">{{ $pagination['total'] ?? 0 }} películas en total</small>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="{{ film_admin_url('genres') }}" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-tags me-1"></i> Géneros
        </a>
        <a href="{{ film_admin_url('tmdb') }}" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-cloud-download me-1"></i> Importar TMDb
        </a>
        <a href="{{ film_admin_url('settings') }}" class="btn btn-outline-dark btn-sm">
          <i class="bi bi-gear me-1"></i> Settings
        </a>
        <a href="{{ film_admin_url('create') }}" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i> Crear Película
        </a>
      </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="{{ film_admin_url() }}" class="d-flex align-items-center gap-2 flex-wrap">
          <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar película..."
                 class="form-control form-control-sm" style="width: 220px;">
          <select name="status" class="form-select form-select-sm" style="width: 150px;">
            <option value="">Todos los estados</option>
            @foreach($statuses as $key => $label)
              <option value="{{ $key }}" {{ ($statusFilter ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-search"></i> Filtrar
          </button>
          @if(!empty($search) || !empty($statusFilter))
            <a href="{{ film_admin_url() }}" class="btn btn-outline-danger btn-sm">
              <i class="bi bi-x-lg"></i> Limpiar
            </a>
          @endif
        </form>
      </div>
    </div>

    {{-- Flash messages --}}
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
    <form method="POST" action="{{ film_admin_url('bulk') }}" id="bulkActionForm">
      @csrf
      <div class="card">
        <div class="card-body table-responsive p-0">
          @if(!empty($films) && count($films) > 0)
          <table class="table table-hover table-striped align-middle mb-0">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                <th style="width:60px;">Póster</th>
                <th>Título</th>
                <th>Director</th>
                <th>Año</th>
                <th>Géneros</th>
                <th>TMDb</th>
                <th>Estado</th>
                <th style="width:100px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach($films as $film)
              <tr>
                <td><input type="checkbox" name="ids[]" value="{{ $film->id }}" class="row-check"></td>
                <td>
                  @if($film->poster_path)
                    <img src="{{ film_poster_url($film->poster_path, 'w92') }}" alt="" style="height:50px; border-radius:4px;">
                  @else
                    <div style="width:35px;height:50px;background:#dee2e6;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                      <i class="bi bi-film text-muted"></i>
                    </div>
                  @endif
                </td>
                <td>
                  <a href="{{ film_admin_url($film->id . '/edit') }}" class="fw-semibold text-decoration-none">
                    {{ $film->title }}
                  </a>
                  @if($film->original_title && $film->original_title !== $film->title)
                    <br><small class="text-muted">{{ $film->original_title }}</small>
                  @endif
                </td>
                <td>{{ $film->director ?? '—' }}</td>
                <td>{{ $film->year ?? '—' }}</td>
                <td>
                  @foreach($filmGenres[$film->id] ?? [] as $g)
                    <span class="badge" style="background-color:{{ $g['color'] ?? '#6c757d' }}">{{ $g['name'] }}</span>
                  @endforeach
                </td>
                <td>
                  @if($film->tmdb_rating)
                    <span class="badge bg-warning text-dark">{{ number_format($film->tmdb_rating, 1) }}</span>
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if($film->status === 'published')
                    <span class="badge bg-success">Publicado</span>
                  @else
                    <span class="badge bg-secondary">Borrador</span>
                  @endif
                </td>
                <td>
                  <a href="{{ film_admin_url($film->id . '/edit') }}" class="btn btn-sm btn-outline-primary" title="Editar">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="{{ $film->id }}" data-title="{{ $film->title }}" title="Eliminar">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @else
            <div class="text-center py-5 text-muted">
              <i class="bi bi-camera-reels" style="font-size:3rem;"></i>
              <p class="mt-2">No hay películas todavía.</p>
              <a href="{{ film_admin_url('tmdb') }}" class="btn btn-primary mt-2">
                <i class="bi bi-cloud-download me-1"></i> Importar desde TMDb
              </a>
            </div>
          @endif
        </div>

        @if(!empty($films) && count($films) > 0)
        <div class="card-footer d-flex justify-content-between align-items-center">
          <div class="d-flex gap-2 align-items-center">
            <select name="action" class="form-select form-select-sm" style="width:160px;">
              <option value="">Acción masiva...</option>
              <option value="publish">Publicar</option>
              <option value="draft">Pasar a borrador</option>
              <option value="delete">Eliminar</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">Aplicar</button>
          </div>

          {{-- Pagination --}}
          @if(($pagination['last_page'] ?? 1) > 1)
          <nav>
            <ul class="pagination pagination-sm mb-0">
              @for($p = 1; $p <= $pagination['last_page']; $p++)
                <li class="page-item {{ $p == $pagination['current_page'] ? 'active' : '' }}">
                  <a class="page-link" href="{{ film_admin_url() }}?page={{ $p }}&search={{ urlencode($search ?? '') }}&status={{ $statusFilter ?? '' }}">{{ $p }}</a>
                </li>
              @endfor
            </ul>
          </nav>
          @endif
        </div>
        @endif
      </div>
    </form>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
  });

  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function() {
      var id = this.dataset.id, title = this.dataset.title;
      Swal.fire({
        title: 'Eliminar película',
        text: '¿Eliminar "' + title + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar'
      }).then(function(result) {
        if (result.isConfirmed) {
          var form = document.createElement('form');
          form.method = 'POST';
          form.action = '{{ film_admin_url() }}/' + id;
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
