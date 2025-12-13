<!doctype html>
<html lang="{{ site_setting('language', 'es') }}" class="no-js">

<head>
    <!-- Configuración básica -->
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    {{-- Título dinámico --}}
    <title>{{ \Screenart\Musedock\View::yieldSection('title') ?: site_setting('site_name', config('app_name', 'MuseDock CMS')) }}</title>

    {{-- Metas dinámicas --}}
    @php
        $metaDescription = \Screenart\Musedock\View::yieldSection('description') ?: site_setting('site_description', '');
        $metaAuthor = site_setting('site_author', '');
    @endphp
    @if($metaDescription)
    <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if($metaAuthor)
    <meta name="author" content="{{ $metaAuthor }}">
    @endif

    <!-- Favicon dinámico -->
    @if(site_setting('site_favicon'))
        <link rel="icon" type="image/x-icon" href="{{ asset(ltrim(site_setting('site_favicon'), '/')) }}">
    @else
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('img/favicon.png') }}">
    @endif

    <!-- SEO Meta Tags dinámicas -->
    @php
        $seoKeywords = \Screenart\Musedock\View::yieldSection('keywords') ?: site_setting('site_keywords', '');
        $ogTitle = \Screenart\Musedock\View::yieldSection('og_title') ?: site_setting('site_name', '');
        $ogDescription = \Screenart\Musedock\View::yieldSection('og_description') ?: site_setting('site_description', '');
        $siteName = site_setting('site_name', '');
        $robotsDirective = trim(\Screenart\Musedock\View::yieldSection('robots', ''));

        $blogPublic = site_setting('blog_public', '1');
        if ($blogPublic == '0' && empty($robotsDirective)) {
            $robotsDirective = 'noindex, nofollow';
        }

        $twitterTitle = \Screenart\Musedock\View::yieldSection('twitter_title') ?: site_setting('site_name', '');
        $twitterDescription = \Screenart\Musedock\View::yieldSection('twitter_description') ?: site_setting('site_description', '');
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
    @if(site_setting('og_image'))
    <meta property="og:image" content="{{ asset(site_setting('og_image')) }}">
    @endif
    <link rel="canonical" href="{{ url($_SERVER['REQUEST_URI']) }}">
    <link rel="alternate" type="application/rss+xml" title="{{ site_setting('site_name', 'MuseDock') }} RSS Feed" href="{{ url('/feed') }}">
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
    @if(site_setting('twitter_site'))
    <meta name="twitter:site" content="{{ site_setting('twitter_site') }}">
    @endif
    @if(site_setting('twitter_image'))
    <meta name="twitter:image" content="{{ asset(site_setting('twitter_image')) }}">
    @elseif(site_setting('og_image'))
    <meta name="twitter:image" content="{{ asset(site_setting('og_image')) }}">
    @endif

    <!-- Responsive -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Bootstrap CSS --}}
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">

    {{-- Theme CSS --}}
    <link rel="stylesheet" href="{{ asset('themes/sustainable-nextjs-1.0.0/css/style.css') }}">

    {{-- CSS personalizado generado dinámicamente --}}
    @php
    $themeSlug = $themeSlug ?? 'sustainable-nextjs-1.0.0';
    $cssTimestampPath = public_path("assets/themes/{$themeSlug}/css/custom.css.timestamp");
    $cssTimestamp = file_exists($cssTimestampPath) ? file_get_contents($cssTimestampPath) : time();
    @endphp
    <link rel="stylesheet" href="{{ asset('themes/sustainable-nextjs-1.0.0/css/custom.css') }}?t={{ $cssTimestamp }}">

    <style>
        body.mobile-menu-open {
            overflow: hidden;
        }

        /* Topbar */
        .header-top {
            background-color: {{ themeOption('topbar.topbar_bg_color', '#1a2a40') }};
            color: {{ themeOption('topbar.topbar_text_color', '#ffffff') }};
            padding: 10px 0;
            font-size: 14px;
        }

        .header-top a {
            color: {{ themeOption('topbar.topbar_text_color', '#ffffff') }};
        }

        /* Header */
        .musedock-header {
            background-color: {{ themeOption('header.header_bg_color', '#f8f9fa') }};
            padding: 15px 0;
            position: relative;
            z-index: 999;
        }

        /* CTA Button */
        .header-btn {
            background-color: {{ themeOption('header.header_cta_bg_color', '#ff5e15') }} !important;
            color: {{ themeOption('header.header_cta_text_color', '#ffffff') }} !important;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
        }

        /* Footer */
        .footer-area {
            background-color: {{ themeOption('footer.footer_bg_color', '#f8fafe') }};
            color: {{ themeOption('footer.footer_text_color', '#333333') }};
        }

        .footer-area a {
            color: {{ themeOption('footer.footer_link_color', '#333333') }};
        }

        .footer-area a:hover {
            color: {{ themeOption('footer.footer_link_hover_color', '#ff5e15') }};
        }
    </style>

    {{-- Estilos adicionales --}}
    @stack('styles')
