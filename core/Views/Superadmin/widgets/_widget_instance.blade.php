{{-- Parcial para mostrar una instancia de widget en el admin --}}
@php
    // Necesitamos WidgetManager aquí también
    use Screenart\Musedock\Widgets\WidgetManager;
    use Illuminate\Support\Str;
    
    // Extraer variables pasadas por compact() o el array
    $widgetSlug = $instanceData['widget_slug'] ?? '';
    
    // Usar un ID temporal si es un widget nuevo arrastrado (aún no tiene ID de BD)
    $instanceDomId = $instanceData['id'] ?? ('new-' . Str::random(8));
    
    // Asegurar que config sea array - esto es clave para que se muestren los datos guardados
    $config = is_array($instanceData['config'] ?? null) ? $instanceData['config'] : [];
    
    // Debugging - revisar qué contiene config
    error_log("Widget {$widgetSlug} (ID: {$instanceDomId}) - Config: " . json_encode($config));
    
    // Obtener info y clase del widget
    $widgetInfo = $instanceData['widget_info'] ?? ['name' => $widgetSlug];
    $widgetClassInstance = WidgetManager::getWidgetInstance($widgetSlug);
@endphp

{{-- Contenedor principal del widget instanciado --}}
<div class="widget-instance" data-instance-id="{{ $instanceDomId }}" data-widget-slug="{{ $widgetSlug }}">
    
    {{-- Cabecera clickeable y arrastrable --}}
    <div class="widget-instance-header">
        <div class="widget-instance-title">
            <i class="bi {{ $widgetInfo['icon'] ?? 'bi-puzzle' }} me-2"></i>
            <span>{{ e($widgetInfo['name'] ?? $widgetSlug) }}</span>
        </div>
        <div class="widget-instance-actions">
            <button type="button" class="widget-edit-toggle" title="Editar configuración">
                <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="widget-delete-button" title="Eliminar widget">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
    
    {{-- Formulario de configuración (oculto por defecto) --}}
    <div class="widget-instance-form">
        {{-- Input oculto crucial para identificar el tipo de widget al guardar --}}
        <input type="hidden" name="areas[{{ $areaSlug }}][{{ $index }}][widget_slug]" value="{{ $widgetSlug }}">
        
        {{-- Renderizar el formulario específico del widget --}}
        @if($widgetClassInstance)
            {{-- Pasamos la configuración actual y un ID único para los elementos del form --}}
            {!! $widgetClassInstance->form($config, $instanceDomId) !!}
        @else
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Error:</strong> No se pudo cargar el widget "{{ e($widgetSlug) }}".
                Es posible que la clase no exista o que haya sido desactivada.
            </div>
            <div class="form-text small text-muted mb-3">
                El widget se guardará pero puede no funcionar correctamente en el sitio.
            </div>
        @endif
    </div>
</div>