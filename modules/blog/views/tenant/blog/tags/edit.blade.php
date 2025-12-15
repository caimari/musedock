@extends('layouts.app')

@section('title', $title ?? 'Editar Etiqueta')

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- Navegación --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb">
        <a href="{{ admin_url('blog/tags') }}">Etiquetas</a> <span class="mx-2">/</span> <span>{{ e($tag->name) }}</span>
      </div>
      <a href="{{ admin_url('blog/tags') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Volver a Etiquetas</a>
    </div>

    {{-- Alertas --}}
    @if (session('success'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(__('common.success')) !!}, text: {!! json_encode(session('success')) !!}, confirmButtonColor: '#3085d6' }); }); </script>
    @endif
    @if (session('error'))
      <script> document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(__('common.error')) !!}, text: {!! json_encode(session('error')) !!}, confirmButtonColor: '#d33' }); }); </script>
    @endif

    <form method="POST" action="{{ admin_url('blog/tags/' . $tag->id) }}" id="tagForm">
      @csrf
      @method('PUT')

      <div class="row">
        {{-- Columna izquierda --}}
        <div class="col-md-9">
          <div class="card mb-4">
            <div class="card-header"><strong>Información de la Etiqueta</strong></div>
            <div class="card-body">
              {{-- Nombre --}}
              <div class="mb-3">
                <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name', $tag->name) }}" required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Slug --}}
              <div class="mb-3">
                <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input type="text" class="form-control @error('slug') is-invalid @enderror" name="slug" id="slug" value="{{ old('slug', $tag->slug) }}" required readonly>
                  <button type="button" class="btn btn-outline-secondary" id="toggle-slug-edit" title="Editar Slug">
                    <i class="bi bi-lock"></i>
                  </button>
                  @error('slug')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <small class="text-muted">URL amigable. Se genera automáticamente desde el nombre.</small>
              </div>

              {{-- Descripción --}}
              <div class="mb-3">
                <label for="description" class="form-label">{{ __('blog.tag.description') }}</label>
                <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="4" placeholder="Descripción breve de la etiqueta (opcional)">{{ old('description', $tag->description) }}</textarea>
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
          {{-- Card Actualizar --}}
          <div class="card mb-4">
            <div class="card-header"><strong>Actualizar</strong></div>
            <div class="card-body">
              <div class="d-grid">
                <button type="submit" class="btn btn-primary">{{ __('common.update') }}</button>
              </div>
            </div>
          </div>

          {{-- Card Color --}}
          <div class="card mb-4">
            <div class="card-header"><strong>Personalización</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="color" class="form-label">Color de la etiqueta</label>
                <input type="color" class="form-control form-control-color @error('color') is-invalid @enderror" name="color" id="color" value="{{ old('color', $tag->color ?? '#6c757d') }}">
                @error('color')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Se usará para badges y destacados.</small>
              </div>
            </div>
          </div>

          {{-- Card Info --}}
          <div class="card mb-4">
            <div class="card-body p-2">
              <small class="text-muted d-block mb-1">
                <strong>Posts asociados:</strong> {{ $tag->posts_count ?? 0 }}
              </small>
              <small class="text-muted d-block mb-1">
                <strong>Creado:</strong> {{ $tag->created_at ? $tag->created_at->format('d/m/Y H:i') : '—' }}
              </small>
              <small class="text-muted d-block">
                <strong>Actualizado:</strong> {{ $tag->updated_at ? $tag->updated_at->format('d/m/Y H:i') : '—' }}
              </small>
            </div>
          </div>

          {{-- Card Eliminar --}}
          <div class="card mb-4">
            <div class="card-body text-center">
              <a href="javascript:void(0);" onclick="confirmDelete({{ $tag->id }})" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-trash me-1"></i> Eliminar etiqueta
              </a>
            </div>
          </div>
        </div>
      </div>
    </form>

  </div>
</div>

<script>
  function confirmDelete(tagId) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: 'Esta acción eliminará permanentemente esta etiqueta y no se puede deshacer.',
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
        form.action = `{{ admin_path() }}/blog/tags/${tagId}`;
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
  const slugInput = document.getElementById('slug');
  const toggleSlugBtn = document.getElementById('toggle-slug-edit');

  // Toggle edición de slug
  if (toggleSlugBtn && slugInput) {
    toggleSlugBtn.addEventListener('click', function() {
      if (slugInput.readOnly) {
        slugInput.readOnly = false;
        this.querySelector('i').className = 'bi bi-unlock';
        slugInput.focus();
      } else {
        slugInput.readOnly = true;
        this.querySelector('i').className = 'bi bi-lock';
      }
    });
  }
});
</script>
@endpush
@endsection
