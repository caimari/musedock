<?php
namespace Screenart\Musedock\Widgets\Types;

use Screenart\Musedock\Widgets\WidgetBase;

class GalleryWidget extends WidgetBase
{
    public static string $slug = 'gallery';
    public static string $name = 'Galería de Imágenes';
    public static string $description = 'Muestra una galería de imágenes del módulo Image Gallery.';
    public static string $icon = 'bi-images';

    /**
     * Renderiza el formulario de configuración del widget en el admin.
     *
     * @param array $config Configuración actual de la instancia del widget.
     * @param string|int|null $instanceId ID de la instancia (opcional).
     * @return string HTML del formulario.
     */
    public function form(array $config = [], $instanceId = null): string
    {
        // Obtener valores de configuración
        $title = $config['title'] ?? '';
        $galleryId = $config['gallery_id'] ?? '';
        $gallerySlug = $config['gallery_slug'] ?? '';
        $displayType = $config['display_type'] ?? 'id'; // 'id' o 'slug'

        // Generar ID único para los campos
        $uniqueId = is_null($instanceId) ? uniqid('gallery_') : 'gallery_' . $instanceId;

        // Obtener lista de galerías
        $galleries = $this->getGalleries();

        $output = '<div class="mb-3">
            <label for="' . $uniqueId . '_title" class="form-label">Título (opcional)</label>
            <input type="text" id="' . $uniqueId . '_title" name="config[title]"
                   value="' . $this->e($title) . '" class="form-control form-control-sm"
                   placeholder="Título del widget">
        </div>';

        $output .= '<div class="mb-3">
            <label class="form-label">Seleccionar Galería</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="config[display_type]"
                       value="id" id="' . $uniqueId . '_type_id" ' . ($displayType === 'id' ? 'checked' : '') .
                       ' onchange="toggleGalleryFields_' . $uniqueId . '()">
                <label class="form-check-label" for="' . $uniqueId . '_type_id">
                    Por ID
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="config[display_type]"
                       value="slug" id="' . $uniqueId . '_type_slug" ' . ($displayType === 'slug' ? 'checked' : '') .
                       ' onchange="toggleGalleryFields_' . $uniqueId . '()">
                <label class="form-check-label" for="' . $uniqueId . '_type_slug">
                    Por Slug
                </label>
            </div>
        </div>';

        $output .= '<div class="mb-3" id="' . $uniqueId . '_id_field" style="display: ' . ($displayType === 'id' ? 'block' : 'none') . '">
            <label for="' . $uniqueId . '_gallery_id" class="form-label">Galería (ID)</label>
            <select id="' . $uniqueId . '_gallery_id" name="config[gallery_id]" class="form-select form-select-sm">';

        $output .= '<option value="">-- Seleccionar Galería --</option>';
        foreach ($galleries as $gallery) {
            $selected = ($galleryId == $gallery['id']) ? 'selected' : '';
            $output .= '<option value="' . $gallery['id'] . '" ' . $selected . '>' .
                       $this->e($gallery['name']) . ' (ID: ' . $gallery['id'] . ')</option>';
        }

        $output .= '</select>
        </div>';

        $output .= '<div class="mb-3" id="' . $uniqueId . '_slug_field" style="display: ' . ($displayType === 'slug' ? 'block' : 'none') . '">
            <label for="' . $uniqueId . '_gallery_slug" class="form-label">Galería (Slug)</label>
            <input type="text" id="' . $uniqueId . '_gallery_slug" name="config[gallery_slug]"
                   value="' . $this->e($gallerySlug) . '" class="form-control form-control-sm"
                   placeholder="slug-de-la-galeria">
            <div class="form-text small text-muted mt-1">
                Introduce el slug de la galería (ej: mi-galeria)
            </div>
        </div>';

        $output .= '<script>
        function toggleGalleryFields_' . $uniqueId . '() {
            const typeId = document.getElementById("' . $uniqueId . '_type_id").checked;
            const idField = document.getElementById("' . $uniqueId . '_id_field");
            const slugField = document.getElementById("' . $uniqueId . '_slug_field");

            if (typeId) {
                idField.style.display = "block";
                slugField.style.display = "none";
            } else {
                idField.style.display = "none";
                slugField.style.display = "block";
            }
        }
        </script>';

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
        $title = $config['title'] ?? '';
        $displayType = $config['display_type'] ?? 'id';
        $galleryId = $config['gallery_id'] ?? '';
        $gallerySlug = $config['gallery_slug'] ?? '';

        // Validar que haya una galería seleccionada
        if (($displayType === 'id' && empty($galleryId)) ||
            ($displayType === 'slug' && empty($gallerySlug))) {
            return '<!-- Widget de Galería: No se ha seleccionado ninguna galería -->';
        }

        // Construir el shortcode con override para widgets
        // Forzar layout carousel y 1 columna para mejor visualización en widgets
        if ($displayType === 'id') {
            $shortcode = '[gallery id=' . intval($galleryId) . ' layout="carousel" columns=1]';
        } else {
            $shortcode = '[gallery slug="' . $this->e($gallerySlug) . '" layout="carousel" columns=1]';
        }

        // Procesar el shortcode
        $content = '';
        if (function_exists('process_shortcodes')) {
            $content = process_shortcodes($shortcode);
        } else {
            $content = '<p class="text-muted">El módulo de galerías no está disponible.</p>';
        }

        // Generar ID único para este widget
        $widgetId = 'gallery-widget-' . uniqid();

        // Estructura del widget con margen inferior
        $output = '<div class="widget widget-gallery" id="' . $widgetId . '" style="margin-bottom: 20px;">';

        if (!empty($title)) {
            $output .= '<h4 class="widget-title">' . $this->e($title) . '</h4>';
        }

        $output .= '<div class="widget-content">' . $content . '</div>';

        // Script para autoplay del carrusel (mejorado para manejar lightbox)
        $output .= '<script>
        (function() {
            const widget = document.getElementById("' . $widgetId . '");
            if (!widget) return;

            const carousel = widget.querySelector(".gallery-carousel");
            if (!carousel) return;

            const items = carousel.querySelectorAll(".gallery-item");
            if (items.length <= 1) return;

            let currentIndex = 0;
            const intervalTime = 3000;
            let autoplayInterval = null;
            let isPaused = false;

            function scrollToNext() {
                if (isPaused) return;
                currentIndex = (currentIndex + 1) % items.length;
                const item = items[currentIndex];
                item.scrollIntoView({
                    behavior: "smooth",
                    block: "nearest",
                    inline: "start"
                });
            }

            function startAutoplay() {
                if (autoplayInterval) clearInterval(autoplayInterval);
                isPaused = false;
                autoplayInterval = setInterval(scrollToNext, intervalTime);
            }

            function stopAutoplay() {
                isPaused = true;
                if (autoplayInterval) {
                    clearInterval(autoplayInterval);
                    autoplayInterval = null;
                }
            }

            // Iniciar autoplay
            startAutoplay();

            // Pausar al interactuar
            carousel.addEventListener("touchstart", stopAutoplay);
            carousel.addEventListener("mousedown", stopAutoplay);

            // Reanudar después de scroll
            let resumeTimeout;
            carousel.addEventListener("scroll", function() {
                clearTimeout(resumeTimeout);
                stopAutoplay();
                resumeTimeout = setTimeout(startAutoplay, 5000);
            });

            // Detectar apertura/cierre de lightbox (Magnific Popup)
            if (typeof jQuery !== "undefined" && jQuery.magnificPopup) {
                jQuery(document).on("mfpOpen", function() {
                    stopAutoplay();
                });
                jQuery(document).on("mfpClose", function() {
                    setTimeout(startAutoplay, 500);
                });
            }

            // Detectar visibilidad de la página
            document.addEventListener("visibilitychange", function() {
                if (document.hidden) {
                    stopAutoplay();
                } else {
                    setTimeout(startAutoplay, 500);
                }
            });

            // Reiniciar si el widget vuelve a ser visible (IntersectionObserver)
            if ("IntersectionObserver" in window) {
                const observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting && !autoplayInterval) {
                            startAutoplay();
                        } else if (!entry.isIntersecting) {
                            stopAutoplay();
                        }
                    });
                }, { threshold: 0.5 });
                observer.observe(widget);
            }
        })();
        </script>';

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

        // Sanitizar título
        $sanitized['title'] = isset($config['title']) ? strip_tags(trim($config['title'])) : '';

        // Validar tipo de visualización
        $sanitized['display_type'] = isset($config['display_type']) && in_array($config['display_type'], ['id', 'slug'])
            ? $config['display_type']
            : 'id';

        // Sanitizar ID de galería
        $sanitized['gallery_id'] = isset($config['gallery_id']) ? intval($config['gallery_id']) : '';

        // Sanitizar slug de galería
        $sanitized['gallery_slug'] = isset($config['gallery_slug'])
            ? preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($config['gallery_slug'])))
            : '';

        return $sanitized;
    }

    /**
     * Obtiene la lista de galerías disponibles
     *
     * @return array Lista de galerías
     */
    private function getGalleries(): array
    {
        try {
            $db = \Screenart\Musedock\Database::connect();
            $stmt = $db->query("SELECT id, name, slug FROM image_galleries WHERE is_active = 1 ORDER BY name ASC");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Log del error para debugging
            error_log("GalleryWidget: Error al obtener galerías - " . $e->getMessage());
            return [];
        }
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
