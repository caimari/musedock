<?php

/**
 * Instagram Gallery Module - Helper Functions
 *
 * Provides shortcode processing, translation, and rendering functions
 * for displaying Instagram galleries in the frontend.
 */

use Modules\InstagramGallery\Models\InstagramConnection;
use Modules\InstagramGallery\Models\InstagramPost;
use Modules\InstagramGallery\Models\InstagramSetting;

/**
 * Translate Instagram Gallery module strings
 */
function __instagram(string $key, array $replacements = []): string
{
    static $translations = null;

    if ($translations === null) {
        $locale = locale() ?? 'es';
        $langFile = __DIR__ . "/lang/{$locale}.json";

        if (!file_exists($langFile)) {
            $langFile = __DIR__ . '/lang/es.json';
        }

        $json = file_get_contents($langFile);
        $translations = json_decode($json, true) ?? [];
    }

    // Navigate through nested keys (e.g., "connection.name")
    $keys = explode('.', $key);
    $value = $translations;

    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $key; // Return key if translation not found
        }
    }

    // Replace placeholders
    foreach ($replacements as $placeholder => $replacement) {
        $value = str_replace("{{$placeholder}}", $replacement, $value);
    }

    return $value;
}

/**
 * Process Instagram shortcodes in content
 *
 * Supported formats:
 * - [instagram connection=1]
 * - [instagram connection=1 layout="grid" columns=4]
 * - [instagram username="myaccount"]
 */
function process_instagram_shortcodes(string $content): string
{
    $pattern = '/\[instagram\s+([^\]]+)\]/i';

    return preg_replace_callback($pattern, function ($matches) {
        $attributes = parse_instagram_shortcode_attributes($matches[1]);

        try {
            return render_instagram_gallery($attributes);
        } catch (Exception $e) {
            error_log('Instagram shortcode error: ' . $e->getMessage());
            return '<div class="instagram-gallery-error">Error loading Instagram gallery</div>';
        }
    }, $content);
}

/**
 * Parse shortcode attributes
 */
function parse_instagram_shortcode_attributes(string $attrString): array
{
    $attributes = [];

    // Match key="value" or key='value' or key=value
    preg_match_all('/(\w+)=["\']?([^"\'\s]+)["\']?/', $attrString, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $key = $match[1];
        $value = $match[2];

        // Try to cast to appropriate type
        if (is_numeric($value)) {
            $value = strpos($value, '.') !== false ? (float) $value : (int) $value;
        } elseif ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        }

        $attributes[$key] = $value;
    }

    return $attributes;
}

/**
 * Render Instagram gallery from shortcode attributes
 */
function render_instagram_gallery(array $attributes): string
{
    global $pdo;

    if (!isset($pdo)) {
        $pdo = \Screenart\Musedock\Database::connect();
        InstagramConnection::setPdo($pdo);
        InstagramPost::setPdo($pdo);
        InstagramSetting::setPdo($pdo);
    }

    // Get current tenant ID (if in tenant context)
    $tenantId = class_exists('TenantManager') ? TenantManager::currentTenantId() : null;

    // Get connection
    $connection = null;

    if (isset($attributes['connection'])) {
        $connection = InstagramConnection::find((int) $attributes['connection']);
    } elseif (isset($attributes['username'])) {
        $connections = InstagramConnection::getActiveByTenant($tenantId);
        foreach ($connections as $conn) {
            if ($conn->username === $attributes['username']) {
                $connection = $conn;
                break;
            }
        }
    }

    if (!$connection || !$connection->is_active) {
        return '<div class="instagram-gallery-error">Instagram connection not found or inactive</div>';
    }

    // Get posts
    $limit = $attributes['limit'] ?? InstagramSetting::get('max_posts_per_gallery', $tenantId, 12);
    $posts = $connection->activePosts((int) $limit);

    if (empty($posts)) {
        return '<div class="instagram-gallery-empty">No posts available</div>';
    }

    // Get layout settings
    $layout = $attributes['layout'] ?? InstagramSetting::get('default_layout', $tenantId, 'grid');
    $columns = $attributes['columns'] ?? InstagramSetting::get('default_columns', $tenantId, 3);
    $gap = $attributes['gap'] ?? InstagramSetting::get('default_gap', $tenantId, 10);

    // Render options
    $options = [
        'show_caption' => $attributes['show_caption'] ?? InstagramSetting::get('show_captions', $tenantId, true),
        'caption_length' => $attributes['caption_length'] ?? InstagramSetting::get('caption_max_length', $tenantId, 150),
        'enable_lightbox' => $attributes['lightbox'] ?? InstagramSetting::get('enable_lightbox', $tenantId, true),
        'lazy_load' => $attributes['lazy_load'] ?? InstagramSetting::get('enable_lazy_loading', $tenantId, true),
        'hover_effect' => $attributes['hover_effect'] ?? InstagramSetting::get('hover_effect', $tenantId, 'zoom'),
        'border_radius' => $attributes['border_radius'] ?? InstagramSetting::get('border_radius', $tenantId, 8),
        'aspect_ratio' => $attributes['aspect_ratio'] ?? InstagramSetting::get('image_aspect_ratio', $tenantId, '1:1')
    ];

    return render_instagram_gallery_html($connection, $posts, $layout, (int) $columns, (int) $gap, $options);
}

