@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <a href="{{ festival_admin_url('types') }}" class="text-muted text-decoration-none">Tipos</a>
        <span class="mx-1 text-muted">/</span> <span>Crear</span>
      </div>
      <a href="{{ festival_admin_url('types') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>

    @if(session('error'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'error', title:'Error', html:{!! json_encode(session('error')) !!} }); });</script>
    @endif

    <form method="POST" action="{{ festival_admin_url('types') }}">
      @csrf
      <div class="card" style="max-width:600px">
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" value="{{ old('name', $type->name ?? '') }}" required placeholder="Ej: Certamen de Cortometrajes">
          </div>
          <div class="mb-3">
            <label class="form-label">Slug</label>
            <input type="text" class="form-control" name="slug" value="{{ old('slug', $type->slug ?? '') }}" placeholder="auto-generado">
          </div>
          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="description" rows="2">{{ old('description', $type->description ?? '') }}</textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Icono <small class="text-muted">(Bootstrap Icons)</small></label>
              <input type="text" class="form-control" name="icon" value="{{ old('icon', $type->icon ?? 'bi-circle') }}" placeholder="bi-camera-reels">
              <small class="text-muted"><a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Ver iconos</a></small>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Color</label>
              <input type="color" class="form-control form-control-color" name="color" value="{{ old('color', $type->color ?? '#6c757d') }}">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Orden</label>
              <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $type->sort_order ?? 0) }}">
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Guardar</button>
          <a href="{{ festival_admin_url('types') }}" class="btn btn-outline-secondary ms-2">Cancelar</a>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
