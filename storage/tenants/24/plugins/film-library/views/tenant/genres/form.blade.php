@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-tags me-2"></i>{{ $title }}</h2>
      <a href="{{ film_admin_url('genres') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
    </div>

    <div class="card" style="max-width:600px;">
      <div class="card-body">
        <form method="POST" action="{{ $isEdit ? film_admin_url('genres/' . $genre->id) : film_admin_url('genres') }}">
          @csrf
          @if($isEdit)
            <input type="hidden" name="_method" value="PUT">
          @endif

          <div class="mb-3">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="{{ $genre->name ?? '' }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="description" class="form-control" rows="3">{{ $genre->description ?? '' }}</textarea>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Color</label>
              <input type="color" name="color" class="form-control form-control-color" value="{{ $genre->color ?? '#6c757d' }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">Orden</label>
              <input type="number" name="sort_order" class="form-control" value="{{ $genre->sort_order ?? 0 }}">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">SEO Title</label>
            <input type="text" name="seo_title" class="form-control" value="{{ $genre->seo_title ?? '' }}">
          </div>
          <div class="mb-3">
            <label class="form-label">SEO Description</label>
            <textarea name="seo_description" class="form-control" rows="2">{{ $genre->seo_description ?? '' }}</textarea>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> {{ $isEdit ? 'Guardar' : 'Crear' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
