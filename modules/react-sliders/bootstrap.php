<?php

/**
 * Bootstrap del módulo React Sliders
 *
 * Este archivo se carga automáticamente cuando el módulo está activo.
 * Aquí se inicializan servicios, helpers y cualquier configuración necesaria.
 */

namespace ReactSliders;

// Evitar carga múltiple
if (defined('REACT_SLIDERS_LOADED')) {
    return;
}

define('REACT_SLIDERS_LOADED', true);

// Definir constante del módulo
define('REACT_SLIDERS_PATH', __DIR__);

require_once __DIR__ . '/../module-menu-helper.php';

// Ejecutar migración automáticamente si las tablas no existen
try {
    $pdo = \Screenart\Musedock\Database::connect();

    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'react_sliders'");
    if (!$stmt->fetch()) {
        // Ejecutar instalación desde SQL
        $sqlFile = __DIR__ . '/install.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            // Dividir por statements y ejecutar
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $pdo->exec($statement);
                    } catch (\Exception $e) {
                        // Ignorar errores de tablas que ya existen
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            error_log("React Sliders install error: " . $e->getMessage());
                        }
                    }
                }
            }
            error_log("React Sliders: Tablas instaladas automáticamente");
        }
    }
} catch (\Exception $e) {
    error_log("React Sliders: Error en auto-instalación: " . $e->getMessage());
}

// Cargar helpers del módulo
require_once __DIR__ . '/helpers.php';

// Cargar modelos
require_once __DIR__ . '/models/ReactSlider.php';
require_once __DIR__ . '/models/ReactSlide.php';

// Cargar controladores (el autoloader se encargará si es necesario)
// Pero para asegurar que estén disponibles:
require_once __DIR__ . '/controllers/Superadmin/ReactSliderController.php';
require_once __DIR__ . '/controllers/Superadmin/ReactSlideController.php';

// Registrar función helper global para obtener un slider por identificador
if (!function_exists('get_react_slider')) {
    /**
     * Obtener un slider por su identificador
     *
     * @param string $identifier Identificador del slider
     * @param int|null $tenantId ID del tenant (null para global)
     * @return \ReactSliders\Models\ReactSlider|null
     */
    function get_react_slider(string $identifier, ?int $tenantId = null): ?\ReactSliders\Models\ReactSlider
    {
        return \ReactSliders\Models\ReactSlider::findByIdentifier($identifier, $tenantId);
    }
}

if (!function_exists('render_react_slider')) {
    /**
     * Renderizar un slider en el frontend
     *
     * @param string $identifier Identificador del slider
     * @param array $options Opciones adicionales
     * @return string HTML del slider
     */
    function render_react_slider(string $identifier, array $options = []): string
    {
        $tenantId = tenant_id();
        $slider = get_react_slider($identifier, $tenantId);

        if (!$slider || !$slider->is_active) {
            return '<!-- Slider no encontrado o inactivo: ' . e($identifier) . ' -->';
        }

        $slides = $slider->slides();

        // Filtrar solo slides activos
        $slides = array_filter($slides, fn($slide) => $slide->is_active);

        if (empty($slides)) {
            return '<!-- Slider sin diapositivas: ' . e($identifier) . ' -->';
        }

        $settings = $slider->getFullSettings();
        $slidesData = array_map(fn($slide) => $slide->toFrontendArray(), $slides);

        // Generar ID único
        $sliderId = 'react-slider-' . $slider->id . '-' . uniqid();

        $html = '<div id="' . e($sliderId) . '" class="react-slider-container"></div>';
        $html .= '<script type="module">';
        $html .= 'import ReactSlider from "/modules/react-sliders/assets/js/ReactSlider.js";';
        $html .= 'import { createRoot } from "react-dom/client";';
        $html .= 'const container = document.getElementById("' . e($sliderId) . '");';
        $html .= 'const root = createRoot(container);';
        $html .= 'root.render(React.createElement(ReactSlider, {';
        $html .= '  slides: ' . json_encode($slidesData) . ',';
        $html .= '  settings: ' . json_encode($settings);
        $html .= '}));';
        $html .= '</script>';

        return $html;
    }
}

