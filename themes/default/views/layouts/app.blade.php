<!doctype html>
@php
    // ========================================
    // CARGAR DATOS DEL SITIO (tenant o global)
    // ========================================
    // Detectar contexto - site_setting() ya maneja esto internamente:
    // - Si hay tenant (tenant_id() != null): usa tenant_settings
    // - Si no hay tenant: usa settings global
    $_tenantId = tenant_id();
    $_isTenant = $_tenantId !== null;

    // Cargar TODOS los settings del sitio una sola vez
    $_siteName = site_setting('site_name', '');
    $_siteDescription = site_setting('site_description', '');
    $_siteKeywords = site_setting('site_keywords', '');
    $_siteAuthor = site_setting('site_author', '');
    $_siteFavicon = site_setting('site_favicon', '');
    $_ogImage = site_setting('og_image', '');
    $_twitterSite = site_setting('twitter_site', '');
    $_twitterImage = site_setting('twitter_image', '');
    $_twitterDescription = site_setting('twitter_description', '');
    $_blogPublic = site_setting('blog_public', '1');
@endphp
<html lang="{{ site_setting('language', 'es') }}" class="no-js">

<head>
    <!-- Configuración básica -->
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    {{-- Título dinámico - trim para evitar espacios --}}
    <title>{{ trim(\Screenart\Musedock\View::yieldSection('title') ?: $_siteName) }}</title>

    {{-- Metas dinámicas --}}
    @php
        // Intentar obtener description de View::sections O del blade engine
        $__yieldDesc = \Screenart\Musedock\View::yieldSection('description') ?? '';
        // Limpiar whitespace agresivamente (trim + strip non-breaking spaces + collapse)
        $__yieldDesc = preg_replace('/[\s\x{00A0}]+/u', ' ', $__yieldDesc);
        $__yieldDesc = trim($__yieldDesc);

        $metaDescription = !empty($__yieldDesc) ? $__yieldDesc : trim($_siteDescription ?? '');

        // Fallback: si no hay descripción, generar del nombre y subtítulo del tenant
        if (empty($metaDescription)) {
            $__sub = site_setting('site_subtitle', '');
            $metaDescription = !empty($__sub) ? $_siteName . ' — ' . $__sub : $_siteName;
        }
        $metaAuthor = $_siteAuthor;
    @endphp
    <meta name="description" content="{{ !empty(trim($metaDescription)) ? trim($metaDescription) : (!empty(site_setting('site_subtitle', '')) ? site_setting('site_name', '') . ' — ' . site_setting('site_subtitle', '') : site_setting('site_name', '')) }}">
    @if($metaAuthor)
    <meta name="author" content="{{ $metaAuthor }}">
    @endif

    <!-- Favicon dinámico -->
    @if($_siteFavicon)
        <link rel="icon" type="image/x-icon" href="{{ public_file_url($_siteFavicon) }}">
    @else
        <!-- Fallback favicon -->
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('img/favicon.png') }}">
    @endif

    <!-- SEO Meta Tags dinámicas -->
    @php
        $seoKeywords = \Screenart\Musedock\View::yieldSection('keywords') ?: $_siteKeywords;
        $ogTitle = trim(\Screenart\Musedock\View::yieldSection('og_title') ?: '') ?: $_siteName;
        $ogDescription = trim(\Screenart\Musedock\View::yieldSection('og_description') ?: '') ?: $metaDescription;
        $ogImage = trim(\Screenart\Musedock\View::yieldSection('og_image', ''));
        $ogType = trim(\Screenart\Musedock\View::yieldSection('og_type', '')) ?: 'website';
        $canonicalUrl = trim(\Screenart\Musedock\View::yieldSection('canonical_url', ''));
        $siteName = $_siteName;
        $robotsDirective = trim(\Screenart\Musedock\View::yieldSection('robots', ''));

        // Verificar setting de visibilidad en buscadores
        if ($_blogPublic == '0' && empty($robotsDirective)) {
            $robotsDirective = 'noindex, nofollow';
        }

        $twitterTitle = trim(\Screenart\Musedock\View::yieldSection('twitter_title') ?: '') ?: $_siteName;
        $twitterDescription = trim(\Screenart\Musedock\View::yieldSection('twitter_description') ?: '') ?: $metaDescription;
        $twitterImage = trim(\Screenart\Musedock\View::yieldSection('twitter_image', ''));
    @endphp
    @if($seoKeywords)
    <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <meta property="og:title" content="{{ $ogTitle ?: $siteName }}">
    <meta property="og:description" content="{{ $ogDescription ?: $siteName }}">
    <meta property="og:url" content="{{ url($_SERVER['REQUEST_URI']) }}">
    @if($siteName)
    <meta property="og:site_name" content="{{ $siteName }}">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    @if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    @elseif($_ogImage)
    <meta property="og:image" content="{{ public_file_url($_ogImage) }}">
    @endif
    @if($canonicalUrl)
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @else
    <link rel="canonical" href="{{ url($_SERVER['REQUEST_URI']) }}">
    @endif
    @if($_siteName)
    <link rel="alternate" type="application/rss+xml" title="{{ $_siteName }} RSS Feed" href="{{ url('/feed') }}">
    @endif
    <meta name="robots" content="{{ $robotsDirective ?: 'index, follow' }}">
    <meta name="twitter:card" content="summary_large_image">
    @if($twitterTitle)
    <meta name="twitter:title" content="{{ $twitterTitle }}">
    @endif
    @if($twitterDescription)
    <meta name="twitter:description" content="{{ $twitterDescription }}">
    @endif
    @if($twitterImage)
    <meta name="twitter:image" content="{{ $twitterImage }}">
    @endif
    @if($_twitterSite)
    <meta name="twitter:site" content="{{ $_twitterSite }}">
    @endif
    @if($_twitterImage)
    <meta name="twitter:image" content="{{ public_file_url($_twitterImage) }}">
    @elseif($_ogImage)
    <meta name="twitter:image" content="{{ public_file_url($_ogImage) }}">
    @endif

    {{-- JSON-LD Structured Data --}}
    {{-- JSON-LD: injected at end of <head> via push, or WebSite default --}}
    @php
        // Default WebSite schema (overridden by blog/single if it pushes Article schema)
        $__websiteJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $_siteName,
            'url' => url('/'),
            'description' => $metaDescription,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('/search?q={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ];
    @endphp
    @if(empty(\Screenart\Musedock\View::yieldSection('jsonld')))
    <script type="application/ld+json">{!! json_encode($__websiteJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @else
    {!! \Screenart\Musedock\View::yieldSection('jsonld') !!}
    @endif

    {{-- hreflang for multilingual tenants --}}
    @php
        $__langs = [];
        if (function_exists('tenant_setting')) {
            $__enableMultilang = site_setting('enable_multilang', '0');
            if ($__enableMultilang === '1') {
                try {
                    $__pdo = \Screenart\Musedock\Database::connect();
                    $__langStmt = $__pdo->query("SELECT code FROM languages WHERE active = 1 ORDER BY is_default DESC, code ASC");
                    $__langs = $__langStmt->fetchAll(\PDO::FETCH_COLUMN);
                } catch (\Exception $e) {}
            }
        }
    @endphp
    @if(count($__langs) > 1)
        @foreach($__langs as $__lang)
    <link rel="alternate" hreflang="{{ $__lang }}" href="{{ url($_SERVER['REQUEST_URI']) }}?lang={{ $__lang }}">
        @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url($_SERVER['REQUEST_URI']) }}">
    @endif

    <!-- Responsive (igual en ambos) -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Carga condicional de Bootstrap --}}
    @php
        $bootstrap = false;
        // ... (mismo código PHP para determinar $bootstrap) ...
         if (function_exists('themeConfig')) {
            $bootstrap = themeConfig('bootstrap', false);
        } elseif (isset($themeConfig) && isset($themeConfig['bootstrap'])) {
            $bootstrap = $themeConfig['bootstrap'];
        } elseif (isset($GLOBALS['themeConfigData']) && isset($GLOBALS['themeConfigData']['bootstrap'])) {
            $bootstrap = $GLOBALS['themeConfigData']['bootstrap'];
        }
    @endphp

    {{-- Google Fonts para tipografías del tema --}}
    @php
        $logoFont = themeOption('header.header_logo_font', 'inherit');
        $contentHeadingFont = themeOption('typography.content_heading_font', 'inherit');
        $contentBodyFont = themeOption('typography.content_body_font', 'inherit');

        // Mapa de fuentes de Google (las fuentes del sistema no necesitan carga)
        // Las claves deben coincidir EXACTAMENTE con los valores en theme.json
        $googleFonts = [
            "'Playfair Display', serif" => 'Playfair+Display:wght@400;700',
            "'Montserrat', sans-serif" => 'Montserrat:wght@400;500;600;700',
            "'Roboto', sans-serif" => 'Roboto:wght@400;500;700',
            "'Open Sans', sans-serif" => 'Open+Sans:wght@400;600;700',
            "'Lato', sans-serif" => 'Lato:wght@400;700',
            "'Poppins', sans-serif" => 'Poppins:wght@400;500;600;700',
            "'Oswald', sans-serif" => 'Oswald:wght@400;500;600;700',
            "'Raleway', sans-serif" => 'Raleway:wght@400;500;600;700',
            "'Merriweather', serif" => 'Merriweather:wght@400;700',
            "'Nunito', sans-serif" => 'Nunito:wght@400;600;700',
            "'Quicksand', sans-serif" => 'Quicksand:wght@400;500;600;700',
        ];

        // Collect unique Google Font families needed
        $fontsToLoad = [];
        foreach ([$logoFont, $contentHeadingFont, $contentBodyFont] as $f) {
            if (isset($googleFonts[$f]) && !in_array($googleFonts[$f], $fontsToLoad)) {
                $fontsToLoad[] = $googleFonts[$f];
            }
        }
    @endphp
    @if(!empty($fontsToLoad))
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?{{ implode('&', array_map(fn($f) => 'family=' . $f, $fontsToLoad)) }}&display=swap" rel="stylesheet">
    @endif

    {{-- Bootstrap CSS local --}}
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css"> 
<!--    <link rel="stylesheet" href="{{ asset('themes/default/css/owl.carousel.min.css') }}"> -->
    <link rel="stylesheet" href="{{ asset('themes/default/css/slicknav.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/css/animate.min.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/css/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/css/themify-icons.css') }}">
