@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Editar idioma</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="/{{ admin_path() }}/languages/{{ $language->id }}/update">
          {!! csrf_field() !!}
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Código del idioma</label>
              <input type="text" name="code" class="form-control" value="{{ $language->code }}" disabled>
              <small class="text-muted">El código no se puede modificar después de crear el idioma</small>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Nombre del idioma</label>
              <input type="text" name="name" class="form-control" value="{{ $language->name }}" required>
            </div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" value="1" id="activeCheck"
              @if($language->active) checked @endif>
            <label class="form-check-label" for="activeCheck">Idioma activo</label>
            <br>
            <small class="text-muted">Los idiomas inactivos no aparecen en los selectores del sitio</small>
          </div>

          <div class="alert alert-warning mt-3">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Importante:</strong> Si desactivas este idioma y es el último activo, la acción será rechazada. Debe haber al menos un idioma activo en tu sitio.
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save me-1"></i> Actualizar idioma
            </button>
            <a href="/{{ admin_path() }}/languages" class="btn btn-secondary">
              <i class="bi bi-x-lg me-1"></i> Cancelar
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
