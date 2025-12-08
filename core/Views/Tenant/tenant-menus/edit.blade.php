@extends('layouts.app')

@section('title', $title ?? 'Editar Menú')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    {{-- Título y Botón Volver --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title ?? 'Editar Menú' }}</h2>
      <a href="{{ route('tenant-menus.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
      </a>
    </div>

    {{-- Formulario --}}
    <div class="card">
      <div class="card-body">
        <form method="POST" action="{{ route('tenant-menus.update', ['id' => $menu->id]) }}">
          <input type="hidden" name="_token" value="{{ csrf_token() }}">

          <div class="row">
            {{-- Título --}}
            <div class="col-md-6 mb-3">
              <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="title" name="title" value="{{ $menu->title }}" required>
            </div>

            {{-- Slug --}}
            <div class="col-md-6 mb-3">
              <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="slug" name="slug" value="{{ $menu->slug }}" required>
              <small class="text-muted">Identificador único</small>
            </div>
          </div>

          <div class="row">
            {{-- URL --}}
            <div class="col-md-8 mb-3">
              <label for="url" class="form-label">URL <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="url" name="url" value="{{ $menu->url }}" required>
              <small class="text-muted">Usa {admin_path} como prefijo (ej: {admin_path}/configuracion)</small>
            </div>

            {{-- Orden --}}
            <div class="col-md-4 mb-3">
              <label for="order_position" class="form-label">Orden</label>
              <input type="number" class="form-control" id="order_position" name="order_position" value="{{ $menu->order_position }}" min="0">
            </div>
          </div>

          <div class="row">
            {{-- Icono --}}
            <div class="col-md-6 mb-3">
              <label for="icon" class="form-label">Icono</label>
              <input type="text" class="form-control" id="icon" name="icon" value="{{ $menu->icon }}">
              <small class="text-muted">
                <a href="https://icons.getbootstrap.com/" target="_blank">Ver iconos Bootstrap Icons</a>
              </small>
            </div>

            {{-- Tipo de Icono --}}
            <div class="col-md-6 mb-3">
              <label for="icon_type" class="form-label">Tipo de Icono</label>
              <select class="form-select" id="icon_type" name="icon_type">
                <option value="bi" {{ $menu->icon_type === 'bi' ? 'selected' : '' }}>Bootstrap Icons</option>
                <option value="fas" {{ $menu->icon_type === 'fas' ? 'selected' : '' }}>FontAwesome Solid</option>
                <option value="far" {{ $menu->icon_type === 'far' ? 'selected' : '' }}>FontAwesome Regular</option>
                <option value="fal" {{ $menu->icon_type === 'fal' ? 'selected' : '' }}>FontAwesome Light</option>
              </select>
            </div>
          </div>

          <div class="row">
            {{-- Estado Activo --}}
            <div class="col-md-12 mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" {{ $menu->is_active ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Menú activo (visible en el panel)</label>
              </div>
            </div>
          </div>

          {{-- Botones --}}
          <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-circle"></i> Guardar Cambios
            </button>
            <a href="{{ route('tenant-menus.index') }}" class="btn btn-outline-secondary">
              Cancelar
            </a>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>
@endsection
