@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <a href="{{ '/' . admin_path() . '/menus/create' }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Crear Nuevo Menú
      </a>
    </div>

    {{-- Mensajes de éxito o error --}}
    @include('partials.alerts-sweetalert2')

    <div class="card">
      <div class="card-body table-responsive p-0">
        @if (!empty($menus) && count($menus) > 0)
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Título</th>
                <th>Idioma</th>
                <th>Ubicación</th>
                <th>Elementos</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($menus as $menu)
                <tr>
                  <td>{{ $menu->title }}</td>
                  <td>
                    <span class="badge bg-info">{{ strtoupper($menu->locale ?? 'es') }}</span>
                  </td>
                  <td>
                    @if($menu->location == 'nav')
                      <span class="badge bg-primary">Navegación principal</span>
                    @elseif($menu->location == 'footer')
                      <span class="badge bg-secondary">Footer</span>
                    @elseif($menu->location == 'sidebar')
                      <span class="badge bg-info">Sidebar</span>
                    @else
                      <span class="badge bg-light text-dark">{{ $menu->location }}</span>
                    @endif
                  </td>
                  <td>{{ $menu->item_count }} elementos</td>
                  <td>
                    <a href="{{ '/' . admin_path() . '/menus/' . $menu->id . '/edit' }}" class="btn btn-sm btn-primary">
                      <i class="bi bi-pencil"></i> Editar
                    </a>
                    <form action="{{ '/' . admin_path() . '/menus/' . $menu->id }}" method="POST" class="d-inline-block" onsubmit="return confirm('¿Seguro que quieres eliminar este menú? Se eliminarán también todos sus elementos.');">
                      @csrf
                      <input type="hidden" name="_method" value="DELETE">
                      <button class="btn btn-sm btn-danger" type="submit">
                        <i class="bi bi-trash"></i> Eliminar
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <div class="p-3 text-center">
            <p class="text-muted">No se han encontrado menús.</p>
            <p>Crea tu primer menú haciendo clic en "Crear Nuevo Menú".</p>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
