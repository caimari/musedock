# Integración de Shortcodes React Sliders

## Sistema de Shortcodes Tipo WordPress

El módulo React Sliders incluye un sistema de shortcodes que permite insertar sliders en el contenido de páginas y posts usando una sintaxis simple tipo WordPress.

## Sintaxis de Shortcodes

```
[react-slider id=1]
[react-slider identifier="hero"]
[react-slider identifier="home-slider"]
```

## Integración con PageController y BlogPostController

Para que los shortcodes funcionen automáticamente, debes procesar el contenido antes de mostrarlo.

### En PageController.php (Frontend)

Busca donde se renderiza el contenido de la página y aplica el filtro:

```php
// En el método show() o similar
$content = $page->content;

// Procesar shortcodes de React Sliders
if (function_exists('process_react_slider_shortcodes')) {
    $content = process_react_slider_shortcodes($content);
}

return View::render('themes::page', [
    'page' => $page,
    'content' => $content // Usar el contenido procesado
]);
```

### En BlogPostController.php (Frontend)

Similar para posts del blog:

```php
$post = BlogPost::find($id);
$content = $post->content;

// Procesar shortcodes
if (function_exists('process_react_slider_shortcodes')) {
    $content = process_react_slider_shortcodes($content);
}

return View::render('blog::post', [
    'post' => $post,
    'content' => $content
]);
```

### En las vistas Blade

Si prefieres procesarlo en las vistas:

```blade
{{-- En page.blade.php --}}
@php
    $processedContent = function_exists('process_react_slider_shortcodes')
        ? process_react_slider_shortcodes($page->content)
        : $page->content;
@endphp

{!! $processedContent !!}
```

## Sistema de Filtros Global

Alternativamente, puedes crear un sistema de filtros global que se aplique automáticamente:

```php
// En helpers.php global
if (!function_exists('apply_content_filters')) {
    function apply_content_filters(string $content): string
    {
        global $content_filters;

        if (!empty($content_filters)) {
            foreach ($content_filters as $filter) {
                if (function_exists($filter)) {
                    $content = $filter($content);
                }
            }
        }

        return $content;
    }
}
```

Luego en las vistas:

```blade
{!! apply_content_filters($page->content) !!}
```

## Ejemplos de Uso

### En el Editor de Páginas

```html
<h1>Bienvenido a nuestro sitio</h1>

[react-slider identifier="hero"]

<p>Este es el contenido de la página...</p>

[react-slider id=2]

<p>Más contenido...</p>
```

### En Posts del Blog

```html
<p>Introducción del artículo...</p>

[react-slider identifier="gallery"]

<p>Continuación del artículo...</p>
```

## Validación de Seguridad

El sistema de shortcodes incluye:

✅ **Validación de tenant**: Solo muestra sliders del tenant actual o globales
✅ **Validación de estado**: Solo muestra sliders activos
✅ **Validación de slides**: Solo muestra slides activos
✅ **Manejo de errores**: Devuelve comentarios HTML en caso de error
✅ **Sanitización**: Usa `e()` para escapar variables

## Logs y Debug

Los errores se registran en el log de PHP:

```bash
tail -f /var/log/php-errors.log | grep "react-slider"
```

Mensajes de log:
- `React Sliders module loaded successfully with shortcode support`
- `Error procesando shortcode react-slider: [mensaje]`

## URLs del Panel

### Superadmin (Sliders Globales)
- **Listado**: `https://musedock.net/musedock/react-sliders`
- **Crear**: `https://musedock.net/musedock/react-sliders/create`
- **Editar**: `https://musedock.net/musedock/react-sliders/{id}/edit`

### Tenant (Sliders del Sitio)
- **Listado**: `https://musedock.net/admin/react-sliders`
- **Crear**: `https://musedock.net/admin/react-sliders/create`
- **Editar**: `https://musedock.net/admin/react-sliders/{id}/edit`

## Troubleshooting

### Shortcode no se procesa
1. Verifica que el módulo esté activo
2. Verifica que `process_react_slider_shortcodes()` esté disponible
3. Verifica que el contenido pase por el filtro

### Slider no se muestra
1. Verifica que el ID o identifier exista
2. Verifica que el slider esté activo (`is_active = 1`)
3. Verifica que tenga slides activos
4. Verifica permisos de tenant

### Error de JavaScript
1. Verifica que React esté cargado
2. Verifica que Swiper esté cargado
3. Revisa la consola del navegador