/**
 * Render Instagram gallery HTML
 */
function render_instagram_gallery_html(
    InstagramConnection $connection,
    array $posts,
    string $layout,
    int $columns,
    int $gap,
    array $options
): string {
    $galleryId = 'instagram-gallery-' . $connection->id;

    // Generate CSS
    $css = generate_instagram_gallery_css($galleryId, $layout, $columns, $gap, $options);

    // Generate HTML
    $html = "<div id=\"{$galleryId}\" class=\"instagram-gallery instagram-gallery-{$layout}\" data-connection=\"{$connection->id}\">";

    // Add header with username (optional)
    if (!empty($options['show_header'])) {
        $html .= '<div class="instagram-gallery-header">';
        if ($connection->profile_picture) {
            $html .= '<img src="' . htmlspecialchars($connection->profile_picture) . '" alt="' . htmlspecialchars($connection->username) . '" class="instagram-profile-picture">';
        }
        $html .= '<h3 class="instagram-username">@' . htmlspecialchars($connection->username) . '</h3>';
        $html .= '</div>';
    }

    // Add posts container
    $html .= '<div class="instagram-gallery-posts">';

    foreach ($posts as $post) {
        $html .= render_instagram_post_html($post, $options);
    }

    $html .= '</div>'; // .instagram-gallery-posts
    $html .= '</div>'; // .instagram-gallery

    // Return CSS + HTML
    return "<style>{$css}</style>\n{$html}";
}

/**
 * Render single Instagram post HTML
 */