</head>
<body>
@php
    \Screenart\Musedock\Services\TranslationService::setContext('tenant');
    $currentLangLayout = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
    \Screenart\Musedock\Services\TranslationService::load($currentLangLayout, 'tenant');
@endphp

@php
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
                        <ul style="list-style: none; padding: 0; margin: 0; display: flex; gap: 25px;">
                            @if($showAddress && site_setting('contact_address', ''))
                                <li><i class="fas fa-map-marker-alt"></i> {{ site_setting('contact_address') }}</li>
                            @endif
                            @if($showEmail && site_setting('contact_email', ''))
                                <li><i class="fas fa-envelope"></i> {{ site_setting('contact_email') }}</li>
                            @endif
                            @if($showWhatsapp && site_setting('contact_whatsapp', ''))
                                <li><i class="fab fa-{{ $whatsappIcon == 'whatsapp' ? 'whatsapp' : 'phone' }}"></i> {{ site_setting('contact_whatsapp') }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-6">
                    <div class="header-info-right text-right">
                        <ul class="header-social d-flex justify-content-end" style="list-style: none; padding: 0; margin: 0; gap: 18px;">
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
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@php
    $headerSticky = themeOption('header.header_sticky', false);
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }}" id="main-header">
    <div class="container">
        <div class="header-container" style="display: flex; align-items: center; justify-content: space-between;">
            <div class="header-logo">
                <a href="{{ url('/') }}" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
                    @php
                        $showLogo = site_setting('show_logo', '1') === '1';
                        $showTitle = site_setting('show_title', '0') === '1';
                        $siteName = site_setting('site_name', '');
                        $logoPath = site_setting('site_logo', '');
                        $defaultLogo = asset('themes/sustainable-nextjs-1.0.0/img/logo.png');
                    @endphp

                    @if($showLogo)
                        <img src="{{ $logoPath ? asset($logoPath) : $defaultLogo }}"
                             alt="{{ $siteName }}"
                             style="max-height: 50px; width: auto;"
                             onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                    @endif

                    @if($showTitle)
                        <span style="font-size: 24px; font-weight: bold; color: #1a2a40;">
                            {{ $siteName }}
                        </span>
                    @endif
                </a>
            </div>

            <div class="header-right-content" style="display: flex; align-items: center; gap: 25px;">
                <div class="header-menu">
                    <nav class="main-navigation">
                        @custommenu('nav', null, [
                            'ul_id' => 'main-menu',
                            'nav_class' => '',
                            'li_class' => '',
                            'a_class' => '',
                            'submenu_class' => 'submenu'
                        ])
                    </nav>
                </div>

                @php
                    $ctaEnabled = themeOption('header.header_cta_enabled', false);
                    $currentLangCta = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
                    $ctaTextEs = themeOption('header.header_cta_text_es', __('header.login_button'));
                    $ctaTextEn = themeOption('header.header_cta_text_en', 'Login');
                    $ctaText = ($currentLangCta === 'en') ? $ctaTextEn : $ctaTextEs;
                    $ctaUrl = themeOption('header.header_cta_url', '#');
                    $langSelectorEnabled = themeOption('header.header_lang_selector_enabled', true);
                @endphp

                <div class="header-actions" style="display: flex; align-items: center; gap: 15px;">
                    @if($ctaEnabled)
                        <a href="{{ $ctaUrl }}" class="header-btn">{{ $ctaText }}</a>
                    @endif

                    @if($langSelectorEnabled)
                        @php
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
                        @endphp

                        @if($showLangSelector)
                        <div class="lang-select">
                            <button type="button" class="lang-btn">{{ strtoupper($currentLang) }}</button>
                            <div class="lang-dropdown" style="display: none;">
                                @foreach($languages as $lang)
                                    <a href="?lang={{ $lang['code'] }}" class="lang-option {{ $currentLang == $lang['code'] ? 'active' : '' }}">
                                        {{ $lang['name'] ?? strtoupper($lang['code']) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>

{{-- Contenedor principal para el contenido yield --}}
<main>
    @yield('content')
</main>

@include('partials.footer')

{{-- Scripts --}}
<script src="{{ asset('themes/sustainable-nextjs-1.0.0/js/theme.js') }}"></script>

@php
    $tenantId = tenant()['id'] ?? null;
    $themeSlug = themeConfig('slug', 'sustainable-nextjs-1.0.0');
    $baseUrl = rtrim(url('/'), '/');
    $jsPath = "/assets/themes/{$themeSlug}/js/custom.js";
    $fullJsUrl = $baseUrl . $jsPath;
    $fullPublicPath = public_path("assets/themes/{$themeSlug}/js/custom.js");
    $customJsExists = file_exists($fullPublicPath);
    $timestampPath = public_path("assets/themes/{$themeSlug}/js/custom.js.timestamp");
    $jsTimestamp = file_exists($timestampPath) ? file_get_contents($timestampPath) : time();
@endphp

@if($customJsExists)
    <script src="{{ $fullJsUrl }}?t={{ $jsTimestamp }}"></script>
@endif

{{-- Scripts adicionales --}}
@stack('scripts')

</body>
</html>
