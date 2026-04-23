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
    $logoAboveMaxHeight = themeOption('header.header_logo_max_height', '120');

    $__hdrSocialEnabled = themeOption('header.header_social_enabled', false);
    $__hdrClockEnabled = themeOption('header.header_clock_enabled', false);
    $__hdrSearchEnabled = themeOption('header.header_search_enabled', false);
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-logo-above-left" id="main-header">
    {{-- Logo row: logo left, social+search right, vertically centered --}}
    <div class="header-logo-above-brand" style="padding:24px 0 16px;border-bottom:1px solid rgba(0,0,0,0.06);background:var(--header-bg-color,#fff);">
        <div class="container">
            <div class="logo-above-brand-row">
                {{-- Logo left --}}
                <div class="logo-above-brand-left-logo">
                    <a href="{{ url('/') }}" style="text-decoration:none;color:inherit;display:inline-block;">
                        <div style="display:flex;flex-direction:column;align-items:flex-start;">
                            @if($showLogo)
                                <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                                     alt="{{ $siteName }}"
                                     class="header-brand-logo"
                                     style="max-height:{{ $logoAboveMaxHeight }}px;width:auto;display:block;"
                                     onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                            @endif
                            @if($showTitle)
                                <div style="font-size:26px;font-weight:bold;color:{{ $logoTextColor }};font-family:{!! $logoFontFamily !!};margin-top:6px;">
                                    {{ $siteName }}
                                </div>
                            @endif
                            @if($taglineEnabled && $showSubtitle && !empty($siteTagline))
                                <div class="header-brand-tagline" style="margin-top:4px;font-size:13px;color:var(--header-tagline-color, #666);">{{ $siteTagline }}</div>
                            @endif
                        </div>
                    </a>
                </div>

                {{-- Social + search right, vertically centered with logo --}}
                @if($__hdrSocialEnabled || $__hdrSearchEnabled)
                <div class="logo-above-brand-right">
                    @if($__hdrSocialEnabled)
                    <div class="header-social-icons">
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
                        @if(site_setting('social_pinterest', ''))
                            <a href="{{ site_setting('social_pinterest') }}" target="_blank" rel="noopener"><i class="fab fa-pinterest"></i></a>
                        @endif
                        @if(site_setting('social_tiktok', ''))
                            <a href="{{ site_setting('social_tiktok') }}" target="_blank" rel="noopener"><i class="fab fa-tiktok"></i></a>
                        @endif
                    </div>
                    @endif

                    @if($__hdrSearchEnabled)
                        @php $__searchMode = themeOption('header.header_search_mode', 'modal'); @endphp
                        @if($__searchMode === 'page')
                        <a href="{{ url('/search') }}" class="header-search-icon" aria-label="Buscar">
                            <i class="fas fa-search"></i>
                        </a>
                        @else
                        <button type="button" class="header-search-toggle header-search-icon" aria-label="Buscar" style="background:none;border:none;cursor:pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        @endif
                    @endif
                </div>
                @endif

                {{-- Mobile hamburger (hidden on desktop, shown in responsive) --}}
                @if(menu_has_items('nav'))
                <button type="button" class="menu-toggle logo-above-mobile-toggle" id="menu-toggle" aria-label="Abrir menú" style="display:none;">
                    <span></span><span></span><span></span>
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Navigation bar: menu left, date/clock right --}}
    @php
        $menuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav', null, [
            'ul_id' => 'main-menu',
            'nav_class' => '',
            'li_class' => '',
            'a_class' => '',
            'submenu_class' => 'submenu'
        ]);
        $hasMenu = !empty(trim(strip_tags($menuHtml ?? '')));
        $hasNavContent = $hasMenu || $ctaEnabled || ($langSelectorEnabled && $showLangSelector) || $__hdrClockEnabled;
    @endphp
    @if($hasNavContent)
    <div class="header-logo-above-nav">
        <div class="container">
            <div class="header-logo-above-nav-inner">
                <div class="logo-above-nav-left">
                    @if($hasMenu)
                    <nav class="main-navigation header-menu">
                        {!! $menuHtml !!}
                    </nav>
                    @endif
                </div>

                <div class="header-actions">
                    @if($ctaEnabled)
                        <a href="{{ $ctaUrl }}" class="header-btn">{{ $ctaText }}</a>
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

                    @if($__hdrClockEnabled)
                    <div class="header-clock" id="headerClockWrap">
                        <span class="header-clock-display" id="headerLiveClock"></span>
                    </div>
                    <script>
                    (function(){
                        var el = document.getElementById('headerLiveClock');
                        if (!el) return;
                        var tz = @json(themeOption('topbar.topbar_clock_timezone', 'Europe/Madrid'));
                        var locale = @json(themeOption('topbar.topbar_clock_locale', 'es'));
                        var localeMap = {'es':'es-ES','en':'en-US','fr':'fr-FR','de':'de-DE','pt':'pt-PT'};
                        var full = localeMap[locale] || 'es-ES';
                        var dateOpts = {weekday:'long',year:'numeric',month:'long',day:'numeric',timeZone:tz};
                        var timeOpts = {hour:'numeric',minute:'2-digit',second:'2-digit',timeZone:tz,hour12:locale==='en'};
                        var dateFmt, timeFmt;
                        try { dateFmt = new Intl.DateTimeFormat(full, dateOpts); timeFmt = new Intl.DateTimeFormat(full, timeOpts); }
                        catch(e) { dateFmt = new Intl.DateTimeFormat('es-ES', dateOpts); timeFmt = new Intl.DateTimeFormat('es-ES', timeOpts); }
                        function update() {
                            var now = new Date();
                            var d = dateFmt.format(now);
                            var t = timeFmt.format(now);
                            el.textContent = d.charAt(0).toUpperCase() + d.slice(1) + '  ·  ' + t;
                        }
                        update();
                        setInterval(update, 1000);
                    })();
                    </script>
                    @endif

                    @if($hasMenu)
                    <button type="button" class="menu-toggle" aria-label="Abrir menú">
                        <span></span><span></span><span></span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</header>
