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
<html lang="{{ site_setting('language', 'es') }}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  
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
  <link rel="canonical" href="{{ url($_SERVER['REQUEST_URI']) }}">
  @endif

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

  {{-- CSS del tema --}}
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/classic-themes.min.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/css-extra1.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/css-extra2.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/js_composer.min.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/bootstrap.min.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/font-awesome.min.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/plugins.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/animated.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/owl.carousel.min.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/styles(1).css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/style.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/colors.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/responsive.css') }}?v={{ cms_version() }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/simple-slider.css') }}?v={{ cms_version() }}">

  {{-- Nice Select 2 --}}
  <link rel="stylesheet" href="{{ asset('vendor/nice-select2/nice-select2.min.css') }}?v={{ cms_version() }}">

  {{-- Cookie consent (RGPD) --}}
  @if(site_setting('cookies_enabled', '1') == '1')
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/cookie-consent.css') }}?v={{ file_exists(public_path('assets/themes/musedock/css/cookie-consent.css')) ? filemtime(public_path('assets/themes/musedock/css/cookie-consent.css')) : cms_version() }}">
  @endif

  {{-- CSS Variables dinámicas --}}
  <style>
    :root {
      /* Defaults alineados con Zipprich (styles(1).css) */
      --topbar-bg-color: {{ themeOption('topbar.topbar_bg_color', '#ffffff') }};
      --topbar-text-color: {{ themeOption('topbar.topbar_text_color', '#808a95') }};
      --header-bg-color: {{ themeOption('header.header_bg_color', '#ffffff') }};
      --header-logo-text-color: {{ themeOption('header.header_logo_text_color', '#243141') }};
      --header-link-color: {{ themeOption('header.header_link_color', '#808a95') }};
      --header-link-hover-color: {{ themeOption('header.header_link_hover_color', '#243141') }};
      --footer-bg-color: {{ themeOption('footer.footer_bg_color', '#0C112A') }};
      --footer-text-color: {{ themeOption('footer.footer_text_color', '#595f7c') }};
      --footer-heading-color: {{ themeOption('footer.footer_heading_color', '#ffffff') }};
      --footer-link-color: {{ themeOption('footer.footer_link_color', '#595f7c') }};
      --footer-link-hover-color: {{ themeOption('footer.footer_link_hover_color', '#ffffff') }};
      --footer-icon-color: {{ themeOption('footer.footer_icon_color', '#ffffff') }};
      --footer-border-color: {{ themeOption('footer.footer_border_color', '#181d35') }};
    }

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
  <script src="{{ asset('themes/musedock/js/jquery.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/bootstrap.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/plugins.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/waypoints.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/counterup.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/owl.carousel.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/slimmenu.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/fitvids.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/sticky.header.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/matchHeight.min.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/scripts.js') }}?v={{ cms_version() }}"></script>
  <script src="{{ asset('themes/musedock/js/simple-slider.js') }}?v={{ cms_version() }}"></script>

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
      $cookieBtnAccept = site_setting('cookies_btn_accept_bg', '#4CAF50');
      $cookieBtnReject = site_setting('cookies_btn_reject_bg', '#f44336');

      $__mdCookiesUrl = function_exists('legal_page_url')
        ? legal_page_url(['cookie-policy', 'cookies', 'politica-de-cookies', 'politica-cookies'], 'cookie-policy')
        : '/p/cookie-policy';
      $__mdTermsUrl = function_exists('legal_page_url')
        ? legal_page_url(['terms-and-conditions', 'terminos-y-condiciones', 'terminos', 'terms', 'condiciones-de-uso'], 'terms-and-conditions')
        : '/p/terms-and-conditions';
    @endphp
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
          <a href="{{ $__mdCookiesUrl }}">{{ __('cookies.policy_link') }}</a>
          <a href="{{ $__mdTermsUrl }}">{{ __('cookies.terms_link') }}</a>
        </div>
      </div>
    </div>

    <div id="cookie-preferences-modal" class="cookie-preferences-modal" style="display: none; --cookie-bg: {{ $cookieBg }}; --cookie-text: {{ $cookieText }}; --cookie-btn-accept: {{ $cookieBtnAccept }}; --cookie-btn-reject: {{ $cookieBtnReject }};">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ __('cookies.modal_title') }}</h3>
          <button id="cookie-modal-close" class="modal-close-btn">×</button>
        </div>
        <div class="modal-body">
          <p>{{ __('cookies.modal_intro') }}</p>
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
