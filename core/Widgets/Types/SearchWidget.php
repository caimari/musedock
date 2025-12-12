<?php
namespace Screenart\Musedock\Widgets\Types;

use Screenart\Musedock\Widgets\WidgetBase;

class SearchWidget extends WidgetBase
{
    public static string $slug = 'search';
    public static string $name = 'Buscador';
    public static string $description = 'Buscador de páginas y posts del blog del sitio.';
    public static string $icon = 'bi-search';

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
        $title = $config['title'] ?? 'Buscar';
        $placeholder = $config['placeholder'] ?? 'Buscar en el sitio...';
        $searchPages = $config['search_pages'] ?? true;
        $searchPosts = $config['search_posts'] ?? true;
        $buttonText = $config['button_text'] ?? 'Buscar';
        $showIcon = $config['show_icon'] ?? true;

        // Generar ID único para los campos del formulario
        $uniqueId = is_null($instanceId) ? uniqid('search_') : 'search_' . $instanceId;

        // Formulario con Bootstrap 5 styling
        $output = '<div class="mb-3">
            <label for="' . $uniqueId . '_title" class="form-label">Título del Widget</label>
            <input type="text" id="' . $uniqueId . '_title" name="config[title]"
                   value="' . $this->e($title) . '" class="form-control form-control-sm"
                   placeholder="Ej: Buscar">
            <div class="form-text small text-muted mt-1">
                Título que se mostrará encima del buscador.
            </div>
        </div>';

        $output .= '<div class="mb-3">
            <label for="' . $uniqueId . '_placeholder" class="form-label">Placeholder</label>
            <input type="text" id="' . $uniqueId . '_placeholder" name="config[placeholder]"
                   value="' . $this->e($placeholder) . '" class="form-control form-control-sm"
                   placeholder="Buscar en el sitio...">
            <div class="form-text small text-muted mt-1">
                Texto que aparecerá en el campo de búsqueda cuando esté vacío.
            </div>
        </div>';

        $output .= '<div class="mb-3">
            <label for="' . $uniqueId . '_button_text" class="form-label">Texto del Botón</label>
            <input type="text" id="' . $uniqueId . '_button_text" name="config[button_text]"
                   value="' . $this->e($buttonText) . '" class="form-control form-control-sm"
                   placeholder="Buscar">
            <div class="form-text small text-muted mt-1">
                Texto que aparecerá en el botón de búsqueda. Dejar vacío para mostrar solo icono.
            </div>
        </div>';

        $output .= '<div class="mb-3">
            <label class="form-label">Buscar en:</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="config[search_pages]"
                       value="1" id="' . $uniqueId . '_search_pages" ' . ($searchPages ? 'checked' : '') . '>
                <label class="form-check-label" for="' . $uniqueId . '_search_pages">
                    Páginas
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="config[search_posts]"
                       value="1" id="' . $uniqueId . '_search_posts" ' . ($searchPosts ? 'checked' : '') . '>
                <label class="form-check-label" for="' . $uniqueId . '_search_posts">
                    Posts del Blog
                </label>
            </div>
        </div>';

        $output .= '<div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="config[show_icon]"
                   value="1" id="' . $uniqueId . '_show_icon" ' . ($showIcon ? 'checked' : '') . '>
            <label class="form-check-label" for="' . $uniqueId . '_show_icon">
                <small>Mostrar icono de búsqueda</small>
            </label>
        </div>';

        // Botón para cerrar el formulario
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
        $title = $config['title'] ?? 'Buscar';
        $placeholder = $config['placeholder'] ?? 'Buscar en el sitio...';
        $searchPages = $config['search_pages'] ?? true;
        $searchPosts = $config['search_posts'] ?? true;

        // Determinar qué buscar
        $searchType = '';
        if ($searchPages && $searchPosts) {
            $searchType = 'all';
        } elseif ($searchPages) {
            $searchType = 'pages';
        } elseif ($searchPosts) {
            $searchType = 'posts';
        } else {
            $searchType = 'all';
        }

        // Estructura del widget con margen superior e inferior para separación
        $output = '<div class="widget widget-search" style="margin-bottom: 15px;">';

        if (!empty($title)) {
            $output .= '<h5 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #333;">' . $this->e($title) . '</h5>';
        }

        // Obtener texto del botón de la configuración
        $buttonText = $config['button_text'] ?? 'Buscar';

        $output .= '<div class="widget-content">
            <form method="get" action="/search">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 0; padding-right: 8px;">
                            <input type="text" name="q"
                                   placeholder="' . $this->e($placeholder) . '"
                                   required
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                        </td>
                        <td style="padding: 0; width: 1px; white-space: nowrap;">
                            <button type="submit" style="background: #ff656a; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; white-space: nowrap;">
                                <i class="bi bi-search"></i>' . (!empty($buttonText) ? ' ' . $this->e($buttonText) : '') . '
                            </button>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="type" value="' . $this->e($searchType) . '">
            </form>
        </div>';

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

        // Sanitizar campos de texto
        $sanitized['title'] = isset($config['title']) ? strip_tags(trim($config['title'])) : 'Buscar';
        $sanitized['placeholder'] = isset($config['placeholder']) ? strip_tags(trim($config['placeholder'])) : 'Buscar en el sitio...';
        $sanitized['button_text'] = isset($config['button_text']) ? strip_tags(trim($config['button_text'])) : 'Buscar';

        // Convertir checkboxes a booleanos
        $sanitized['search_pages'] = !empty($config['search_pages']);
        $sanitized['search_posts'] = !empty($config['search_posts']);
        $sanitized['show_icon'] = !empty($config['show_icon']);

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
