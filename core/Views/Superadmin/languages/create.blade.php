@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Añadir nuevo idioma</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('languages.store') }}">
          {!! csrf_field() !!}
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Código del idioma <span class="text-muted">(ej. "es", "en")</span></label>
              <input type="text" name="code" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Nombre</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Tenant</label>
              <select name="tenant_id" class="form-control">
                <option value="global">Global (CMS Principal)</option>
                @foreach ($tenants as $tenant)
                  <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" value="1" id="activeCheck" checked>
            <label class="form-check-label" for="activeCheck">Idioma activo</label>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-success">Guardar</button>
            <a href="{{ route('languages.index') }}" class="btn btn-secondary">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection