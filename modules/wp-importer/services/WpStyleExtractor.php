<?php

namespace WpImporter\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Extrae estilos visuales de un sitio WordPress y los mapea
 * a las theme_options del tema default de MuseDock
 */
class WpStyleExtractor
{
    private WpApiClient $client;
    private WpMediaImporter $mediaImporter;
    private ?int $tenantId;
    private string $themeSlug;

    public function __construct(
        WpApiClient $client,
        WpMediaImporter $mediaImporter,
        ?int $tenantId = null,
        string $themeSlug = 'default'
    ) {
        $this->client = $client;
        $this->mediaImporter = $mediaImporter;
        $this->tenantId = $tenantId;
        $this->themeSlug = $themeSlug;
    }

    /**
     * Ejecutar extracción completa de estilos
     */
    public function extract(): array
    {
        $result = [
            'theme_options' => [],
            'site_settings' => [],
            'google_fonts' => [],
            'errors' => [],
        ];

        // 1. Obtener HTML de la home
        $html = $this->client->fetchHomepageHtml();
        if (!$html) {
            $result['errors'][] = 'No se pudo obtener el HTML de la página principal';
            return $result;
        }

        // 2. Extraer Google Fonts
        $result['google_fonts'] = $this->extractGoogleFonts($html);

        // 3. Obtener y analizar CSS
        $stylesheetUrls = $this->client->fetchStylesheets($html);
        $css = $this->client->fetchCssContent($stylesheetUrls);

        // 4. Extraer colores y estilos del CSS
        $extractedStyles = $this->extractStylesFromCss($css, $html);
        $result['theme_options'] = $extractedStyles;

        // 5. Mapear Google Fonts a las opciones del tema
        $result['theme_options'] = $this->mapFontsToOptions($result['theme_options'], $result['google_fonts']);

        // 6. Extraer información del site (logo, favicon, nombre, etc.)
        $result['site_settings'] = $this->extractSiteInfo($html);

        // 7. Importar logo y favicon al Media Manager si existen
        $result['site_settings'] = $this->importSiteAssets($result['site_settings']);

        return $result;
    }