function render_instagram_post_html(InstagramPost $post, array $options): string
{
    $displayUrl = $post->getDisplayUrl();
    $caption = $post->getFormattedCaption($options['caption_length'] ?? 150);

    $html = '<div class="instagram-post" data-id="' . $post->id . '">';

    // Link wrapper
    $html .= '<a href="' . htmlspecialchars($post->permalink) . '" target="_blank" rel="noopener noreferrer" class="instagram-post-link">';

    // Image
    if ($displayUrl) {
        $html .= '<div class="instagram-post-image-wrapper">';
        $html .= '<img src="' . htmlspecialchars($displayUrl) . '" ';
        $html .= 'alt="' . htmlspecialchars($caption ?? 'Instagram post') . '" ';
        $html .= 'class="instagram-post-image" ';

        if ($options['lazy_load'] ?? true) {
            $html .= 'loading="lazy" ';
        }

        $html .= '>';

        // Overlay for hover effect
        $html .= '<div class="instagram-post-overlay">';

        // Video indicator
        if ($post->isVideo()) {
            $html .= '<div class="instagram-media-indicator video"><i class="bi bi-play-circle-fill"></i></div>';
        }

        // Carousel indicator
        if ($post->isCarousel()) {
            $html .= '<div class="instagram-media-indicator carousel"><i class="bi bi-collection-fill"></i></div>';
        }

        // Stats overlay (optional)
        if (!empty($options['show_stats'])) {
            $html .= '<div class="instagram-post-stats">';
            if ($post->like_count !== null) {
                $html .= '<span class="stat-likes"><i class="bi bi-heart-fill"></i> ' . number_format($post->like_count) . '</span>';
            }
            if ($post->comments_count !== null) {
                $html .= '<span class="stat-comments"><i class="bi bi-chat-fill"></i> ' . number_format($post->comments_count) . '</span>';
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // .instagram-post-overlay
        $html .= '</div>'; // .instagram-post-image-wrapper
    }

    $html .= '</a>';

    // Caption (outside link)
    if (($options['show_caption'] ?? true) && $caption) {
        $html .= '<div class="instagram-post-caption">' . htmlspecialchars($caption) . '</div>';
    }

    $html .= '</div>'; // .instagram-post

    return $html;
}

/**
 * Generate CSS for Instagram gallery
 */
function generate_instagram_gallery_css(
    string $galleryId,
    string $layout,
    int $columns,
    int $gap,
    array $options
): string {
    $borderRadius = $options['border_radius'] ?? 8;
    $hoverEffect = $options['hover_effect'] ?? 'zoom';
    $aspectRatio = $options['aspect_ratio'] ?? '1:1';

    // Convert aspect ratio string to CSS value
    $aspectRatioCss = str_replace(':', ' / ', $aspectRatio);
    if ($aspectRatio === 'original') {
        $aspectRatioCss = 'auto';
    }

    $css = "
/* Instagram Gallery: {$galleryId} */
#{$galleryId} {
    --instagram-columns: {$columns};
    --instagram-gap: {$gap}px;
    --instagram-radius: {$borderRadius}px;
    --instagram-aspect-ratio: {$aspectRatioCss};
}

#{$galleryId} .instagram-gallery-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border-radius: var(--instagram-radius);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#{$galleryId} .instagram-profile-picture {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

#{$galleryId} .instagram-username {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

#{$galleryId} .instagram-post {
    position: relative;
    overflow: hidden;
    border-radius: var(--instagram-radius);
    background: #f9f9f9;
}

#{$galleryId} .instagram-post-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

#{$galleryId} .instagram-post-image-wrapper {
    position: relative;
    width: 100%;
    overflow: hidden;
    background: #f0f0f0;
}

#{$galleryId} .instagram-post-image {
    width: 100%;
    height: auto;
    display: block;
    " . ($aspectRatio !== 'original' ? "aspect-ratio: var(--instagram-aspect-ratio); object-fit: cover;" : "") . "
    transition: transform 0.3s ease;
}

#{$galleryId} .instagram-post-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

#{$galleryId} .instagram-post:hover .instagram-post-overlay {
    opacity: 1;
}

#{$galleryId} .instagram-media-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    color: white;
    font-size: 24px;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

#{$galleryId} .instagram-post-stats {
    display: flex;
    gap: 15px;
    color: white;
    font-size: 14px;
    font-weight: 600;
}

#{$galleryId} .instagram-post-stats .bi {
    margin-right: 5px;
}

#{$galleryId} .instagram-post-caption {
    padding: 10px;
    font-size: 0.875rem;
    line-height: 1.4;
    color: #333;
}
";

    // Layout-specific CSS
    if ($layout === 'grid') {
        $css .= "
#{$galleryId} .instagram-gallery-posts {
    display: grid;
    grid-template-columns: repeat(var(--instagram-columns), 1fr);
    gap: var(--instagram-gap);
}

@media (max-width: 768px) {
    #{$galleryId} {
        --instagram-columns: 2;
    }
}

@media (max-width: 480px) {
    #{$galleryId} {
        --instagram-columns: 1;
    }
}
";
    } elseif ($layout === 'masonry') {
        $css .= "
#{$galleryId} .instagram-gallery-posts {
    column-count: var(--instagram-columns);
    column-gap: var(--instagram-gap);
}

#{$galleryId} .instagram-post {
    break-inside: avoid;
    margin-bottom: var(--instagram-gap);
}

