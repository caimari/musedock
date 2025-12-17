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

    {{-- Título dinámico - Sin fallback a MuseDock para tenants --}}
    <title>{{ \Screenart\Musedock\View::yieldSection('title') ?: $_siteName }}</title>

    {{-- Metas dinámicas - Solo usa datos del tenant/cms actual, sin fallback cruzado --}}
    @php
        $metaDescription = \Screenart\Musedock\View::yieldSection('description') ?: $_siteDescription;
        $metaAuthor = $_siteAuthor;
    @endphp
    @if($metaDescription)
    <meta name="description" content="{{ $metaDescription }}">
    @endif
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
        $ogTitle = \Screenart\Musedock\View::yieldSection('og_title') ?: $_siteName;
        $ogDescription = \Screenart\Musedock\View::yieldSection('og_description') ?: $_siteDescription;
        $siteName = $_siteName;
        $robotsDirective = trim(\Screenart\Musedock\View::yieldSection('robots', ''));

        // Verificar setting de visibilidad en buscadores
        if ($_blogPublic == '0' && empty($robotsDirective)) {
            $robotsDirective = 'noindex, nofollow';
        }

        $twitterTitle = \Screenart\Musedock\View::yieldSection('twitter_title') ?: $_siteName;
        $twitterDescription = \Screenart\Musedock\View::yieldSection('twitter_description') ?: ($_twitterDescription ?: $_siteDescription);
    @endphp
    @if($seoKeywords)
    <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    @if($ogTitle)
    <meta property="og:title" content="{{ $ogTitle }}">
    @endif
    @if($ogDescription)
    <meta property="og:description" content="{{ $ogDescription }}">
    @endif
    <meta property="og:url" content="{{ url($_SERVER['REQUEST_URI']) }}">
    @if($siteName)
    <meta property="og:site_name" content="{{ $siteName }}">
    @endif
    <meta property="og:type" content="website">
    @if($_ogImage)
    <meta property="og:image" content="{{ public_file_url($_ogImage) }}">
    @endif
    <link rel="canonical" href="{{ url($_SERVER['REQUEST_URI']) }}">
    @if($_siteName)
    <link rel="alternate" type="application/rss+xml" title="{{ $_siteName }} RSS Feed" href="{{ url('/feed') }}">
    @endif
    @if($robotsDirective)
    <meta name="robots" content="{{ $robotsDirective }}">
    @endif
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
    <meta name="twitter:image" content="{{ public_file_url($_twitterImage) }}">
    @elseif($_ogImage)
    <meta name="twitter:image" content="{{ public_file_url($_ogImage) }}">
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
        ];

        // Detectar si necesitamos cargar Google Fonts
        $needsGoogleFont = isset($googleFonts[$logoFont]);
    @endphp
    @if($needsGoogleFont)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family={{ $googleFonts[$logoFont] }}&display=swap" rel="stylesheet">
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

    {{-- CSS Custom del tenant - DEBE ir después de template.css para sobrescribir --}}
    <link rel="stylesheet" href="{{ asset('themes/default/css/custom.css') }}?v={{ time() }}">

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
        --header-bg-color: {{ themeOption('header.header_bg_color', '#f8f9fa') }};
        --header-logo-text-color: {{ themeOption('header.header_logo_text_color', '#1a2a40') }};
        --header-logo-font: {!! themeOption('header.header_logo_font', 'inherit') !!};
        --header-link-color: {{ themeOption('header.header_link_color', '#333333') }};
        --header-link-hover-color: {{ themeOption('header.header_link_hover_color', '#ff5e15') }};
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
        /* Scroll to top button */
        --scroll-to-top-bg-color: {{ themeOption('scroll_to_top.scroll_to_top_bg_color', '#ff5e15') }};
        --scroll-to-top-icon-color: {{ themeOption('scroll_to_top.scroll_to_top_icon_color', '#ffffff') }};
        --scroll-to-top-hover-bg-color: {{ themeOption('scroll_to_top.scroll_to_top_hover_bg_color', '#e54c08') }};
    }

    /* ===== Estilos del Header usando CSS Variables ===== */
    /* Logo texto */
    .header-logo span,
    .header-logo .site-title {
        color: var(--header-logo-text-color) !important;
        font-family: var(--header-logo-font) !important;
    }

    /* Enlaces del menú de navegación */
    .main-navigation a,
    .header-menu a {
        color: var(--header-link-color) !important;
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
        margin-bottom: 1rem !important;
        line-height: 1.7;
    }
    .page-content p:empty, .page-body p:empty, .content p:empty, article p:empty {
        display: none !important;
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
        border: 0;
        border-top: 2px solid #ddd !important;
        height: 0;
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

    /* Asegurar que body y html no tengan scroll horizontal */
    html {
        overflow-x: hidden !important;
        overflow-y: auto !important;
    }

    body {
        overflow-x: hidden !important;
        overflow-y: auto !important;
    }

    /* Cuando el menú móvil está abierto, bloquear scroll del body */
    body.mobile-menu-open {
        overflow: hidden !important;
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

/* ================================================ */
/* === Estilos para el Topbar (.header-top) === */
/* ================================================ */

.header-top {
    background-color: var(--topbar-bg-color, #1a2a40); /* Color de fondo dinámico */
    padding: 10px 0;        /* Espaciado vertical interno */
    color: var(--topbar-text-color, white); /* Color de texto dinámico */
    font-size: 14px;        /* Tamaño de fuente base para el topbar */
    line-height: 1.5;       /* Altura de línea para mejor legibilidad */
    /* Las clases d-none d-lg-block se encargan de mostrar/ocultar.
       Si Bootstrap no funcionara correctamente, podrías añadir:
       display: none;
       @media (min-width: 992px) { display: block; }
    */
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
    padding: 15px 0;
    position: relative;
    background-color: var(--header-bg-color, #f8f9fa); /* Color de fondo dinámico */
    border-bottom: 1px solid #eee; /* Borde sutil */
    z-index: 999; /* Asegura que esté sobre otro contenido */
}

/* Contenedor principal dentro del header */
.container-fluid { /* O tu clase de contenedor principal (.container) */
    max-width: 1200px; /* Ajusta el ancho máximo según tu diseño */
    margin: 0 auto;
    padding: 0 15px; /* Espaciado lateral estándar */
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
    max-height: 45px; /* Ajusta la altura máxima de tu logo */
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
    display: none; /* Oculto por defecto */
    position: absolute;
    top: 100%; /* Justo debajo del ítem padre */
    left: 0;
    background-color: white;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    min-width: 180px; /* Ancho mínimo del submenú */
    z-index: 1000; /* Sobre otros elementos */
    list-style: none;
    padding: 0; /* Evitar espacio extra encima/debajo de la primera opción */
    border-radius: 4px;
    border: 1px solid #eee; /* Borde sutil */
}
.main-navigation li:hover > .submenu { /* Mostrar al hacer hover sobre el li padre */
    display: block;
}
.main-navigation .submenu li {
     margin: 0; /* Sin margen extra */
}
.main-navigation .submenu a {
    padding: 8px 15px; /* Padding de los ítems del submenú */
    font-size: 14px;
    white-space: nowrap;
    color: #555; /* Color diferente para submenú */
}
.main-navigation .submenu a:hover {
    background-color: #f5f5f5; /* Fondo al hacer hover */
    color: #ff5e15; /* Color de texto al hacer hover */
}

/* Contenedor de las acciones (botón + idiomas + toggle) */
.header-actions {
    display: flex;
    align-items: center;
    /* Espacio entre los elementos de acción (botón, idioma, toggle) */
    gap: 15px;
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
    color: #ff5e15; /* Tu color naranja */
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

    /* Ocultar botón de acción y selector de idioma de escritorio */
    .header-actions .header-btn,
    .header-actions .lang-select {
        display: none;
    }

    /* Mostrar el botón hamburguesa */
    .menu-toggle {
        display: block;
    }

    /* Ajustar el contenedor derecho si es necesario (puede no ser necesario) */
    .header-right-content {
        gap: 0; /* Eliminar gap si solo queda el toggle */
        /* Asegurar que no ocupe espacio extra */
        /* flex-grow: 0; */ /* Descomentar si causa problemas de layout */
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
}
.mobile-nav a:hover {
    color: #ff5e15;
}

/* Estilos para submenús móviles (si los implementas con JS para desplegar) */
.mobile-submenu {
    padding-left: 15px; /* Indentación */
    list-style: none;
    /* Inicialmente oculto si usas acordeón */
    /* display: none; */
    /* max-height: 0; */
    /* overflow: hidden; */
    /* transition: max-height 0.3s ease-out; */
}
/* .mobile-submenu.active { */
    /* display: block; */
    /* max-height: 500px; */ /* Un valor grande */
/* } */
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


    {{-- Estilos adicionales --}}
    @stack('styles')
</head>
<body>
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

@if($topbarEnabled)
    <div class="header-top top-bg d-none d-lg-block">
   <div class="container">
       <div class="row d-flex justify-content-between align-items-center">
           <div class="col-xl-6 col-lg-6">
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
           </div>
           <div class="col-xl-6 col-lg-6">
               <div class="header-info-right text-right">
               <ul class="header-social d-flex justify-content-end">
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
               </div>
           </div>
       </div>
   </div>
</div>
@endif 
                


@php
    // Obtener opción sticky del header
    $headerSticky = themeOption('header.header_sticky', false);
@endphp

    <header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }}" id="main-header">
    <div class="container">
        <div class="header-container">
            <!-- Logo (Alineado a la Izquierda) -->
            <div class="header-logo">
                <a href="{{ url('/') }}" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
                    @php
                        // Configuraciones de logo y título
                        $showLogo = site_setting('show_logo', '1') === '1';
                        $showTitle = site_setting('show_title', '0') === '1';
                        $siteName = site_setting('site_name', '');
                        $logoPath = site_setting('site_logo', '');
                        $defaultLogo = asset('themes/default/img/logo/logo.png');
                    @endphp

                    {{-- Mostrar logo si está habilitado --}}
                    @if($showLogo)
                        <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                             alt="{{ $siteName }}"
                             style="max-height: 50px; width: auto;"
                             onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                    @endif

                    {{-- Mostrar título si está habilitado --}}
                    @if($showTitle)
                        @php
                            $logoTextColor = themeOption('header.header_logo_text_color', '#1a2a40');
                            $logoFontFamily = themeOption('header.header_logo_font', 'inherit');
                        @endphp
                        <span class="site-title" style="font-size: 24px; font-weight: bold; color: {{ $logoTextColor }}; font-family: {!! $logoFontFamily !!};">
                            {{ $siteName }}
                        </span>
                    @endif
                </a>
            </div>

            <!-- Contenido Derecho: Agrupa Menu + Acciones (Alineado a la Derecha) -->
            <div class="header-right-content">
                <!-- Menú Principal (escritorio) -->
                <div class="header-menu">
                    <nav class="main-navigation">
                        @custommenu('nav', null, [
                            'ul_id' => 'main-menu',
                            'nav_class' => '', // Clases CSS se aplican via .main-navigation ul
                            'li_class' => '', // Clases CSS se aplican via .main-navigation li
                            'a_class' => '',  // Clases CSS se aplican via .main-navigation a
                            'submenu_class' => 'submenu' // Clase para submenús desplegables
                        ])
                    </nav>
                </div>

                <!-- Acciones (botón + idiomas + toggle móvil) -->
                @php
                    // Obtener opciones del tema para header
                    $ctaEnabled = themeOption('header.header_cta_enabled', false);
                    // Obtener texto del botón según el idioma actual
                    $currentLangCta = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
                    $ctaTextEs = themeOption('header.header_cta_text_es', __('header.login_button'));
                    $ctaTextEn = themeOption('header.header_cta_text_en', 'Login');
                    $ctaText = ($currentLangCta === 'en') ? $ctaTextEn : $ctaTextEs;
                    $ctaUrl = themeOption('header.header_cta_url', '#');
                    $langSelectorEnabled = themeOption('header.header_lang_selector_enabled', true);
                @endphp

                <div class="header-actions">
                    @if($ctaEnabled)
                        {{-- Botón CTA --}}
                        <a href="{{ $ctaUrl }}" class="header-btn">
                            {{ $ctaText }}
                        </a>
                    @endif

                    @if($langSelectorEnabled)
                        {{-- Selector de Idioma Escritorio --}}
                        @php
                            // --- Lógica para obtener idiomas ordenados ---
                            try {
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
                            } catch (\Exception $e) {
                                // Fallback por si falla la DB
                                $languages = [['code' => 'es', 'name' => 'Español'], ['code' => 'en', 'name' => 'English']];
                            }
                            $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? setting('language', 'es'));
                            $showLangSelector = count($languages) > 1;
                        @endphp

                        @if($showLangSelector)
                        <div class="lang-select">
                            <button type="button" class="lang-btn">
                                {{ strtoupper($currentLang) }}
                            </button>

                            <div class="lang-dropdown">
                                @foreach($languages as $lang)
                                    <a href="?lang={{ $lang['code'] }}" class="lang-option {{ $currentLang == $lang['code'] ? 'active' : '' }}">
                                        {{-- Muestra el nombre si existe, sino el código --}}
                                        {{ $lang['name'] ?? strtoupper($lang['code']) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif

                    <!-- Toggle único para móvil (se mostrará/ocultará con CSS) -->
                    <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>


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
        <nav>
             {{-- Renderiza tu menú móvil aquí. Asegúrate que @custommenu funciona correctamente --}}
             @custommenu('nav', null, [
                 'ul_id' => 'mobile-menu-items',
                 'nav_class' => 'mobile-nav', // Clase para el UL móvil
                 'li_class' => 'mobile-item', // Clase para LI móvil (opcional)
                 'a_class' => 'mobile-link', // Clase para A móvil (opcional)
                 'submenu_class' => 'mobile-submenu' // Clase para submenús móviles
             ])
        </nav>

        @if($showLangSelector)
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
        // body.style.overflow = 'hidden'; // Alternativa directa (menos preferida que la clase)
    }
}

// Función para cerrar el menú
function closeMobileMenu() {
    if (mobileMenu && overlay && body) {
        mobileMenu.classList.remove('active');
        overlay.classList.remove('active');
        body.classList.remove('mobile-menu-open'); // Quita clase del body
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
    let lastScroll = 0;
    const headerHeight = header.offsetHeight;

    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

        if (currentScroll > headerHeight) {
            header.classList.add('sticky');
            document.body.classList.add('has-sticky-header');
        } else {
            header.classList.remove('sticky');
            document.body.classList.remove('has-sticky-header');
        }

        lastScroll = currentScroll;
    });
}

}); // Fin de DOMContentLoaded
</script>

    {{-- Contenedor principal para el contenido yield --}}
    <main>
        @yield('content')
    </main>

    @include('partials.footer')
    
    {{-- COOKIES  --}}
    @if(site_setting('cookies_enabled', '1') == '1')
        <!-- ===== Cookie Consent Popup (Card Style) ===== -->
        <div id="cookie-consent-popup" class="cookie-consent-popup" style="display: none;">
        <div class="cookie-popup-content">
            <h4>{{ __('cookies.title') }}</h4>
            <p>{{ __('cookies.text') }}</p>
            <div class="cookie-popup-actions">
                <button id="cookie-manage-prefs" class="cookie-btn cookie-btn-manage">{{ __('cookies.manage_preferences') }}</button>
                <button id="cookie-reject-all" class="cookie-btn cookie-btn-reject">{{ __('cookies.reject_all') }}</button>
                <button id="cookie-accept-all" class="cookie-btn cookie-btn-accept">{{ __('cookies.accept_all') }}</button>
            </div>
            <div class="cookie-popup-links">
                <a href="{{ url(site_setting('cookies_policy_url', '/p/cookie-policy')) }}">{{ __('cookies.policy_link') }}</a>
                <a href="{{ url(site_setting('cookies_terms_url', '/p/terms-and-conditions')) }}">{{ __('cookies.terms_link') }}</a>
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
    
    // Definir la URL correctamente sin doble barra
    $baseUrl = rtrim(url('/'), '/'); // Elimina la barra final si existe
    $jsPath = "/assets/themes/{$themeSlug}/js/custom.js";
    $fullJsUrl = $baseUrl . $jsPath;
    
    // Verificar si el archivo existe físicamente
    $fullPublicPath = public_path("assets/themes/{$themeSlug}/js/custom.js");
    $customJsExists = file_exists($fullPublicPath);
    
    // Timestamp para cache busting
    $timestampPath = public_path("assets/themes/{$themeSlug}/js/custom.js.timestamp");
    $jsTimestamp = file_exists($timestampPath) ? file_get_contents($timestampPath) : time();
@endphp

@if($customJsExists)
    <script src="{{ $fullJsUrl }}?t={{ $jsTimestamp }}"></script>
@endif

<script>
// Forzar lightbox en enlaces con data-lightbox o clase .lightbox aunque no haya galería previa
document.addEventListener('DOMContentLoaded', function() {
    // Si ya existe lightbox.js (galleries) no aplicar Magnific para evitar duplicados
    if (typeof jQuery === 'undefined' || !jQuery.fn.magnificPopup || window.lightbox || window.musedockGalleryLightbox) {
        return;
    }
    var $ = jQuery;
    var $links = $('a[data-lightbox]:not([data-lightbox^="gallery-"]), a.lightbox').not('.md-mfp-bound');
    if ($links.length) {
        $links.magnificPopup({
            type: 'image',
            gallery: {
                enabled: true
            }
        });
        $links.addClass('md-mfp-bound');
    }
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


{{-- Scripts adicionales  --}}
@stack('scripts')

</body>
</html>