    /**
     * Aplicar los estilos extraídos a las theme_options del tenant
     */
    public function applyThemeOptions(array $themeOptions): bool
    {
        try {
            // Obtener opciones actuales
            $query = Database::query(
                "SELECT id, value FROM theme_options WHERE theme_slug = :theme_slug AND " .
                ($this->tenantId !== null ? "tenant_id = :tenant_id" : "tenant_id IS NULL"),
                $this->tenantId !== null
                    ? ['theme_slug' => $this->themeSlug, 'tenant_id' => $this->tenantId]
                    : ['theme_slug' => $this->themeSlug]
            );
            $existing = $query->fetch();

            $currentOptions = [];
            if ($existing) {
                $currentOptions = json_decode($existing['value'], true) ?: [];
            }

            // Merge: las opciones extraídas sobreescriben las actuales
            $mergedOptions = $this->deepMerge($currentOptions, $themeOptions);
            $jsonValue = json_encode($mergedOptions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            if ($existing) {
                Database::query(
                    "UPDATE theme_options SET value = :value, updated_at = NOW() WHERE id = :id",
                    ['value' => $jsonValue, 'id' => $existing['id']]
                );
            } else {
                Database::query(
                    "INSERT INTO theme_options (tenant_id, theme_slug, value, created_at, updated_at)
                     VALUES (:tenant_id, :theme_slug, :value, NOW(), NOW())",
                    [
                        'tenant_id' => $this->tenantId,
                        'theme_slug' => $this->themeSlug,
                        'value' => $jsonValue,
                    ]
                );
            }

            Logger::info("WpStyleExtractor: Theme options aplicadas para tenant " . ($this->tenantId ?? 'global'));
            return true;
        } catch (\Throwable $e) {
            Logger::error("WpStyleExtractor: Error aplicando theme options: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Aplicar site settings extraídas
     */
    public function applySiteSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if ($value === null || $value === '') continue;

            try {
                if ($this->tenantId !== null) {
                    // Para tenant: actualizar en tabla settings con tenant_id
                    $existing = Database::query(
                        "SELECT id FROM settings WHERE key = :key AND tenant_id = :tenant_id",
                        ['key' => $key, 'tenant_id' => $this->tenantId]
                    )->fetch();

                    if ($existing) {
                        Database::query(
                            "UPDATE settings SET value = :value WHERE id = :id",
                            ['value' => $value, 'id' => $existing['id']]
                        );
                    } else {
                        Database::query(
                            "INSERT INTO settings (tenant_id, key, value) VALUES (:tenant_id, :key, :value)",
                            ['tenant_id' => $this->tenantId, 'key' => $key, 'value' => $value]
                        );
                    }
                } else {
                    // Para superadmin: settings globales
                    $existing = Database::query(
                        "SELECT id FROM settings WHERE key = :key AND tenant_id IS NULL",
                        ['key' => $key]
                    )->fetch();

                    if ($existing) {
                        Database::query(
                            "UPDATE settings SET value = :value WHERE id = :id",
                            ['value' => $value, 'id' => $existing['id']]
                        );
                    } else {
                        Database::query(
                            "INSERT INTO settings (key, value) VALUES (:key, :value)",
                            ['key' => $key, 'value' => $value]
                        );
                    }
                }
            } catch (\Throwable $e) {
                Logger::error("WpStyleExtractor: Error aplicando setting '{$key}': " . $e->getMessage());
            }
        }
    }

    // ====================================================================
    // EXTRACTION METHODS
    // ====================================================================

    /**
     * Extraer Google Fonts del HTML
     */
    private function extractGoogleFonts(string $html): array
    {
        $fonts = [];

        // Buscar <link> de Google Fonts o Bunny Fonts
        $patterns = [
            '/fonts\.googleapis\.com\/css2?\?family=([^"\'&]+)/i',
            '/fonts\.bunny\.net\/css2?\?family=([^"\'&]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $fontParam) {
                    // Parsear familias: "Roboto:wght@400;700|Open+Sans:wght@300;400"
                    $families = explode('|', urldecode($fontParam));
                    foreach ($families as $family) {
                        $fontName = explode(':', $family)[0];
                        $fontName = str_replace('+', ' ', $fontName);
                        $fonts[] = $fontName;
                    }
                }
            }
        }

        // Buscar en @import dentro de <style>
        if (preg_match_all('/@import\s+url\(["\']?(.*?fonts\.(googleapis|bunny)\.net.*?)["\']?\)/i', $html, $matches)) {
            foreach ($matches[1] as $importUrl) {
                if (preg_match('/family=([^"\'&]+)/i', $importUrl, $fontMatch)) {
                    $families = explode('|', urldecode($fontMatch[1]));
                    foreach ($families as $family) {
                        $fontName = explode(':', $family)[0];
                        $fontName = str_replace('+', ' ', $fontName);
                        $fonts[] = $fontName;
                    }
                }
            }
        }

        return array_unique($fonts);
    }

