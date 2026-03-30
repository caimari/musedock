<?php

/**
 * Image Gallery Module Helpers
 *
 * Funciones auxiliares y procesador de shortcodes
 */

// ============================================================================
// FUNCIONES DE TRADUCCIÓN
// ============================================================================

if (!function_exists('__gallery')) {
    /**
     * Traduce una clave del módulo de galerías
     *
     * @param string $key Clave de traducción (ej: 'gallery.name')
     * @param array $replace Variables a reemplazar
     * @return string
     */
    function __gallery(string $key, array $replace = []): string
    {
        static $translations = null;

        if ($translations === null) {
            $locale = function_exists('get_locale') ? get_locale() : 'es';
            $langFile = __DIR__ . '/lang/' . $locale . '.json';

            if (!file_exists($langFile)) {
                $langFile = __DIR__ . '/lang/es.json';
            }

            if (file_exists($langFile)) {
                $translations = json_decode(file_get_contents($langFile), true) ?? [];
            } else {
                $translations = [];
            }
        }

        // Obtener valor anidado
        $keys = explode('.', $key);
        $value = $translations;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // Devolver la clave si no existe traducción
            }
        }

        if (!is_string($value)) {
            return $key;
        }

        // Reemplazar variables
        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, $replacement, $value);
        }

        return $value;
    }
}

// ============================================================================
// FUNCIONES DE ACCESO A GALERÍAS
// ============================================================================

if (!function_exists('get_gallery')) {
    /**
     * Obtiene una galería por ID o slug
     *
     * @param int|string $identifier ID numérico o slug
     * @param int|null $tenantId ID del tenant (null para incluir globales)
     * @return \ImageGallery\Models\Gallery|null
     */
    function get_gallery($identifier, ?int $tenantId = null): ?\ImageGallery\Models\Gallery
    {
        if (is_numeric($identifier)) {
            return \ImageGallery\Models\Gallery::find((int) $identifier);
        }

        return \ImageGallery\Models\Gallery::findBySlug($identifier, $tenantId);
    }
}

if (!function_exists('get_gallery_images')) {
    /**
     * Obtiene las imágenes de una galería
     *
     * @param int $galleryId ID de la galería
     * @param bool $onlyActive Solo imágenes activas
     * @return array
     */
    function get_gallery_images(int $galleryId, bool $onlyActive = true): array
    {
        return \ImageGallery\Models\GalleryImage::getByGallery($galleryId, $onlyActive);
    }
}

if (!function_exists('get_galleries')) {
    /**
     * Obtiene galerías activas
     *
     * @param int|null $tenantId ID del tenant
     * @param int $limit Límite de resultados
     * @return array
     */
    function get_galleries(?int $tenantId = null, int $limit = 10): array
    {
        return \ImageGallery\Models\Gallery::getActive($tenantId);
    }
}

if (!function_exists('get_featured_galleries')) {
    /**
     * Obtiene galerías destacadas
     *
     * @param int|null $tenantId ID del tenant
     * @param int $limit Límite de resultados
     * @return array
     */
    function get_featured_galleries(?int $tenantId = null, int $limit = 5): array
    {
        return \ImageGallery\Models\Gallery::getFeatured($tenantId, $limit);
    }
}

// ============================================================================
// PROCESADOR DE SHORTCODES
// ============================================================================

