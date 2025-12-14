<!doctype html>
@php
    // Asegurar contexto tenant para traducciones del frontend
    \Screenart\Musedock\Services\TranslationService::setContext('tenant');
    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
    \Screenart\Musedock\Services\TranslationService::load($currentLang, 'tenant');

    // ========================================
    // CARGAR DATOS DEL SITIO (tenant o global)
    // ========================================
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
<html lang="{{ site_setting('language', 'es') }}">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    {{-- Título dinámico --}}
    <title>{{ \Screenart\Musedock\View::yieldSection('title') ?: $_siteName }}</title>

    {{-- Meta description --}}
    @php
        $metaDescription = \Screenart\Musedock\View::yieldSection('description') ?: $_siteDescription;
    @endphp
    @if($metaDescription)
    <meta name="description" content="{{ $metaDescription }}">
    @endif

    <!-- Favicon dinámico -->
    @if($_siteFavicon)
        <link rel="icon" type="image/x-icon" href="{{ asset(ltrim($_siteFavicon, '/')) }}">
    @else
        <link rel="shortcut icon" href="{{ asset('themes/play-bootstrap/img/favicon.svg') }}" type="image/svg" />
    @endif

    {{-- SEO Meta Tags --}}
    @php
        $seoKeywords = \Screenart\Musedock\View::yieldSection('keywords') ?: $_siteKeywords;
        $ogTitle = \Screenart\Musedock\View::yieldSection('og_title') ?: $_siteName;
        $ogDescription = \Screenart\Musedock\View::yieldSection('og_description') ?: $_siteDescription;
        $robotsDirective = trim(\Screenart\Musedock\View::yieldSection('robots', ''));

        if ($_blogPublic == '0' && empty($robotsDirective)) {
            $robotsDirective = 'noindex, nofollow';
        }

        $twitterTitle = \Screenart\Musedock\View::yieldSection('twitter_title') ?: $_siteName;
        $twitterDescription = \Screenart\Musedock\View::yieldSection('twitter_description') ?: ($_twitterDescription ?: $_siteDescription);
    @endphp

    @if($seoKeywords)
    <meta name="keywords" content="{{ $seoKeywords }}">
    @endif

    @if($_siteAuthor)
    <meta name="author" content="{{ $_siteAuthor }}">
    @endif

    {{-- Open Graph / Facebook --}}
    @if($ogTitle)
    <meta property="og:title" content="{{ $ogTitle }}">
    @endif
    @if($ogDescription)
    <meta property="og:description" content="{{ $ogDescription }}">
    @endif
    <meta property="og:url" content="{{ url($_SERVER['REQUEST_URI']) }}">
    @if($_siteName)
    <meta property="og:site_name" content="{{ $_siteName }}">
    @endif
    <meta property="og:type" content="website">
    @if($_ogImage)
    <meta property="og:image" content="{{ asset($_ogImage) }}">
    @endif

    {{-- Twitter/X Cards --}}
    <meta name="twitter:card" content="summary_large_image">
    @if($twitterTitle)
    <meta name="twitter:title" content="{{ $twitterTitle }}">
    @endif
    @if($twitterDescription)
    <meta name="twitter:description" content="{{ $twitterDescription }}">
    @endif
    @if($_twitterSite)
    <meta name="twitter:site" content="{{ $_twitterSite }}">
    @endif
    @if($_twitterImage)
    <meta name="twitter:image" content="{{ asset($_twitterImage) }}">
    @elseif($_ogImage)
    <meta name="twitter:image" content="{{ asset($_ogImage) }}">
    @endif

    {{-- Canonical y RSS --}}
    <link rel="canonical" href="{{ url($_SERVER['REQUEST_URI']) }}">
    @if($_siteName)
    <link rel="alternate" type="application/rss+xml" title="{{ $_siteName }} RSS Feed" href="{{ url('/feed') }}">
    @endif

    @if($robotsDirective)
    <meta name="robots" content="{{ $robotsDirective }}">
    @endif

    {{-- Play Bootstrap CSS Files --}}
    <link rel="stylesheet" href="{{ asset('themes/play-bootstrap/css/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('themes/play-bootstrap/css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('themes/play-bootstrap/css/lineicons.css') }}" />
    {{-- Slider (Swiper + themes) --}}
    <link rel="stylesheet" href="{{ asset('css/swiper-bundle.min.css') }}?v=1" />
    <link rel="stylesheet" href="{{ asset('themes/default/css/slider-themes.css') }}?v=2" />
    <link rel="stylesheet" href="{{ asset('themes/play-bootstrap/vendor/choices/choices.min.css') }}?v=1" />
    <link rel="stylesheet" href="{{ asset('themes/play-bootstrap/css/ud-styles.css') }}?v=7" />

    {{-- Google Fonts para tipografías del tema --}}
    @php
        $logoFont = themeOption('header.header_logo_font', 'inherit');

        // Mapa de fuentes de Google (las fuentes del sistema no necesitan carga)
        $googleFonts = [
            "'Playfair Display', serif" => 'Playfair+Display:wght@400;700',
            "'Montserrat', sans-serif" => 'Montserrat:wght@400;500;600;700',
            "'Roboto', sans-serif" => 'Roboto:wght@400;500;700',
            "'Open Sans', sans-serif" => 'Open+Sans:wght@400;600;700',
            "'Lato', sans-serif" => 'Lato:wght@400;700',
            "'Poppins', sans-serif" => 'Poppins:wght@400;500;600;700',
            "'Oswald', sans-serif" => 'Oswald:wght@400;500;600;700',
            "'Raleway', sans-serif" => 'Raleway:wght@400;500;600;700',
        ];

        // Detectar si necesitamos cargar Google Fonts
        $needsGoogleFont = isset($googleFonts[$logoFont]);
    @endphp
    @if($needsGoogleFont)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family={{ $googleFonts[$logoFont] }}&display=swap" rel="stylesheet">
    @endif

    {{-- CSS Variables dinámicas desde la base de datos (tenant-aware) --}}
    <style>
        :root {
            /* Colores principales */
            --primary-color: {{ themeOption('colors.primary', '#3056d3') }};
            --secondary-color: {{ themeOption('colors.secondary', '#13c296') }};
            --dark-color: {{ themeOption('colors.dark', '#1d2144') }};
            --light-color: {{ themeOption('colors.light', '#f8f9fa') }};

            /* Header/Cabecera */
            --header-bg-color: {{ themeOption('header.header_bg_color', '#2546b8') }};
            --header-sticky-bg-color: {!! themeOption('header.header_sticky_bg_color', 'rgba(37, 70, 184, 0.95)') !!};
            --header-logo-text-color: {{ themeOption('header.header_logo_text_color', '#ffffff') }};
            --header-logo-font: {!! themeOption('header.header_logo_font', 'inherit') !!};
            --header-link-color: {{ themeOption('header.header_link_color', '#ffffff') }};
            --header-link-hover-color: {!! themeOption('header.header_link_hover_color', 'rgba(255, 255, 255, 0.7)') !!};
            --header-cta-bg-color: {{ themeOption('header.header_cta_bg_color', '#3056d3') }};
            --header-cta-text-color: {{ themeOption('header.header_cta_text_color', '#ffffff') }};
            --header-cta-hover-color: {{ themeOption('header.header_cta_hover_color', '#2546b8') }};

            /* Footer/Pie de página */
            --footer-bg-color: {{ themeOption('footer.footer_bg_color', '#f8fafe') }};
            --footer-text-color: {{ themeOption('footer.footer_text_color', '#717171') }};
            --footer-heading-color: {{ themeOption('footer.footer_heading_color', '#212529') }};
            --footer-link-color: {{ themeOption('footer.footer_link_color', '#717171') }};
            --footer-link-hover-color: {{ themeOption('footer.footer_link_hover_color', '#3056d3') }};
            --footer-icon-color: {{ themeOption('footer.footer_icon_color', '#3056d3') }};
            --footer-border-color: {{ themeOption('footer.footer_border_color', '#e5e5e5') }};
        }
    </style>

    @stack('styles')
