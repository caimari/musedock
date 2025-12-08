{{-- core/views/superadmin/themes/appearance.blade.php --}}
@extends('layouts.app')

@section('title', $title ?? 'Personalizar Apariencia')

{{-- Añadir CSS específico para el personalizador si es necesario --}}
@push('styles')
<style>
    /* Estilos básicos para el personalizador */
    .appearance-section { border: 1px solid #e9ecef; border-radius: .25rem; margin-bottom: 1.5rem; }
    .appearance-section-header { background-color: #f8f9fa; padding: .75rem 1.25rem; border-bottom: 1px solid #e9ecef; font-weight: 600; }
    .appearance-section-body { padding: 1.25rem; }
    .form-group { margin-bottom: 1rem; }
    /* Estilos para dependencia condicional (ocultar/mostrar) */
    .option-group.hidden-by-dependency { display: none; }
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- Título y Botones --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
             <div class="breadcrumb">
                <a href="{{ route('themes.index') }}">Temas</a> {{-- Asume ruta themes.index --}}
                <span class="mx-2">/</span>
                <span>Personalizar: {{ e($theme['name'] ?? $slug) }} {{ $tenantId ? '(Tenant #'.$tenantId.')' : '(Global)' }}</span>
            </div>
            <div>
                 {{-- Botón para previsualizar (funcionalidad futura) --}}
                 {{-- <button type="button" class="btn btn-outline-secondary me-2" id="preview-button">Previsualizar</button> --}}
                 {{-- Botón Guardar apunta al formulario principal --}}
                 <button type="submit" form="appearanceForm" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </div>

        {{-- Alertas SweetAlert2 --}}
        @include('partials.alerts-sweetalert2')

        {{-- Formulario principal --}}
        <form method="POST" action="{{ route('themes.appearance.save', ['slug' => $slug, 'tenantId' => $tenantId]) }}" id="appearanceForm">
             @csrf {{-- Asume directiva csrf --}}
             {{-- Usar PUT si tu ruta lo espera --}}
             {{-- @method('PUT') --}}

             {{-- Mensaje sobre el origen de las opciones --}}
             @if($optionSource === 'global' && $tenantId)
                <div class="alert alert-info small">
                    Estás editando las opciones globales para este tema. Los cambios afectarán a todos los tenants que no tengan sus propias opciones personalizadas. Para crear opciones específicas para este tenant, simplemente guarda los cambios.
                </div>
             @elseif($optionSource === 'tenant')
                 <div class="alert alert-success small">
                     Estás editando las opciones específicas para este tenant (ID: {{ $tenantId }}).
                 </div>
             @else
                 <div class="alert alert-secondary small">
                     Estás editando las opciones globales para el tema '{{ e($slug) }}'.
                 </div>
             @endif


             {{-- Renderizar Secciones y Opciones dinámicamente desde theme.json --}}
             @foreach ($optionsSchema as $sectionSlug => $section)
                 @if($section['type'] === 'section')
                 <div class="appearance-section" id="section-{{ $sectionSlug }}">
                     <div class="appearance-section-header">{{ $section['label'] ?? 'Sección' }}</div>
                     <div class="appearance-section-body">
                         @if(!empty($section['options']))
                             @foreach ($section['options'] as $optionSlug => $option)
                                 @php
                                     // Clave completa para el name del input y buscar valor guardado
                                     $optionKey = $sectionSlug . '.' . $optionSlug;
                                     // Obtener valor guardado o el default del theme.json
									$savedValue = array_get_nested($savedOptions, $optionKey, $option['default'] ?? null);

                                     // Aplicar old() si existe
                                     $currentValue = old('options.' . $optionKey, $savedValue);
                                 @endphp

                                 {{-- Grupo de opción con manejo de dependencia --}}
                                <div class="form-group option-group"
     id="group-{{ $optionKey }}"
     @if(!empty($option['depends_on']))
         data-depends-on="{{ $option['depends_on'] }}"
     @endif
     style="{{ array_check_dependency($savedOptions, $option['depends_on'] ?? null) ? '' : 'display: none;' }}"
>

                                     <label for="option-{{ $optionKey }}" class="form-label">{{ $option['label'] ?? 'Opción' }}</label>

                                     {{-- Renderizar input según el tipo --}}
                                     @switch($option['type'])
                                         @case('toggle')
                                             <div class="form-check form-switch">
                                                 <input class="form-check-input" type="checkbox" value="1" role="switch"
                                                        id="option-{{ $optionKey }}" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                        @checked($currentValue)
                                                        {{-- Añadir data-dependency-driver si otros dependen de este --}}
                                                         data-dependency-driver="true" >
                                                 <label class="form-check-label" for="option-{{ $optionKey }}">Activar</label>
                                             </div>
                                             {{-- Campo oculto para asegurar que se envía 0 si no está marcado --}}
                                             <input type="hidden" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]" value="0" @if($currentValue) disabled @endif>
                                             @break

                                         @case('text')
                                         @case('url')
                                             <input type="{{ $option['type'] === 'url' ? 'url' : 'text' }}" class="form-control"
                                                    id="option-{{ $optionKey }}" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                    value="{{ $currentValue ?? '' }}"
                                                    placeholder="{{ $option['placeholder'] ?? '' }}">
                                             @break

                                         @case('color')
                                             <input type="color" class="form-control form-control-color" {{-- Clase específica para color picker --}}
                                                    id="option-{{ $optionKey }}" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                    value="{{ $currentValue ?? '#ffffff' }}">
                                             @break

                                          @case('image')
                                             {{-- Integración básica con gestor de medios (pendiente) --}}
                                             <div class="input-group">
                                                  <input type="text" class="form-control"
                                                         id="option-{{ $optionKey }}" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                         value="{{ $currentValue ?? '' }}" placeholder="URL de la imagen">
                                                  <button type="button" class="btn btn-outline-secondary btn-media-manager" data-input-id="option-{{ $optionKey }}" title="Seleccionar Imagen">
                                                      <i class="bi bi-image"></i>
                                                  </button>
                                             </div>
                                              @if($currentValue) <img src="{{ $currentValue }}" style="max-height: 50px; margin-top: 5px;"> @endif
                                             @break

                                         @case('textarea')
                                             <textarea class="form-control" id="option-{{ $optionKey }}" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                       rows="{{ $option['rows'] ?? 3 }}">{{ $currentValue ?? '' }}</textarea>
                                             @break

                                          @case('code')
                                             {{-- Necesitaría un editor como CodeMirror o Ace --}}
                                             <textarea class="form-control font-monospace" style="min-height: 150px;"
                                                       id="option-{{ $optionKey }}" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                       placeholder="Escribe tu código {{ $option['language'] ?? '' }} aquí...">{{ $currentValue ?? '' }}</textarea>
                                             <small class="text-muted">Lenguaje: {{ $option['language'] ?? 'N/A' }}</small>
                                             @break

                                         @case('select')
                                             <select class="form-select" id="option-{{ $optionKey }}" name="options[{{ $sectionSlug }}][{{ $optionSlug }}]"
                                                      {{-- Añadir data-dependency-driver si otros dependen de este --}}
                                                      @if($option['is_driver'] ?? false) data-dependency-driver="true" @endif >
                                                 @if(!empty($option['options']) && is_array($option['options']))
                                                     @foreach($option['options'] as $value => $label)
                                                         <option value="{{ $value }}" @selected($currentValue == $value)>
                                                             {{ $label }}
                                                         </option>
                                                     @endforeach
                                                 @endif
                                             </select>
                                             @break

                                         @default
                                             <p class="text-danger small">Tipo de campo no soportado: {{ $option['type'] }}</p>
                                     @endswitch

                                     {{-- Mostrar descripción si existe --}}
                                     @if(!empty($option['description']))
                                         <small class="form-text text-muted">{{ $option['description'] }}</small>
                                     @endif
                                 </div> {{-- Fin option-group --}}
                             @endforeach
                         @endif
                     </div> {{-- Fin appearance-section-body --}}
                 </div> {{-- Fin appearance-section --}}
                 @endif
             @endforeach

             <div class="mt-4 d-flex justify-content-between">
                 <button type="button" class="btn btn-outline-danger" id="btn-reset-defaults">
                     <i class="bi bi-arrow-counterclockwise"></i> Restaurar valores por defecto
                 </button>
                 <button type="submit" class="btn btn-primary">Guardar Cambios</button>
             </div>
        </form>

        {{-- Formulario oculto para reset --}}
        <form method="POST"
              action="{{ $tenantId ? route('themes.appearance.reset.tenant', ['slug' => $slug, 'tenantId' => $tenantId]) : route('themes.appearance.reset.global', ['slug' => $slug]) }}"
              id="resetForm" style="display: none;">
            @csrf
        </form>

    </div> {{-- Fin container-fluid --}}
</div> {{-- Fin app-content --}}
@endsection

@push('scripts')
{{-- Script para manejar la visibilidad condicional (dependencias) --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Función para obtener el valor de una opción (checkbox, select, etc.)
    function getOptionValue(optionKey) {
        const element = document.getElementById('option-' + optionKey.replace(/\./g, '-')); // Reemplazar puntos por guiones si ID los usa
        if (!element) return null;

        if (element.type === 'checkbox') {
            // Para el switch, buscamos el input real (el hidden no nos sirve aquí)
             const realCheckbox = document.querySelector(`input[type='checkbox'][id='option-${optionKey}']`);
             return realCheckbox ? realCheckbox.checked : false;
           // return element.checked; // Esto funcionaría si solo hubiera el checkbox
        } else if (element.tagName === 'SELECT') {
            return element.value;
        }
        // Añadir otros tipos si es necesario
        return element.value; // Para text, color, etc.
    }

    // Función para actualizar la visibilidad de TODOS los campos dependientes
    function updateDependencies() {
        document.querySelectorAll('.option-group[data-depends-on]').forEach(dependentGroup => {
            const dependencyKey = dependentGroup.dataset.dependsOn;
            const driverValue = getOptionValue(dependencyKey); // Valor del campo del que depende

            // Lógica simple: mostrar si el driver tiene un valor considerado "true"
            // Podrías hacer lógica más compleja aquí (ej: data-depends-value="some_value")
            const shouldShow = Boolean(driverValue) && driverValue !== '0' && driverValue !== 'false'; // Ajusta esta lógica si es necesario

            if (shouldShow) {
                dependentGroup.style.display = ''; // Mostrar
                 dependentGroup.classList.remove('hidden-by-dependency');
            } else {
                dependentGroup.style.display = 'none'; // Ocultar
                 dependentGroup.classList.add('hidden-by-dependency');
            }
        });
    }

    // Añadir listeners a los campos que controlan dependencias
    document.querySelectorAll('[data-dependency-driver="true"]').forEach(driverElement => {
        driverElement.addEventListener('change', updateDependencies);
    });

    // Ejecutar al cargar la página para establecer el estado inicial
    updateDependencies();

    // Manejar toggles con hidden inputs
    document.querySelectorAll('input[type="checkbox"][role="switch"]').forEach(checkbox => {
        // Obtener el hidden input que está justo después del div.form-check
        const parentDiv = checkbox.closest('.form-check');
        if (parentDiv && parentDiv.nextElementSibling) {
            const hiddenInput = parentDiv.nextElementSibling;
            if (hiddenInput.tagName === 'INPUT' && hiddenInput.type === 'hidden') {
                // Listener para cambios en el checkbox
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        hiddenInput.disabled = true; // Deshabilitar hidden cuando checkbox está marcado
                    } else {
                        hiddenInput.disabled = false; // Habilitar hidden cuando checkbox está desmarcado
                    }
                });
            }
        }
    });

    // Botón de restaurar valores por defecto
    const resetBtn = document.getElementById('btn-reset-defaults');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Restaurar valores por defecto?',
                    text: 'Se eliminarán todas las personalizaciones y se restaurarán los valores originales del tema. Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, restaurar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('resetForm').submit();
                    }
                });
            } else {
                if (confirm('¿Estás seguro de que deseas restaurar los valores por defecto? Se eliminarán todas las personalizaciones.')) {
                    document.getElementById('resetForm').submit();
                }
            }
        });
    }

     // Script para gestor de medios (básico)
     document.querySelectorAll('.btn-media-manager').forEach(button => {
         button.addEventListener('click', function() {
             const inputId = this.dataset.inputId;
             // Aquí abrirías tu modal/popup del gestor de medios
             // y cuando se seleccione una imagen, harías:
             // document.getElementById(inputId).value = selectedImageUrl;
             alert('Gestor de Medios no implementado. Input ID: ' + inputId);
         });
     });

     // Script para inicializar CodeMirror si se usa (necesita incluir librerías)
     /*
     document.querySelectorAll('textarea[data-language]').forEach(editor => {
         CodeMirror.fromTextArea(editor, {
             lineNumbers: true,
             mode: editor.dataset.language || 'css', // css, javascript, etc.
             theme: 'default' // o el tema que prefieras
         });
     });
     */
});
</script>
@endpush