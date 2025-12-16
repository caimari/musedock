@extends('layouts.app')

@section('title', $title ?? __('blog.category.edit'))

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- Navegación --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb">
        <a href="{{ route('blog.categories.index') }}">{{ __('blog.categories') }}</a> <span class="mx-2">/</span> <span>{{ e($category->name) }}</span>
      </div>
      <a href="{{ route('blog.categories.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> {{ __('blog.category.back_to_categories') }}</a>
    </div>

    {{-- Alertas Toast --}}
    @if (session('success'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: {!! json_encode(session('success')) !!},
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
          });
        });
      </script>
    @endif
    @if (session('error'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'error',
            title: {!! json_encode(session('error')) !!},
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true
          });
        });
      </script>
    @endif

    <form method="POST" action="{{ route('blog.categories.update', ['id' => $category->id]) }}" id="categoryForm" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      <div class="row">
        {{-- Columna izquierda --}}
        <div class="col-md-9">
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.category.info') }}</strong></div>
            <div class="card-body">
              {{-- Nombre --}}
              <div class="mb-3">
                <label for="name" class="form-label">{{ __('blog.category.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name', $category->name) }}" required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Slug --}}
              <div class="mb-3">
                <label for="slug" class="form-label">{{ __('blog.category.slug') }} <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input type="text" class="form-control @error('slug') is-invalid @enderror" name="slug" id="slug" value="{{ old('slug', $category->slug) }}" required readonly>
                  <button type="button" class="btn btn-outline-secondary" id="toggle-slug-edit" title="Editar Slug">
                    <i class="bi bi-lock"></i>
                  </button>
                  @error('slug')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <small class="text-muted">{{ __('blog.category.slug_help') }}</small>
                <span id="slug-check-result" class="ms-1 fw-bold"></span>
              </div>

              {{-- Descripción --}}
              <div class="mb-3">
                <label for="description" class="form-label">{{ __('blog.category.description') }}</label>
                <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="4">{{ old('description', $category->description) }}</textarea>
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
                <button type="submit" class="btn btn-primary">{{ __('common.update') }}</button>
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
                  @foreach($parentCategories as $parentCategory)
                    @if($parentCategory->id !== $category->id)
                      <option value="{{ $parentCategory->id }}" @selected(old('parent_id', $category->parent_id) == $parentCategory->id)>
                        {{ str_repeat('— ', $parentCategory->depth ?? 0) }}{{ $parentCategory->name }}
                      </option>
                    @endif
                  @endforeach
                </select>
                @error('parent_id')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-3">
                <label for="order" class="form-label">{{ __('blog.category.order') }}</label>
                <input type="number" class="form-control @error('order') is-invalid @enderror" name="order" id="order" value="{{ old('order', $category->order ?? 0) }}" min="0">
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
                <input type="text" class="form-control @error('image') is-invalid @enderror" name="image" id="image" value="{{ old('image', $category->image) }}" placeholder="{{ __('blog.category.image_placeholder') }}">
                @error('image')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div id="image-preview" class="mt-2">
                @if($category->image)
                  <img src="{{ $category->image }}" class="img-fluid rounded" alt="Preview">
                @endif
              </div>
            </div>
          </div>

          {{-- Card Color --}}
          <div class="card mb-4">
            <div class="card-header"><strong>{{ __('blog.category.color') }}</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="color" class="form-label">{{ __('blog.category.color_label') }}</label>
                <input type="color" class="form-control form-control-color @error('color') is-invalid @enderror" name="color" id="color" value="{{ old('color', $category->color ?? '#007bff') }}">
                @error('color')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('blog.category.color_help') }}</small>
              </div>
            </div>
          </div>

          {{-- Card Info --}}
          <div class="card mb-4">
            <div class="card-body p-2">
              <small class="text-muted d-block mb-1">
                <strong>Posts asociados:</strong> {{ $category->posts_count ?? 0 }}
              </small>
              <small class="text-muted d-block mb-1">
                <strong>Creado:</strong> {{ $category->created_at ? $category->created_at->format('d/m/Y H:i') : '—' }}
              </small>
              <small class="text-muted d-block">
                <strong>Actualizado:</strong> {{ $category->updated_at ? $category->updated_at->format('d/m/Y H:i') : '—' }}
              </small>
            </div>
          </div>

          {{-- Card Eliminar --}}
          <div class="card mb-4">
            <div class="card-body text-center">
              <a href="javascript:void(0);" onclick="confirmDelete({{ $category->id }})" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-trash me-1"></i> Eliminar categoría
              </a>
            </div>
          </div>
        </div>
      </div>
    </form>

  </div>
</div>

<script>
  function confirmDelete(categoryId) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: 'Esta acción eliminará permanentemente esta categoría y no se puede deshacer.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `{{ admin_path() }}/blog/categories/${categoryId}`;
        form.innerHTML = `
          @csrf
          @method('DELETE')
        `;
        document.body.appendChild(form);
        form.submit();
      }
    });
  }
</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const imageInput = document.getElementById('image');
  const imagePreview = document.getElementById('image-preview');

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
@endsection

@include('Blog::partials._taxonomy_slug_scripts', ['type' => 'category', 'entityId' => $category->id])
