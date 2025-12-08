# Páginas de Error Personalizadas - MuseDock

Este directorio contiene las páginas de error personalizadas del sistema MuseDock.

## Características

- **Diseño atractivo**: Páginas con diseño moderno y animaciones
- **Modo DEBUG**: Muestra información detallada cuando `APP_DEBUG=true` en el archivo `.env`
- **Información útil**: En modo debug, muestra datos de la request, errores, sugerencias, etc.

## Páginas Disponibles

### 404 - Página No Encontrada
- **Archivo**: `404.blade.php`
- **Uso**: Se muestra automáticamente cuando no se encuentra una ruta
- **Casos de uso**: Módulos desconectados, rutas no definidas, URLs incorrectas

### 403 - Acceso Denegado
- **Archivo**: `403.blade.php`
- **Uso**: Para recursos que requieren permisos especiales
- **Casos de uso**: Falta de permisos, sesión inválida, IP bloqueada

### 500 - Error Interno del Servidor
- **Archivo**: `500.blade.php`
- **Uso**: Para errores críticos del servidor
- **Casos de uso**: Excepciones no capturadas, errores de base de datos, fallos del sistema

## Cómo Usar

### Método 1: Usando el ErrorHandler (Recomendado)

```php
use Screenart\Musedock\ErrorHandler;

// Para un error 404
ErrorHandler::http(404, 'Módulo no encontrado');

// Para un error 403
ErrorHandler::http(403, 'No tienes permisos para acceder aquí');

// Para un error 500
ErrorHandler::http(500, 'Error en la base de datos');

// Para mostrar una excepción completa
try {
    // código que puede fallar
} catch (\Exception $e) {
    ErrorHandler::render($e, 500, 'Error en la Aplicación');
}
```

### Método 2: Renderizando la vista directamente

```php
use Screenart\Musedock\View;

http_response_code(404);
echo View::renderTheme('errors.404');
exit;
```

### Método 3: En el router (ya implementado)

El sistema ya maneja automáticamente los errores 404 cuando no se encuentra una ruta.

## Modo DEBUG

Para activar el modo debug y ver información detallada de los errores:

1. Edita el archivo `.env` en la raíz del proyecto
2. Cambia `APP_DEBUG=false` a `APP_DEBUG=true`
3. Guarda el archivo

**IMPORTANTE**: Nunca dejes el modo DEBUG activado en producción, ya que expone información sensible del sistema.

## Información Mostrada en Modo DEBUG

Cuando `APP_DEBUG=true`, las páginas de error muestran:

- URL solicitada
- Método HTTP (GET, POST, etc.)
- IP del cliente
- Timestamp del error
- Referrer
- User Agent
- Información de la sesión
- Stack trace (para errores 500)
- Sugerencias para resolver el problema

## Personalización

Puedes personalizar estas páginas editando directamente los archivos `.blade.php`:

- **Colores**: Modifica los gradientes en las secciones `<style>`
- **Mensajes**: Edita los textos en las secciones `<p class="error-message">`
- **Debug info**: Agrega más información en las secciones `<?php if ($debug): ?>`

## Ejemplo de Uso en un Controlador

```php
namespace Modules\Blog\Controllers;

use Screenart\Musedock\ErrorHandler;

class PostController
{
    public function show($id)
    {
        $post = Post::find($id);

        if (!$post) {
            // Muestra página 404 personalizada
            ErrorHandler::http(404, 'El post no existe');
            return;
        }

        // Verificar permisos
        if (!current_user_can('read_post', $post)) {
            // Muestra página 403 personalizada
            ErrorHandler::http(403, 'No tienes permisos para ver este post');
            return;
        }

        // Mostrar el post
        return view('blog.show', compact('post'));
    }
}
```

## Logs

Todos los errores se registran automáticamente en:
- `storage/logs/error.log`
- `storage/logs/app.log`

Revisa estos archivos para más detalles sobre errores recientes.

## Sincronización de Páginas de Error en Todos los Temas

Las páginas de error master están en `core/Views/errors/` pero cada tema tiene su propia copia en `themes/[nombre-tema]/views/errors/`.

Para sincronizar las páginas de error actualizadas a todos los temas, usa el script:

```bash
bash core/Views/errors/sync-errors.sh
```

Este script:
- Busca automáticamente todos los temas que tienen directorio `views/errors/`
- Copia las versiones actualizadas de 404.blade.php, 403.blade.php y 500.blade.php
- Muestra un resumen de los temas actualizados

**¿Cuándo usar este script?**
- Después de modificar alguna página de error en `core/Views/errors/`
- Al agregar nuevos estilos o funcionalidades
- Para asegurar que todos los tenants muestren las páginas de error actualizadas
