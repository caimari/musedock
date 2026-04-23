@php
    $headerSticky = $headerSticky ?? false;
    $ctaEnabled = $ctaEnabled ?? false;
    $ctaUrl = $ctaUrl ?? '#';
    $ctaText = $ctaText ?? '';
    $headerSearchEnabled = $headerSearchEnabled ?? false;
    $langSelectorEnabled = $langSelectorEnabled ?? false;
    $showLangSelector = $showLangSelector ?? false;
    $currentLang = $currentLang ?? 'es';
    $languages = $languages ?? [];

    $showLogo = site_setting('show_logo', '1') === '1';
    $showTitle = site_setting('show_title', '0') === '1';
    $siteName = site_setting('site_name', '');
    $siteSubtitle = site_setting('site_subtitle', '');
    $showSubtitle = site_setting('show_subtitle', '1') === '1';
    $logoPath = site_setting('site_logo', '');
    $defaultLogo = asset('img/musedock_logo.png');
    $logoTextColor = themeOption('header.header_logo_text_color', '#ffffff');
    $logoFont = themeOption('header.header_logo_font', 'inherit');
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-banner" id="main-header">
    <div class="header-banner-wrap">
        {{-- Logo banner (accent background, extends left) --}}
        <div class="header-banner-logo">
            <div class="header-banner-logo-inner">
                <a href="{{ url('/') }}" class="header-banner-link">
                    @if($showLogo && $logoPath)
                        <img src="{{ public_file_url($logoPath) }}"
                             alt="{{ $siteName }}"
                             class="header-banner-logo-img"
                             onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                    @endif
                    @if($showTitle || (!$showLogo && !$logoPath))
                        <div class="header-banner-text">
                            <h1 class="header-banner-title" style="font-family: {!! $logoFont !!}; color: {{ $logoTextColor }};">{{ $siteName }}</h1>
                            @if($showSubtitle && !empty($siteSubtitle))
                                <p class="header-banner-subtitle">{{ $siteSubtitle }}</p>
                            @endif
                        </div>
                    @endif
                </a>
            </div>
        </div>

        {{-- Right side: social icons above + menu below --}}
        <div class="header-banner-right">
            {{-- Social icons row (top right) --}}
            @php
                $__hasSocials = site_setting('social_facebook') || site_setting('social_twitter') || site_setting('social_instagram') || site_setting('social_linkedin') || site_setting('social_youtube') || site_setting('social_tiktok');
            @endphp
            @if($__hasSocials)
            <div class="header-banner-social">
                @if(site_setting('social_facebook', ''))
                    <a href="{{ site_setting('social_facebook') }}" target="_blank"><i class="fab fa-facebook-f"></i></a>
                @endif
                @if(site_setting('social_vimeo', ''))
                    <a href="{{ site_setting('social_vimeo') }}" target="_blank"><i class="fab fa-vimeo-v"></i></a>
                @endif
                @if(site_setting('social_twitter', ''))
                    <a href="{{ site_setting('social_twitter') }}" target="_blank"><i class="fab fa-twitter"></i></a>
                @endif
                @if(site_setting('social_instagram', ''))
                    <a href="{{ site_setting('social_instagram') }}" target="_blank"><i class="fab fa-instagram"></i></a>
                @endif
                @if(site_setting('social_linkedin', ''))
                    <a href="{{ site_setting('social_linkedin') }}" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                @endif
                @if(site_setting('social_youtube', ''))
                    <a href="{{ site_setting('social_youtube') }}" target="_blank"><i class="fab fa-youtube"></i></a>
                @endif
            </div>
            @endif

            {{-- Menu row (bottom right) --}}
            <div class="header-banner-nav-row">
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

                <div class="header-actions">
                    @include('partials._header-extras')

                    @if($ctaEnabled)
                        <a href="{{ $ctaUrl }}" class="header-btn">{{ $ctaText }}</a>
                    @endif

                    @include('partials._header-search')

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

                    @if(menu_has_items('nav'))
                    <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
                        <span></span><span></span><span></span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>
