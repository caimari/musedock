@php
    $headerSticky = $headerSticky ?? false;
    $ctaEnabled = $ctaEnabled ?? false;
    $ctaUrl = $ctaUrl ?? '#';
    $ctaText = $ctaText ?? '';
    $langSelectorEnabled = $langSelectorEnabled ?? false;
    $headerSearchEnabled = $headerSearchEnabled ?? false;
    $showLangSelector = $showLangSelector ?? false;
    $currentLang = $currentLang ?? 'es';
    $languages = $languages ?? [];

    $showLogo = site_setting('show_logo', '1') === '1';
    $showTitle = site_setting('show_title', '0') === '1';
    $siteName = site_setting('site_name', '');
    $logoPath = site_setting('site_logo', '');
    $defaultLogo = asset('img/musedock_logo.png');
    $taglineEnabled = themeOption('header.header_tagline_enabled', true);
    $showSubtitle = site_setting('show_subtitle', '1') === '1';
    $siteTagline = site_setting('site_subtitle', '');
    $logoTextColor = themeOption('header.header_logo_text_color', '#1a2a40');
    $logoFontFamily = themeOption('header.header_logo_font', 'inherit');
    $logoMaxHeight = themeOption('header.header_logo_max_height', '80');

    $__hdrSocialEnabled = themeOption('header.header_social_enabled', false);
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-classic" id="main-header">
    {{-- TOP BAR: menu left + social icons right --}}
    <div class="classic-topbar">
        <div class="container">
            <div class="classic-topbar-inner">
                {{-- Menu in the topbar --}}
                <nav class="main-navigation classic-topbar-nav">
                    @custommenu('nav', null, [
                        'ul_id' => 'main-menu',
                        'nav_class' => '',
                        'li_class' => '',
                        'a_class' => '',
                        'submenu_class' => 'submenu'
                    ])
                </nav>

                {{-- Right side: social icons + extras --}}
                <div class="classic-topbar-right">
                    @if($ctaEnabled)
                        <a href="{{ $ctaUrl }}" class="header-btn classic-topbar-cta">{{ $ctaText }}</a>
                    @endif

                    @if($langSelectorEnabled && $showLangSelector)
                        <div class="lang-select">
                            <button type="button" class="lang-btn">{{ strtoupper($currentLang) }}</button>
                            <div class="lang-dropdown">
                                @foreach($languages as $lang)
                                    <a href="?lang={{ $lang['code'] }}" class="lang-option {{ $currentLang == $lang['code'] ? 'active' : '' }}">
                                        {{ $lang['name'] ?? strtoupper($lang['code']) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($__hdrSocialEnabled)
                    <div class="header-social-icons classic-topbar-social">
                        @if(site_setting('social_facebook', ''))
                            <a href="{{ site_setting('social_facebook') }}" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                        @endif
                        @if(site_setting('social_twitter', ''))
                            <a href="{{ site_setting('social_twitter') }}" target="_blank" rel="noopener"><i class="fab fa-twitter"></i></a>
                        @endif
                        @if(site_setting('social_instagram', ''))
                            <a href="{{ site_setting('social_instagram') }}" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                        @endif
                        @if(site_setting('social_linkedin', ''))
                            <a href="{{ site_setting('social_linkedin') }}" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a>
                        @endif
                        @if(site_setting('social_youtube', ''))
                            <a href="{{ site_setting('social_youtube') }}" target="_blank" rel="noopener"><i class="fab fa-youtube"></i></a>
                        @endif
                        @if(site_setting('social_tiktok', ''))
                            <a href="{{ site_setting('social_tiktok') }}" target="_blank" rel="noopener"><i class="fab fa-tiktok"></i></a>
                        @endif
                    </div>
                    @endif

                    <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- BRAND BAR: logo left + optional tagline --}}
    <div class="classic-brand">
        <div class="container">
            <a href="{{ url('/') }}" class="classic-brand-link">
                @if($showLogo)
                    <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                         alt="{{ $siteName }}"
                         class="header-brand-logo"
                         style="max-height:{{ $logoMaxHeight }}px;"
                         onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                @endif
                @if($showTitle)
                    <span class="classic-brand-title" style="color:{{ $logoTextColor }};font-family:{!! $logoFontFamily !!};">
                        {{ $siteName }}
                    </span>
                @endif
            </a>
            @if($taglineEnabled && $showSubtitle && !empty($siteTagline))
                <div class="classic-brand-tagline">{{ $siteTagline }}</div>
            @endif
        </div>
    </div>
</header>
