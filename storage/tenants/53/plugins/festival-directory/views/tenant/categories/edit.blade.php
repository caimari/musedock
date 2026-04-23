@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <a href="{{ festival_admin_url('categories') }}" class="text-muted text-decoration-none">Categorías</a>
        <span class="mx-1 text-muted">/</span>
        <span>Editar: {{ $category->name }}</span>
      </div>
      <a href="{{ festival_admin_url('categories') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>

    @if(session('error'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'error', title:'Error', html:{!! json_encode(session('error')) !!} }); });</script>
    @endif
    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'success', title:'OK', text:{!! json_encode(session('success')) !!}, timer:3000 }); });</script>
    @endif

    <form method="POST" action="{{ festival_admin_url('categories/' . $category->id) }}">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <div class="card" style="max-width:700px">
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" value="{{ old('name', $category->name) }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Slug</label>
            <input type="text" class="form-control" name="slug" value="{{ old('slug', $category->slug) }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="description" rows="3">{{ old('description', $category->description) }}</textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Color</label>
              <input type="color" class="form-control form-control-color" name="color" value="{{ old('color', $category->color ?? '#6c757d') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Orden</label>
              <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Imagen</label>
            <input type="url" class="form-control" name="image" value="{{ old('image', $category->image) }}" placeholder="URL">
          </div>
          <hr>
          <div class="mb-3">
            <label class="form-label">Título SEO</label>
            <input type="text" class="form-control" name="seo_title" value="{{ old('seo_title', $category->seo_title) }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Descripción SEO</label>
            <textarea class="form-control" name="seo_description" rows="2">{{ old('seo_description', $category->seo_description) }}</textarea>
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Actualizar</button>
          <a href="{{ festival_admin_url('categories') }}" class="btn btn-outline-secondary ms-2">Cancelar</a>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