    /**
     * Extraer estilos del CSS y HTML analizando selectores conocidos
     */
    private function extractStylesFromCss(string $css, string $html): array
    {
        $options = [];

        // ============ COLORES ============

        // Header/Navigation background
        $headerBg = $this->findCssProperty($css, [
            'header', '.site-header', '.main-header', '#masthead',
            '.navbar', '.nav-wrapper', '.header-area',
        ], 'background-color');
        if ($headerBg) $options['header']['header_bg_color'] = $headerBg;

        // Header link color
        $headerLink = $this->findCssProperty($css, [
            '.main-navigation a', '.navbar-nav a', '.nav-menu a',
            'header nav a', '.site-header a', '.menu-item a',
        ], 'color');
        if ($headerLink) $options['header']['header_link_color'] = $headerLink;

        // Header link hover
        $headerLinkHover = $this->findCssProperty($css, [
            '.main-navigation a:hover', '.navbar-nav a:hover',
            'header nav a:hover', '.menu-item a:hover',
        ], 'color');
        if ($headerLinkHover) $options['header']['header_link_hover_color'] = $headerLinkHover;

        // Site title / logo text color
        $logoColor = $this->findCssProperty($css, [
            '.site-title a', '.site-title', '.logo a', '.navbar-brand',
            '.custom-logo-link', '.logo-text',
        ], 'color');
        if ($logoColor) $options['header']['header_logo_text_color'] = $logoColor;

        // Sticky header detection
        $stickyHeader = $this->detectStickyHeader($css, $html);
        if ($stickyHeader !== null) $options['header']['header_sticky'] = $stickyHeader;

        // Uppercase menu detection
        $menuUppercase = $this->findCssProperty($css, [
            '.main-navigation', '.navbar-nav', '.nav-menu',
            'header nav', '.menu-item',
        ], 'text-transform');
        if ($menuUppercase) $options['header']['header_menu_uppercase'] = ($menuUppercase === 'uppercase');

        // Tagline detection
        $taglineEnabled = $this->detectElement($html, [
            '.site-description', '.tagline', '.site-tagline',
        ]);
        $options['header']['header_tagline_enabled'] = $taglineEnabled;

        // Tagline color
        $taglineColor = $this->findCssProperty($css, [
            '.site-description', '.tagline', '.site-tagline',
        ], 'color');
        if ($taglineColor) $options['header']['header_tagline_color'] = $taglineColor;

        // CTA button detection
        $ctaDetected = $this->detectElement($html, [
            'header .btn', 'header .button', '.header-cta',
            '.navbar .btn', '.nav-cta',
        ]);
        $options['header']['header_cta_enabled'] = $ctaDetected;

        if ($ctaDetected) {
            $ctaBg = $this->findCssProperty($css, [
                'header .btn', '.header-cta', '.navbar .btn',
                'header .button', '.nav-cta',
            ], 'background-color');
            if ($ctaBg) $options['header']['header_cta_bg_color'] = $ctaBg;

            $ctaTextColor = $this->findCssProperty($css, [
                'header .btn', '.header-cta', '.navbar .btn',
            ], 'color');
            if ($ctaTextColor) $options['header']['header_cta_text_color'] = $ctaTextColor;
        }

        // Footer background
        $footerBg = $this->findCssProperty($css, [
            'footer', '.site-footer', '.footer-area', '#colophon',
            '.footer-section', '.footer-wrapper',
        ], 'background-color');
        if ($footerBg) $options['footer']['footer_bg_color'] = $footerBg;

        // Footer text color
        $footerText = $this->findCssProperty($css, [
            'footer', '.site-footer', '.footer-area', '.footer-wrapper',
        ], 'color');
        if ($footerText) $options['footer']['footer_text_color'] = $footerText;

        // Footer heading color
        $footerHeading = $this->findCssProperty($css, [
            'footer h1', 'footer h2', 'footer h3', 'footer h4',
            '.site-footer h3', '.footer-area h4', '.widget-title',
        ], 'color');
        if ($footerHeading) $options['footer']['footer_heading_color'] = $footerHeading;

        // Footer link color
        $footerLink = $this->findCssProperty($css, [
            'footer a', '.site-footer a', '.footer-area a',
        ], 'color');
        if ($footerLink) $options['footer']['footer_link_color'] = $footerLink;

        // Footer link hover
        $footerLinkHover = $this->findCssProperty($css, [
            'footer a:hover', '.site-footer a:hover',
        ], 'color');
        if ($footerLinkHover) $options['footer']['footer_link_hover_color'] = $footerLinkHover;

        // Footer border
        $footerBorder = $this->findCssProperty($css, [
            '.footer-bottom', '.site-info', '.copyright-area',
        ], 'border-color');
        if (!$footerBorder) {
            $footerBorder = $this->findCssProperty($css, [
                '.footer-bottom', '.site-info',
            ], 'border-top-color');
        }
        if ($footerBorder) $options['footer']['footer_border_color'] = $footerBorder;

        // Topbar detection
        $topbarDetected = $this->detectElement($html, [
            '.topbar', '.top-bar', '.header-top', '.pre-header',
            '.announcement-bar', '.top-header',
        ]);
        $options['topbar']['topbar_enabled'] = $topbarDetected;

        if ($topbarDetected) {
            $topbarBg = $this->findCssProperty($css, [
                '.topbar', '.top-bar', '.header-top', '.pre-header',
            ], 'background-color');
            if ($topbarBg) $options['topbar']['topbar_bg_color'] = $topbarBg;

            $topbarText = $this->findCssProperty($css, [
                '.topbar', '.top-bar', '.header-top', '.pre-header',
            ], 'color');
            if ($topbarText) $options['topbar']['topbar_text_color'] = $topbarText;
        }

        // Hero overlay color
        $heroOverlay = $this->findCssProperty($css, [
            '.hero-overlay', '.overlay', '.hero::before', '.hero::after',
            '.banner-overlay', '.page-header::before',
        ], 'background-color');
        if ($heroOverlay) $options['hero']['hero_overlay_color'] = $heroOverlay;

        // Hero title color
        $heroTitle = $this->findCssProperty($css, [
            '.hero h1', '.hero-title', '.banner-title',
            '.page-header h1', '.hero-content h1',
        ], 'color');
        if ($heroTitle) $options['hero']['hero_title_color'] = $heroTitle;

        // Scroll to top detection
        $scrollToTop = $this->detectElement($html, [
            '.scroll-to-top', '.back-to-top', '#scroll-top',
            '.scrollup', '#scrollUp', '.to-top',
        ]);
        $options['scroll_to_top']['scroll_to_top_enabled'] = $scrollToTop;

        if ($scrollToTop) {
            $scrollBg = $this->findCssProperty($css, [
                '.scroll-to-top', '.back-to-top', '#scroll-top',
                '.scrollup', '#scrollUp',
            ], 'background-color');
            if ($scrollBg) $options['scroll_to_top']['scroll_to_top_bg_color'] = $scrollBg;
        }

        // ============ CSS VARIABLES (temas modernos) ============
        $cssVars = $this->extractCssVariables($css);
        $options = $this->mapCssVariablesToOptions($options, $cssVars);

        return $options;
    }

