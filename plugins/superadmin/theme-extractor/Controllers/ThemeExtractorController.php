<?php

namespace ThemeExtractor\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Models\ThemeSkin;

class ThemeExtractorController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        return View::renderSuperadmin('plugins.theme-extractor.index', [
            'title' => 'Theme Extractor',
            'extracted' => null,
        ]);
    }

    public function extract()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $url = trim($_POST['url'] ?? '');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            flash('error', 'URL no válida.');
            header('Location: /musedock/theme-extractor');
            exit;
        }

        // Block internal IPs (SSRF protection)
        $host = parse_url($url, PHP_URL_HOST);
        $ip = gethostbyname($host);
        if (preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.|0\.0\.0\.0|localhost)/i', $ip)) {
            flash('error', 'No se permiten URLs internas.');
            header('Location: /musedock/theme-extractor');
            exit;
        }

        try {
            // Fetch HTML
            $html = $this->fetchUrl($url, 15);
            if (!$html) {
                flash('error', 'No se pudo acceder a la URL.');
                header('Location: /musedock/theme-extractor');
                exit;
            }

            // Extract CSS sources
            $cssTexts = $this->extractCssSources($html, $url);

            // Extract JS sources
            $jsSources = $this->extractJsSources($html, $url);

            // Parse all CSS
            $allCss = implode("\n", $cssTexts);
            $colors = $this->extractColors($allCss);
            $fonts = $this->extractFonts($allCss);
            $cssVars = $this->extractCssVariables($allCss);
            $bgImages = $this->extractBackgroundImages($allCss, $url);

            // Auto-map to MuseDock variables
            $autoMapping = $this->autoMapColors($colors);

            // Get tenants for assignment dropdown
            $pdo = Database::connect();
            $tenants = $pdo->query("SELECT id, name, domain FROM tenants ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);

            return View::renderSuperadmin('plugins.theme-extractor.index', [
                'title' => 'Theme Extractor - Resultados',
                'extracted' => true,
                'sourceUrl' => $url,
                'colors' => $colors,
                'fonts' => $fonts,
                'cssVars' => $cssVars,
                'bgImages' => $bgImages,
                'jsSources' => $jsSources,
                'autoMapping' => $autoMapping,
                'rawCssLength' => strlen($allCss),
                'rawCss' => mb_substr($allCss, 0, 500000),
                'tenants' => $tenants,
            ]);
        } catch (\Throwable $e) {
            flash('error', 'Error al extraer: ' . $e->getMessage());
            header('Location: /musedock/theme-extractor');
            exit;
        }
    }

    public function save()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $name = trim($_POST['skin_name'] ?? '');
        if (empty($name)) {
            flash('error', 'El nombre del skin es requerido.');
            header('Location: /musedock/theme-extractor');
            exit;
        }

        $tenantId = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
        $isGlobal = $tenantId === null ? 1 : 0;
        $customCss = trim($_POST['custom_css'] ?? '');

        // Build options from mapped colors
        $options = [
            'topbar' => [
                'topbar_bg_color' => $_POST['map_topbar_bg_color'] ?? '#1a2a40',
                'topbar_text_color' => $_POST['map_topbar_text_color'] ?? '#ffffff',
            ],
            'header' => [
                'header_bg_color' => $_POST['map_header_bg_color'] ?? '#ffffff',
                'header_link_color' => $_POST['map_header_link_color'] ?? '#333333',
                'header_link_hover_color' => $_POST['map_header_link_hover_color'] ?? '#ff5e15',
                'header_cta_bg_color' => $_POST['map_header_cta_bg_color'] ?? '#ff5e15',
                'header_cta_text_color' => $_POST['map_header_cta_text_color'] ?? '#ffffff',
            ],
            'hero' => [
                'hero_title_color' => $_POST['map_hero_title_color'] ?? '#ffffff',
                'hero_overlay_color' => $_POST['map_hero_overlay_color'] ?? '#000000',
            ],
            'footer' => [
                'footer_bg_color' => $_POST['map_footer_bg_color'] ?? '#1a1a2e',
                'footer_text_color' => $_POST['map_footer_text_color'] ?? '#cccccc',
                'footer_heading_color' => $_POST['map_footer_heading_color'] ?? '#ffffff',
                'footer_link_color' => $_POST['map_footer_link_color'] ?? '#cccccc',
                'footer_link_hover_color' => $_POST['map_footer_link_hover_color'] ?? '#ff5e15',
                'footer_icon_color' => $_POST['map_footer_icon_color'] ?? '#cccccc',
            ],
            'scroll_to_top' => [
                'scroll_to_top_bg_color' => $_POST['map_scroll_to_top_bg_color'] ?? '#ff5e15',
            ],
        ];

        if (!empty($customCss)) {
            $options['custom_code'] = [
                'custom_css' => $customCss,
            ];
        }

        $slug = ThemeSkin::generateSlug($name);

        $skinData = [
            'slug' => $slug,
            'name' => $name,
            'description' => trim($_POST['skin_description'] ?? 'Skin extraído de ' . ($_POST['source_url'] ?? '')),
            'author' => $_SESSION['super_admin']['name'] ?? 'MuseDock Admin',
            'version' => '1.0',
            'theme_slug' => 'default',
            'screenshot' => null,
            'options' => $options,
            'is_global' => $isGlobal,
            'tenant_id' => $tenantId,
            'is_active' => 1,
        ];

        if (ThemeSkin::saveSkin($skinData)) {
            flash('success', "Skin '{$name}' creado correctamente." . ($tenantId ? " Asignado al tenant #{$tenantId}." : ' Disponible globalmente.'));
            header('Location: /musedock/theme-extractor');
        } else {
            flash('error', 'Error al guardar el skin.');
            header('Location: /musedock/theme-extractor');
        }
        exit;
    }

    // ==================== CSS EXTRACTION ====================

    private function fetchUrl(string $url, int $timeout = 10): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_MAXFILESIZE => 5 * 1024 * 1024, // 5MB max
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 400 && $result) ? $result : null;
    }

    private function extractCssSources(string $html, string $baseUrl): array
    {
        $cssTexts = [];
        $baseParts = parse_url($baseUrl);
        $baseScheme = ($baseParts['scheme'] ?? 'https') . '://';
        $baseHost = $baseParts['host'] ?? '';
        $basePath = rtrim(dirname($baseParts['path'] ?? '/'), '/');

        // External stylesheets
        preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        // Also match href before rel
        preg_match_all('/<link[^>]+href=["\']([^"\']+)["\'][^>]*rel=["\']stylesheet["\'][^>]*>/i', $html, $matches2);
        $cssUrls = array_unique(array_merge($matches[1] ?? [], $matches2[1] ?? []));

        $fetched = 0;
        foreach ($cssUrls as $cssUrl) {
            if ($fetched >= 15) break;

            // Resolve relative URLs
            if (str_starts_with($cssUrl, '//')) {
                $cssUrl = $baseParts['scheme'] . ':' . $cssUrl;
            } elseif (str_starts_with($cssUrl, '/')) {
                $cssUrl = $baseScheme . $baseHost . $cssUrl;
            } elseif (!str_starts_with($cssUrl, 'http')) {
                $cssUrl = $baseScheme . $baseHost . $basePath . '/' . $cssUrl;
            }

            $css = $this->fetchUrl($cssUrl, 8);
            if ($css && strlen($css) < 2 * 1024 * 1024) {
                $cssTexts[] = $css;
                $fetched++;
            }
        }

        // Inline styles
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $inlineMatches);
        foreach ($inlineMatches[1] ?? [] as $inline) {
            $cssTexts[] = $inline;
        }

        return $cssTexts;
    }

    private function extractJsSources(string $html, string $baseUrl): array
    {
        $jsSources = [];
        $baseParts = parse_url($baseUrl);
        $baseScheme = ($baseParts['scheme'] ?? 'https') . '://';
        $baseHost = $baseParts['host'] ?? '';

        preg_match_all('/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        foreach ($matches[1] ?? [] as $src) {
            // Resolve URL
            if (str_starts_with($src, '//')) {
                $src = $baseParts['scheme'] . ':' . $src;
            } elseif (str_starts_with($src, '/')) {
                $src = $baseScheme . $baseHost . $src;
            } elseif (!str_starts_with($src, 'http')) {
                $src = $baseScheme . $baseHost . '/' . $src;
            }
            // Skip common CDN libraries
            $isLibrary = preg_match('/(jquery|bootstrap|popper|cdn|cloudflare|google|facebook|analytics|gtag|recaptcha)/i', $src);
            $jsSources[] = [
                'url' => $src,
                'filename' => basename(parse_url($src, PHP_URL_PATH)),
                'is_library' => (bool)$isLibrary,
            ];
        }
        return array_slice($jsSources, 0, 30);
    }

    private function extractBackgroundImages(string $css, string $baseUrl): array
    {
        $images = [];
        $baseParts = parse_url($baseUrl);
        $baseScheme = ($baseParts['scheme'] ?? 'https') . '://';
        $baseHost = $baseParts['host'] ?? '';

        preg_match_all('/background(?:-image)?\s*:[^;]*url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i', $css, $matches);
        foreach ($matches[1] ?? [] as $imgUrl) {
            if (str_starts_with($imgUrl, 'data:')) continue; // Skip data URIs

            // Resolve URL
            if (str_starts_with($imgUrl, '//')) {
                $imgUrl = $baseParts['scheme'] . ':' . $imgUrl;
            } elseif (str_starts_with($imgUrl, '/')) {
                $imgUrl = $baseScheme . $baseHost . $imgUrl;
            } elseif (!str_starts_with($imgUrl, 'http')) {
                $imgUrl = $baseScheme . $baseHost . '/' . $imgUrl;
            }

            $ext = strtolower(pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif']);

            if ($isImage || empty($ext)) {
                $images[] = [
                    'url' => $imgUrl,
                    'filename' => basename(parse_url($imgUrl, PHP_URL_PATH)),
                ];
            }
        }

        // Also extract from HTML (hero images, sliders, etc.)
        return array_values(array_unique($images, SORT_REGULAR));
    }

    private function extractColors(string $css): array
    {
        $colors = [];

        // Hex colors
        preg_match_all('/#([0-9a-fA-F]{3,8})\b/', $css, $hexMatches);
        foreach ($hexMatches[0] as $hex) {
            $normalized = strtolower($hex);
            // Expand 3-char to 6-char
            if (strlen($normalized) === 4) {
                $normalized = '#' . $normalized[1] . $normalized[1] . $normalized[2] . $normalized[2] . $normalized[3] . $normalized[3];
            }
            if (strlen($normalized) === 7 || strlen($normalized) === 9) {
                $colors[$normalized] = ($colors[$normalized] ?? 0) + 1;
            }
        }

        // RGB/RGBA
        preg_match_all('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*[\d.]+)?\s*\)/i', $css, $rgbMatches, PREG_SET_ORDER);
        foreach ($rgbMatches as $m) {
            $hex = sprintf('#%02x%02x%02x', (int)$m[1], (int)$m[2], (int)$m[3]);
            $colors[$hex] = ($colors[$hex] ?? 0) + 1;
        }

        // Sort by frequency
        arsort($colors);

        // Categorize each color
        $result = [];
        foreach ($colors as $hex => $count) {
            $hsl = $this->hexToHsl($hex);
            $category = 'accent';
            if ($hsl['l'] > 85) $category = 'background';
            elseif ($hsl['l'] < 20) $category = 'text';
            elseif ($hsl['s'] < 10) $category = ($hsl['l'] > 50 ? 'background' : 'text');

            $result[] = [
                'hex' => $hex,
                'count' => $count,
                'category' => $category,
                'hsl' => $hsl,
            ];
        }

        // Limit to top 60
        return array_slice($result, 0, 60);
    }

    private function extractFonts(string $css): array
    {
        $fonts = [];
        preg_match_all('/font-family\s*:\s*([^;}{]+)/i', $css, $matches);
        foreach ($matches[1] as $fontStr) {
            $fontStr = trim($fontStr, " \t\n\r\0\x0B!important");
            // Get first font in the stack
            $parts = explode(',', $fontStr);
            $primary = trim($parts[0], " \t\n\r\"'");
            if ($primary && strlen($primary) < 100 && !preg_match('/^(inherit|initial|unset|revert)$/i', $primary)) {
                $fonts[$primary] = ($fonts[$primary] ?? 0) + 1;
            }
        }
        arsort($fonts);
        return array_slice($fonts, 0, 20, true);
    }

    private function extractCssVariables(string $css): array
    {
        $vars = [];
        preg_match_all('/--([\w-]+)\s*:\s*([^;}{]+)/i', $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $name = '--' . trim($m[1]);
            $value = trim($m[2]);
            if (strlen($value) < 200) {
                $vars[$name] = $value;
            }
        }
        return array_slice($vars, 0, 50, true);
    }

    private function autoMapColors(array $colors): array
    {
        $backgrounds = array_filter($colors, fn($c) => $c['category'] === 'background');
        $texts = array_filter($colors, fn($c) => $c['category'] === 'text');
        $accents = array_filter($colors, fn($c) => $c['category'] === 'accent');

        $darkest = !empty($texts) ? reset($texts)['hex'] : '#1a2a40';
        $lightest = !empty($backgrounds) ? reset($backgrounds)['hex'] : '#ffffff';
        $accent = !empty($accents) ? reset($accents)['hex'] : '#ff5e15';

        // Second accent if available
        $accent2 = $accent;
        $accentsArr = array_values($accents);
        if (count($accentsArr) > 1) $accent2 = $accentsArr[1]['hex'];

        return [
            'topbar_bg_color' => $darkest,
            'topbar_text_color' => $lightest,
            'header_bg_color' => $lightest,
            'header_link_color' => $darkest,
            'header_link_hover_color' => $accent,
            'header_cta_bg_color' => $accent,
            'header_cta_text_color' => '#ffffff',
            'hero_title_color' => '#ffffff',
            'hero_overlay_color' => $darkest,
            'footer_bg_color' => $darkest,
            'footer_text_color' => '#cccccc',
            'footer_heading_color' => $lightest,
            'footer_link_color' => '#cccccc',
            'footer_link_hover_color' => $accent,
            'footer_icon_color' => '#cccccc',
            'scroll_to_top_bg_color' => $accent,
        ];
    }

    private function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6; break;
                case $g: $h = (($b - $r) / $d + 2) / 6; break;
                case $b: $h = (($r - $g) / $d + 4) / 6; break;
            }
        }

        return ['h' => round($h * 360), 's' => round($s * 100), 'l' => round($l * 100)];
    }
}
