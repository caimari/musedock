{!! csrf_field() !!}

<div class="mb-3">
    <label for="name" class="form-label">Nombre del Slider <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
           value="{{ old('name', $slider->name ?? '') }}" required>
    <small class="text-muted">Nombre interno para identificar el slider (ej: "Slider Portada").</small>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">
        {{ (isset($form_mode) && $form_mode === 'edit') ? 'Actualizar Slider' : 'Crear Slider' }}
    </button>
    <a href="/{{ admin_path() }}/sliders" class="btn btn-secondary">Cancelar</a>
</div>
