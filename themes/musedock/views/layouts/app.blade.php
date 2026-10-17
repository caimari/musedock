@php 
$_tenantId = tenant_id(); 
$_isTenant = $_tenantId !== null; 
// Cargar settings del sitio 
$_siteName = site_setting('site_name', ''); 
$_siteDescription = site_setting('site_description', ''); 
$_siteKeywords = site_setting('site_keywords', ''); 
$_siteAuthor = site_setting('site_author', ''); 
$_siteFavicon = site_setting('site_favicon', ''); 
$_ogImage = site_setting('og_image', ''); 
$_twitterSite = site_setting('twitter_site', ''); 
$_twitterImage = site_setting('twitter_image', ''); 
$_twitterDescription = site_setting('twitter_description', ''); 
$_contactEmail = site_setting('contact_email', ''); 
$_contactPhone = site_setting('contact_phone', ''); 
$_contactAddress = site_setting('contact_address', '');
@endphp<!doctype html>
<html lang="{{ detectLanguage() }}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  {{-- Título --}}
  <title>{{ \Screenart\Musedock\View::yieldSection('title') ?: $_siteName }}</title>
  
  {{-- Meta description --}}
  @php 
  $metaDescription = \Screenart\Musedock\View::yieldSection('description') ?: $_siteDescription; 
  @endphp
  @if($metaDescription)
  <meta name="description" content="{{ $metaDescription }}">
  @endif
  
  {{-- Favicon --}}
  @if($_siteFavicon)
  <link rel="icon" type="image/x-icon" href="{{ asset(ltrim($_siteFavicon, '/')) }}">
  @else
  <link rel="shortcut icon" href="{{ asset('img/favicon.png') }}">
  @endif
  
  {{-- Meta keywords --}}
  @php 
  $seoKeywords = \Screenart\Musedock\View::yieldSection('keywords') ?: $_siteKeywords; 
  @endphp
  @if($seoKeywords)
  <meta name="keywords" content="{{ $seoKeywords }}">
  @endif
  
  {{-- Author --}}
  @if($_siteAuthor)
  <meta name="author" content="{{ $_siteAuthor }}">
  @endif
  
  {{-- Open Graph (Facebook, LinkedIn, WhatsApp, etc.) --}}
  @php
  $ogTitle = \Screenart\Musedock\View::yieldSection('og_title') ?: $_siteName;
  $ogDescription = \Screenart\Musedock\View::yieldSection('og_description') ?: $_siteDescription;
  $ogImage = trim(\Screenart\Musedock\View::yieldSection('og_image', ''));
  $ogType = trim(\Screenart\Musedock\View::yieldSection('og_type', '')) ?: 'website';
  $canonicalUrl = trim(\Screenart\Musedock\View::yieldSection('canonical_url', ''));
  @endphp
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
  <meta property="og:type" content="{{ $ogType }}">
  @if($ogImage)
  <meta property="og:image" content="{{ $ogImage }}">
  @elseif($_ogImage)
  <meta property="og:image" content="{{ asset($_ogImage) }}">
  @endif
  @if($canonicalUrl)
  <link rel="canonical" href="{{ $canonicalUrl }}">
  @else
  <link rel="canonical" href="{{ seo_language_canonical() ?: url($_SERVER['REQUEST_URI']) }}">
  @endif
  {!! seo_language_tags() !!}

  {{-- Twitter/X Cards --}}
  <meta name="twitter:card" content="summary_large_image">
  @php
  $twitterTitle = \Screenart\Musedock\View::yieldSection('twitter_title') ?: $_siteName;
  $twitterDescription = \Screenart\Musedock\View::yieldSection('twitter_description') ?: ($_twitterDescription ?: $_siteDescription);
  $twitterImage = trim(\Screenart\Musedock\View::yieldSection('twitter_image', ''));
  @endphp
  @if($twitterTitle)
  <meta name="twitter:title" content="{{ $twitterTitle }}">
  @endif
  @if($twitterDescription)
  <meta name="twitter:description" content="{{ $twitterDescription }}">
  @endif
  @if($_twitterSite)
  <meta name="twitter:site" content="{{ $_twitterSite }}">
  @endif
  @if($twitterImage)
  <meta name="twitter:image" content="{{ $twitterImage }}">
  @elseif($_twitterImage)
  <meta name="twitter:image" content="{{ asset($_twitterImage) }}">
  @elseif($_ogImage)
  <meta name="twitter:image" content="{{ asset($_ogImage) }}">
  @endif
  
  {{-- RSS Feed --}}
  @if($_siteName)
  <link rel="alternate" type="application/rss+xml" title="{{ $_siteName }} RSS Feed" href="{{ url('/feed') }}">
  @endif
  
  {{-- Preload de la fuente de iconos: debe coincidir EXACTAMENTE con la URL usada en font-awesome.min.css --}}
  <link rel="preload" href="{{ url('assets/themes/musedock/fonts/fontawesome-webfont.woff2') }}?v=4.6.3.1" as="font" type="font/woff2" crossorigin>

  {{-- CSS del tema.
       Versión = CMS + fecha del fichero: editar un asset cambia su URL (Caddy los sirve
       "immutable" 1 año; con solo la versión del CMS los cambios no llegaban al navegador).
       js_composer.min.css (WPBakery, 462 KB) retirado: ninguna clase de la web usa sus reglas. --}}
  @php $themeAsset = fn(string $f) => asset("themes/musedock/{$f}") . '?v=' . cms_version() . '.' . (@filemtime(public_path("assets/themes/musedock/{$f}")) ?: 0); @endphp
  <link rel="stylesheet" href="{{ $themeAsset('css/classic-themes.min.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/css-extra1.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/css-extra2.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/font-awesome.min.css') }}">
  {{-- El tema trae Font Awesome 4 (clases «fa fa-x»), pero algunas vistas (blog, panel de clientes,
       posts relacionados) usan la sintaxis de Font Awesome 5 («far/fas/fab fa-x») y salían cuadrados
       vacíos. Con esto usan la misma fuente; vale para todo icono que exista en la versión 4. --}}
  <style>.far,.fas,.fab{display:inline-block;font:normal normal normal 14px/1 FontAwesome;font-size:inherit;text-rendering:auto;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}</style>
  <link rel="stylesheet" href="{{ $themeAsset('css/plugins.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/animated.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/owl.carousel.min.css') }}">
  {{-- styles-lite.css = styles(1).css sin las reglas que la web no usa (PurgeCSS + csso sin reestructurar;
       274→142 KB). Validado con capturas píxel a píxel y estilos calculados (2026-10-13).
       Si se añaden plantillas con clases nuevas de styles(1).css hay que regenerarlo; ?css=full carga el original. --}}
  @if(($_GET['css'] ?? '') === 'full')
  <link rel="stylesheet" href="{{ $themeAsset('css/styles(1).css') }}">
  @else
  <link rel="stylesheet" href="{{ $themeAsset('css/styles-lite.css') }}">
  @endif
  <link rel="stylesheet" href="{{ $themeAsset('css/style.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/colors.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/responsive.css') }}">
  <link rel="stylesheet" href="{{ $themeAsset('css/simple-slider.css') }}">

  {{-- Nice Select 2 --}}
  <link rel="stylesheet" href="{{ asset('vendor/nice-select2/nice-select2.min.css') }}?v={{ cms_version() }}">

  {{-- Cookie consent (RGPD) --}}
  @if(site_setting('cookies_enabled', '1') == '1')
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/cookie-consent.css') }}?v={{ file_exists(public_path('assets/themes/musedock/css/cookie-consent.css')) ? filemtime(public_path('assets/themes/musedock/css/cookie-consent.css')) : cms_version() }}">
  @endif

  {{-- CSS Variables dinámicas --}}
  <style>
    :root {
      /* Defaults alineados con Zipprich (styles(1).css), ajustados a contraste WCAG AA (≥4.5:1):
         gris #808a95→#5f6873 (válido sobre blanco y sobre #eef3f8), pie #595f7c→#8a90ab sobre #0C112A */
      --topbar-bg-color: {{ themeOption('topbar.topbar_bg_color', '#ffffff') }};
      --topbar-text-color: {{ themeOption('topbar.topbar_text_color', '#5f6873') }};
      --header-bg-color: {{ themeOption('header.header_bg_color', '#ffffff') }};
      --header-logo-text-color: {{ themeOption('header.header_logo_text_color', '#243141') }};
      --header-link-color: {{ themeOption('header.header_link_color', '#5f6873') }};
      --header-link-hover-color: {{ themeOption('header.header_link_hover_color', '#243141') }};
      --footer-bg-color: {{ themeOption('footer.footer_bg_color', '#0C112A') }};
      --footer-text-color: {{ themeOption('footer.footer_text_color', '#8a90ab') }};
      --footer-heading-color: {{ themeOption('footer.footer_heading_color', '#ffffff') }};
      --footer-link-color: {{ themeOption('footer.footer_link_color', '#8a90ab') }};
      --footer-link-hover-color: {{ themeOption('footer.footer_link_hover_color', '#ffffff') }};
      --footer-icon-color: {{ themeOption('footer.footer_icon_color', '#ffffff') }};
      --footer-border-color: {{ themeOption('footer.footer_border_color', '#181d35') }};
    }

    /* Botones verde de marca (#86c401): texto oscuro para contraste AA (6.2:1; blanco daba 2.1:1).
       En hover el fondo pasa a azul/navy → texto blanco. */
    .ziph-headlogin_btn a.btn,
    .ziph-domainsrch_form input[type="submit"],
    .simple-slide .slide-button a { color: #243141 !important; }
    .ziph-headlogin_btn a.btn:hover, .ziph-headlogin_btn a.btn:focus,
    .ziph-domainsrch_form input[type="submit"]:hover,
    .ziph-domainsrch_form input[type="submit"]:focus { color: #fff !important; }
    /* Newsletter del pie: azul #267AE9 (4.2:1) → #2372db; en hover pasa a verde → texto oscuro */
    .ziph-ftrform_warp button[type="submit"],
    .ziph-ftrform_warp input[type="submit"] { background-color: #2372db; }
    .ziph-ftrform_warp button[type="submit"]:hover, .ziph-ftrform_warp button[type="submit"]:focus,
    .ziph-ftrform_warp input[type="submit"]:hover, .ziph-ftrform_warp input[type="submit"]:focus { color: #243141; }

    /* Grises fijos de styles(1).css (#808a95 y #595f7c, contraste < 4.5:1) → variables del tema,
       que siguen siendo personalizables desde Ajustes */
    .ziph-head_info a { color: var(--topbar-text-color); }
    .ziph-footer_top a,
    .ziph-footer_widget ul li, .ziph-footer_widget ul li a,
    .ziph-footrwidget_loc, .ziph-footrwidget_loc a,
    .ziph-footer_widget.vt-text-widget { color: var(--footer-link-color); }
    .ziph-footer_widget .tagcloud a { color: var(--footer-link-color); border-color: var(--footer-link-color); }

    /* Navbar con tono gris-azulado (home + páginas secundarias) */
    .ziph-header_navigation {
      background-color: #eef3f8;
      border-bottom: 1px solid rgba(36, 49, 65, 0.08);
    }
    /* Embeds de video responsive */
    .post-content iframe, article iframe { max-width: 100%; border: 0; }
    .post-content iframe[src*="youtube"], .post-content iframe[src*="vimeo"],
    article iframe[src*="youtube"], article iframe[src*="vimeo"] { width: 100%; aspect-ratio: 16 / 9; height: auto; }
    .post-content video, article video { max-width: 100%; height: auto; }
    /* Lineas separadoras mas visibles */
    main hr {
      margin: 2rem 0 !important;
      border: 0 !important;
      border-top: 5px solid #bbb !important;
      height: 0 !important;
      opacity: 1 !important;
    }
  </style>

  {{-- Additional CSS for theme customization --}}
  @stack('styles')
</head>
@php
  $isHomePage = request()->is('/') || request()->is('home');
@endphp
<body class="ziph-page {{ $isHomePage ? 'ziph-is-home' : 'ziph-is-inner' }}">
  <div id="zipprich-wrapper" class="ziph_page">
    @include('partials.header')
    
    <main>
      @yield('content')
    </main>
    
    @include('partials.footer')
  </div>

  {{-- Scripts del tema --}}
  <script src="{{ $themeAsset('js/jquery.min.js') }}"></script>
  <script src="{{ $themeAsset('js/bootstrap.min.js') }}"></script>
  <script src="{{ $themeAsset('js/plugins.js') }}"></script>
  <script src="{{ $themeAsset('js/waypoints.min.js') }}"></script>
  <script src="{{ $themeAsset('js/counterup.min.js') }}"></script>
  <script src="{{ $themeAsset('js/owl.carousel.min.js') }}"></script>
  <script src="{{ $themeAsset('js/slimmenu.min.js') }}"></script>
  <script src="{{ $themeAsset('js/fitvids.min.js') }}"></script>
  <script src="{{ $themeAsset('js/sticky.header.min.js') }}"></script>
  <script src="{{ $themeAsset('js/matchHeight.min.js') }}"></script>
  <script src="{{ $themeAsset('js/scripts.js') }}"></script>
  <script src="{{ $themeAsset('js/simple-slider.js') }}"></script>

  {{-- Nice Select 2 --}}
  <script src="{{ asset('vendor/nice-select2/nice-select2.min.js') }}?v={{ cms_version() }}"></script>

  @stack('scripts')
  
  {{-- El slider se auto-inicializa en simple-slider.js (clase SimpleSlider, sin jQuery) --}}

  {{-- Inicializar Nice Select 2 para el selector de idiomas del footer --}}
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var footerLangSelect = document.getElementById('footer-language-select');
      if (footerLangSelect && typeof NiceSelect !== 'undefined') {
        NiceSelect.bind(footerLangSelect, {
          searchable: false,
          placeholder: 'select'
        });
      }
    });
  </script>

  {{-- Refresco de token CSRF: las páginas servidas desde html-cache llevan un token de otra sesión --}}
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    var inputs = document.querySelectorAll('input[name="_csrf"], input[name="_token"]');
    if (!inputs.length) return;
    fetch('/csrf-token', { credentials: 'same-origin', cache: 'no-store' })
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d && d.token) inputs.forEach(function(i) { i.value = d.token; }); })
      .catch(function() {});
  });
  </script>

  {{-- ===== COOKIES (RGPD - doble opt-in, banner + preferencias) ===== --}}
  @if(site_setting('cookies_enabled', '1') == '1')
    @php
      $cookieLayout = site_setting('cookies_banner_layout', 'card');
      $cookieBg     = site_setting('cookies_bg_color', '#ffffff');
      $cookieText   = site_setting('cookies_text_color', '#333333');
      // Defaults con contraste AA para texto blanco (#4CAF50 daba 2.8:1, #f44336 3.7:1)
      $cookieBtnAccept = site_setting('cookies_btn_accept_bg', '#2e7d32');
      $cookieBtnReject = site_setting('cookies_btn_reject_bg', '#d32f2f');

      $__mdCookiesUrl = function_exists('legal_page_url')
        ? legal_page_url(['cookie-policy', 'cookies', 'politica-de-cookies', 'politica-cookies'], 'cookie-policy')
        : '/p/cookie-policy';
      $__mdTermsUrl = function_exists('legal_page_url')
        ? legal_page_url(['terms-and-conditions', 'terminos-y-condiciones', 'terminos', 'terms', 'condiciones-de-uso'], 'terms-and-conditions')
        : '/p/terms-and-conditions';
    @endphp
    <div id="cookie-consent-popup" class="cookie-consent-popup cookie-layout-{{ $cookieLayout }}" style="display: none; --cookie-bg: {{ $cookieBg }}; --cookie-text: {{ $cookieText }}; --cookie-btn-accept: {{ $cookieBtnAccept }}; --cookie-btn-reject: {{ $cookieBtnReject }};">
      <div class="cookie-popup-content">
        <h4 aria-level="2">{{ __('cookies.title') }}</h4>
        <p>{{ __('cookies.text') }}</p>
        <div class="cookie-popup-actions">
          <button id="cookie-manage-prefs" class="cookie-btn cookie-btn-manage">{{ __('cookies.manage_preferences') }}</button>
          <button id="cookie-reject-all" class="cookie-btn cookie-btn-reject">{{ __('cookies.reject_all') }}</button>
          <button id="cookie-accept-all" class="cookie-btn cookie-btn-accept">{{ __('cookies.accept_all') }}</button>
        </div>
        <div class="cookie-popup-links">
          <a href="{{ $__mdCookiesUrl }}">{{ __('cookies.policy_link') }}</a>
          <a href="{{ $__mdTermsUrl }}">{{ __('cookies.terms_link') }}</a>
        </div>
      </div>
    </div>

    <div id="cookie-preferences-modal" class="cookie-preferences-modal" style="display: none; --cookie-bg: {{ $cookieBg }}; --cookie-text: {{ $cookieText }}; --cookie-btn-accept: {{ $cookieBtnAccept }}; --cookie-btn-reject: {{ $cookieBtnReject }};">
      <div class="modal-content">
        <div class="modal-header">
          <h3 aria-level="2">{{ __('cookies.modal_title') }}</h3>
          <button id="cookie-modal-close" class="modal-close-btn">×</button>
        </div>
        <div class="modal-body">
          <p>{{ __('cookies.modal_intro') }}</p>
          <div class="cookie-category">
            <div class="category-header">
              <h4 aria-level="3">{{ __('cookies.cat_necessary_title') }}</h4>
              <label class="switch always-on">
                <input type="checkbox" checked disabled>
                <span class="slider round"></span>
              </label>
            </div>
            <p>{{ __('cookies.cat_necessary_desc') }}</p>
          </div>
          <div class="cookie-category">
            <div class="category-header">
              <h4 aria-level="3">{{ __('cookies.cat_analytics_title') ?: 'Cookies de Analítica' }}</h4>
              <label class="switch">
                <input type="checkbox" id="cookie-pref-analytics">
                <span class="slider round"></span>
              </label>
            </div>
            <p>{{ __('cookies.cat_analytics_desc') ?: 'Nos permiten medir el tráfico y analizar tu comportamiento para mejorar nuestro servicio.' }}</p>
          </div>
        </div>
        <div class="modal-footer">
          <button id="cookie-modal-accept-all" class="cookie-btn cookie-btn-accept">{{ __('cookies.accept_all') }}</button>
          <button id="cookie-modal-save" class="cookie-btn cookie-btn-manage">{{ __('cookies.save_preferences') }}</button>
          <button id="cookie-modal-reject-all" class="cookie-btn cookie-btn-reject">{{ __('cookies.reject_all') }}</button>
        </div>
      </div>
    </div>

    <script src="{{ asset('js/analytics.js') }}"></script>
    <script src="{{ asset('themes/musedock/js/cookie-consent.js') }}?v={{ file_exists(public_path('assets/themes/musedock/js/cookie-consent.js')) ? filemtime(public_path('assets/themes/musedock/js/cookie-consent.js')) : cms_version() }}"></script>
  @endif
</body>
</html>