</head>
<body>
    {{-- Header --}}
    @include('partials.header')

    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    @include('partials.footer')

    {{-- Back to Top --}}
    <a href="javascript:void(0)" class="back-to-top">
        <i class="lni lni-chevron-up"></i>
    </a>

    {{-- Play Bootstrap JS Files --}}
    <script src="{{ asset('themes/play-bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('themes/play-bootstrap/js/wow.min.js') }}"></script>
    <script src="{{ asset('themes/play-bootstrap/vendor/choices/choices.min.js') }}?v=1"></script>
    <script src="{{ asset('themes/play-bootstrap/js/main.js') }}?v=4"></script>
    <script src="{{ asset('themes/play-bootstrap/js/choices-init.js') }}?v=1"></script>

    {{-- Detectar sliders como primer elemento para ajustar spacing --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mainEl = document.querySelector('main');
            if (!mainEl) return;

            const wrappers = [];
            const pageContentWrapper = mainEl.querySelector('.page-content-wrapper');
            const pageBody = mainEl.querySelector('.page-body');
            if (pageContentWrapper) wrappers.push(pageContentWrapper);
            if (pageBody) wrappers.push(pageBody);

            const shouldSkipTag = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'LINK', 'META']);
            const contentTags = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LI', 'BLOCKQUOTE', 'PRE', 'CODE', 'FIGURE', 'IMG', 'VIDEO', 'IFRAME', 'TABLE']);

            const isVisibleTextElement = (el) => {
                if (!el) return false;
                if (el.matches('img, video, iframe, svg, canvas')) return true;
                const text = ((el.innerText || el.textContent || '') + '').replace(/\u00a0/g, ' ').trim();
                if (text.length > 0) return true;
                return !!el.querySelector('img, video, iframe, svg, canvas');
            };

            const findFirstMeaningfulElement = (root) => {
                if (!root) return null;
                const walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT, {
                    acceptNode(node) {
                        if (shouldSkipTag.has(node.tagName)) return NodeFilter.FILTER_SKIP;
                        return NodeFilter.FILTER_ACCEPT;
                    }
                });

                let node = walker.nextNode();
                while (node) {
                    if (node.closest('.slider-full-width-wrapper, .swiper, .gallery-container')) {
                        // Prefer returning the actual slider wrapper element if this node is inside it
                        return node.closest('.slider-full-width-wrapper, .swiper, .gallery-container');
                    }

                    if (contentTags.has(node.tagName) && isVisibleTextElement(node)) return node;
                    node = walker.nextNode();
                }
                return null;
            };

            wrappers.some(function(wrapper) {
                const firstEl = findFirstMeaningfulElement(wrapper);
                if (!firstEl) return false;

                if (firstEl.classList.contains('slider-full-width-wrapper')) {
                    document.body.classList.add('has-fullwidth-slider-first');
                    const pageContentSection = firstEl.closest('.ud-page-content');
                    if (pageContentSection) pageContentSection.classList.add('ud-page-content--slider-first');
                    const possibleParagraph = firstEl.previousElementSibling;
                    if (possibleParagraph && possibleParagraph.tagName === 'P' && !isVisibleTextElement(possibleParagraph)) {
                        possibleParagraph.classList.add('ud-slider-paragraph');
                    }
                    return true;
                }

                if (firstEl.classList.contains('swiper') || firstEl.classList.contains('gallery-container')) {
                    document.body.classList.add('has-slider-first');
                    const pageContentSection = firstEl.closest('.ud-page-content');
                    if (pageContentSection) pageContentSection.classList.add('ud-page-content--slider-first');
                }

                return false;
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
