@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <a href="{{ festival_admin_url('tags') }}" class="text-muted text-decoration-none">Tags</a>
        <span class="mx-1 text-muted">/</span> <span>{{ $isNew ? 'Crear' : 'Editar' }}</span>
      </div>
      <a href="{{ festival_admin_url('tags') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>

    @if(session('error'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'error', title:'Error', html:{!! json_encode(session('error')) !!} }); });</script>
    @endif

    <form method="POST" action="{{ festival_admin_url('tags') }}">
      @csrf
      <div class="card" style="max-width:500px">
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" value="{{ old('name', $tag->name ?? '') }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Slug</label>
            <input type="text" class="form-control" name="slug" value="{{ old('slug', $tag->slug ?? '') }}" placeholder="auto-generado">
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Guardar</button>
          <a href="{{ festival_admin_url('tags') }}" class="btn btn-outline-secondary ms-2">Cancelar</a>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
