@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <a href="{{ festival_admin_url('types') }}" class="text-muted text-decoration-none">Tipos</a>
        <span class="mx-1 text-muted">/</span> <span>Editar: {{ $type->name }}</span>
      </div>
      <a href="{{ festival_admin_url('types') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>

    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'success', title:'OK', text:{!! json_encode(session('success')) !!}, timer:3000 }); });</script>
    @endif
    @if(session('error'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'error', title:'Error', html:{!! json_encode(session('error')) !!} }); });</script>
    @endif

    <form method="POST" action="{{ festival_admin_url('types/' . $type->id) }}">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <div class="card" style="max-width:600px">
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" value="{{ old('name', $type->name) }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Slug</label>
            <input type="text" class="form-control" name="slug" value="{{ old('slug', $type->slug) }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="description" rows="2">{{ old('description', $type->description) }}</textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Icono</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi {{ $type->icon ?? 'bi-circle' }}"></i></span>
                <input type="text" class="form-control" name="icon" value="{{ old('icon', $type->icon) }}" placeholder="bi-camera-reels">
              </div>
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
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Actualizar</button>
          <a href="{{ festival_admin_url('types') }}" class="btn btn-outline-secondary ms-2">Cancelar</a>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
