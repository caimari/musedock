{{-- 
  Archivo: widget-renderer.blade.php
  Ubicación: themes/play-bootstrap/views/partials/widget-renderer.blade.php
  
  Uso: Incluir en plantillas para renderizar áreas de widgets
  Ejemplo: @include('partials.widget-renderer', ['areaSlug' => 'footer1'])
--}}

@php
    $areaSlug = $areaSlug ?? null;

    if ($areaSlug && isset($widgetContent[$areaSlug])) {
        // Si tenemos contenido pre-renderizado de PageController, usarlo directamente
        $content = $widgetContent[$areaSlug];
    } elseif ($areaSlug) {
        // Si no, intentar renderizar aquí (fallback)
        $tenantId = \Screenart\Musedock\Services\TenantManager::currentTenantId();
        $themeSlug = setting('default_theme', 'default');

        \Screenart\Musedock\Widgets\WidgetManager::registerAvailableWidgets();
        $content = \Screenart\Musedock\Widgets\WidgetManager::renderArea($areaSlug, $tenantId, $themeSlug);
    } else {
        $content = "<!-- Área de widgets no especificada -->";
    }
@endphp

{!! $content !!}

