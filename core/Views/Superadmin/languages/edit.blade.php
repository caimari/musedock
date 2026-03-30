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
        <form method="POST" action="{{ route('languages.update', $language->id) }}">
          {!! csrf_field() !!}
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Código del idioma</label>
              <input type="text" name="code" class="form-control" value="{{ $language->code }}" required>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Nombre</label>
              <input type="text" name="name" class="form-control" value="{{ $language->name }}" required>
            </div>
            <input type="hidden" name="tenant_id" value="global">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" value="1" id="activeCheck"
              @if($language->active) checked @endif>
            <label class="form-check-label" for="activeCheck">Idioma activo</label>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="{{ route('languages.index') }}" class="btn btn-secondary">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