<!-- <link rel="stylesheet" href="{{ asset('themes/default/css/slick.css') }}"> --}} -->
    {{-- DESACTIVADO - nice-select interfiere con selectores de idioma --}}
    {{-- <link rel="stylesheet" href="{{ asset('themes/default/css/nice-select.css') }}"> --}}
    <link rel="stylesheet" href="{{ asset('themes/default/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/css/responsive.css') }}">
	
	{{-- Swiper CSS local (DEBE cargarse ANTES de slider-themes.css para que nuestros estilos sobrescriban) --}}
	<link rel="stylesheet" href="/assets/css/swiper-bundle.min.css?v={{ file_exists(public_path('assets/css/swiper-bundle.min.css')) ? filemtime(public_path('assets/css/swiper-bundle.min.css')) : time() }}">
	<link rel="stylesheet" href="{{ asset('themes/default/css/slider-themes.css') }}?v={{ file_exists(public_path('assets/themes/default/css/slider-themes.css')) ? filemtime(public_path('assets/themes/default/css/slider-themes.css')) : time() }}">
{{-- Slick Carousel CSS (local) --}}
<link rel="stylesheet" href="{{ asset('vendor/slick/slick.min.css') }}" />
<link rel="stylesheet" href="{{ asset('vendor/slick/slick-theme.min.css') }}" />
{{-- Owl Carousel CSS (local) --}}
<link rel="stylesheet" href="{{ asset('vendor/owl-carousel/owl.carousel.min.css') }}" />
<link rel="stylesheet" href="{{ asset('vendor/owl-carousel/owl.theme.default.min.css') }}" />



    {{-- CSS de Cookies --}}
    <link rel="stylesheet" href="{{ asset('themes/default/css/cookie-consent.css') }}">

    {{-- CSS Personalizado - Espaciado y estilos del tema --}}
    <link rel="stylesheet" href="{{ asset('themes/default/css/template.css') }}?v={{ time() }}">

    {{-- CSS Custom global del tema (layout, anchos, header styles, etc.) --}}
    <link rel="stylesheet" href="{{ asset('themes/default/css/custom.css') }}?v={{ time() }}">

    {{-- CSS Custom del tenant - se carga DESPUÉS del global para poder sobrescribir --}}
    @php
        $cssTenantId = tenant()['id'] ?? null;
        $cssThemeSlug = themeConfig('slug', 'default');
        $cssTenantPrefix = $cssTenantId ? "tenant_{$cssTenantId}/{$cssThemeSlug}" : $cssThemeSlug;
        $cssCustomPath = public_path("assets/themes/{$cssTenantPrefix}/css/custom.css");
        $cssTimestampPath = public_path("assets/themes/{$cssTenantPrefix}/css/custom.css.timestamp");
        $cssTimestamp = file_exists($cssTimestampPath) ? file_get_contents($cssTimestampPath) : time();
    @endphp
    @if($cssTenantId && file_exists($cssCustomPath))
        <link rel="stylesheet" href="{{ asset("themes/{$cssTenantPrefix}/css/custom.css") }}?v={{ $cssTimestamp }}">
    @endif

    {{-- Nice Select 2 CSS --}}
    <link rel="stylesheet" href="/assets/vendor/nice-select2/nice-select2.min.css">

	{{-- CSS Variables dinámicas desde la base de datos (tenant-aware) --}}
    <style>
    :root {
        --topbar-bg-color: {{ themeOption('topbar.topbar_bg_color', '#1a2a40') }};
        --topbar-text-color: {{ themeOption('topbar.topbar_text_color', '#ffffff') }};
        /* Hero */
        --hero-title-color: {{ themeOption('hero.hero_title_color', '#ffffff') }};
        --hero-title-font: {!! themeOption('hero.hero_title_font', 'inherit') !!};
        --hero-subtitle-color: {{ themeOption('hero.hero_subtitle_color', '#ffffff') }};
        --hero-overlay-color: {{ themeOption('hero.hero_overlay_color', '#000000') }};
        --hero-overlay-opacity: {{ themeOption('hero.hero_overlay_opacity', '0.5') }};
        /* Header */
        --header-logo-max-height: {{ themeOption('header.header_logo_max_height', '45') }}px;
        --header-bg-color: {{ themeOption('header.header_bg_color', '#f8f9fa') }};
        --header-logo-text-color: {{ themeOption('header.header_logo_text_color', '#1a2a40') }};
        --header-logo-font: {!! themeOption('header.header_logo_font', 'inherit') !!};
        --header-link-color: {{ themeOption('header.header_link_color', '#333333') }};
        --header-link-hover-color: {{ themeOption('header.header_link_hover_color', '#ff5e15') }};
        --header-menu-font: {!! themeOption('header.header_menu_font', 'Poppins, sans-serif') !!};
        --header-menu-text-transform: {{ themeOption('header.header_menu_uppercase', true) ? 'uppercase' : 'none' }};
        --header-tagline-color: {{ themeOption('header.header_tagline_color', '#111827') }};
        --header-cta-bg-color: {{ themeOption('header.header_cta_bg_color', '#ff5e15') }};
        --header-cta-text-color: {{ themeOption('header.header_cta_text_color', '#ffffff') }};
        --header-cta-hover-color: {{ themeOption('header.header_cta_hover_color', '#e54c08') }};
        --footer-bg-color: {{ themeOption('footer.footer_bg_color', '#f8fafe') }};
        --footer-text-color: {{ themeOption('footer.footer_text_color', '#333333') }};
        --footer-heading-color: {{ themeOption('footer.footer_heading_color', '#333333') }};
        --footer-link-color: {{ themeOption('footer.footer_link_color', '#333333') }};
        --footer-link-hover-color: {{ themeOption('footer.footer_link_hover_color', '#ff5e15') }};
        --footer-icon-color: {{ themeOption('footer.footer_icon_color', '#333333') }};
        --footer-border-color: {{ themeOption('footer.footer_border_color', '#e5e5e5') }};
        --footer-bottom-bg-color: {{ themeOption('footer.footer_bottom_bg_color', '#ffffff') }};
        /* Scroll to top button */
        --scroll-to-top-bg-color: {{ themeOption('scroll_to_top.scroll_to_top_bg_color', '#ff5e15') }};
        --scroll-to-top-icon-color: {{ themeOption('scroll_to_top.scroll_to_top_icon_color', '#ffffff') }};
        --scroll-to-top-hover-bg-color: {{ themeOption('scroll_to_top.scroll_to_top_hover_bg_color', '#e54c08') }};
        /* Tipografía del contenido */
        --content-heading-font: {!! themeOption('typography.content_heading_font', 'inherit') !!};
        --content-body-font: {!! themeOption('typography.content_body_font', 'inherit') !!};
        --content-text-color: {{ themeOption('typography.content_text_color', '#334155') }};
        --content-heading-color: {{ themeOption('typography.content_heading_color', '#0f172a') }};
        --content-link-color: {{ themeOption('typography.content_link_color', '#3b82f6') }};
        @php
            $__typoScale = themeOption('typography.content_type_scale', 'normal');
            $__typoScales = [
                'compact' => ['h1' => '28px', 'h2' => '24px', 'h3' => '20px', 'h4' => '18px', 'h5' => '16px', 'h6' => '14px', 'body' => '15px', 'lh' => '1.6'],
                'normal'  => ['h1' => '36px', 'h2' => '28px', 'h3' => '24px', 'h4' => '20px', 'h5' => '18px', 'h6' => '16px', 'body' => '16px', 'lh' => '1.7'],
                'large'   => ['h1' => '48px', 'h2' => '36px', 'h3' => '28px', 'h4' => '24px', 'h5' => '20px', 'h6' => '18px', 'body' => '17px', 'lh' => '1.8'],
            ];
            $__ts = $__typoScales[$__typoScale] ?? $__typoScales['normal'];
        @endphp
        --content-h1-size: {{ $__ts['h1'] }};
        --content-h2-size: {{ $__ts['h2'] }};
        --content-h3-size: {{ $__ts['h3'] }};
        --content-h4-size: {{ $__ts['h4'] }};
        --content-h5-size: {{ $__ts['h5'] }};
        --content-h6-size: {{ $__ts['h6'] }};
        --content-body-size: {{ $__ts['body'] }};
        --content-line-height: {{ $__ts['lh'] }};
    }

    /* ===== Estilos del Header usando CSS Variables ===== */
    /* Logo texto */
    .header-logo span,
    .header-logo .site-title {
        color: var(--header-logo-text-color) !important;
        font-family: var(--header-logo-font) !important;
    }
    /* Sidebar mobile header: use sidebar-specific colors/fonts, not header colors */
    .musedock-sidebar-mobile-header .header-logo span {
        color: inherit !important;
        font-family: inherit !important;
    }

    /* Enlaces del menú de navegación */
    .main-navigation a,
    .header-menu a {
        color: var(--header-link-color) !important;
        font-family: var(--header-menu-font) !important;
        text-transform: var(--header-menu-text-transform) !important;
    }

    .main-navigation a:hover,
    .header-menu a:hover {
        color: var(--header-link-hover-color) !important;
    }

    /* ===== Estilos del Footer usando CSS Variables ===== */
    .footer-area,
    .footer-padding {
        background-color: var(--footer-bg-color) !important;
    }

    /* Textos del footer (excluyendo selectores de idioma) */
    .footer-area p,
    .footer-area .footer-pera p,
    .footer-copy-right p,
    .footer-bottom-area p {
        color: var(--footer-text-color) !important;
    }

    .footer-area span {
        color: var(--footer-text-color) !important;
    }

    /* Títulos del footer - tamaño uniforme */
    .footer-area h4,
    .footer-area .footer-tittle h4,
    .footer-tittle h4,
    .single-footer-caption h4 {
        color: var(--footer-heading-color) !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        margin-bottom: 12px !important;
    }

    /* Enlaces del footer - SIN decoración */
    .footer-area a,
    .footer-tittle ul li a,
    .footer-area .footer-tittle a {
        color: var(--footer-link-color) !important;
        text-decoration: none !important;
    }

    .footer-area a:hover,
    .footer-tittle ul li a:hover,
    .footer-area .footer-tittle a:hover {
        color: var(--footer-link-hover-color) !important;
        text-decoration: none !important;
    }

    /* Mantener subrayado solo en enlace de cookies */
    .cookie-settings-link a {
        text-decoration: underline !important;
    }

    /* Iconos sociales del footer */
    .footer-social a,
    .footer-social a i,
    .footer-area .footer-social a {
        color: var(--footer-icon-color) !important;
    }

    .footer-social a:hover,
    .footer-social a:hover i {
        color: var(--footer-link-hover-color) !important;
    }

    /* Línea divisoria del footer */
    .footer-border,
    .footer-bottom-area {
        border-top-color: var(--footer-border-color) !important;
    }

    .footer-border {
        border-top: 1px solid var(--footer-border-color) !important;
    }

    /* ===============================================
       BOTÓN VOLVER ARRIBA (SCROLL TO TOP)
       =============================================== */
    #scrollUp {
        background-color: var(--scroll-to-top-bg-color) !important;
        color: var(--scroll-to-top-icon-color) !important;
        text-decoration: none !important;
        border: none !important;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex !important;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }

    #scrollUp:hover {
        background-color: var(--scroll-to-top-hover-bg-color) !important;
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }

    #scrollUp i {
        color: var(--scroll-to-top-icon-color) !important;
        font-size: 18px;
    }

    /* ===============================================
       ESPACIADO PROFESIONAL DE CONTENIDO
       =============================================== */
    .page-content p, .page-body p, .content p, article p {
        margin-top: 0;
        margin-bottom: 0 !important;
        padding-bottom: 1rem;
        line-height: 1.7;
    }
    /* Only hide truly empty paragraphs (no content at all) */
    .page-content p:empty, .page-body p:empty, .content p:empty, article p:empty {
        display: none !important;
    }
    /* Editor spacing - preserved from TinyMCE (uses div to avoid p nesting issues) */
    .editor-spacing {
        display: block !important;
        height: 24px !important;
        min-height: 24px !important;
        margin: 0 !important;
        padding: 0 !important;
        visibility: visible !important;
    }
    .page-content h2, .page-body h2, .content h2, article h2 {
        margin-top: 2.5rem !important;
        margin-bottom: 1.25rem !important;
        font-weight: 700;
        line-height: 1.3;
    }
    .page-content h2:first-child, .page-body h2:first-child, .content h2:first-child {
        margin-top: 0 !important;
    }
    .page-content h3, .page-body h3, .content h3, article h3 {
        margin-top: 2rem !important;
        margin-bottom: 1rem !important;
        font-weight: 600;
        line-height: 1.4;
    }
    .page-content h3:first-child, .page-body h3:first-child, .content h3:first-child {
        margin-top: 0 !important;
    }
    .page-content hr, .page-body hr, .content hr, article hr {
        margin: 2rem 0 !important;
        border: 0 !important;
        border-top: 1px solid #aaa !important;
        height: 0 !important;
        opacity: 1 !important;
    }
    .page-content hr + h2, .page-body hr + h2, .content hr + h2,
    .page-content hr + h3, .page-body hr + h3, .content hr + h3 {
        margin-top: 1.5rem !important;
    }
    .page-content ul, .page-body ul, .content ul,
    .page-content ol, .page-body ol, .content ol, article ul, article ol {
        margin: 1.2rem 0 !important;
        padding-left: 2.5rem !important;
        list-style-position: outside !important;
    }
    .page-content ul, .page-body ul, .content ul, article ul {
        list-style-type: disc !important;
        list-style: disc !important;
    }
    .page-content ol, .page-body ol, .content ol, article ol {
        list-style-type: decimal !important;
        list-style: decimal !important;
    }
    .page-content li, .page-body li, .content li, article li {
        margin-bottom: 0.35rem !important;
        margin-left: 0 !important;
        padding-left: 0.5rem !important;
        line-height: 1.6;
        display: list-item !important;
        list-style-position: outside !important;
    }
    .page-content ul li, .page-body ul li, .content ul li, article ul li {
        list-style-type: disc !important;
    }
    .page-content ol li, .page-body ol li, .content ol li, article ol li {
        list-style-type: decimal !important;
    }
    .page-content li:last-child, .page-body li:last-child, .content li:last-child {
        margin-bottom: 0;
    }
    .page-content li p, .page-body li p, .content li p, article li p {
        margin: 0 !important;
    }
    .page-content strong, .page-body strong, .content strong {
        font-weight: 700;
        color: #333;
    }
    /* === ENLACES - Links tradicionales azules === */
    .page-content a, .page-body a, .content a, article a {
        color: #0066cc !important;
        text-decoration: underline;
    }
    .page-content a:hover, .page-body a:hover, .content a:hover, article a:hover {
        color: #0052a3 !important;
        text-decoration: underline;
    }
    .page-content a:visited, .page-body a:visited, .content a:visited, article a:visited {
        color: #551a8b !important;
    }
    /* Enlaces que contienen imágenes: sin decoración ni color heredado */
    .page-content a:has(> img), .page-body a:has(> img), .content a:has(> img), article a:has(> img) {
        text-decoration: none !important;
        color: inherit !important;
        border: none !important;
    }
    .page-content a:has(> img):visited, .page-body a:has(> img):visited {
        color: inherit !important;
    }

    /* === EMBEDS DE VIDEO RESPONSIVE === */
    .page-content iframe, .page-body iframe, .content iframe, article iframe,
    .post-content iframe {
        max-width: 100%;
        border: 0;
    }
    .page-content iframe[src*="youtube"], .page-body iframe[src*="youtube"],
    .page-content iframe[src*="vimeo"], .page-body iframe[src*="vimeo"],
    .content iframe[src*="youtube"], .content iframe[src*="vimeo"],
    .post-content iframe[src*="youtube"], .post-content iframe[src*="vimeo"],
    article iframe[src*="youtube"], article iframe[src*="vimeo"] {
        width: 100%;
        aspect-ratio: 16 / 9;
        height: auto;
    }
    .page-content video, .page-body video, .content video, article video,
    .post-content video {
        max-width: 100%;
        height: auto;
    }

    /* === EVITAR BARRAS DE DESPLAZAMIENTO NO DESEADAS === */
    /* Prevenir scroll en Nice Select y todos sus componentes */
    .nice-select,
    .nice-select .list,
    .nice-select-dropdown {
        overflow: visible !important;
        overflow-y: visible !important;
        overflow-x: visible !important;
        max-height: none !important;
    }

    /* Forzar todos los divs del header y footer a no tener scroll */
    header, header *,
    .header-area, .header-area *,
    .main-header, .main-header *,
    .header-bottom, .header-bottom *,
    footer, footer *,
    .footer-area, .footer-area *,
    .footer-padding, .footer-padding *,
    .single-footer-caption, .single-footer-caption * {
        overflow: visible !important;
        overflow-y: visible !important;
        overflow-x: visible !important;
    }

    /* Excepción: permitir scroll solo en el menú móvil */
    .mobile-menu {
        overflow-y: auto !important;
    }

    /* Prevenir scroll en lang-dropdown */
    .lang-dropdown {
        overflow: visible !important;
    }

    /* Evitar doble scrollbar: SOLO html scrollea */
    html {
        overflow-x: hidden !important;
        overflow-y: auto !important;
    }

    body {
        overflow-x: hidden !important;
        overflow-y: visible !important;
    }

    /* Cuando el menú móvil está abierto, bloquear scroll del body */
    body.mobile-menu-open {
        overflow: hidden !important;
    }

    /* ===============================================
       ESTILOS PARA ELEMENT-HERO (sobrescribir módulo elements)
       =============================================== */
    /* Altura del Hero controlada por CSS (no inline) */
    .page-content-wrapper .page-body .element-hero,
    .page-body .element-hero,
    section.element-hero {
        min-height: unset !important;
    }

    /* Reducir espacio del divider que sigue al hero */
    .element-divider,
    .element-hero + .element-divider,
    .page-body > .element-hero + .element-divider {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    </style>
	
    <!-- Modernizr  -->
    <script src="{{ asset('themes/default/js/vendor/modernizr-3.5.0.min.js') }}"></script>

    <!-- Progressive Web App (PWA)  -->
    <!-- <link rel="manifest" href="{{ asset('site.webmanifest') }}"> -->

<style>
body.mobile-menu-open {
    overflow: hidden;
}

html.mobile-menu-open {
    overflow: hidden !important;
}

/* ================================================ */
/* === Estilos para el Topbar (.header-top) === */
/* ================================================ */

.header-top {
    background-color: var(--topbar-bg-color, #1a2a40);
    padding: 10px 0;
    color: var(--topbar-text-color, white);
    font-size: 14px;
    line-height: 1.5;
}

/* Topbar flex layout: contact | ticker | social */
.topbar-flex {
    display: flex;
    align-items: center;
    gap: 0;
}
.topbar-flex .header-info-left {
    flex-shrink: 0;
}
.topbar-flex .header-info-right {
    flex-shrink: 0;
    margin-left: auto;
}

/* Topbar inline ticker */
.topbar-ticker-zone {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    overflow: hidden;
    margin: 0 16px;
    border-left: 1px solid rgba(255,255,255,0.15);
    border-right: 1px solid rgba(255,255,255,0.15);
}
.topbar-ticker-label {
    background: rgba(255,255,255,0.13);
    color: var(--topbar-text-color, #fff);
    font-weight: 700;
    font-size: 0.68rem;
    padding: 4px 12px;
    white-space: nowrap;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    flex-shrink: 0;
}
.topbar-ticker-marquee-wrap {
    flex: 1;
    overflow: hidden;
    position: relative;
    mask-image: linear-gradient(to right, transparent 0%, black 3%, black 97%, transparent 100%);
    -webkit-mask-image: linear-gradient(to right, transparent 0%, black 3%, black 97%, transparent 100%);
}
.topbar-ticker-marquee {
    display: flex;
    white-space: nowrap;
    animation: topbarTickerScroll 35s linear infinite;
}
.topbar-ticker-marquee:hover {
    animation-play-state: paused;
}
.topbar-ticker-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 20px;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--topbar-text-color, #fff) !important;
    text-decoration: none !important;
    white-space: nowrap;
    flex-shrink: 0;
    opacity: 0.8;
}
.topbar-ticker-item:hover {
    opacity: 1;
}
.topbar-ticker-item i {
    color: var(--topbar-text-color, #fff);
    font-size: 0.28rem;
    opacity: 0.35;
}
@keyframes topbarTickerScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

/* Contenedor dentro del topbar (ya tiene estilos de Bootstrap) */
/* .header-top .container-fluid { ... } */

/* Fila dentro del topbar (ya tiene estilos de Bootstrap) */
/* .header-top .row { ... } */

/* --- Información Izquierda (Contacto) --- */
.header-info-left ul {
    list-style: none;       /* Quitar viñetas */
    padding: 0;             /* Quitar padding por defecto */
    margin: 0;              /* Quitar margen por defecto */
    display: flex;          /* Alinear elementos horizontalmente */
    align-items: center;    /* Centrar verticalmente los items */
    gap: 25px;              /* Espacio entre los elementos <li> (dirección, email, etc.) */
}

.header-info-left li {
    color: white;           /* Asegurar color blanco para el texto del <li> */
    display: flex;          /* Alinear icono y texto dentro del <li> */
    align-items: center;    /* Centrar verticalmente icono y texto */
}

.header-info-left li i {
    color: white;           /* Asegurar color blanco para el icono <i class="fas..."> */
    margin-right: 8px;      /* Espacio entre el icono y el texto */
    font-size: 1em;         /* Tamaño del icono similar al texto */
}

/* --- Información Derecha (Redes Sociales) --- */
/* .header-info-right tiene text-right de Bootstrap, pero el ul interno controla el final */

.header-info-right .header-social {
    list-style: none;       /* Quitar viñetas */
    padding: 0;             /* Quitar padding por defecto */
    margin: 0;              /* Quitar margen por defecto */
    /* Las clases d-flex y justify-content-end ya están en el HTML */
    gap: 18px;              /* Espacio entre los iconos sociales <li> */
}

.header-info-right .header-social li {
    /* No necesita estilos específicos si usamos gap en el ul */
}

.header-info-right .header-social a {
    color: white;           /* Color blanco para el enlace (y el icono dentro) */
    text-decoration: none;  /* Quitar subrayado */
    display: inline-block;  /* Para que el hover funcione bien */
    transition: color 0.3s ease; /* Transición suave para el hover */
}

.header-info-right .header-social a:hover {
    color: #ff5e15;         /* Cambiar a tu color naranja de acento al pasar el ratón */
}

.header-info-right .header-social a i {
   font-size: 1.1em;        /* Hacer los iconos sociales ligeramente más grandes */
   /* El color se hereda del 'a' */
}

/* ================================================ */
/* === Fin Estilos Topbar === */
/* ================================================ */

/* RESET Y FORZADO - Elimina estilos conflictivos (Opcional pero recomendado) */
.navbar-toggler,
.mobile-menu-toggle,
.mobile-menu-toggle-btn,
.mobile-toggle,
.navbar-toggle,
.hamburger-menu {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

/* Estilos base del header */
.musedock-header {
    padding: 0;
    min-height: 80px;
    display: flex;
    align-items: center;
    position: relative;
    background-color: var(--header-bg-color, #f8f9fa); /* Color de fondo dinámico */
    border-bottom: 1px solid #eee; /* Borde sutil */
    z-index: 999; /* Asegura que esté sobre otro contenido */
}

/* Cuando no hay topbar, el header es mas alto para compensar */
.no-topbar .musedock-header {
    min-height: 90px;
}
.no-topbar .musedock-header.header-layout-logo-above,
.no-topbar .musedock-header.header-layout-logo-above-left {
    display: block !important;
    padding: 0;
    min-height: auto !important;
}
/* Asegurar que las filas del layout logo-above ocupen todo el ancho */
.musedock-header.header-layout-logo-above .header-logo-above-brand,
.musedock-header.header-layout-logo-above-left .header-logo-above-brand,
.musedock-header.header-layout-logo-above .header-logo-above-nav,
.musedock-header.header-layout-logo-above-left .header-logo-above-nav {
    width: 100%;
}

/* Logo-above brand row: 3-column layout for logo centered + social right */
.logo-above-brand-row {
    display: flex;
    align-items: center;
    min-height: 80px;
}
.logo-above-brand-side {
    flex: 1;
    min-width: 0;
    align-self: center;
}
.logo-above-brand-center {
    flex: 0 0 auto;
    display: flex;
    justify-content: center;
    align-self: center;
}
.logo-above-brand-right {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    align-self: center;
    gap: 10px;
}
.header-search-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    color: var(--header-link-color, #333);
    font-size: 15px;
    text-decoration: none;
    transition: color 0.2s;
}
.header-search-icon:hover {
    color: var(--header-link-hover-color, #ff5e15);
}
.logo-above-brand-left {
    /* empty spacer for balance (centered layout) */
}
.logo-above-brand-left-logo {
    flex: 1;
    min-width: 0;
}

/* Logo-above nav bar: menu left, actions right */
.header-logo-above-nav-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.logo-above-nav-left {
    flex: 1;
    min-width: 0;
}
.header-layout-logo-above .logo-above-nav-left,
.header-layout-logo-above-left .logo-above-nav-left {
    text-align: left;
}
.header-layout-logo-above .logo-above-nav-left .main-navigation,
.header-layout-logo-above-left .logo-above-nav-left .main-navigation {
    display: flex;
    justify-content: flex-start;
}
.header-layout-logo-above .logo-above-nav-left .main-navigation ul,
.header-layout-logo-above-left .logo-above-nav-left .main-navigation ul {
    justify-content: flex-start !important;
}

/* Header inline search input */
.header-inline-search {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;
    height: 32px;
}
.header-inline-search input {
    border: none;
    padding: 4px 10px;
    font-size: 0.8rem;
    outline: none;
    background: transparent;
    font-family: inherit;
    color: #333;
    width: 140px;
}
.header-inline-search input::placeholder { color: #aaa; }
.header-inline-search button {
    border: none;
    background: transparent;
    color: #888;
    padding: 4px 10px;
    cursor: pointer;
    font-size: 0.8rem;
}
.header-inline-search button:hover { color: #333; }

@media (max-width: 991px) {
    .logo-above-brand-row { justify-content: center; }
    .logo-above-brand-side { display: none; }
    .header-inline-search { display: none; }
}

/* ============================================================
   LAYOUT SIDEBAR: Portfolio/personal — fixed sidebar left
   Uses CSS variables so any tenant can customize colors
   ============================================================ */

/* --- Fixed dark background column behind sidebar --- */
body.layout-sidebar { background: var(--header-bg-color, #292929) !important; }
body.layout-sidebar::before {
    content: ""; position: fixed; top: 0; left: 0;
    width: calc(15% + 280px); height: 100%;
    background: var(--header-bg-color, #292929); z-index: 999;
}
body.layout-sidebar::after {
    content: ""; position: fixed; top: 0; left: 0;
    width: calc(15% + 280px); height: 100%;
    background: #000; opacity: 0.3; z-index: 999;
}

/* --- Sidebar nav: fixed, positioned inside the dark column --- */
.musedock-sidebar-nav {
    position: fixed !important; top: 50px; left: 15%;
    width: 280px !important; height: auto !important;
    max-height: calc(100vh - 80px);
    background: var(--header-bg-color, #292929) !important;
    z-index: 1000 !important; overflow-y: auto; overflow-x: visible;
}
.sidebar-nav-inner {
    width: 100%; display: flex; flex-direction: column;
    position: relative; min-height: 100%;
}
/* Yellow line on top */
.sidebar-nav-inner::before {
    content: ""; display: block; height: 4px;
    background: var(--header-link-hover-color, #f1c311);
    flex-shrink: 0;
}

/* --- Logo holder: dashed border, centered --- */
.sidebar-nav-brand {
    margin: 30px 20px !important;
    border: 1px dashed rgba(255,255,255,0.35) !important;
    background: rgba(255,255,255,0.03) !important;
    overflow: hidden;
}
.sidebar-nav-brand-link {
    text-decoration: none !important;
    display: block;
    padding: 25px 15px;
    text-align: center;
}
.sidebar-nav-brand-centered { margin-top: auto !important; margin-bottom: auto !important; }
.sidebar-nav-logo { max-height: 80px; max-width: 100%; width: auto; margin: 0 auto; display: block; object-fit: contain; }
.sidebar-nav-title {
    display: block; margin-top: 10px; font-weight: 300;
    text-align: center; line-height: 1.25;
    color: var(--header-link-hover-color, #f1c311);
}
.sidebar-nav-subtitle {
    display: block; margin-top: 4px;
    text-align: center; font-weight: 300;
    color: var(--header-link-color, #999);
    opacity: 0.65; letter-spacing: 0.5px;
}

/* --- Navigation items --- */
.sidebar-nav-menu { flex: 1; padding: 0 !important; }
.sidebar-nav-list { list-style: none; padding: 0; margin: 0; width: 100%; }
.sidebar-nav-item {
    background: rgba(255,255,255,0.08) !important;
    border-bottom: 1px solid rgba(255,255,255,0.12) !important;
    border-left: none !important; position: relative;
}
.sidebar-nav-item:first-child { border-top: 1px solid rgba(255,255,255,0.12) !important; }
/* Active indicator bar */
.sidebar-nav-item::before {
    content: ""; position: absolute; top: 0; left: 0;
    width: 4px; height: 0; background: var(--header-link-hover-color, #f1c311);
    transition: height 0.2s ease; z-index: 2;
}
.sidebar-nav-item:hover::before, .sidebar-nav-item.active::before { height: 100%; }
.sidebar-nav-link {
    display: block !important; padding: 14px 10px 14px 40px !important;
    color: var(--header-link-color, #fff) !important; text-decoration: none !important;
    font-size: 12px !important; font-weight: 600 !important;
    text-transform: uppercase !important; letter-spacing: 1px !important;
    font-family: var(--header-menu-font, inherit) !important;
    transition: color 0.1s linear !important;
}
.sidebar-nav-link:hover { color: rgba(255,255,255,0.6) !important; background: none !important; }

/* --- Submenu popup --- */
.sidebar-nav-item .sidebar-nav-submenu { display: none; }
.sidebar-nav-item:hover > .sidebar-nav-submenu { display: block; }
.sidebar-nav-submenu {
    position: absolute !important; top: 0 !important; left: 100% !important;
    width: 200px !important; background: rgba(51,51,51,0.91) !important;
    padding: 20px 0 !important; margin: 0 !important;
    border-right: 4px solid var(--header-link-hover-color, #f1c311) !important;
    z-index: 1000 !important; list-style: none;
    box-shadow: 5px 5px 15px rgba(0,0,0,0.3);
}
.sidebar-nav-submenu .sidebar-nav-item {
    background: none !important; border: none !important; padding: 8px 20px !important;
}
.sidebar-nav-submenu .sidebar-nav-item::before { display: none !important; }
.sidebar-nav-submenu .sidebar-nav-link {
    font-size: 11px !important; padding: 0 0 10px 0 !important;
    text-transform: none !important; border-bottom: 1px dashed rgba(255,255,255,0.15) !important;
    letter-spacing: 0 !important; font-weight: 400 !important;
}

/* --- Sidebar footer --- */
.sidebar-nav-footer {
    padding: 15px 25px 20px;
    border-top: 1px solid rgba(128,128,128,0.15) !important;
    background: rgba(0,0,0,0.06);
    margin-top: auto;
}
.sidebar-nav-lang-select {
    width: 100%; padding: 8px 10px;
    border: 1px solid rgba(128,128,128,0.25);
    background: transparent;
    color: var(--header-link-color, #ccc);
    border-radius: 4px;
    font-size: 0.85rem; margin-bottom: 10px;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23999' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 30px;
}
.sidebar-nav-lang-select:focus {
    border-color: rgba(128,128,128,0.4);
    box-shadow: 0 0 0 2px rgba(128,128,128,0.1);
}
.sidebar-nav-lang-select option {
    background: #fff;
    color: #333;
}
.sidebar-nav-cta {
    display: block; padding: 10px 15px;
    background: var(--header-cta-bg-color, #f1c311);
    color: var(--header-cta-text-color, #fff) !important;
    text-align: center; text-decoration: none !important;
    border-radius: 4px; font-weight: 600; font-size: 0.85rem;
    transition: opacity 0.2s;
}
.sidebar-nav-cta:hover { opacity: 0.9; }

/* --- Content area: 85% width, shifted right --- */
.sidebar-layout-content {
    float: right !important; margin-left: 0 !important;
    width: 85% !important; padding-left: 280px !important;
    position: relative; z-index: 1; min-height: 100vh;
    box-sizing: border-box; display: flex; flex-direction: column;
}
.sidebar-layout-content main { flex: 1; background: #fff; }
.sidebar-layout-content main .container { max-width: 980px; }
.sidebar-layout-content .footer-sidebar-layout { margin-top: auto; }
.sidebar-layout-content .footer-sidebar-layout .container { max-width: 980px; }

/* Hide mobile header on desktop */
.layout-sidebar .musedock-sidebar-mobile-header { display: none !important; }
/* Mobile header style for sidebar layout */
.musedock-sidebar-mobile-header {
    background: var(--header-bg-color, #292929);
    border-bottom: 3px solid var(--header-link-hover-color, #f1c311);
    padding: 10px 0;
    position: sticky; top: 0; z-index: 999;
}
.musedock-sidebar-mobile-header .menu-toggle { display: block !important; }
.musedock-sidebar-mobile-header .menu-toggle span { background: var(--header-link-color, #ccc); }

/* --- Footer sidebar layout --- */
.footer-sidebar-layout {
    background: var(--footer-bg-color, #292929) !important;
    border-bottom: 5px solid var(--header-link-hover-color, #f1c311);
}
.footer-sidebar-main { padding: 70px 50px 20px !important; }
.footer-sidebar-heading {
    font-size: 12px !important; font-weight: 700 !important;
    text-transform: uppercase !important; letter-spacing: 1px !important;
    color: var(--footer-heading-color, #fff) !important;
    margin-bottom: 45px !important; padding-bottom: 10px !important;
    position: relative !important;
}
.footer-sidebar-heading::after {
    content: ""; position: absolute; bottom: -10px; left: 0;
    width: 100%; height: 1px; background: rgba(255,255,255,0.15);
}
.footer-sidebar-accent {
    position: absolute !important; bottom: -9px !important; left: 0 !important;
    width: 30px !important; height: 4px !important; margin-top: 0 !important; z-index: 1;
}
.footer-sidebar-text { color: var(--footer-text-color, #fff) !important; font-size: 12px; }
.footer-sidebar-contact-list { list-style: none; padding: 0; margin: 0; }
.footer-sidebar-contact-list li {
    color: var(--footer-text-color, #aaa); padding: 6px 0; font-size: 0.85rem;
}
.footer-sidebar-menu {
    list-style: none; padding: 0; margin: 0;
    column-count: 2; column-gap: 20px;
}
.footer-sidebar-menu li {
    margin-bottom: 8px; position: relative; padding-left: 10px;
}
.footer-sidebar-menu li::before {
    content: ""; position: absolute; top: 50%; left: 0; margin-top: -1px;
    width: 5px; height: 1px; background: var(--header-link-hover-color, #f1c311);
}
.footer-sidebar-menu a {
    color: var(--footer-link-color, #eee) !important;
    text-decoration: none !important; font-size: 12px; font-weight: 700;
}
.footer-sidebar-menu a::before { content: "" !important; }
.footer-sidebar-menu a:hover { color: var(--footer-link-hover-color, #f1c311) !important; }
.footer-sidebar-copyright {
    background: rgba(255,255,255,0.05) !important; border-top: none !important; padding: 0;
}
.footer-sidebar-copyright p {
    font-size: 10px !important; letter-spacing: 1px; font-weight: 600;
    color: rgba(255,255,255,0.6) !important;
}
.footer-sidebar-social { display: flex; gap: 15px; }
.footer-sidebar-social a {
    color: var(--footer-icon-color, #fff) !important;
    text-decoration: none !important; font-size: 12px; transition: color 0.2s;
}
.footer-sidebar-social a:hover { color: var(--footer-link-hover-color, #f1c311) !important; }

/* Scroll-to-top */
body.layout-sidebar #scrollUp { border-radius: 0 !important; }

/* --- Responsive: hide sidebar, show mobile header --- */
@media (max-width: 991px) {
    body.layout-sidebar::before, body.layout-sidebar::after { display: none !important; }
    .musedock-sidebar-nav { display: none !important; }
    .layout-sidebar .musedock-sidebar-mobile-header { display: flex !important; }
    .sidebar-layout-content {
        margin-left: 0 !important; width: 100% !important;
        float: none !important; padding-left: 0 !important;
    }
}
body.layout-sidebar .header-top { display: none !important; }

/* ============================================================
   LAYOUT BANNER: Logo with accent flag + menu right
   The logo text aligns with page content; the flag extends left
   ============================================================ */
.header-layout-banner {
    display: block !important;
    min-height: auto !important;
    padding: 0 !important;
    background: var(--header-bg-color, #fff) !important;
    position: relative;
    overflow: visible;
}
.header-banner-wrap {
    display: flex;
    align-items: stretch;
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 12px;
    position: relative;
    z-index: 1;
}
/* Logo banner — sits on top of the accent bar */
.header-banner-logo {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
    background: var(--header-cta-bg-color, #f3595b);
}
/* Flag extends LEFT from the logo to the edge of screen */
.header-banner-logo::before {
    content: "";
    position: absolute;
    top: 0; bottom: 0;
    right: 100%;
    width: 100vw;
    background: var(--header-cta-bg-color, #f3595b);
}
.header-banner-logo-inner {
    padding: 18px 50px 18px 0;
    margin-left: -6px;
    position: relative;
    z-index: 1;
}
.header-banner-link {
    text-decoration: none !important;
    display: flex;
    align-items: center;
    gap: 15px;
}
.header-banner-logo-img {
    max-height: var(--header-logo-max-height, 50px);
    width: auto;
}
.header-banner-text { }
.header-banner-title {
    font-size: 2.2rem;
    font-weight: 300;
    color: var(--header-cta-text-color, #fff) !important;
    margin: 0;
    line-height: 1.1;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.header-banner-subtitle {
    font-size: 0.6rem;
    color: var(--header-cta-text-color, #fff) !important;
    opacity: 0.9;
    margin: 6px 0 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 300;
    line-height: 1.3;
}
/* Dropdown arrow for menu items with children */
.header-layout-banner .main-navigation li:has(> .submenu) > a::after {
    content: " \25BE";
    font-size: 0.7em;
    margin-left: 4px;
    opacity: 0.6;
}
/* Right side: two rows — social icons float above, menu area matches banner height */
.header-banner-right {
    flex: 1;
    position: relative;
    background: var(--header-bg-color, #fff);
}
/* Social icons — absolutely positioned above the banner, flush to top */
.header-banner-social {
    position: absolute;
    top: -44px;
    right: 0;
    display: flex;
    gap: 0;
    z-index: 5;
}
.header-banner-social a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    color: #bbb !important;
    text-decoration: none !important;
    border: 1px solid #ddd;
    border-right: none;
    border-top: none;
    font-size: 18px;
    transition: color 0.2s, background 0.2s;
}
.header-banner-social a:last-child { border-right: 1px solid #ddd; }
.header-banner-social a:hover {
    color: var(--header-link-hover-color, #f3595b) !important;
    background: #f8f8f8;
}
/* Top line — extends full width above the banner */
.header-layout-banner::after {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: #eee;
    z-index: 4;
}
/* Menu row — fills the full height of the banner, bottom line */
.header-banner-nav-row {
    display: flex;
    align-items: center;
    height: 100%;
    border-bottom: 1px solid #eee;
    padding: 0 0 0 25px;
}
.header-layout-banner .header-menu {
    flex: 1;
}
.header-layout-banner .main-navigation ul {
    justify-content: flex-start;
    gap: 30px;
}
/* Space above header for the social icons — flush to browser top */
.header-layout-banner {
    margin-top: 44px;
}
/* Remove any body/html margin that creates gap at top */
body:has(.header-layout-banner) { padding-top: 0 !important; margin-top: 0 !important; }
body:has(.header-layout-banner) .header-top { display: none !important; }
.header-layout-banner .main-navigation ul { gap: 30px; }
.header-layout-banner .main-navigation a {
    font-size: 13px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    color: var(--header-link-color, #666) !important;
}
.header-layout-banner .main-navigation a:hover {
    color: var(--header-link-hover-color, #f3595b) !important;
}

/* Responsive */
@media (max-width: 991px) {
    .header-banner-wrap { flex-wrap: wrap; }
    .header-banner-logo::before { display: none; }
    .header-banner-right { padding: 10px 15px; width: 100%; }
    .header-layout-banner .header-menu { display: none; }
    .header-layout-banner .menu-toggle { display: block; }
}
@media (max-width: 575px) {
    .header-banner-title { font-size: 1.4rem; }
    .header-banner-logo-inner { padding: 12px 20px; }
    .header-banner-subtitle { font-size: 0.55rem; }
}

/* === LAYOUT BOXED: header/footer contenido alineado al contenido de pagina === */
/* En boxed, tanto header, footer como contenido comparten el mismo max-width */
.header-boxed .musedock-header > .container,
.header-boxed .musedock-header .container,
.header-boxed .header-top > .container,
.header-boxed .header-top > .container-fluid {
    max-width: var(--content-max-width, 1140px);
    margin: 0 auto;
}
.footer-boxed .footer-area > .container,
.footer-boxed .footer-bottom-area > .container,
.footer-boxed .footer-minimal-copyright > .container,
.footer-boxed .footer-minimal-legal > .container {
    max-width: var(--content-max-width, 1140px);
    margin: 0 auto;
}
/* El contenido de pagina tambien se limita al mismo ancho en modo boxed */
.header-boxed ~ main > .container,
.header-boxed ~ main > .container-fluid {
    max-width: var(--content-max-width, 1140px);
    margin: 0 auto;
}

/* Contenedor principal dentro del header */
/* Usa 1320px y padding 2rem para alinear con Hero y Highlight */
.container-fluid { /* O tu clase de contenedor principal (.container) */
    max-width: 1320px; /* Alineado con Hero y Highlight */
    margin: 0 auto;
    padding: 0 2rem; /* Alineado con Hero y Highlight (32px) */
}

/* Contenedor Flex para alinear Logo (izquierda) y Contenido Derecho (derecha) */
.header-container {
    display: flex;
    align-items: center;
    justify-content: space-between; /* Separa logo y contenido derecho */
    width: 100%;
}

/* Logo */
.header-logo {
    flex: 0 0 auto; /* No crece, no se encoge, tamaño basado en contenido */
    margin-right: 20px; /* Espacio entre logo y el resto del contenido derecho */
}

.header-logo img {
    max-height: var(--header-logo-max-height, 45px); /* Controlable desde apariencia */
    display: block; /* Evita espacio extra debajo de la imagen */
}

/* Contenedor para agrupar Menu + Acciones en el lado derecho */
.header-right-content {
    display: flex;
    align-items: center;
    /* Espacio entre el menú y el bloque de acciones */
    gap: 25px; /* Ajusta este valor para acercar/alejar menú y acciones */
}

/* Menú principal (contenedor de la navegación) */
.header-menu {
    /* No necesita flex aquí si solo contiene la nav */
    /* margin-right: auto; <- Esto lo alejaría de las acciones, lo quitamos */
}

/* Estilos de la navegación principal (ul, li, a) */
.main-navigation {
    margin: 0;
    padding: 0;
}

.main-navigation ul {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    /* Espacio entre los ítems del menú */
    gap: 20px; /* Ajusta el espacio entre elementos li */
}

.main-navigation li {
    margin: 0; /* El gap se encarga del espaciado */
    position: relative; /* Para posicionar submenús */
}

.main-navigation a {
    color: #333;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    padding: 5px 0; /* Padding vertical para el área de click */
    display: block;
    transition: color 0.3s ease;
    white-space: nowrap; /* Evita que textos largos del menú se partan */
}

.main-navigation a:hover {
    color: #ff5e15; /* Color de hover (tu color naranja) */
}

/* Estilos básicos para submenús desplegables */
.main-navigation .submenu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background-color: var(--header-bg-color, #fff);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    min-width: 200px;
    z-index: 1000;
    list-style: none;
    padding: 6px 0;
    border-radius: 6px;
    border: none;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    overflow: hidden;
}
/* Dark overlay layer on top of inherited bg */
.main-navigation .submenu::before {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.25);
    border-radius: 6px;
    pointer-events: none;
    z-index: 0;
}
.main-navigation li:hover > .submenu {
    display: block;
}
/* Dropdown arrow indicator for items with children */
.main-navigation > ul > li:has(> .submenu) > a::after {
    content: " \25BE";
    font-size: 0.65em;
    margin-left: 4px;
    opacity: 0.5;
}
.main-navigation .submenu li {
    margin: 0;
    position: relative;
    z-index: 1;
}
.main-navigation .submenu a {
    padding: 10px 18px;
    font-size: 13px;
    white-space: nowrap;
    color: var(--header-link-color, #fff);
    transition: background 0.15s, color 0.15s;
    display: block;
}
.main-navigation .submenu a:hover {
    background: rgba(255,255,255,0.1);
    color: var(--header-link-hover-color, #ff5e15);
}
/* Nested submenus (sub-sub) */
.main-navigation .submenu .submenu {
    top: 0;
    left: 100%;
    margin-top: -6px;
}

/* Contenedor de las acciones (botón + idiomas + toggle) */
.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

/* Header social icons */
.header-social-icons {
    display: flex;
    align-items: center;
    gap: 4px;
}
.header-social-icons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    color: var(--header-link-color, #333);
    font-size: 14px;
    text-decoration: none;
    border-radius: 50%;
    transition: color 0.2s, background 0.2s;
}
.header-social-icons a:hover {
    color: var(--header-link-hover-color, #ff5e15);
    background: rgba(0,0,0,0.04);
}

/* Header clock/date */
.header-clock {
    white-space: nowrap;
}
.header-clock-display {
    font-size: 0.82rem;
    color: var(--header-link-color, #555);
    font-weight: 400;
    letter-spacing: 0.01em;
}

@media (max-width: 991px) {
    .header-social-icons,
    .header-clock { display: none; }
}

/* Botón de acción principal (Ej: Inscríbete) */
.header-btn {
    display: inline-block;
    padding: 8px 20px; /* Tamaño del botón */
    background-color: var(--header-cta-bg-color, #ff5e15); /* Color dinámico */
    color: var(--header-cta-text-color, #fff) !important; /* Color texto dinámico */
    text-decoration: none !important; /* Quitar subrayado */
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    transition: background-color 0.3s ease;
    white-space: nowrap; /* Evitar que el texto se parta */
    border: none; /* Asegurar que no tenga borde por defecto */
    cursor: pointer;
}

.header-btn:hover {
    background-color: var(--header-cta-hover-color, #e54c08); /* Color hover dinámico */
    color: var(--header-cta-text-color, #fff) !important;
}

/* Selector de idioma de escritorio */
.lang-select {
    position: relative; /* Para posicionar el dropdown */
}

/* Botón que muestra el idioma actual */
.lang-btn {
    border: 1px solid #ddd;
    background: transparent;
    padding: 6px 30px 6px 12px; /* Espacio izq, derecha (para flecha), arriba/abajo */
    border-radius: 4px;
    cursor: pointer;
    position: relative; /* Para la pseudo-clase ::after */
    min-width: 65px; /* Ancho mínimo para que quepa el código de idioma */
    text-align: left;
    font-size: 14px;
    color: #333;
}

/* Flecha del dropdown */
.lang-btn::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%); /* Centrar verticalmente */
    border-width: 4px 4px 0 4px; /* Tamaño de la flecha (arriba, izq/der, abajo) */
    border-style: solid;
    border-color: #666 transparent transparent transparent; /* Color de la flecha */
    pointer-events: none; /* Para que no interfiera con el click del botón */
}

/* Contenedor del dropdown de idiomas */
.lang-dropdown {
    position: absolute;
    top: calc(100% + 5px); /* Posición debajo del botón con un pequeño espacio */
    left: 0;
    min-width: 100%; /* Mismo ancho que el botón como mínimo */
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    z-index: 1000; /* Encima de otros elementos */
    display: none; /* Oculto por defecto */
    padding: 0; /* Evitar espacio extra encima/debajo de la primera opción */
}

/* Opciones individuales de idioma en el dropdown */
.lang-option {
    display: block;
    padding: 10px 12px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    white-space: nowrap;
    text-align: center; /* Centrar el texto para alinear los idiomas visualmente */
    transition: background-color 0.2s ease, color 0.2s ease;
}

.lang-option.active, /* Estilo para el idioma actual */
.lang-option:hover {  /* Estilo al pasar el ratón */
    background-color: #f5f5f5;
    color: var(--header-link-hover-color, #ff5e15);
}

/* Botón "Hamburguesa" para menú móvil */
.menu-toggle {
    display: none; /* Oculto en escritorio por defecto */
    border: none;
    background: transparent;
    width: 28px;
    height: 22px; /* Altura total basada en las barras y espacios */
    position: relative;
    cursor: pointer;
    padding: 0; /* Quitar padding por defecto de button */
    margin-left: 5px; /* Pequeño margen si está muy pegado al idioma */
}

.menu-toggle span {
    display: block;
    position: absolute;
    height: 3px; /* Grosor de las barras */
    width: 100%;
    background: #333; /* Color de las barras */
    border-radius: 3px;
    opacity: 1;
    left: 0;
    transform: rotate(0deg);
    transition: .25s ease-in-out;
}
/* Posición de cada barra */
.menu-toggle span:nth-child(1) { top: 0px; }
.menu-toggle span:nth-child(2) { top: 9px; } /* (altura barra + espacio) */
.menu-toggle span:nth-child(3) { top: 18px; } /* (altura barra + espacio) * 2 */


/* --- Media Query para Responsive (Tablets y Móviles) --- */
@media (max-width: 991px) { /* Punto de quiebre común para tablets */

    /* Ocultar menú de escritorio */
    .header-menu {
        display: none;
    }

    /* Logo-above layouts: hide nav bar, show only hamburger in brand row */
    .header-logo-above-nav {
        display: none !important;
    }
    .logo-above-brand-right {
        display: none !important;
    }
    /* Show hamburger in brand row for logo-above layouts */
    .logo-above-mobile-toggle {
        display: block !important;
    }
    /* Add breathing room on smaller screens */
    .header-logo-above-brand .container,
    .header-logo-above-nav .container {
        padding-left: 20px !important;
        padding-right: 20px !important;
    }
    .logo-above-brand-row {
        padding: 0 5px;
    }

    /* Ocultar botón de acción y selector de idioma de escritorio */
    .header-actions .header-btn,
    .header-actions .lang-select {
        display: none;
    }

    /* Mostrar el botón hamburguesa */
    .menu-toggle {
        display: block;
    }

    /* Ajustar el contenedor derecho */
    .header-right-content {
        gap: 0;
    }

    /* Ensure padding on all header containers */
    .musedock-header > .container {
        padding-left: 20px !important;
        padding-right: 20px !important;
    }

    /* Ajustar el contenedor principal si el logo y toggle quedan mal */
    .header-container {
        /* Puedes necesitar ajustar algo aquí si el logo y el toggle no se alinean bien */
    }
}

/* Ajustes adicionales para móviles muy pequeños (Opcional) */
 @media (max-width: 480px) {
     .header-logo img {
         max-height: 35px; /* Logo aún más pequeño */
     }
     .container-fluid {
        padding: 0 10px; /* Menos padding lateral */
     }
 }

 /* --- Estilos para el Menú Móvil (el que se desliza) --- */
 /* Estos estilos van fuera del media query principal */

 .mobile-menu {
    position: fixed;
    top: 0;
    right: -320px; /* Empieza fuera de la pantalla (ancho + un poco más) */
    width: 300px; /* Ancho del menú lateral */
    height: 100%;
    background: white;
    z-index: 9999; /* Encima de todo, incluido el overlay */
    transition: right 0.35s cubic-bezier(0.77, 0, 0.175, 1); /* Transición suave */
    box-shadow: -3px 0 15px rgba(0,0,0,0.15); /* Sombra lateral */
    overflow-y: auto; /* Scroll si el contenido es largo */
    display: flex;
    flex-direction: column; /* Para alinear header, content y footer */
}

.mobile-menu.active {
    right: 0; /* Se desliza hacia adentro */
}

.mobile-menu-header {
    display: flex;
    justify-content: flex-end; /* Botón de cierre a la derecha */
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    flex-shrink: 0; /* Evita que se encoja si el contenido es largo */
}

.close-menu {
    background: transparent;
    border: none;
    font-size: 28px;
    line-height: 1;
    padding: 5px;
    cursor: pointer;
    color: #555;
}
.close-menu:hover {
    color: #000;
}

.mobile-menu-content {
    padding: 25px 20px;
    flex-grow: 1; /* Ocupa el espacio vertical disponible */
    display: flex;
    flex-direction: column; /* Para posicionar CTA al final */
}

/* Navegación dentro del menú móvil */
.mobile-nav {
    margin: 0 0 30px 0; /* Margen inferior antes de idiomas/CTA */
    padding: 0;
    list-style: none;
}

.mobile-nav li {
    border-bottom: 1px solid #f0f0f0;
}
.mobile-nav li:last-child {
    border-bottom: none;
}

.mobile-nav a {
 display: block;
 padding: 12px 0;
 color: #333;
 text-decoration: none;
 font-weight: 500;
 font-size: 16px;
 transition: color 0.2s ease;
 text-transform: var(--header-menu-text-transform);
 font-family: var(--header-menu-font);
}
.mobile-nav a:hover {
    color: #ff5e15;
}

/* Submenús móviles: colapsados por defecto, expandibles */
.mobile-submenu {
    padding-left: 15px;
    list-style: none;
    display: none;
    margin: 0;
}
.mobile-submenu.active {
    display: block;
}
/* Indicador de flecha para items con hijos */
.mobile-item.has-children > a {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.mobile-item.has-children > a::after {
    content: '';
    width: 8px; height: 8px;
    border-right: 2px solid #999;
    border-bottom: 2px solid #999;
    transform: rotate(45deg);
    transition: transform 0.2s;
    flex-shrink: 0;
    margin-left: 10px;
}
.mobile-item.has-children.open > a::after {
    transform: rotate(-135deg);
}
.mobile-submenu a {
    padding: 10px 15px;
    font-size: 14px;
    color: #666;
}
.mobile-submenu .mobile-submenu {
    padding-left: 15px;
}
.mobile-submenu .mobile-submenu a {
    font-size: 13px;
    color: #888;
}
.mobile-submenu a {
    font-size: 15px;
    font-weight: 400;
    color: #555;
    padding: 8px 0; /* Menos padding vertical */
}

/* Sección de idiomas en el menú móvil */
.mobile-languages {
    margin-top: auto; /* Empuja hacia abajo si hay espacio */
    padding-top: 20px;
    margin-bottom: 20px; /* Espacio antes del CTA */
    border-top: 1px solid #eee;
}

.mobile-languages h4 {
    margin-bottom: 15px;
    font-size: 15px;
    font-weight: 600;
    color: #555;
}

/* Estilos para el SELECT de idiomas móvil */
.mobile-lang-select select {
    width: 100%;
    padding: 10px 35px 10px 12px; /* Espacio derecha para flecha custom */
    border: 1px solid #ccc;
    border-radius: 4px;
    background-color: #fff;
    font-size: 15px;
    cursor: pointer;
    /* Ocultar flecha nativa y poner una custom */
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url('data:image/svg+xml;utf8,<svg fill="%23666" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 18px;
}
/* Estilo focus para el select */
 .mobile-lang-select select:focus {
     outline: none;
     border-color: #ff5e15; /* Borde naranja al enfocar */
     box-shadow: 0 0 0 2px rgba(255, 94, 21, 0.2); /* Sombra suave */
 }

/* Botón de acción (CTA) en el menú móvil */
.mobile-cta {
    margin-top: 10px; /* Pequeño espacio sobre el CTA si idiomas están justo antes */
    padding-top: 20px;
    border-top: 1px solid #eee;
    flex-shrink: 0; /* Evitar que se encoja */
}
.mobile-cta .header-btn {
    width: 100%; /* Botón ocupa todo el ancho */
    text-align: center;
    padding: 12px 20px; /* Padding botón móvil */
    font-size: 16px;
}

/* Overlay oscuro detrás del menú móvil */
.mobile-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6); /* Fondo semi-transparente */
    z-index: 9998; /* Justo debajo del menú móvil */
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.35s ease, visibility 0.35s ease; /* Transición suave */
}

.mobile-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Evitar scroll del body cuando el menú móvil está abierto */
body.mobile-menu-open {
    overflow: hidden;
}
        </style>


    {{-- Google Fonts detectadas en el contenido de la página (inline font-family) --}}
    @if(!empty($__contentFontsLink))
    {!! $__contentFontsLink !!}
    @endif

    {{-- Estilos adicionales --}}
    @stack('styles')

    {{-- Codigo personalizado del tenant en <head> --}}
    @php $_customHeadCode = site_setting('custom_head_code', ''); @endphp
    @if(!empty($_customHeadCode))
    {!! $_customHeadCode !!}
    @endif
</head>
@php
    $__ps = themeOption('structure.page_structure', 'classic');
    $__hl = themeOption('header.header_layout', 'default');
    $__isSidebar = ($__ps === 'sidebar' || ($__ps === 'classic' && $__hl === 'sidebar'));
@endphp
<body class="{{ themeOption('topbar.topbar_enabled', true) && !$__isSidebar ? '' : 'no-topbar' }} {{ themeOption('header.header_content_width', 'full') === 'boxed' ? 'header-boxed' : '' }} {{ themeOption('footer.footer_content_width', 'full') === 'boxed' ? 'footer-boxed' : '' }} {{ $__isSidebar ? 'layout-sidebar' : '' }}">
{{-- Codigo personalizado del tenant despues de <body> --}}
@php $_customBodyStartCode = site_setting('custom_body_start_code', ''); @endphp
@if(!empty($_customBodyStartCode))
{!! $_customBodyStartCode !!}
@endif

@php
    // Cargar traducciones del tenant para el frontend
    \Screenart\Musedock\Services\TranslationService::setContext('tenant');
    $currentLangLayout = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
    \Screenart\Musedock\Services\TranslationService::load($currentLangLayout, 'tenant');
@endphp

    <!-- Preloader Start -->
 <!--   <div id="preloader-active">
        <div class="preloader d-flex align-items-center justify-content-center">
            <div class="preloader-inner position-relative">
                <div class="preloader-circle"></div>
                <div class="preloader-img pere-text">
                    <img src="assets/img/logo/logo.png" alt="">
                </div>
            </div>
        </div>
    </div> -->
    <!-- Preloader Start -->



@php
    // Obtener opciones del tema para topbar
    $topbarEnabled = themeOption('topbar.topbar_enabled', true);
    $showAddress = themeOption('topbar.topbar_show_address', false);
    $showEmail = themeOption('topbar.topbar_show_email', true);
    $showWhatsapp = themeOption('topbar.topbar_show_whatsapp', true);
    $whatsappIcon = themeOption('topbar.topbar_whatsapp_icon', 'whatsapp');
@endphp

@php
    $__topbarTickerOn = themeOption('blog.blog_topbar_ticker', false);
    $__topbarPosts = [];
    if ($__topbarTickerOn) {
        try {
            $__pdo = \Screenart\Musedock\Database::connect();
            $__tid = tenant_id();
            if ($__tid) {
                $__st = $__pdo->prepare("SELECT title, slug FROM blog_posts WHERE tenant_id = ? AND status = 'published' ORDER BY published_at DESC LIMIT 8");
                $__st->execute([$__tid]);
            } else {
                $__st = $__pdo->query("SELECT title, slug FROM blog_posts WHERE tenant_id IS NULL AND status = 'published' ORDER BY published_at DESC LIMIT 8");
            }
            $__topbarPosts = $__st->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {}
    }
    $__hasTopbarTicker = !empty($__topbarPosts);
@endphp
@if($topbarEnabled && $headerLayout !== 'sidebar')
    <div class="header-top top-bg d-none d-lg-block">
   <div class="container">
       <div class="topbar-flex">
           <div class="header-info-left">
               <ul>
                   @if($showAddress && site_setting('contact_address', ''))
                       <li><i class="fas fa-map-marker-alt"></i>{{ site_setting('contact_address') }}</li>
                   @endif
                   @if($showEmail && site_setting('contact_email', ''))
                       <li><i class="fas fa-envelope"></i>{{ site_setting('contact_email') }}</li>
                   @endif
                   @if($showWhatsapp && site_setting('contact_whatsapp', ''))
                       <li><i class="fab fa-{{ $whatsappIcon == 'whatsapp' ? 'whatsapp' : 'phone' }}"></i>{{ site_setting('contact_whatsapp') }}</li>
                   @endif
               </ul>
           </div>

           @if($__hasTopbarTicker)
           <div class="topbar-ticker-zone">
               <span class="topbar-ticker-label">Latest Post</span>
               <div class="topbar-ticker-marquee-wrap">
                   <div class="topbar-ticker-marquee">
                       @foreach($__topbarPosts as $__tp)
                       <a href="{{ blog_url($__tp->slug) }}" class="topbar-ticker-item">
                           <i class="fas fa-circle"></i> {{ $__tp->title }}
                       </a>
                       @endforeach
                       @foreach($__topbarPosts as $__tp)
                       <a href="{{ blog_url($__tp->slug) }}" class="topbar-ticker-item">
                           <i class="fas fa-circle"></i> {{ $__tp->title }}
                       </a>
                       @endforeach
                   </div>
               </div>
           </div>
           @endif

           <div class="header-info-right d-flex align-items-center">
               @php
                   $topbarClock = themeOption('topbar.topbar_clock', false);
               @endphp
               @if($topbarClock)
               <span class="topbar-clock-display" id="topbarLiveClock" style="margin-right: 15px; font-size: 13px; opacity: 0.9; white-space: nowrap;"><i class="fas fa-clock" style="margin-right: 5px; font-size: 11px;"></i></span>
               @endif
               @if(themeOption('topbar.topbar_show_social', true))
               <ul class="header-social d-flex">
                    @if(site_setting('social_linkedin', ''))
                        <li><a href="{{ site_setting('social_linkedin') }}" target="_blank"><i class="fab fa-linkedin-in"></i></a></li>
                    @endif
                    @if(site_setting('social_twitter', ''))
                        <li><a href="{{ site_setting('social_twitter') }}" target="_blank"><i class="fab fa-twitter"></i></a></li>
                    @endif
                    @if(site_setting('social_facebook', ''))
                        <li><a href="{{ site_setting('social_facebook') }}" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                    @endif
                    @if(site_setting('social_instagram', ''))
                        <li><a href="{{ site_setting('social_instagram') }}" target="_blank"><i class="fab fa-instagram"></i></a></li>
                    @endif
                    @if(site_setting('social_pinterest', ''))
                        <li><a href="{{ site_setting('social_pinterest') }}" target="_blank"><i class="fab fa-pinterest"></i></a></li>
                    @endif
                    @if(site_setting('social_youtube', ''))
                        <li><a href="{{ site_setting('social_youtube') }}" target="_blank"><i class="fab fa-youtube"></i></a></li>
                    @endif
                    @if(site_setting('social_tiktok', ''))
                        <li><a href="{{ site_setting('social_tiktok') }}" target="_blank"><i class="fab fa-tiktok"></i></a></li>
                    @endif
                </ul>
                @endif
           </div>
       </div>
   </div>
</div>
@endif



@php
    // Page structure (retrocompatible: if page_structure not set but header_layout is sidebar, treat as sidebar)
    $pageStructure = themeOption('structure.page_structure', 'classic');
    $headerLayout = themeOption('header.header_layout', 'default');

    // Retrocompatibility: old tenants with header_layout=sidebar but no page_structure
    if ($pageStructure === 'classic' && $headerLayout === 'sidebar') {
        $pageStructure = 'sidebar';
    }
    // If page_structure is sidebar, force headerLayout for the template system
    if ($pageStructure === 'sidebar') {
        $headerLayout = 'sidebar';
    }

    // Opciones de header/footer
    $headerSticky = themeOption('header.header_sticky', false);
    $footerLayout = themeOption('footer.footer_layout', 'default');

    // CTA y selector de idioma
    $ctaEnabled = themeOption('header.header_cta_enabled', false);
    $currentLangCta = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
    $ctaTextEs = themeOption('header.header_cta_text_es', __('header.login_button'));
    $ctaTextEn = themeOption('header.header_cta_text_en', 'Login');
    $ctaText = ($currentLangCta === 'en') ? $ctaTextEn : $ctaTextEs;
    $ctaUrl = themeOption('header.header_cta_url', '#');
    $headerSearchEnabled = themeOption('header.header_search_enabled', false);
    $langSelectorEnabled = themeOption('header.header_lang_selector_enabled', true);
    // Tenant puede desactivar selector de idioma desde /admin/settings
    if ($langSelectorEnabled && function_exists('tenant_setting')) {
        $showLangSwitcherSetting = tenant_setting('show_language_switcher', '1');
        if ($showLangSwitcherSetting === '0') {
            $langSelectorEnabled = false;
        }
    }

    try {
        $pdo = \Screenart\Musedock\Database::connect();
        $tenantId = tenant_id();
        if ($tenantId) {
            $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id = ? AND active = 1 ORDER BY order_position ASC, id ASC");
            $stmt->execute([$tenantId]);
        } else {
            $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id IS NULL AND active = 1 ORDER BY order_position ASC, id ASC");
            $stmt->execute();
        }
        $languages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        $languages = [['code' => 'es', 'name' => 'Español'], ['code' => 'en', 'name' => 'English']];
    }

    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? setting('language', 'es'));
    $showLangSelector = count($languages) > 1;

    $headerPartial = 'partials.header-' . $headerLayout;
    // Comprobar si el partial existe EN LA RUTA REAL DE VISTAS DEL TEMA (tenant override o tema compartido).
    $themeSlug = get_active_theme_slug();
    $tenantId = tenant_id();
    $themeBase = APP_ROOT . "/themes/{$themeSlug}";
    if ($tenantId && is_dir(APP_ROOT . "/themes/tenant_{$tenantId}/{$themeSlug}/views")) {
        $themeBase = APP_ROOT . "/themes/tenant_{$tenantId}/{$themeSlug}";
    }
    $headerPartialPath = $themeBase . '/views/partials/header-' . $headerLayout . '.blade.php';
    if (!file_exists($headerPartialPath)) {
        $headerPartial = 'partials.header-default';
    }
@endphp

@include($headerPartial, [
    'headerSticky' => $headerSticky,
    'ctaEnabled' => $ctaEnabled,
    'ctaUrl' => $ctaUrl,
    'ctaText' => $ctaText,
    'headerSearchEnabled' => $headerSearchEnabled,
    'langSelectorEnabled' => $langSelectorEnabled,
    'showLangSelector' => $showLangSelector,
    'currentLang' => $currentLang,
    'languages' => $languages,
])


<!-- Menú móvil completamente nuevo -->
<div class="mobile-menu" id="mobile-menu">
    <div class="mobile-menu-header">
        {{-- Re-obtener idiomas si no están en scope --}}
        @php
             try {
                 if (!isset($languages) || !isset($showLangSelector)) {
                     $pdo = \Screenart\Musedock\Database::connect();
                     $tenantId = tenant_id();
                     if ($tenantId) {
                         // Tenant: obtener idiomas del tenant
                         $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id = ? AND active = 1 ORDER BY order_position ASC, id ASC");
                         $stmt->execute([$tenantId]);
                     } else {
                         // Global/Superadmin: obtener idiomas globales
                         $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id IS NULL AND active = 1 ORDER BY order_position ASC, id ASC");
                         $stmt->execute();
                     }
                     $languages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                     $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
                     $showLangSelector = count($languages) > 1;
                 }
             } catch (\Exception $e) {
                 if (!isset($languages)) {
                     $languages = [['code' => 'es', 'name' => 'Español'], ['code' => 'en', 'name' => 'English']];
                     $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
                     $showLangSelector = true;
                 }
             }
         @endphp
        <button type="button" class="close-menu" id="close-menu" aria-label="Cerrar menú">×</button>
    </div>

    <div class="mobile-menu-content">
        @if($headerSearchEnabled ?? false)
        <div class="mobile-menu-search" style="padding: 0 20px 16px;">
            <form action="{{ url('/search') }}" method="GET">
                <div style="display:flex; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                    <input type="text" name="q" placeholder="{{ ($currentLang ?? 'es') === 'en' ? 'Search...' : 'Buscar...' }}" required minlength="2" style="flex:1; border:none; padding: 10px 14px; font-size: 15px; outline:none; background: #f8f8f8;">
                    <button type="submit" style="border:none; background: var(--header-link-hover-color, #ff5e15); color:#fff; padding: 10px 14px; cursor:pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </button>
                </div>
            </form>
        </div>
        @endif
        <nav>
             {{-- Renderiza tu menú móvil aquí. Asegúrate que @custommenu funciona correctamente --}}
             @if($headerLayout === 'centered')
                 @php
                     $mobileLeftMenuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav_left', null, [
                         'ul_id' => 'mobile-menu-items-left',
                         'nav_class' => 'mobile-nav',
                         'li_class' => 'mobile-item',
                         'a_class' => 'mobile-link',
                         'submenu_class' => 'mobile-submenu'
                     ]);
                     if (trim($mobileLeftMenuHtml) === '') {
                         $mobileLeftMenuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav', null, [
                             'ul_id' => 'mobile-menu-items-left',
                             'nav_class' => 'mobile-nav',
                             'li_class' => 'mobile-item',
                             'a_class' => 'mobile-link',
                             'submenu_class' => 'mobile-submenu'
                         ]);
                     }
                     $mobileRightMenuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav_right', null, [
                         'ul_id' => 'mobile-menu-items-right',
                         'nav_class' => 'mobile-nav',
                         'li_class' => 'mobile-item',
                         'a_class' => 'mobile-link',
                         'submenu_class' => 'mobile-submenu'
                     ]);
                 @endphp

                 {!! $mobileLeftMenuHtml !!}
                 {!! $mobileRightMenuHtml !!}
             @else
                 @custommenu('nav', null, [
                     'ul_id' => 'mobile-menu-items',
                     'nav_class' => 'mobile-nav',
                     'li_class' => 'mobile-item',
                     'a_class' => 'mobile-link',
                     'submenu_class' => 'mobile-submenu'
                 ])
             @endif
        </nav>

        @if($showLangSelector && ($langSelectorEnabled ?? true))
        <div class="mobile-languages">
            <h4>{{ __('mobile_menu.select_language') }}</h4>
            <div class="mobile-lang-select">
                <select id="mobile-lang-switcher" onchange="window.location.href='?lang=' + this.value;" style="
                    border: 1px solid #000;
                    border-radius: 4px;
                    padding: 10px 14px;
                    font-size: 15px;
                    color: #000;
                    background-color: transparent;
                    cursor: pointer;
                    width: 100%;
                ">
                    @foreach($languages as $lang)
                        <option value="{{ $lang['code'] }}" {{ $currentLang == $lang['code'] ? 'selected' : '' }}>
                            {{ $lang['name'] ?? strtoupper($lang['code']) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif

        {{-- Botón CTA móvil - usa la misma config que el navbar --}}
        @if($ctaEnabled)
        <div class="mobile-cta">
            <a href="{{ $ctaUrl }}" class="header-btn">
                {{ $ctaText }}
            </a>
        </div>
        @endif
    </div>
</div>

<!-- Overlay oscuro para el menú móvil -->
<div class="mobile-overlay" id="mobile-overlay"></div>




    <script>
        document.addEventListener('DOMContentLoaded', function() {

// --- Fix: preserve spacing paragraphs from TinyMCE editor ---
document.querySelectorAll('.page-content-wrapper p, .blog-post-single p, .page-body p').forEach(function(p) {
    // Skip paragraphs that contain images, links with images, or other media
    if (p.querySelector('img, video, iframe, canvas, svg')) return;
    // Get raw text content
    var text = p.textContent;
    var trimmed = text.replace(/\s/g, '').replace(/\u00A0/g, '');
    // Check if paragraph only contains whitespace/nbsp (no real content)
    var hasOnlySpacing = (trimmed === '' && text.length > 0);
    // Also check spans that only contain nbsp
    if (!hasOnlySpacing && p.children.length > 0) {
        var innerText = '';
        p.querySelectorAll('span, strong, em, b, i').forEach(function(el) { innerText += el.textContent; });
        hasOnlySpacing = (innerText.replace(/\s/g, '').replace(/\u00A0/g, '') === '' && innerText.length > 0);
    }
    if (hasOnlySpacing) {
        p.style.setProperty('display', 'block', 'important');
        p.style.setProperty('height', '1.5em', 'important');
        p.style.setProperty('min-height', '1.5em', 'important');
    }
});

// (Image width fix moved to server-side PHP for instant rendering without flash)

// --- Opcional: Eliminar Toggles Conflictivos ---
// Intenta eliminar otros botones de menú que puedan venir de temas/plugins
var oldToggles = document.querySelectorAll('.navbar-toggler, .mobile-menu-toggle, .mobile-menu-toggle-btn, .navbar-toggle, .hamburger-menu');
oldToggles.forEach(function(toggle) {
    // IMPORTANTE: No eliminar nuestro toggle principal
    if (toggle.id !== 'menu-toggle') {
        console.log('Removing potentially conflicting toggle:', toggle); // Para depuración
        if (toggle.parentNode) {
            toggle.parentNode.removeChild(toggle);
        }
    }
});

// --- Control del Menú Móvil ---
var menuToggle = document.getElementById('menu-toggle');
var closeMenuBtn = document.getElementById('close-menu');
var mobileMenu = document.getElementById('mobile-menu');
var overlay = document.getElementById('mobile-overlay');
var body = document.body; // Referencia al body

// Función para abrir el menú
function openMobileMenu() {
 if (mobileMenu && overlay && body) {
 mobileMenu.classList.add('active');
 overlay.classList.add('active');
 body.classList.add('mobile-menu-open'); // Añade clase al body
 document.documentElement.classList.add('mobile-menu-open'); // Evita scroll del documento (doble scrollbar)
 // body.style.overflow = 'hidden'; // Alternativa directa (menos preferida que la clase)
 }
}

// Función para cerrar el menú
function closeMobileMenu() {
 if (mobileMenu && overlay && body) {
 mobileMenu.classList.remove('active');
 overlay.classList.remove('active');
 body.classList.remove('mobile-menu-open'); // Quita clase del body
 document.documentElement.classList.remove('mobile-menu-open');
 // body.style.overflow = ''; // Alternativa directa
 }
}

// Event listeners para abrir/cerrar menú móvil
if (menuToggle) {
    menuToggle.addEventListener('click', openMobileMenu);
}
if (closeMenuBtn) {
    closeMenuBtn.addEventListener('click', closeMobileMenu);
}
if (overlay) {
    overlay.addEventListener('click', closeMobileMenu);
}

// --- Submenús móviles: acordeón colapsable ---
document.querySelectorAll('.mobile-nav .mobile-item').forEach(function(item) {
    var submenu = item.querySelector('.mobile-submenu');
    if (submenu) {
        item.classList.add('has-children');
        item.querySelector('a').addEventListener('click', function(e) {
            // Only toggle if clicking the parent link, not submenu links
            if (e.target.closest('.mobile-submenu')) return;
            e.preventDefault();
            item.classList.toggle('open');
            submenu.classList.toggle('active');
        });
    }
});

// --- Control del Dropdown de Idioma (Escritorio) ---
var langBtn = document.querySelector('.lang-btn');
var langDropdown = document.querySelector('.lang-dropdown');

if (langBtn && langDropdown) {
    // Abrir/cerrar al hacer clic en el botón
    langBtn.addEventListener('click', function(event) {
        // Detiene la propagación para que el clic no llegue al 'document'
        // y cierre el menú inmediatamente.
        event.stopPropagation();

        // Comprueba si está visible y alterna
        var isVisible = langDropdown.style.display === 'block';
        langDropdown.style.display = isVisible ? 'none' : 'block';
    });

    // Cerrar al hacer clic en una opción dentro del dropdown
    langDropdown.addEventListener('click', function() {
         langDropdown.style.display = 'none';
    });

    // Cerrar al hacer clic fuera del botón y del dropdown
    document.addEventListener('click', function(event) {
        // Comprueba si el clic NO fue en el botón Y NO fue dentro del dropdown
        if (!langBtn.contains(event.target) && !langDropdown.contains(event.target)) {
            langDropdown.style.display = 'none';
        }
    });
}

// --- Control del Select de Idioma (Móvil) ---
// La lógica principal ya está en el atributo `onchange` del HTML:
// <select id="mobile-lang-switcher" onchange="window.location.href='?lang=' + this.value;">
// Por lo tanto, no se necesita JS adicional aquí para esa funcionalidad básica.

// Si prefirieras manejarlo con JS en lugar de 'onchange':
/*
var mobileLangSelect = document.getElementById('mobile-lang-switcher');
if (mobileLangSelect) {
    mobileLangSelect.addEventListener('change', function() {
        var selectedLang = this.value;
        if (selectedLang) {
            // Construye la URL actual añadiendo o reemplazando el parámetro 'lang'
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('lang', selectedLang);
            window.location.href = currentUrl.toString();
        }
    });
}
*/

// --- Opcional: Añadir CSS para la clase del body ---
// Es mejor poner esto en tu archivo CSS principal, pero si no puedes,
// puedes inyectarlo aquí (menos ideal).
/*
var style = document.createElement('style');
style.textContent = `
    body.mobile-menu-open {
        overflow: hidden;
    }
`;
document.head.appendChild(style);
*/

// --- Sticky Header ---
const header = document.getElementById('main-header');
if (header && header.classList.contains('enable-sticky')) {
    const applySticky = function() {
        const headerHeight = header.offsetHeight || 0;
        document.documentElement.style.setProperty('--sticky-header-height', headerHeight + 'px');
        header.classList.add('sticky');
        document.body.classList.add('has-sticky-header');
    };

    applySticky();
    window.addEventListener('resize', applySticky);
    window.addEventListener('load', applySticky);
}

// Limpiar párrafos y nodos residuales en page-body (importación WP)
document.querySelectorAll('.page-body').forEach(function(pageBody) {
    // 1. Eliminar párrafos sin contenido visible
    pageBody.querySelectorAll('p').forEach(function(p) {
        var text = p.textContent.replace(/\u00A0/g, '').trim();
        if (text === '' && !p.querySelector('img, video, iframe, embed, object, svg, canvas, table')) {
            p.remove();
        }
    });
    // 2. Limpiar nodos de texto whitespace entre elementos block
    Array.from(pageBody.childNodes).forEach(function(node) {
        if (node.nodeType === 3 && node.textContent.trim() === '') {
            node.remove();
        }
    });
    // 3. Colapsar margen del primer y último hijo directo
    var firstChild = pageBody.firstElementChild;
    if (firstChild) firstChild.style.marginTop = '0';
});

// Marcar columnas del footer vacías (solo ocultar en móvil vía CSS)
document.querySelectorAll('.footer-area .widget-area').forEach(function(wa) {
    if (wa.children.length === 0) {
        var col = wa.closest('[class*="col-"]');
        if (!col) return;
        var hasOtherContent = false;
        col.querySelectorAll('.footer-tittle, .footer-logo, .footer-social, .footer-pera, .language-selector').forEach(function(el) {
            if (el.offsetHeight > 0 || el.textContent.trim()) hasOtherContent = true;
        });
        if (!hasOtherContent) col.classList.add('footer-col-empty');
    }
});

}); // Fin de DOMContentLoaded
</script>

    {{-- Contenedor principal para el contenido yield --}}
    @if($headerLayout === 'sidebar')
    <div class="sidebar-layout-content">
    @endif
    <main>
        @yield('content')
    </main>

    @if($headerLayout === 'sidebar')
        @include('partials.footer-sidebar')
    </div>
    @elseif($footerLayout === 'minimal')
        @include('partials.footer-minimal')
    @elseif($footerLayout === 'banner')
        @include('partials.footer-banner')
    @else
        @include('partials.footer')
    @endif
    
    {{-- COOKIES  --}}
    @if(site_setting('cookies_enabled', '1') == '1')
        @php
            $cookieLayout = themeOption('footer.footer_cookie_banner_layout', site_setting('cookies_banner_layout', 'card'));
            $cookieBg     = site_setting('cookies_bg_color', '#ffffff');
            $cookieText   = site_setting('cookies_text_color', '#333333');
            $cookieBtnAccept = site_setting('cookies_btn_accept_bg', '#4CAF50');
            $cookieBtnReject = site_setting('cookies_btn_reject_bg', '#f44336');

            // Resolver URLs legales inteligentemente (buscar páginas reales del tenant)
            $cookiePdo = \Screenart\Musedock\Database::connect();
            $cookieTenantId = tenant_id();
            $cookieLegalPageUrl = function(array $slugCandidates, string $defaultSlug, string $settingKey, string $settingDefault) use ($cookiePdo, $cookieTenantId) {
                // 1. Si el usuario configuró una URL custom en settings, respetar eso
                $customUrl = site_setting($settingKey, '');
                if (!empty($customUrl) && $customUrl !== $settingDefault) {
                    return url($customUrl);
                }
                // 2. Buscar página publicada por slugs candidatos
                $placeholders = implode(',', array_fill(0, count($slugCandidates), '?'));
                if ($cookieTenantId) {
                    $stmt = $cookiePdo->prepare("
                        SELECT s.slug, s.prefix
                        FROM slugs s
                        JOIN pages p ON p.id = s.reference_id
                        WHERE s.module = 'pages'
                          AND s.slug IN ($placeholders)
                          AND s.tenant_id = ?
                          AND p.status = 'published'
                    ");
                    $stmt->execute(array_merge($slugCandidates, [$cookieTenantId]));
                } else {
                    $stmt = $cookiePdo->prepare("
                        SELECT s.slug, s.prefix
                        FROM slugs s
                        JOIN pages p ON p.id = s.reference_id
                        WHERE s.module = 'pages'
                          AND s.slug IN ($placeholders)
                          AND s.tenant_id IS NULL
                          AND p.status = 'published'
                    ");
                    $stmt->execute($slugCandidates);
                }
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($slugCandidates as $candidate) {
                    foreach ($rows as $row) {
                        if ($row['slug'] === $candidate) {
                            $prefix = $row['prefix'] ? '/' . $row['prefix'] . '/' : '/';
                            return url($prefix . $row['slug']);
                        }
                    }
                }
                return url(page_url($defaultSlug));
            };

            $bannerCookiesUrl  = $cookieLegalPageUrl(
                ['cookie-policy', 'cookies', 'politica-de-cookies', 'politica-cookies'],
                'cookie-policy',
                'cookies_policy_url',
                '/p/cookie-policy'
            );
            $bannerTermsUrl = $cookieLegalPageUrl(
                ['terms-and-conditions', 'terminos-y-condiciones', 'terminos-y-condiciones-de-uso', 'terminos', 'terms', 'condiciones-de-uso'],
                'terms-and-conditions',
                'cookies_terms_url',
                '/p/terms-and-conditions'
            );
        @endphp
        <!-- ===== Cookie Consent Popup ===== -->
        <div id="cookie-consent-popup" class="cookie-consent-popup cookie-layout-{{ $cookieLayout }}" style="display: none; --cookie-bg: {{ $cookieBg }}; --cookie-text: {{ $cookieText }}; --cookie-btn-accept: {{ $cookieBtnAccept }}; --cookie-btn-reject: {{ $cookieBtnReject }};">
        <div class="cookie-popup-content">
            <h4>{{ __('cookies.title') }}</h4>
            <p>{{ __('cookies.text') }}</p>
            <div class="cookie-popup-actions">
                <button id="cookie-manage-prefs" class="cookie-btn cookie-btn-manage">{{ __('cookies.manage_preferences') }}</button>
                <button id="cookie-reject-all" class="cookie-btn cookie-btn-reject">{{ __('cookies.reject_all') }}</button>
                <button id="cookie-accept-all" class="cookie-btn cookie-btn-accept">{{ __('cookies.accept_all') }}</button>
            </div>
            <div class="cookie-popup-links">
                <a href="{{ $bannerCookiesUrl }}">{{ __('cookies.policy_link') }}</a>
                <a href="{{ $bannerTermsUrl }}">{{ __('cookies.terms_link') }}</a>
            </div>
        </div>
    </div>

    <!-- ===== Cookie Preferences Modal ===== -->
    <div id="cookie-preferences-modal" class="cookie-preferences-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>{{ __('cookies.modal_title') }}</h3>
                <button id="cookie-modal-close" class="modal-close-btn">×</button>
            </div>
            <div class="modal-body">
                <p>{{ __('cookies.modal_intro') }}</p>

                <!-- Strictly Necessary Cookies -->
                <div class="cookie-category">
                    <div class="category-header">
                        <h4>{{ __('cookies.cat_necessary_title') }}</h4>
                        <label class="switch always-on">
                            <input type="checkbox" checked disabled>
                            <span class="slider round"></span>
                        </label>
                    </div>
                    <p>{{ __('cookies.cat_necessary_desc') }}</p>
                </div>

                <!-- Analytics Cookies -->
                <div class="cookie-category">
                    <div class="category-header">
                        <h4>{{ __('cookies.cat_analytics_title') ?: 'Cookies de Analítica' }}</h4>
                        <label class="switch">
                            <input type="checkbox" id="cookie-pref-analytics">
                            <span class="slider round"></span>
                        </label>
                    </div>
                    <p>{{ __('cookies.cat_analytics_desc') ?: 'Nos permiten medir el tráfico y analizar tu comportamiento para mejorar nuestro servicio.' }}</p>
                </div>
            </div>
            <div class="modal-footer">
                <button id="cookie-modal-reject-all" class="cookie-btn cookie-btn-reject">{{ __('cookies.reject_all') }}</button>
                <button id="cookie-modal-save" class="cookie-btn cookie-btn-manage">{{ __('cookies.save_preferences') }}</button>
                <button id="cookie-modal-accept-all" class="cookie-btn cookie-btn-accept">{{ __('cookies.accept_all') }}</button>
            </div>
        </div>
    </div>
    @endif

    <script src="{{ asset('themes/default/js/vendor/jquery-1.12.4.min.js') }}"></script>

{{-- Nice Select 2 JS --}}
<script src="/assets/vendor/nice-select2/nice-select2.min.js"></script>

<script src="{{ asset('themes/default/js/popper.min.js') }}"></script>
<script src="{{ asset('themes/default/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('themes/default/js/jquery.slicknav.min.js') }}"></script>
<!-- <script src="{{ asset('themes/default/js/owl.carousel.min.js') }}"></script> -->
<!-- <script src="{{ asset('themes/default/js/slick.min.js') }}"></script> -->
<script src="{{ asset('themes/default/js/wow.min.js') }}"></script>
<script src="{{ asset('themes/default/js/animated.headline.js') }}"></script>
<script src="{{ asset('themes/default/js/jquery.magnific-popup.js') }}"></script>
@php
    $scrollToTopEnabled = themeOption('scroll_to_top.scroll_to_top_enabled', true);
    $showScrollToTop = $scrollToTopEnabled === true || $scrollToTopEnabled === 1 || $scrollToTopEnabled === '1' || $scrollToTopEnabled === null;
@endphp
@if($showScrollToTop)
<script src="{{ asset('themes/default/js/jquery.scrollUp.min.js') }}"></script>
@endif
{{-- DESACTIVADO - nice-select interfiere con selectores de idioma --}}
{{-- <script src="{{ asset('themes/default/js/jquery.nice-select.min.js') }}"></script> --}}
<script src="{{ asset('themes/default/js/jquery.sticky.js') }}"></script>
<script src="{{ asset('themes/default/js/contact.js') }}"></script>
<script src="{{ asset('themes/default/js/jquery.form.js') }}"></script>
<script src="{{ asset('themes/default/js/jquery.validate.min.js') }}"></script>
<script src="{{ asset('themes/default/js/mail-script.js') }}"></script>
<script src="{{ asset('themes/default/js/jquery.ajaxchimp.min.js') }}"></script>
<script src="{{ asset('themes/default/js/plugins.js') }}"></script>
<script src="{{ asset('themes/default/js/main.js') }}"></script>

{{-- Fix para data-background - ejecuta inmediatamente --}}
<script>
(function($) {
    // Aplicar background images inmediatamente
    $("[data-background]").each(function() {
        var bg = $(this).attr("data-background");
        if (bg) {
            $(this).css({
                "background-image": "url(" + bg + ")",
                "background-size": "cover",
                "background-position": "center center",
                "background-repeat": "no-repeat"
            });
        }
    });
})(jQuery);
</script>

	{{-- Swiper JS local (reemplaza CDN) --}}
<script src="/assets/js/swiper-bundle.min.js"></script>
{{-- Slick Carousel JS (local) --}}
<script src="{{ asset('vendor/slick/slick.min.js') }}"></script>
{{-- Owl Carousel JS (local) --}}
<script src="{{ asset('vendor/owl-carousel/owl.carousel.min.js') }}"></script>




	
	
{{-- Web Analytics - debe cargarse ANTES que cookie-consent para que window.MuseDockAnalytics exista --}}
<script src="{{ asset('js/analytics.js') }}"></script>

@if(site_setting('cookies_enabled', '1') == '1')
<script src="{{ asset('themes/default/js/cookie-consent.js') }}?v={{ filemtime(public_path('assets/themes/default/js/cookie-consent.js')) ?: time() }}"></script>
@endif
	
@php
    $tenantId = tenant()['id'] ?? null;
    $themeSlug = themeConfig('slug', 'default');
    $tenantThemePrefix = $tenantId ? "tenant_{$tenantId}/{$themeSlug}" : $themeSlug;
    $customJsPath = public_path("assets/themes/{$tenantThemePrefix}/js/custom.js");
    $customJsContent = file_exists($customJsPath) ? trim(file_get_contents($customJsPath)) : '';
@endphp

@if(!empty($customJsContent))
    @if(str_contains($customJsContent, '<script'))
        {{-- Contiene tags <script>, inyectar como HTML directo --}}
        {!! $customJsContent !!}
    @else
        {{-- JS puro, envolver en tag script --}}
        <script>{!! $customJsContent !!}</script>
    @endif
@endif

<script>
// Forzar lightbox en enlaces con data-lightbox o clase .lightbox aunque no haya galería previa
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery === 'undefined' || !jQuery.fn.magnificPopup || window.lightbox || window.musedockGalleryLightbox) {
        return;
    }
    var $ = jQuery;
    var $links = $('a[data-lightbox]:not([data-lightbox^="gallery-"]), a.lightbox').not('.md-mfp-bound');
    if ($links.length) {
        $links.magnificPopup({
            type: 'image',
            gallery: { enabled: true }
        });
        $links.addClass('md-mfp-bound');
    }

    // Lightbox automático para imágenes en el contenido de la página
    // 1. Convertir enlaces a imágenes (target="_blank") en lightbox
    $('.page-body a[href]').each(function() {
        var $a = $(this);
        // Solo enlaces que contienen una imagen y apuntan a un archivo de imagen
        var href = $a.attr('href') || '';
        if (!$a.find('img').length) return;
        if (!href.match(/\.(jpg|jpeg|png|gif|webp|svg)(\/[a-z]*)?(\?.*)?$/i) && !href.match(/\/media\//)) return;
        // Saltar si está dentro de slider, gallery, hero, nav
        if ($a.closest('.swiper, .gallery-container, .portrait-lightbox, .element-hero, header, footer, nav').length) return;
        // Saltar si ya es lightbox
        if ($a.hasClass('md-mfp-bound') || $a.hasClass('page-content-lightbox')) return;
        // Convertir: quitar target="_blank" y marcar como lightbox
        $a.removeAttr('target').removeAttr('rel');
        $a.addClass('page-content-lightbox');
        $a.css('cursor', 'zoom-in');
    });

    // 2. Imágenes sueltas sin enlace: envolverlas en <a> para lightbox
    //    Configurable via panel de apariencia (typography.content_auto_lightbox)
    //    Solo para imágenes suficientemente grandes (>400px) — laureles, badges e iconos no se amplían
    @php $__autoLightbox = themeOption('typography.content_auto_lightbox', true); @endphp
    @if($__autoLightbox)
    $('.page-body img').each(function() {
        var $img = $(this);
        if ($img.closest('a, .swiper, .gallery-container, .portrait-lightbox, .element-hero, header, footer, nav').length) return;
        var w = parseInt($img.attr('width') || $img.prop('naturalWidth') || 0);
        var h = parseInt($img.attr('height') || $img.prop('naturalHeight') || 0);
        if (w < 400 || h < 250) return;
        var src = $img.attr('src');
        if (!src) return;
        // Heredar el display de la imagen para no romper centrado ni layouts inline
        var imgDisplay = window.getComputedStyle(this).display;
        var linkCss = { cursor: 'zoom-in', textDecoration: 'none' };
        if (imgDisplay === 'block') {
            linkCss.display = 'block';
        } else {
            linkCss.display = 'inline';
        }
        var $link = $('<a>', {
            href: src,
            'class': 'page-content-lightbox',
            css: linkCss
        });
        $img.wrap($link);
    });
    @endif

    // 3. Inicializar Magnific Popup en todos los lightbox del contenido
    var $contentLinks = $('.page-body .page-content-lightbox');
    if ($contentLinks.length) {
        $contentLinks.magnificPopup({
            type: 'image',
            gallery: { enabled: true, tCounter: '%curr% / %total%' },
            zoom: { enabled: true, duration: 300, easing: 'ease-in-out' },
            image: { titleSrc: function(item) { return item.el.find('img').attr('alt') || ''; } },
            callbacks: {
                open: function() { $('html, body').addClass('mfp-helper'); },
                close: function() { $('html, body').removeClass('mfp-helper'); }
            }
        });
    }

    // Aplicar mfp-helper a TODOS los magnificPopup del sitio
    $(document).on('mfpOpen', function() { $('html, body').addClass('mfp-helper'); });
    $(document).on('mfpClose', function() { $('html, body').removeClass('mfp-helper'); });
});
</script>

{{-- Nice Select 2 Initialization --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Nice Select 2 en el select de idiomas del footer
    var footerLangSelect = document.getElementById('language-select');
    if (footerLangSelect && typeof NiceSelect !== 'undefined') {
        NiceSelect.bind(footerLangSelect, {
            searchable: false,
            placeholder: 'select'
        });
    }

    // Inicializar Nice Select 2 en el select de idiomas móvil
    var mobileLangSelect = document.getElementById('mobile-lang-switcher');
    if (mobileLangSelect && typeof NiceSelect !== 'undefined') {
        NiceSelect.bind(mobileLangSelect, {
            searchable: false,
            placeholder: 'select'
        });
    }
});
</script>


{{-- Topbar live clock --}}
@if(themeOption('topbar.topbar_clock', false) && themeOption('topbar.topbar_enabled', true))
<script>
(function() {
    var el = document.getElementById('topbarLiveClock');
    if (!el) return;
    var tz = @json(themeOption('topbar.topbar_clock_timezone', 'Europe/Madrid'));
    var locale = @json(themeOption('topbar.topbar_clock_locale', 'es'));
    var localeMap = {'es':'es-ES','en':'en-US','fr':'fr-FR','de':'de-DE','pt':'pt-PT'};
    var fullLocale = localeMap[locale] || 'es-ES';
    var dateOpts = {weekday:'short',year:'numeric',month:'short',day:'numeric',timeZone:tz};
    var timeOpts = {hour:'numeric',minute:'2-digit',second:'2-digit',timeZone:tz,hour12:locale==='en'};
    var dateFmt, timeFmt;
    try { dateFmt = new Intl.DateTimeFormat(fullLocale, dateOpts); timeFmt = new Intl.DateTimeFormat(fullLocale, timeOpts); }
    catch(e) { dateFmt = new Intl.DateTimeFormat('es-ES', dateOpts); timeFmt = new Intl.DateTimeFormat('es-ES', timeOpts); }
    function tick() {
        var now = new Date();
        var d = dateFmt.format(now); d = d.charAt(0).toUpperCase() + d.slice(1);
        el.innerHTML = '<i class="fas fa-clock" style="margin-right:5px;font-size:11px;"></i>' + d + ' \u00B7 ' + timeFmt.format(now);
    }
    tick(); setInterval(tick, 1000);
})();
</script>
@endif

{{-- Search Overlay --}}
@include('partials._search-overlay')

{{-- Codigo personalizado del tenant antes de </body> --}}
@php $_customBodyEndCode = site_setting('custom_body_end_code', ''); @endphp
@if(!empty($_customBodyEndCode))
{!! $_customBodyEndCode !!}
@endif

{{-- Scripts adicionales  --}}
@stack('scripts')

</body>
</html>
