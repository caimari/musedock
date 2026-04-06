<?php
namespace Screenart\Musedock\Widgets\Types;

use Screenart\Musedock\Widgets\WidgetBase;

/**
 * Instagram Feed Widget
 *
 * Displays an Instagram feed in any widget zone.
 * Supports two modes:
 *   - Graph API: Shows posts from a connected Instagram account (requires API config)
 *   - oEmbed: Embeds a specific public Instagram post by URL (no API needed)
 */
class InstagramFeedWidget extends WidgetBase
{
    public static string $slug = 'instagram-feed';
    public static string $name = 'Instagram Feed';
    public static string $description = 'Muestra un feed de Instagram o un post individual embebido.';
    public static string $icon = 'bi-instagram';

    /**
     * Admin configuration form.
     */
    public function form(array $config = [], ?int $instanceId = null): string
    {
        $title = $config['title'] ?? '';
        $mode = $config['mode'] ?? 'feed'; // 'feed' or 'oembed'
        $connectionId = $config['connection_id'] ?? '';
        $layout = $config['layout'] ?? 'grid';
        $columns = $config['columns'] ?? 3;
        $limit = $config['limit'] ?? 9;
        $oembedUrl = $config['oembed_url'] ?? '';
        $uid = is_null($instanceId) ? uniqid('ig_') : 'ig_' . $instanceId;

        // Get available connections
        $connections = [];
        try {
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            if (class_exists('\\Modules\\InstagramGallery\\Models\\InstagramConnection')) {
                $connections = \Modules\InstagramGallery\Models\InstagramConnection::getActiveByTenant($tenantId);
            }
        } catch (\Exception $e) {
            // Module may not be fully loaded
        }

        $output = '<div class="mb-3">
            <label for="' . $uid . '_title" class="form-label">Título (opcional)</label>
            <input type="text" id="' . $uid . '_title" name="config[title]" value="' . $this->e($title) . '" class="form-control form-control-sm" placeholder="Ej: Síguenos en Instagram">
        </div>';

        // Mode selector
        $output .= '<div class="mb-3">
            <label class="form-label">Modo</label>
            <div class="form-check">
                <input class="form-check-input ig-mode-radio" type="radio" name="config[mode]" value="feed" id="' . $uid . '_mode_feed" ' . ($mode === 'feed' ? 'checked' : '') . '>
                <label class="form-check-label" for="' . $uid . '_mode_feed">
                    <strong>Feed de Instagram</strong> <small class="text-muted">— Muestra posts de tu cuenta conectada (requiere API)</small>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input ig-mode-radio" type="radio" name="config[mode]" value="oembed" id="' . $uid . '_mode_oembed" ' . ($mode === 'oembed' ? 'checked' : '') . '>
                <label class="form-check-label" for="' . $uid . '_mode_oembed">
                    <strong>Post individual</strong> <small class="text-muted">— Embebe un post público por URL (sin API)</small>
                </label>
            </div>
        </div>';

        // Feed options (shown when mode=feed)
        $output .= '<div class="ig-feed-options" style="' . ($mode !== 'feed' ? 'display:none;' : '') . '">
            <div class="mb-3">
                <label for="' . $uid . '_connection" class="form-label">Cuenta de Instagram</label>
                <select id="' . $uid . '_connection" name="config[connection_id]" class="form-select form-select-sm">';

        if (empty($connections)) {
            $output .= '<option value="">No hay cuentas conectadas</option>';
        } else {
            $output .= '<option value="">Seleccionar cuenta...</option>';
            foreach ($connections as $conn) {
                $selected = ((string)$connectionId === (string)$conn->id) ? 'selected' : '';
                $output .= '<option value="' . $conn->id . '" ' . $selected . '>@' . $this->e($conn->username) . '</option>';
            }
        }

        $output .= '</select></div>';

        $output .= '<div class="row">
            <div class="col-4 mb-3">
                <label for="' . $uid . '_layout" class="form-label">Diseño</label>
                <select id="' . $uid . '_layout" name="config[layout]" class="form-select form-select-sm">
                    <option value="grid" ' . ($layout === 'grid' ? 'selected' : '') . '>Cuadrícula</option>
                    <option value="masonry" ' . ($layout === 'masonry' ? 'selected' : '') . '>Masonry</option>
                    <option value="carousel" ' . ($layout === 'carousel' ? 'selected' : '') . '>Carrusel</option>
                </select>
            </div>
            <div class="col-4 mb-3">
                <label for="' . $uid . '_columns" class="form-label">Columnas</label>
                <input type="number" id="' . $uid . '_columns" name="config[columns]" value="' . (int)$columns . '" class="form-control form-control-sm" min="1" max="6">
            </div>
            <div class="col-4 mb-3">
                <label for="' . $uid . '_limit" class="form-label">Posts</label>
                <input type="number" id="' . $uid . '_limit" name="config[limit]" value="' . (int)$limit . '" class="form-control form-control-sm" min="1" max="50">
            </div>
        </div>
        </div>';

        // oEmbed options (shown when mode=oembed)
        $output .= '<div class="ig-oembed-options" style="' . ($mode !== 'oembed' ? 'display:none;' : '') . '">
            <div class="mb-3">
                <label for="' . $uid . '_oembed_url" class="form-label">URL del post de Instagram</label>
                <input type="url" id="' . $uid . '_oembed_url" name="config[oembed_url]" value="' . $this->e($oembedUrl) . '" class="form-control form-control-sm" placeholder="https://www.instagram.com/p/ABC123/">
                <small class="text-muted">Pega la URL de cualquier post público de Instagram</small>
            </div>
        </div>';

        // JS to toggle mode sections
        $output .= '<script>
        document.querySelectorAll(".ig-mode-radio").forEach(function(radio) {
            radio.addEventListener("change", function() {
                var feedOpts = this.closest(".mb-3").parentNode.querySelector(".ig-feed-options");
                var oembedOpts = this.closest(".mb-3").parentNode.querySelector(".ig-oembed-options");
                if (this.value === "feed") {
                    if (feedOpts) feedOpts.style.display = "";
                    if (oembedOpts) oembedOpts.style.display = "none";
                } else {
                    if (feedOpts) feedOpts.style.display = "none";
                    if (oembedOpts) oembedOpts.style.display = "";
                }
            });
        });
        </script>';

        return $output;
    }

