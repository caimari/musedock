{{-- 
  Archivo: widget-renderer.blade.php
  Ubicación: views/partials/widget-renderer.blade.php
  
  Uso: Incluir en plantillas para renderizar áreas de widgets
  Ejemplo: @include('partials.widget-renderer', ['areaSlug' => 'footer1'])
--}}

@php
  // Verificar si el área de widgets está definida
  $areaSlug = $areaSlug ?? null;
  
  if ($areaSlug && isset($widgetContent[$areaSlug])) {
    // Si tenemos contenido pre-renderizado de PageController, usarlo directamente
    $content = $widgetContent[$areaSlug];
  } else if ($areaSlug) {
    // Si no, intentar renderizar aquí (fallback)
    $tenantId = \Screenart\Musedock\Services\TenantManager::currentTenantId();
    $themeSlug = setting('default_theme', 'default');
    
    // Asegurarse de que los widgets estén registrados
    \Screenart\Musedock\Widgets\WidgetManager::registerAvailableWidgets();
    
    // Renderizar el área
    $content = \Screenart\Musedock\Widgets\WidgetManager::renderArea($areaSlug, $tenantId, $themeSlug);
  } else {
    // Si no se especificó un área, mostrar un mensaje
    $content = "<!-- Área de widgets no especificada -->";
  }
@endphp

{!! $content !!}