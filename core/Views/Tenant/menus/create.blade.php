@extends('layouts.app')
@section('title', 'Crear Nuevo Menú')
@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>Crear Nuevo Menú</h2>
      <a href="{{ '/' . admin_path() . '/menus' }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al listado
      </a>
    </div>

    {{-- Mensajes de éxito o error --}}
    @include('partials.alerts-sweetalert2')

    <div class="card">
      <div class="card-body">
        <form method="POST" action="{{ '/' . admin_path() . '/menus/store' }}">
          @csrf
          <div class="mb-3">
            <label for="title" class="form-label">Título del menú</label>
            <input type="text" class="form-control" id="title" name="title" required>
            <small class="text-muted">Este es el nombre que identificará al menú en el panel de administración.</small>
          </div>

          <div class="mb-3">
            <label for="location" class="form-label">Ubicación</label>
            <select class="form-select" id="location" name="location">
              <option value="">Sin ubicación</option>
              @foreach ($menuAreas as $area)
                <option value="{{ $area['id'] }}">{{ $area['name'] }}</option>
              @endforeach
            </select>
            <small class="text-muted">Define dónde se mostrará este menú en el sitio web.</small>
          </div>

          <div class="mb-3">
            <label for="locale" class="form-label">Idioma</label>
            <select class="form-select" id="locale" name="locale">
              @foreach ($languages as $language)
                <option value="{{ $language->code }}" {{ $language->code == $defaultLanguage ? 'selected' : '' }}>
                  {{ $language->name }}
                </option>
              @endforeach
            </select>
            <small class="text-muted">El idioma principal para este menú. Podrás añadir más traducciones después.</small>
          </div>

          <div class="d-flex justify-content-end">
            <a href="{{ '/' . admin_path() . '/menus' }}" class="btn btn-secondary me-2">Cancelar</a>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-plus-circle me-1"></i> Crear Menú
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