if (!function_exists('process_gallery_shortcodes')) {
    /**
     * Procesa los shortcodes de galería en el contenido
     *
     * Soporta:
     * - [gallery id=1]
     * - [gallery slug="mi-galeria"]
     * - [gallery id=1 columns=4 layout="masonry"]
     *
     * @param string $content Contenido con shortcodes
     * @return string Contenido con HTML de galerías
     */
    function process_gallery_shortcodes(string $content): string
    {
        // Patrón para detectar shortcodes de galería
        $pattern = '/\[gallery\s+([^\]]+)\]/i';

        return preg_replace_callback($pattern, function ($matches) {
            try {
                $attributes = parse_shortcode_attributes($matches[1]);

                // Obtener galería
                $gallery = null;
                $tenantId = function_exists('tenant_id') ? tenant_id() : null;

                if (!empty($attributes['id'])) {
                    $gallery = \ImageGallery\Models\Gallery::find((int) $attributes['id']);
                } elseif (!empty($attributes['slug'])) {
                    $gallery = \ImageGallery\Models\Gallery::findBySlug($attributes['slug'], $tenantId);
                }

                if (!$gallery || !$gallery->is_active) {
                    return '<!-- Gallery not found or inactive -->';
                }

                // Obtener imágenes activas
                $images = $gallery->activeImages();

                if (empty($images)) {
                    return '<!-- Gallery has no images -->';
                }

                // Obtener configuración (permite sobrescribir desde shortcode)
                $settings = $gallery->getSettings();
                $layout = $attributes['layout'] ?? $gallery->layout_type;
                $columns = (int) ($attributes['columns'] ?? $gallery->columns);
                $gap = (int) ($attributes['gap'] ?? $gallery->gap);

                // Renderizar galería
                return render_gallery_html($gallery, $images, $layout, $columns, $gap, $settings);

            } catch (\Exception $e) {
                error_log("Error processing gallery shortcode: " . $e->getMessage());
                return '<!-- Error loading gallery -->';
            }
        }, $content);
    }
}

if (!function_exists('parse_shortcode_attributes')) {
    /**
     * Parsea los atributos de un shortcode
     *
     * @param string $text Texto de atributos (ej: 'id=1 columns=4')
     * @return array
     */
    function parse_shortcode_attributes(string $text): array
    {
        $attributes = [];

        // Patrón para atributos: key=value, key="value", key='value'
        $pattern = '/(\w+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+))/';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];
                $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }
}