    /**
     * Frontend render.
     */
    public function render(array $config = []): string
    {
        $title = $config['title'] ?? '';
        $mode = $config['mode'] ?? 'feed';

        $html = '';

        // Widget title
        if (!empty($title)) {
            $html .= '<h4 class="widget-title">' . htmlspecialchars($title) . '</h4>';
        }

        if ($mode === 'oembed') {
            // oEmbed mode: embed a single post
            $url = $config['oembed_url'] ?? '';
            if (empty($url)) {
                return $html . '<p class="text-muted small">No se ha configurado la URL del post.</p>';
            }
            $html .= render_instagram_oembed(['url' => $url]);
        } else {
            // Feed mode: show posts grid via shortcode
            $connectionId = $config['connection_id'] ?? '';
            if (empty($connectionId)) {
                return $html . '<p class="text-muted small">No se ha seleccionado una cuenta de Instagram.</p>';
            }
            $layout = $config['layout'] ?? 'grid';
            $columns = (int) ($config['columns'] ?? 3);
            $limit = (int) ($config['limit'] ?? 9);

            $html .= render_instagram_gallery([
                'connection' => (int) $connectionId,
                'layout' => $layout,
                'columns' => $columns,
                'limit' => $limit,
                'show_caption' => false, // Widgets are compact
            ]);
        }

        return $html;
    }

    /**
     * Sanitize config.
     */
    public function sanitizeConfig(array $config): array
    {
        return [
            'title' => strip_tags($config['title'] ?? ''),
            'mode' => in_array($config['mode'] ?? '', ['feed', 'oembed']) ? $config['mode'] : 'feed',
            'connection_id' => (int) ($config['connection_id'] ?? 0),
            'layout' => in_array($config['layout'] ?? '', ['grid', 'masonry', 'carousel']) ? $config['layout'] : 'grid',
            'columns' => max(1, min(6, (int) ($config['columns'] ?? 3))),
            'limit' => max(1, min(50, (int) ($config['limit'] ?? 9))),
            'oembed_url' => filter_var($config['oembed_url'] ?? '', FILTER_SANITIZE_URL),
        ];
    }

    /**
     * HTML entity escape helper.
     */
    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
