<?php
namespace Screenart\Musedock\Widgets\Types;

use Screenart\Musedock\Widgets\WidgetBase;

class TextWidget extends WidgetBase
{
    public static string $slug = 'text'; // Identificador único
    public static string $name = 'Texto/HTML';
    public static string $description = 'Muestra texto simple o código HTML personalizado.';
    public static string $icon = 'bi-code-slash'; // Ícono de Bootstrap Icons

    /**
     * Renderiza el formulario de configuración del widget en el admin.
     *
     * @param array $config Configuración actual de la instancia del widget.
     * @param string|int|null $instanceId ID de la instancia (opcional).
     * @return string HTML del formulario.
     */
    public function form(array $config = [], $instanceId = null): string
    {
        // Obtener valores de configuración o valores por defecto
        $title = $config['title'] ?? '';
        $content = $config['content'] ?? '';
        $applyFilters = $config['apply_filters'] ?? false; // Opción para procesar shortcodes

        // Generar ID único para los campos del formulario
        $uniqueId = is_null($instanceId) ? uniqid('text_') : 'text_' . $instanceId;

        // Formulario con Bootstrap 5 styling
        $output = '<div class="mb-3">
            <label for="' . $uniqueId . '_title" class="form-label">Título (opcional)</label>
            <input type="text" id="' . $uniqueId . '_title" name="config[title]" 
                   value="' . $this->e($title) . '" class="form-control form-control-sm" 
                   placeholder="Introduce un título (opcional)">
        </div>';

        $output .= '<div class="mb-3">
            <label for="' . $uniqueId . '_content" class="form-label">Contenido (HTML permitido)</label>
            <textarea id="' . $uniqueId . '_content" name="config[content]" 
                      class="form-control form-control-sm" rows="6" 
                      placeholder="Introduce texto simple o código HTML">' . $this->e($content) . '</textarea>
            <div class="form-text small text-muted mt-1">
                Puedes usar HTML para dar formato al texto o insertar elementos como imágenes, enlaces, etc.
            </div>
        </div>';

        // Opción para procesar shortcodes
        $output .= '<div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="config[apply_filters]" 
                   value="1" id="' . $uniqueId . '_apply_filters" ' . ($applyFilters ? 'checked' : '') . '>
            <label class="form-check-label" for="' . $uniqueId . '_apply_filters">
                <small>Procesar Shortcodes y Filtros</small>
            </label>
            <div class="form-text small text-muted mt-1">
                Activa esta opción si deseas que se procesen los shortcodes en el contenido.
            </div>
        </div>';

        // Botón para cerrar el formulario (opcional)
        $output .= '<div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary widget-form-close">
                <i class="bi bi-check-circle me-1"></i> Aceptar
            </button>
        </div>';

        return $output;
    }

    /**
     * Renderiza el widget en el frontend.
     *
     * @param array $config Configuración guardada de la instancia.
     * @return string HTML a mostrar.
     */
    public function render(array $config = []): string
    {
        // Extraer configuración
        $title = $config['title'] ?? '';
        $content = $config['content'] ?? '';
        $applyFilters = $config['apply_filters'] ?? false;

        // Si no hay contenido, devolver comentario HTML vacío
        if (empty($content) && empty($title)) {
            return '<!-- Widget de Texto/HTML vacío -->';
        }

        // Procesar shortcodes si la opción está activa
        if ($applyFilters && function_exists('process_shortcodes')) {
            $content = process_shortcodes($content);
        }

        // Estructura del widget en el frontend con clases más específicas
        $output = '<div class="widget widget-text">';
        
        if (!empty($title)) {
            $output .= '<h4 class="widget-title">' . $this->e($title) . '</h4>';
        }
        
        $output .= '<div class="widget-content textwidget">' . $content . '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Validación/Sanitización antes de guardar la configuración.
     *
     * @param array $config Configuración recibida del formulario.
     * @return array Configuración limpia y validada.
     */
    public function sanitizeConfig(array $config): array
    {
        $sanitized = [];
        
        // Sanitizar título (strip_tags para quitar HTML)
        $sanitized['title'] = isset($config['title']) ? strip_tags(trim($config['title'])) : '';
        
        // Permitir HTML en el contenido pero eliminar scripts potencialmente peligrosos
        if (isset($config['content'])) {
            $content = trim($config['content']);
            
            // Opcionalmente podríamos usar una librería como HTMLPurifier para una sanitización más segura
            // Por ahora, simplemente eliminamos scripts y eventos on* potencialmente peligrosos
            $dangerous = ['<script', 'javascript:', 'onerror=', 'onload=', 'onclick=', 'onmouseover='];
            $isSafe = true;
            
            foreach ($dangerous as $check) {
                if (stripos($content, $check) !== false) {
                    $isSafe = false;
                    break;
                }
            }
            
            // Si hay contenido peligroso, advertir y eliminar los scripts
            if (!$isSafe) {
                error_log("Contenido potencialmente inseguro detectado en widget Text: " . substr($content, 0, 100) . "...");
                $content = strip_tags($content, '<p><br><a><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><img><blockquote><table><tr><td><th><span><div><hr>');
            }
            
            $sanitized['content'] = $content;
        } else {
            $sanitized['content'] = '';
        }
        
        // Convertir checkbox a booleano
        $sanitized['apply_filters'] = !empty($config['apply_filters']);
        
        return $sanitized;
    }
    
    /**
     * Método helper para escapar HTML
     * 
     * @param string $string Cadena a escapar
     * @return string Cadena escapada
     */
    private function e($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}