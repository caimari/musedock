<!DOCTYPE html>
<html lang="{{ site_setting('site_language', 'es') }}">
<head>
    <meta charset="utf-8">

    @php
        $_tenantId = tenant_id();
        $_isTenant = $_tenantId !== null;
        $_siteName = site_setting('site_name', '');
        $_siteDescription = site_setting('site_description', '');
        $_siteKeywords = site_setting('site_keywords', '');
        $_siteLanguage = site_setting('site_language', 'es');
        $_ogImage = site_setting('og_image', '');
        $_favicon = site_setting('favicon', '');
    @endphp

    {{-- Título dinámico --}}
    <title>@yield('title', $_siteName)</title>

    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    {{-- Meta tags SEO --}}
    <meta name="description" content="@yield('description', $_siteDescription)">
    <meta name="keywords" content="@yield('keywords', $_siteKeywords)">
    <meta name="robots" content="@yield('robots', 'index,follow')">

    {{-- Open Graph (Facebook, LinkedIn, WhatsApp) --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $_siteName }}">
    <meta property="og:title" content="@yield('og_title', $_siteName)">
    <meta property="og:description" content="@yield('og_description', $_siteDescription)">
    <meta property="og:url" content="@yield('og_url', url($_SERVER['REQUEST_URI']))">
    @if(!empty($_ogImage))
        <meta property="og:image" content="{{ asset($_ogImage) }}">
    @endif
    @yield('og_image')

    {{-- Twitter Cards --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('twitter_title', $_siteName)">
    <meta name="twitter:description" content="@yield('twitter_description', $_siteDescription)">
    @if(!empty($_ogImage))
        <meta name="twitter:image" content="{{ asset($_ogImage) }}">
    @endif
    @yield('twitter_image')

    {{-- Canonical URL --}}
    @yield('canonical')

    {{-- Favicon --}}
    @if(!empty($_favicon))
        <link rel="icon" href="{{ asset($_favicon) }}" type="image/x-icon">
    @endif

    {{-- Google Web Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Saira:wght@500;600;700&display=swap" rel="stylesheet">

    {{-- Icon Font Stylesheet --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    {{-- Libraries Stylesheet --}}
    <link href="{{ asset('themes/HighTechIT/lib/animate/animate.min.css') }}" rel="stylesheet">
    <link href="{{ asset('themes/HighTechIT/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">

    {{-- Customized Bootstrap Stylesheet --}}
    <link href="{{ asset('themes/HighTechIT/css/bootstrap.min.css') }}" rel="stylesheet">

    {{-- Template Stylesheet --}}
    <link href="{{ asset('themes/HighTechIT/css/style.css') }}" rel="stylesheet">

    {{-- Custom CSS from theme options --}}
    <style>
        :root {
            --primary: {{ themeOption('primary_color', '#0d6efd') }};
            --secondary: {{ themeOption('secondary_color', '#6c757d') }};
        }
    </style>

    @yield('extra_head')
</head>

<body>
    {{-- Spinner Start --}}
    <div id="spinner" class="show position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-grow text-primary" role="status"></div>
    </div>
    {{-- Spinner End --}}

    {{-- Header --}}
    @include('partials.header')

    {{-- Main Content --}}
    @yield('content')

    {{-- Footer --}}
    @include('partials.footer')

    {{-- Back to Top --}}
    <a href="#" class="btn btn-secondary btn-square rounded-circle back-to-top"><i class="fa fa-arrow-up text-white"></i></a>

    {{-- JavaScript Libraries --}}
    <script src="{{ asset('themes/HighTechIT/lib/jquery-3.6.4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('themes/HighTechIT/lib/wow/wow.min.js') }}"></script>
    <script src="{{ asset('themes/HighTechIT/lib/easing/easing.min.js') }}"></script>
    <script src="{{ asset('themes/HighTechIT/lib/waypoints/waypoints.min.js') }}"></script>
    <script src="{{ asset('themes/HighTechIT/lib/owlcarousel/owl.carousel.min.js') }}"></script>

    {{-- Template Javascript --}}
    <script src="{{ asset('themes/HighTechIT/js/main.js') }}"></script>

    @yield('extra_scripts')
</body>
</html>
