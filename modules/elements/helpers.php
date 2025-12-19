<?php

/**
 * Elements Module Helpers
 *
 * Translation functions and shortcode processors
 */

use Elements\Models\Element;

// ============================================================================
// TRANSLATION HELPERS
// ============================================================================

if (!function_exists('__element')) {
    /**
     * Translate element module strings
     *
     * @param string $key Translation key in dot notation (e.g., 'element.name')
     * @param array $replace Optional replacements
     * @return string
     */
    function __element(string $key, array $replace = []): string
    {
        static $translations = null;

        if ($translations === null) {
            $lang = $_SESSION['lang'] ?? 'es';
            $langFile = __DIR__ . "/lang/{$lang}.json";

            if (!file_exists($langFile)) {
                $langFile = __DIR__ . "/lang/es.json";
            }

            $json = file_get_contents($langFile);
            $translations = json_decode($json, true) ?? [];
        }

        // Navigate through the array using dot notation
        $keys = explode('.', $key);
        $value = $translations;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // Return key if not found
            }
        }

        // Apply replacements
        if (!empty($replace) && is_string($value)) {
            foreach ($replace as $search => $replaceValue) {
                $value = str_replace(':' . $search, $replaceValue, $value);
            }
        }

        return is_string($value) ? $value : $key;
    }
}

// ============================================================================
// SHORTCODE PROCESSING
// ============================================================================

if (!function_exists('process_element_shortcodes')) {
    /**
     * Process element shortcodes in content
     *
     * Supported formats:
     * [element id="123"]
     * [element slug="hero-homepage"]
     * [hero id="123"]
     * [faq slug="general-questions"]
     * [cta id="456"]
     *
     * @param string $content
     * @return string
     */
    function process_element_shortcodes(string $content): string
    {
        // Generic element shortcode: [element id="X"] or [element slug="X"]
        $content = preg_replace_callback(
            '/\[element\s+(?:id="(\d+)"|slug="([^"]+)")\s*\]/i',
            function ($matches) {
                $id = $matches[1] ?? null;
                $slug = $matches[2] ?? null;
                return render_element_shortcode($id, $slug);
            },
            $content
        );

        // Type-specific shortcodes: [hero id="X"], [faq slug="X"], etc.
        $types = ['hero', 'highlight', 'faq', 'cta', 'features', 'testimonials', 'stats', 'timeline'];

        foreach ($types as $type) {
            $content = preg_replace_callback(
                '/\[' . $type . '\s+(?:id="(\d+)"|slug="([^"]+)")\s*\]/i',
                function ($matches) use ($type) {
                    $id = $matches[1] ?? null;
                    $slug = $matches[2] ?? null;
                    return render_element_shortcode($id, $slug, $type);
                },
                $content
            );
        }

        return $content;
    }
}

if (!function_exists('render_element_shortcode')) {
    /**
     * Render an element from shortcode
     *
     * @param int|null $id
     * @param string|null $slug
     * @param string|null $expectedType
     * @return string
     */
    function render_element_shortcode(?int $id = null, ?string $slug = null, ?string $expectedType = null): string
    {
        try {
            $element = null;
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;

            // Find element by ID or slug
            if ($id) {
                $element = Element::find($id);
            } elseif ($slug) {
                $element = Element::findBySlug($slug, $tenantId);
            }

            // Element not found or not active
            if (!$element || !$element->is_active) {
                return '<!-- Element not found or inactive -->';
            }

            // Validate type if expected type is provided
            if ($expectedType && $element->type !== $expectedType) {
                return '<!-- Element type mismatch -->';
            }

            // Render based on type
            return render_element($element);

        } catch (\Exception $e) {
            error_log("Element shortcode error: " . $e->getMessage());
            return '<!-- Element render error -->';
        }
    }
}

if (!function_exists('render_element')) {
    /**
     * Render an element to HTML
     *
     * @param Element $element
     * @return string
     */
    function render_element(Element $element): string
    {
        static $assetsInjected = false;

        $type = $element->type;
        $layout = $element->layout_type;
        $data = $element->getData();
        $settings = $element->getSettings();

        // Determine template path
        $templateFile = __DIR__ . "/views/partials/{$type}.blade.php";

        if (!file_exists($templateFile)) {
            return "<!-- Template not found for type: {$type} -->";
        }

        // Inject CSS and JS assets once (on first element render)
        $assetsHTML = '';
        if (!$assetsInjected) {
            $preset = elements_get_active_preset($element);
            $version = defined('ELEMENTS_VERSION') ? ELEMENTS_VERSION : '1.0.0';
            $cssUrl = '/assets/modules/elements/css/' . $preset . '.css';
            $jsUrl = '/assets/modules/elements/js/elements.js';

            $assetsHTML = <<<HTML
<!-- Elements Module Assets -->
<link rel="stylesheet" href="{$cssUrl}?v={$version}">
<script src="{$jsUrl}?v={$version}" defer></script>

HTML;
            $assetsInjected = true;
        }

        // Render template
        ob_start();
        extract(compact('element', 'type', 'layout', 'data', 'settings'));
        include $templateFile;
        $elementHTML = ob_get_clean();

        return $assetsHTML . $elementHTML;
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

if (!function_exists('element_url')) {
    /**
     * Generate URL for element resource
     *
     * @param string $path
     * @return string
     */
    function element_url(string $path = ''): string
    {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $baseUrl . '/modules/elements/' . ltrim($path, '/');
    }
}

if (!function_exists('escape_html')) {
    /**
     * Escape HTML for safe output
     *
     * @param string|null $text
     * @return string
     */
    function escape_html(?string $text): string
    {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('element_asset')) {
    /**
     * Get element asset URL
     *
     * @param string $path
     * @return string
     */
    function element_asset(string $path): string
    {
        return '/public/modules/elements/' . ltrim($path, '/');
    }
}

if (!function_exists('elements_get_active_preset')) {
    /**
     * Get the active preset for elements
     *
     * Always returns 'default' - loads /public/assets/modules/elements/css/default.css
     * Themes can override by providing their own CSS at /themes/{theme}/elements/css/default.css
     *
     * @param Element|null $element
     * @return string
     */
    function elements_get_active_preset($element = null): string
    {
        // Simplified: always use default.css
        // Future: themes can define get_theme_elements_preset() to use a different preset
        return 'default';
    }
}
