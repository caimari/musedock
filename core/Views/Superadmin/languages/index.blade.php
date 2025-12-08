@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h2 class="mb-4">{{ $title }}</h2>
    @include('partials.alerts')
    <a href="{{ route('languages.create') }}" class="btn btn-success mb-3">Añadir nuevo idioma</a>
    <div class="card">
      <div class="card-body table-responsive p-0">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>Código</th>
              <th>Nombre</th>
              <th>Activo</th>
              <th>Tenant</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($languages as $lang)
            <tr>
              <td>{{ $lang->code }}</td>
              <td>{{ $lang->name }}</td>
              <td>
                <form method="POST" action="{{ route('languages.toggle', $lang->id) }}">
                  {!! csrf_field() !!}
                  <button class="btn btn-sm {{ $lang->active ? 'btn-success' : 'btn-outline-secondary' }}">
                    {{ $lang->active ? 'Activo' : 'Inactivo' }}
                  </button>
                </form>
              </td>
              <td>
                @if($lang->tenant_id)
                  {{ $lang->tenant_domain ?? $lang->tenant_name ?? $lang->tenant_id }}
                @else
                  Global
                @endif
              </td>
              <td>
                <a href="{{ route('languages.edit', $lang->id) }}" class="btn btn-sm btn-primary">Editar</a>
                <form action="{{ route('languages.delete', $lang->id) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('¿Seguro que quieres eliminar este idioma?');">
                  {!! csrf_field() !!}
                  <button class="btn btn-sm btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection