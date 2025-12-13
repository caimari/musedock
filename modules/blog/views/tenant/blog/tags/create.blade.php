@extends('layouts.app')

@section('title', $title ?? 'Nueva Etiqueta')

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- Navegación --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb">
        <a href="{{ route('blog.tags.index') }}">Etiquetas</a> <span class="mx-2">/</span> <span>Nueva etiqueta</span>
      </div>
      <a href="{{ route('blog.tags.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Volver a Etiquetas</a>
    </div>

    {{-- Alertas --}}
    @if (session('success'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(__('common.success')) !!}, text: {!! json_encode(session('success')) !!}, confirmButtonColor: '#3085d6' }); }); </script>
    @endif
    @if (session('error'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(__('common.error')) !!}, text: {!! json_encode(session('error')) !!}, confirmButtonColor: '#d33' }); }); </script>
    @endif

    <form method="POST" action="{{ route('blog.tags.store') }}" id="tagForm">
      @csrf

      <div class="row">
        {{-- Columna izquierda --}}
        <div class="col-md-9">
          <div class="card mb-4">
            <div class="card-header"><strong>Información de la Etiqueta</strong></div>
            <div class="card-body">
              {{-- Nombre --}}
              <div class="mb-3">
                <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name') }}" required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Slug --}}
              <div class="mb-3">
                <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('slug') is-invalid @enderror" name="slug" id="slug" value="{{ old('slug') }}" required>
                @error('slug')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">URL amigable. Se genera automáticamente desde el nombre.</small>
              </div>

              {{-- Descripción --}}
              <div class="mb-3">
                <label for="description" class="form-label">{{ __('blog.tag.description') }}</label>
                <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="4" placeholder="Descripción breve de la etiqueta (opcional)">{{ old('description') }}</textarea>
                @error('description')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Esta descripción puede aparecer en páginas de archivo de la etiqueta.</small>
              </div>
            </div>
          </div>
        </div>

        {{-- Columna derecha --}}
        <div class="col-md-3">
          {{-- Card Publicar --}}
          <div class="card mb-4">
            <div class="card-header"><strong>Publicar</strong></div>
            <div class="card-body">
              <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('blog.tag.create') }}</button>
              </div>
            </div>
          </div>

          {{-- Card Color --}}
          <div class="card mb-4">
            <div class="card-header"><strong>Personalización</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="color" class="form-label">Color de la etiqueta</label>
                <input type="color" class="form-control form-control-color @error('color') is-invalid @enderror" name="color" id="color" value="{{ old('color', '#6c757d') }}">
                @error('color')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Se usará para badges y destacados.</small>
              </div>
            </div>
          </div>

          {{-- Card Cancelar --}}
          <div class="card">
            <div class="card-body text-center">
              <a href="{{ route('blog.tags.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('common.cancel') }}</a>
            </div>
          </div>
        </div>
      </div>
    </form>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const nameInput = document.getElementById('name');
  const slugInput = document.getElementById('slug');

  // Auto-generar slug desde nombre
  if (nameInput && slugInput) {
    nameInput.addEventListener('input', function() {
      const slug = generateSlug(this.value);
      slugInput.value = slug;
    });
  }

  // Función para generar slug
  function generateSlug(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-');
  }
});
</script>
@endpush
