@extends('layouts.app')

@section('title', $title ?? __('blog.category.new_category'))

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- Navegación --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb">
        <a href="{{ admin_url('blog/categories') }}">{{ __('blog.categories') }}</a> <span class="mx-2">/</span> <span>{{ __('blog.category.new_category') }}</span>
      </div>
      <a href="{{ admin_url('blog/categories') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> {{ __('blog.category.back_to_categories') }}</a>
    </div>

    {{-- Alertas --}}
    @if (session('success'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(__('common.success')) !!}, text: {!! json_encode(session('success')) !!}, confirmButtonColor: '#3085d6' }); }); </script>
    @endif
    @if (session('error'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(__('common.error')) !!}, text: {!! json_encode(session('error')) !!}, confirmButtonColor: '#d33' }); }); </script>
    @endif

    <form method="POST" action="{{ admin_url('blog/categories') }}" id="categoryForm" enctype="multipart/form-data">
      @csrf

      <div class="row">
        {{-- Columna izquierda --}}
        <div class="col-md-9">
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.category.info') }}</strong></div>
            <div class="card-body">
              {{-- Nombre --}}
              <div class="mb-3">
                <label for="name" class="form-label">{{ __('blog.category.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name') }}" required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Slug --}}
              <div class="mb-3">
                <label for="slug" class="form-label">{{ __('blog.category.slug') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('slug') is-invalid @enderror" name="slug" id="slug" value="{{ old('slug') }}" required>
                @error('slug')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.category.slug_help') }}</small>
                <span id="slug-check-result" class="ms-1 fw-bold"></span>
              </div>

              {{-- Descripción --}}
              <div class="mb-3">
                <label for="description" class="form-label">{{ __('blog.category.description') }}</label>
                <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="4">{{ old('description') }}</textarea>
                @error('description')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>
        </div>

        {{-- Columna derecha --}}
        <div class="col-md-3">
          {{-- Card Publicar --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.category.publish_box') }}</strong></div>
            <div class="card-body">
              <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('blog.category.create') }}</button>
              </div>
            </div>
          </div>

          {{-- Card Categoría Padre --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.category.hierarchy') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="parent_id" class="form-label">{{ __('blog.category.parent') }}</label>
                <select class="form-select @error('parent_id') is-invalid @enderror" name="parent_id" id="parent_id">
                  <option value="">{{ __('blog.category.none') }}</option>
                  @foreach($parentCategories as $category)
                    <option value="{{ $category->id }}" @selected(old('parent_id') == $category->id)>
                      {{ str_repeat('— ', $category->depth ?? 0) }}{{ $category->name }}
                    </option>
                  @endforeach
                </select>
                @error('parent_id')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-3">
                <label for="order" class="form-label">{{ __('blog.category.order') }}</label>
                <input type="number" class="form-control @error('order') is-invalid @enderror" name="order" id="order" value="{{ old('order', 0) }}" min="0">
                @error('order')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.category.order_help') }}</small>
              </div>
            </div>
          </div>

          {{-- Card Imagen --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.category.image') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="image" class="form-label">{{ __('blog.category.image_url') }}</label>
                <input type="text" class="form-control @error('image') is-invalid @enderror" name="image" id="image" value="{{ old('image') }}" placeholder="{{ __('blog.category.image_placeholder') }}">
                @error('image')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div id="image-preview" class="mt-2"></div>
            </div>
          </div>

          {{-- Card Color --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.category.color') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="color" class="form-label">{{ __('blog.category.color_label') }}</label>
                <input type="color" class="form-control form-control-color @error('color') is-invalid @enderror" name="color" id="color" value="{{ old('color', '#007bff') }}">
                @error('color')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.category.color_help') }}</small>
              </div>
            </div>
          </div>

          {{-- Card Cancelar --}}
          <div class="card">
            <div class="card-body text-center">
              <a href="{{ admin_url('blog/categories') }}" class="btn btn-sm btn-outline-secondary">{{ __('common.cancel') }}</a>
            </div>
          </div>
        </div>
      </div>
    </form>

  </div>
</div>
@endsection

@include('Blog::partials._taxonomy_slug_scripts', ['type' => 'category', 'entityId' => 'new'])

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const nameInput = document.getElementById('name');
  const slugInput = document.getElementById('slug');
  const imageInput = document.getElementById('image');
  const imagePreview = document.getElementById('image-preview');

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

  // Preview de imagen
  if (imageInput && imagePreview) {
    imageInput.addEventListener('input', function() {
      const url = this.value.trim();
      if (url) {
        imagePreview.innerHTML = `<img src="${url}" class="img-fluid rounded" alt="Preview" onerror="this.parentElement.innerHTML='<p class=text-danger>No se pudo cargar la imagen</p>'">`;
      } else {
        imagePreview.innerHTML = '';
      }
    });
  }
});
</script>
@endpush
