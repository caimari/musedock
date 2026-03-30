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

{{-- Opcional: Ajustes JSON --}}
{{--
<div class="mb-3">
    <label for="settings" class="form-label">Ajustes (JSON)</label>
    <textarea class="form-control @error('settings') is-invalid @enderror" id="settings" name="settings" rows="3"
              placeholder='{ "autoplay": true, "delay": 5000 }'>{{ old('settings', $slider->settings ?? '') }}</textarea>
    <small class="text-muted">Opciones avanzadas en formato JSON (ej: velocidad, autoplay).</small>
    @error('settings')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
--}}

<div class="mt-4">
    <button type="submit" class="btn btn-primary">
        {{ (isset($form_mode) && $form_mode === 'edit') ? 'Actualizar Slider' : 'Crear Slider' }}
    </button>
    <a href="{{ route('sliders.index') }}" class="btn btn-secondary">Cancelar</a>
</div>
