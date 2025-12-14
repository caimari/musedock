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
  <link rel="shortcut icon" href="{{ asset('themes/musedock/images/favicon.png') }}">
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
  @endphp
  @if($ogTitle)
  <meta property="og:title" content="{{ $ogTitle }}">
  @endif
  @if($ogDescription)
  <meta property="og:description" content="{{ $ogDescription }}">
  @endif
  @if($_siteName)
  <meta property="og:site_name" content="{{ $_siteName }}">
  @endif
  <meta property="og:type" content="website">
  @if($_ogImage)
  <meta property="og:image" content="{{ asset($_ogImage) }}">
  @endif
  
  {{-- Twitter/X Cards --}}
  <meta name="twitter:card" content="summary_large_image">
  @php 
  $twitterTitle = \Screenart\Musedock\View::yieldSection('twitter_title') ?: $_siteName; 
  $twitterDescription = \Screenart\Musedock\View::yieldSection('twitter_description') ?: ($_twitterDescription ?: $_siteDescription); 
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
  @if($_twitterImage)
  <meta name="twitter:image" content="{{ asset($_twitterImage) }}">
  @elseif($_ogImage)
  <meta name="twitter:image" content="{{ asset($_ogImage) }}">
  @endif
  
  {{-- RSS Feed --}}
  @if($_siteName)
  <link rel="alternate" type="application/rss+xml" title="{{ $_siteName }} RSS Feed" href="{{ url('/feed') }}">
  @endif
  
  {{-- CSS del tema --}}
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/font-awesome.min.css') }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/plugins.css') }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/animated.css') }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/owl.carousel.min.css') }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/colors.css') }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('themes/musedock/css/simple-slider.css') }}">
  
  {{-- CSS Variables dinámicas --}}
  <style>
    :root {
      --topbar-bg-color: {{ themeOption('topbar.topbar_bg_color', '#1a2a40') }};
      --topbar-text-color: {{ themeOption('topbar.topbar_text_color', '#ffffff') }};
      --header-bg-color: {{ themeOption('header.header_bg_color', '#f8f9fa') }};
      --header-logo-text-color: {{ themeOption('header.header_logo_text_color', '#1a2a40') }};
      --header-link-color: {{ themeOption('header.header_link_color', '#333333') }};
      --header-link-hover-color: {{ themeOption('header.header_link_hover_color', '#ff5e15') }};
      --footer-bg-color: {{ themeOption('footer.footer_bg_color', '#f8fafe') }};
      --footer-text-color: {{ themeOption('footer.footer_text_color', '#333333') }};
      --footer-heading-color: {{ themeOption('footer.footer_heading_color', '#333333') }};
      --footer-link-color: {{ themeOption('footer.footer_link_color', '#333333') }};
      --footer-link-hover-color: {{ themeOption('footer.footer_link_hover_color', '#ff5e15') }};
      --footer-icon-color: {{ themeOption('footer.footer_icon_color', '#333333') }};
      --footer-border-color: {{ themeOption('footer.footer_border_color', '#e5e5e5') }};
    }
  </style>
  
  {{-- Additional CSS for theme customization --}}
  @stack('styles')
</head>
<body class="ziph-page">
  <div id="zipprich-wrapper" class="ziph_page">
    @include('partials.header')
    
    <main>
      @yield('content')
    </main>
    
    @include('partials.footer')
  </div>

  {{-- Scripts del tema --}}
  <script src="{{ asset('themes/musedock/js/jquery.min.js') }}"></script>
  <script src="{{ asset('themes/musedock/js/bootstrap.min.js') }}"></script>
  <script src="{{ asset('themes/musedock/js/owl.carousel.min.js') }}"></script>
  <script src="{{ asset('themes/musedock/js/plugins.js') }}"></script>
  <script src="{{ asset('themes/musedock/js/scripts.js') }}"></script>
  <script src="{{ asset('themes/musedock/js/simple-slider.js') }}"></script>
  
  @stack('scripts')
  
  {{-- Script para inicializar el slider si existe --}}
  @if(request()->is('/') || request()->is('home'))
  <script>
    $(document).ready(function() {
      if ($('#simple-slider').length) {
        $('#simple-slider').simpleSlider({
          autoPlay: true,
          delay: 5000,
          transition: 'fade'
        });
      }
    });
  </script>
  @endif
</body>
</html>