// ========== SISTEMA DE SHORTCODES ==========

if (!function_exists('process_react_slider_shortcodes')) {
    /**
     * Procesar shortcodes de React Sliders en el contenido
     * Soporta: [react-slider id=1], [react-slider identifier="hero"]
     *
     * @param string $content Contenido con shortcodes
     * @return string Contenido con shortcodes reemplazados
     */
    function process_react_slider_shortcodes(string $content): string
    {
        // Patrón para [react-slider id=X] o [react-slider identifier="xxx"]
        $pattern = '/\[react-slider\s+(?:id=(\d+)|identifier=["\']?([a-z0-9-]+)["\']?)\s*\]/i';

        $content = preg_replace_callback($pattern, function($matches) {
            try {
                $tenantId = tenant_id();

                if (!empty($matches[1])) {
                    // Por ID: [react-slider id=1]
                    $slider = \ReactSliders\Models\ReactSlider::find((int)$matches[1]);
                } else {
                    // Por identifier: [react-slider identifier="hero"]
                    $identifier = $matches[2];
                    $slider = \ReactSliders\Models\ReactSlider::findByIdentifier($identifier, $tenantId);
                }

                if (!$slider || !$slider->is_active) {
                    return '<!-- Slider no encontrado o inactivo -->';
                }

                // Verificar permisos de tenant
                if ($tenantId !== null && $slider->tenant_id !== null && $slider->tenant_id !== $tenantId) {
                    return '<!-- Slider no disponible para este tenant -->';
                }

                $slides = $slider->slides();
                $slides = array_filter($slides, fn($slide) => $slide->is_active);

                if (empty($slides)) {
                    return '<!-- Slider sin diapositivas -->';
                }

                $settings = $slider->getFullSettings();
                $slidesData = array_map(fn($slide) => $slide->toFrontendArray(), $slides);
                $sliderId = 'react-slider-' . $slider->id . '-' . uniqid();

                $html = '<div id="' . e($sliderId) . '" class="react-slider-container" style="width: 100%; height: 500px;"></div>';
                $html .= '<script type="module">';
                $html .= 'import ReactSlider from "/modules/react-sliders/assets/js/ReactSlider.js";';
                $html .= 'import { createRoot } from "react-dom/client";';
                $html .= 'const container = document.getElementById("' . e($sliderId) . '");';
                $html .= 'if (container) {';
                $html .= '  const root = createRoot(container);';
                $html .= '  root.render(React.createElement(ReactSlider, {';
                $html .= '    slides: ' . json_encode($slidesData) . ',';
                $html .= '    settings: ' . json_encode($settings);
                $html .= '  }));';
                $html .= '}';
                $html .= '</script>';

                return $html;

            } catch (\Exception $e) {
                error_log("Error procesando shortcode react-slider: " . $e->getMessage());
                return '<!-- Error al cargar slider -->';
            }
        }, $content);

        return $content;
    }
}

// Registrar filtro para procesar shortcodes en el contenido de páginas/posts usando el sistema de hooks
if (function_exists('add_filter')) {
    add_filter('the_content', '\ReactSliders\process_react_slider_shortcodes', 10);
    error_log("React Sliders: Shortcode filter registered with hooks system");
} else {
    // Fallback: Si no existe sistema de filtros, crear uno básico
    global $content_filters;
    if (!isset($content_filters)) {
        $content_filters = [];
    }
    $content_filters[] = 'ReactSliders\process_react_slider_shortcodes';
    error_log("React Sliders: Using fallback content_filters system");
}

// Log de inicialización
error_log("React Sliders module loaded successfully with shortcode support");

\register_module_admin_menu([
    'module_slug'    => 'react-sliders',
    'menu_slug'      => 'appearance-react-sliders',
    'title'          => 'React Sliders',
    'superadmin_url' => '{admin_path}/react-sliders',
    'tenant_url'     => '{admin_path}/react-sliders',
    'parent_slug'    => 'appearance',
    'icon'           => 'sliders',
    'icon_type'      => 'bi',
    'order'          => 4,
    'permission'     => 'react_sliders.manage',
]);