if (!function_exists('render_gallery_html')) {
    /**
     * Renderiza el HTML de una galería
     *
     * @param \ImageGallery\Models\Gallery $gallery
     * @param array $images
     * @param string $layout
     * @param int $columns
     * @param int $gap
     * @param array $settings
     * @return string
     */
    function render_gallery_html($gallery, array $images, string $layout, int $columns, int $gap, array $settings): string
    {
        $galleryId = 'gallery-' . $gallery->id . '-' . uniqid();
        $enableLightbox = $settings['enable_lightbox'] ?? true;
        $enableLazy = $settings['enable_lazy_loading'] ?? true;
        $showTitle = $settings['show_title'] ?? false;
        $showCaption = $settings['show_caption'] ?? false;
        $hoverEffect = $settings['hover_effect'] ?? 'zoom';
        $imageFit = $settings['image_fit'] ?? 'cover';
        $aspectRatio = $settings['aspect_ratio'] ?? '1:1';
        $borderRadius = (int) ($settings['border_radius'] ?? 8);

        // Calcular aspect ratio CSS
        $aspectRatioCSS = '1 / 1';
        if ($aspectRatio === 'auto') {
            $aspectRatioCSS = 'auto';
        } elseif (strpos($aspectRatio, ':') !== false) {
            list($w, $h) = explode(':', $aspectRatio);
            $aspectRatioCSS = "$w / $h";
        }

        // Generar CSS inline para la galería
        $css = "
        <style>
        #{$galleryId} {
            --gallery-columns: {$columns};
            --gallery-gap: {$gap}px;
            --gallery-radius: {$borderRadius}px;
            --gallery-fit: {$imageFit};
            --gallery-aspect: {$aspectRatioCSS};
        }
        #{$galleryId}.gallery-grid {
            display: grid;
            grid-template-columns: repeat(var(--gallery-columns), 1fr);
            gap: var(--gallery-gap);
        }
        #{$galleryId}.gallery-masonry {
            column-count: var(--gallery-columns);
            column-gap: var(--gallery-gap);
        }
        #{$galleryId}.gallery-masonry .gallery-item {
            break-inside: avoid;
            margin-bottom: var(--gallery-gap);
        }
        #{$galleryId} .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: var(--gallery-radius);
            background: #f0f0f0;
        }
        #{$galleryId}.gallery-grid .gallery-item {
            aspect-ratio: var(--gallery-aspect);
        }
        #{$galleryId} .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: var(--gallery-fit);
            transition: transform 0.3s ease;
        }
        #{$galleryId} .gallery-item:hover img {
            " . ($hoverEffect === 'zoom' ? 'transform: scale(1.1);' : '') . "
        }
        #{$galleryId} .gallery-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: white;
            opacity: 0;
            transition: opacity 0.3s;
        }
        #{$galleryId} .gallery-item:hover .gallery-caption {
            opacity: 1;
        }
        #{$galleryId}.gallery-carousel {
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            gap: var(--gallery-gap);
            scrollbar-width: none;
        }
        #{$galleryId}.gallery-carousel::-webkit-scrollbar {
            display: none;
        }
        #{$galleryId}.gallery-carousel .gallery-item {
            flex: 0 0 calc((100% - var(--gallery-gap) * (var(--gallery-columns) - 1)) / var(--gallery-columns));
            scroll-snap-align: start;
        }
        @media (max-width: 768px) {
            #{$galleryId} {
                --gallery-columns: 2;
            }
        }
        @media (max-width: 480px) {
            #{$galleryId} {
                --gallery-columns: 1;
            }
        }
        </style>";

        // Determinar clase de layout
        $layoutClass = 'gallery-grid';
        if ($layout === 'masonry') {
            $layoutClass = 'gallery-masonry';
        } elseif ($layout === 'carousel') {
            $layoutClass = 'gallery-carousel';
        }

        // Construir HTML
        $html = $css;
        $html .= '<div id="' . $galleryId . '" class="musedock-gallery ' . $layoutClass . '" data-gallery="' . $gallery->id . '">';

        foreach ($images as $image) {
            $imgUrl = $image->medium_url ?: $image->image_url;
            $fullUrl = $image->image_url;
            $alt = htmlspecialchars($image->getAltText(), ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars($image->title ?? '', ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars($image->caption ?? '', ENT_QUOTES, 'UTF-8');
            $loading = $enableLazy ? 'loading="lazy"' : '';

            $html .= '<div class="gallery-item">';

            // Enlace (lightbox o URL personalizada)
            if ($enableLightbox) {
                $html .= '<a href="' . $fullUrl . '" data-lightbox="' . $galleryId . '" data-title="' . $title . '">';
            } elseif ($image->hasLink()) {
                $target = $image->link_target === '_blank' ? ' target="_blank" rel="noopener"' : '';
                $html .= '<a href="' . htmlspecialchars($image->link_url, ENT_QUOTES, 'UTF-8') . '"' . $target . '>';
            }

            $html .= '<img src="' . $imgUrl . '" alt="' . $alt . '" ' . $loading . '>';

            if ($enableLightbox || $image->hasLink()) {
                $html .= '</a>';
            }

            // Caption
            if (($showTitle && $title) || ($showCaption && $caption)) {
                $html .= '<div class="gallery-caption">';
                if ($showTitle && $title) {
                    $html .= '<strong>' . $title . '</strong>';
                }
                if ($showCaption && $caption) {
                    $html .= '<p class="mb-0">' . $caption . '</p>';
                }
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        // Añadir script de lightbox si está habilitado
        if ($enableLightbox) {
            $html .= '<script>
                if (!window.musedockGalleryLightbox) {
                    window.musedockGalleryLightbox = true;
                    document.head.insertAdjacentHTML("beforeend", \'<link rel="stylesheet" href="/modules/image-gallery/css/lightbox.css">\');
                    var script = document.createElement("script");
                    script.src = "/modules/image-gallery/js/lightbox.js";
                    document.body.appendChild(script);
                }
            </script>';
        }

        return $html;
    }
}

// ============================================================================
// REGISTRO DE SHORTCODES EN EL SISTEMA
// ============================================================================

// Registrar el procesador de shortcodes en el sistema global
if (!defined('GALLERY_SHORTCODES_REGISTERED')) {
    define('GALLERY_SHORTCODES_REGISTERED', true);

    // Añadir a filtros de contenido si existe el sistema
    if (function_exists('add_content_filter')) {
        add_content_filter('process_gallery_shortcodes');
    }

    // Registrar en el array global de filtros
    global $content_filters;
    if (!isset($content_filters)) {
        $content_filters = [];
    }
    if (!in_array('process_gallery_shortcodes', $content_filters)) {
        $content_filters[] = 'process_gallery_shortcodes';
    }
}