#{$galleryId} .instagram-post-image {
    aspect-ratio: auto !important;
}

@media (max-width: 768px) {
    #{$galleryId} {
        --instagram-columns: 2;
    }
}

@media (max-width: 480px) {
    #{$galleryId} {
        --instagram-columns: 1;
    }
}
";
    } elseif ($layout === 'carousel') {
        $css .= "
#{$galleryId} .instagram-gallery-posts {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    gap: var(--instagram-gap);
    padding-bottom: 10px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

#{$galleryId} .instagram-post {
    flex: 0 0 calc((100% - (var(--instagram-columns) - 1) * var(--instagram-gap)) / var(--instagram-columns));
    scroll-snap-align: start;
}

@media (max-width: 768px) {
    #{$galleryId} .instagram-post {
        flex: 0 0 calc(50% - var(--instagram-gap) / 2);
    }
}

@media (max-width: 480px) {
    #{$galleryId} .instagram-post {
        flex: 0 0 calc(100% - var(--instagram-gap));
    }
}
";
    }

    // Hover effects
    if ($hoverEffect === 'zoom') {
        $css .= "
#{$galleryId} .instagram-post:hover .instagram-post-image {
    transform: scale(1.1);
}
";
    } elseif ($hoverEffect === 'fade') {
        $css .= "
#{$galleryId} .instagram-post:hover .instagram-post-image {
    opacity: 0.8;
}
";
    }

    return $css;
}

/**
 * Get available Instagram layouts
 */
function get_instagram_layouts(): array
{
    return InstagramSetting::getAvailableLayouts();
}

/**
 * Sync Instagram posts for a connection
 */
function sync_instagram_posts(int $connectionId): array
{
    global $pdo;

    if (!isset($pdo)) {
        $pdo = \Screenart\Musedock\Database::connect();
        InstagramConnection::setPdo($pdo);
        InstagramPost::setPdo($pdo);
        InstagramSetting::setPdo($pdo);
    }

    $connection = InstagramConnection::find($connectionId);

    if (!$connection) {
        throw new Exception('Connection not found');
    }

    // Get API credentials
    $appId = InstagramSetting::get('instagram_app_id', $connection->tenant_id);
    $appSecret = InstagramSetting::get('instagram_app_secret', $connection->tenant_id);
    $redirectUri = InstagramSetting::get('instagram_redirect_uri', $connection->tenant_id);

    if (!$appId || !$appSecret) {
        throw new Exception('Instagram API credentials not configured');
    }

    // Initialize API
    $api = new \Modules\InstagramGallery\Services\InstagramApiService($appId, $appSecret, $redirectUri);

    // Get max posts setting
    $maxPosts = InstagramSetting::get('max_posts_per_gallery', $connection->tenant_id, 50);

    // Fetch posts from Instagram
    $mediaPosts = $api->getAllUserMedia($connection->access_token, $maxPosts);

    $syncedCount = 0;
    $errors = [];

    foreach ($mediaPosts as $mediaData) {
        try {
            InstagramPost::createOrUpdate([
                'connection_id' => $connection->id,
                'instagram_id' => $mediaData['id'],
                'media_type' => $mediaData['media_type'],
                'media_url' => $mediaData['media_url'] ?? null,
                'thumbnail_url' => $mediaData['thumbnail_url'] ?? null,
                'permalink' => $mediaData['permalink'],
                'caption' => $mediaData['caption'] ?? null,
                'timestamp' => isset($mediaData['timestamp']) ? date('Y-m-d H:i:s', strtotime($mediaData['timestamp'])) : null,
                'is_active' => 1
            ]);
            $syncedCount++;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    // Update last_synced_at
    $connection->update([
        'last_synced_at' => date('Y-m-d H:i:s'),
        'last_error' => empty($errors) ? null : implode('; ', $errors)
    ]);

    // Prune old posts (keep only max_posts)
    InstagramPost::pruneOldPosts($connection->id, $maxPosts);

    return [
        'success' => true,
        'synced_count' => $syncedCount,
        'errors' => $errors
    ];
}