    /**
     * Extraer CSS custom properties (:root { --color-primary: #xxx })
     */
    private function extractCssVariables(string $css): array
    {
        $vars = [];

        if (preg_match_all('/:root\s*\{([^}]+)\}/s', $css, $rootBlocks)) {
            foreach ($rootBlocks[1] as $block) {
                if (preg_match_all('/--([\w-]+)\s*:\s*([^;]+);/', $block, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $vars['--' . trim($match[1])] = trim($match[2]);
                    }
                }
            }
        }

        return $vars;
    }

    /**
     * Mapear CSS variables comunes a opciones del tema
     */
    private function mapCssVariablesToOptions(array $options, array $cssVars): array
    {
        // Mapeo de variables CSS comunes en temas WP modernos
        $varMapping = [
            // Primary colors
            '--primary' => ['header', 'header_link_hover_color'],
            '--primary-color' => ['header', 'header_link_hover_color'],
            '--color-primary' => ['header', 'header_link_hover_color'],
            '--accent' => ['header', 'header_link_hover_color'],
            '--accent-color' => ['header', 'header_link_hover_color'],
            '--wp--preset--color--primary' => ['header', 'header_link_hover_color'],

            // Background
            '--background' => ['header', 'header_bg_color'],
            '--bg-color' => ['header', 'header_bg_color'],
            '--wp--preset--color--background' => ['header', 'header_bg_color'],

            // Text
            '--text-color' => ['footer', 'footer_text_color'],
            '--color-text' => ['footer', 'footer_text_color'],
            '--wp--preset--color--foreground' => ['header', 'header_link_color'],

            // Header specific
            '--header-bg' => ['header', 'header_bg_color'],
            '--header-color' => ['header', 'header_link_color'],
            '--nav-color' => ['header', 'header_link_color'],

            // Footer specific
            '--footer-bg' => ['footer', 'footer_bg_color'],
            '--footer-color' => ['footer', 'footer_text_color'],
        ];

        foreach ($varMapping as $cssVar => [$section, $option]) {
            if (isset($cssVars[$cssVar])) {
                $value = $cssVars[$cssVar];
                // Resolver si la variable referencia otra variable
                if (strpos($value, 'var(') === false) {
                    $color = $this->normalizeColor($value);
                    if ($color && !isset($options[$section][$option])) {
                        $options[$section][$option] = $color;
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Mapear fuentes detectadas a las opciones del tema
     */
    private function mapFontsToOptions(array $options, array $fonts): array
    {
        if (empty($fonts)) {
            return $options;
        }

        // Fuentes disponibles en el tema default de MuseDock
        $availableFonts = [
            'Playfair Display' => "'Playfair Display', serif",
            'Montserrat' => "'Montserrat', sans-serif",
            'Roboto' => "'Roboto', sans-serif",
            'Open Sans' => "'Open Sans', sans-serif",
            'Lato' => "'Lato', sans-serif",
            'Poppins' => "'Poppins', sans-serif",
            'Oswald' => "'Oswald', sans-serif",
            'Raleway' => "'Raleway', sans-serif",
            'Merriweather' => "'Merriweather', serif",
            'Nunito' => "'Nunito', sans-serif",
            'Quicksand' => "'Quicksand', sans-serif",
        ];

        $matchedFonts = [];
        foreach ($fonts as $font) {
            foreach ($availableFonts as $name => $value) {
                if (stripos($font, $name) !== false) {
                    $matchedFonts[] = ['name' => $name, 'value' => $value];
                    break;
                }
            }
        }

        if (!empty($matchedFonts)) {
            // Primera fuente detectada → logo
            if (!isset($options['header']['header_logo_font'])) {
                $options['header']['header_logo_font'] = $matchedFonts[0]['value'];
            }

            // Si hay segunda fuente → menú
            if (count($matchedFonts) > 1 && !isset($options['header']['header_menu_font'])) {
                $options['header']['header_menu_font'] = $matchedFonts[1]['value'];
            } elseif (!isset($options['header']['header_menu_font'])) {
                $options['header']['header_menu_font'] = $matchedFonts[0]['value'];
            }

            // Hero font
            if (!isset($options['hero']['hero_title_font'])) {
                $options['hero']['hero_title_font'] = $matchedFonts[0]['value'];
            }
        }

        return $options;
    }

    /**
     * Extraer información del sitio desde el HTML
     */
    private function extractSiteInfo(string $html): array
    {
        $info = [];

        // Site name desde <title> o .site-title
        if (preg_match('/<title>([^<|–-]+)/i', $html, $match)) {
            $info['site_name'] = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
        }

        // Tagline / description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/', $html, $match)) {
            $info['site_description'] = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
        }

        // Logo URL
        if (preg_match('/class=["\'][^"\']*custom-logo[^"\']*["\'][^>]*src=["\']([^"\']+)["\']/', $html, $match)) {
            $info['logo_url'] = $match[1];
        } elseif (preg_match('/class=["\'][^"\']*site-logo[^"\']*["\'].*?<img[^>]*src=["\']([^"\']+)["\']/', $html, $match)) {
            $info['logo_url'] = $match[1];
        } elseif (preg_match('/class=["\'][^"\']*navbar-brand[^"\']*["\'].*?<img[^>]*src=["\']([^"\']+)["\']/', $html, $match)) {
            $info['logo_url'] = $match[1];
        }

        // Favicon
        if (preg_match('/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]+href=["\']([^"\']+)["\']/', $html, $match)) {
            $info['favicon_url'] = $match[1];
        } elseif (preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\'](?:shortcut )?icon["\']/', $html, $match)) {
            $info['favicon_url'] = $match[1];
        }

        // Social links
        $socialPatterns = [
            'facebook' => '/href=["\']([^"\']*facebook\.com[^"\']*)["\']/',
            'twitter' => '/href=["\']([^"\']*(?:twitter|x)\.com[^"\']*)["\']/',
            'instagram' => '/href=["\']([^"\']*instagram\.com[^"\']*)["\']/',
            'linkedin' => '/href=["\']([^"\']*linkedin\.com[^"\']*)["\']/',
            'youtube' => '/href=["\']([^"\']*youtube\.com[^"\']*)["\']/',
            'tiktok' => '/href=["\']([^"\']*tiktok\.com[^"\']*)["\']/',
            'pinterest' => '/href=["\']([^"\']*pinterest\.com[^"\']*)["\']/',
        ];

        foreach ($socialPatterns as $network => $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $info['social_' . $network] = $match[1];
            }
        }

        return $info;
    }

    /**
     * Importar logo y favicon al Media Manager
     */
    private function importSiteAssets(array $settings): array
    {
        // Importar logo
        if (!empty($settings['logo_url'])) {
            $logoMedia = $this->mediaImporter->importSingleMedia([
                'source_url' => $settings['logo_url'],
                'mime_type' => $this->guessMimeType($settings['logo_url']),
                'title' => ['rendered' => 'site-logo'],
                'alt_text' => $settings['site_name'] ?? 'Logo',
                'caption' => ['rendered' => ''],
                'id' => null,
            ]);

            if ($logoMedia) {
                $settings['site_logo'] = $logoMedia['url'];
                $settings['show_logo'] = '1';
            }
            unset($settings['logo_url']);
        }

        // Importar favicon
        if (!empty($settings['favicon_url'])) {
            $faviconMedia = $this->mediaImporter->importSingleMedia([
                'source_url' => $settings['favicon_url'],
                'mime_type' => $this->guessMimeType($settings['favicon_url']),
                'title' => ['rendered' => 'favicon'],
                'alt_text' => 'Favicon',
                'caption' => ['rendered' => ''],
                'id' => null,
            ]);

            if ($faviconMedia) {
                $settings['favicon'] = $faviconMedia['url'];
            }
            unset($settings['favicon_url']);
        }

        return $settings;
    }

    // ====================================================================
    // CSS PARSING HELPERS
    // ====================================================================

    /**
     * Buscar una propiedad CSS en múltiples selectores
     */
    private function findCssProperty(string $css, array $selectors, string $property): ?string
    {
        foreach ($selectors as $selector) {
            // Escapar selector para regex
            $escaped = preg_quote($selector, '/');
            // Permitir variantes del selector (con/sin espacios, combinadores)
            $pattern = '/' . $escaped . '\s*\{([^}]+)\}/si';

            if (preg_match_all($pattern, $css, $matches)) {
                foreach ($matches[1] as $block) {
                    $escapedProp = preg_quote($property, '/');
                    if (preg_match('/' . $escapedProp . '\s*:\s*([^;!]+)/i', $block, $propMatch)) {
                        $value = trim($propMatch[1]);
                        $color = $this->normalizeColor($value);
                        if ($color) {
                            return $color;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Detectar si un elemento existe en el HTML
     */
    private function detectElement(string $html, array $selectors): bool
    {
        foreach ($selectors as $selector) {
            // Convertir selector CSS a patrón de búsqueda HTML simple
            $selector = ltrim($selector, '.#');

            if (strpos($html, $selector) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detectar si el header es sticky
     */
    private function detectStickyHeader(string $css, string $html): ?bool
    {
        $stickySelectors = [
            'header', '.site-header', '.main-header', '#masthead',
            '.navbar', '.header-area',
        ];

        foreach ($stickySelectors as $selector) {
            $escaped = preg_quote($selector, '/');
            if (preg_match('/' . $escaped . '[^{]*\{[^}]*(position\s*:\s*(fixed|sticky))[^}]*\}/si', $css)) {
                return true;
            }
        }

        // Detectar por clases en el HTML
        if (preg_match('/class=["\'][^"\']*(?:sticky-header|fixed-header|is-sticky)[^"\']*["\']/', $html)) {
            return true;
        }

        return false;
    }

    /**
     * Normalizar un valor de color a formato hex
     */
    private function normalizeColor(string $value): ?string
    {
        $value = trim($value);

        // Ya es hex
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            // Expandir shorthand #abc → #aabbcc
            if (strlen($value) === 4) {
                $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
            }
            return strtolower($value);
        }

        // rgb(r, g, b) o rgba(r, g, b, a)
        if (preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $value, $match)) {
            return sprintf('#%02x%02x%02x', (int)$match[1], (int)$match[2], (int)$match[3]);
        }

        // Colores con nombre
        $namedColors = [
            'white' => '#ffffff', 'black' => '#000000', 'red' => '#ff0000',
            'blue' => '#0000ff', 'green' => '#008000', 'transparent' => null,
            'inherit' => null, 'initial' => null,
        ];

        $lower = strtolower($value);
        if (isset($namedColors[$lower])) {
            return $namedColors[$lower];
        }

        return null;
    }

    /**
     * Adivinar MIME type por extensión de URL
     */
    private function guessMimeType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon', 'bmp' => 'image/bmp',
        ];

        return $map[$ext] ?? 'image/png';
    }

    /**
     * Deep merge de arrays
     */
    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
