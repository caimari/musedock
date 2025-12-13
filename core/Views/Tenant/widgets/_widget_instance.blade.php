{{-- Parcial para mostrar una instancia de widget en el admin de tenant --}}
@php
    use Screenart\Musedock\Widgets\WidgetManager;
    use Illuminate\Support\Str;

    $widgetSlug = $instanceData['widget_slug'] ?? '';
    $instanceDomId = $instanceData['id'] ?? ('new-' . Str::random(8));
    $config = is_array($instanceData['config'] ?? null) ? $instanceData['config'] : [];
    $widgetInfo = $instanceData['widget_info'] ?? ['name' => $widgetSlug];
    $widgetClassInstance = WidgetManager::getWidgetInstance($widgetSlug);
@endphp

<div class="widget-instance" data-instance-id="{{ $instanceDomId }}" data-widget-slug="{{ $widgetSlug }}">

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

    <div class="widget-instance-form">
        <input type="hidden" name="areas[{{ $areaSlug }}][{{ $index }}][widget_slug]" value="{{ $widgetSlug }}">

        @if($widgetClassInstance)
            {!! $widgetClassInstance->form($config, $instanceDomId) !!}
        @else
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Error:</strong> No se pudo cargar el widget "{{ e($widgetSlug) }}".
            </div>
        @endif
    </div>
</div>
