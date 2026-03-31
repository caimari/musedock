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
    $logoTextColor = themeOption('header.header_logo_text_color', '#333');
    $logoFontFamily = themeOption('header.header_logo_font', 'inherit');
    $logoMaxHeight = themeOption('header.header_logo_max_height', '80');

    $__hdrSocialEnabled = themeOption('header.header_social_enabled', false);
    $__hdrClockEnabled = themeOption('header.header_clock_enabled', false);
    $__hdrSearchEnabled = themeOption('header.header_search_enabled', false);

    $headerBg = themeOption('header.header_bg_color', '#ffffff');
    $linkColor = themeOption('header.header_link_color', '#333333');
    $linkHover = themeOption('header.header_link_hover_color', '#000000');
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-filmconnect" id="main-header">

    {{-- Brand row: white background, logo left with optional subtitle --}}
    <div class="hdr-fc-brand" style="background:{{ $headerBg }};border-bottom:1px solid rgba(0,0,0,0.06);">
        <div class="container">
            <div class="hdr-fc-brand-inner">
                <a href="{{ url('/') }}" class="hdr-fc-logo-link" style="text-decoration:none;color:inherit;display:inline-flex;align-items:center;gap:12px;">
                    @if($showLogo)
                        <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                             alt="{{ $siteName }}"
                             class="header-brand-logo"
                             style="max-height:{{ $logoMaxHeight }}px;width:auto;display:block;"
                             onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                    @endif
                    @if($showTitle)
                        <div class="hdr-fc-titles">
                            <div class="hdr-fc-site-title" style="font-family:{!! $logoFontFamily !!};color:{{ $logoTextColor }};">
                                {{ $siteName }}
                            </div>
                            @if($taglineEnabled && $showSubtitle && !empty($siteTagline))
                                <div class="hdr-fc-tagline">{{ $siteTagline }}</div>
                            @endif
                        </div>
                    @endif
                </a>

                {{-- Right side: social, search, widget area --}}
                <div class="hdr-fc-brand-right">
                    @if($__hdrSocialEnabled)
                    <div class="header-social-icons hdr-fc-social">
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
                    </div>
                    @endif

                    @if($__hdrSearchEnabled)
                    <a href="{{ url('/search') }}" class="header-search-icon hdr-fc-search" aria-label="Buscar">
                        <i class="fas fa-search"></i>
                    </a>
                    @endif
                </div>

                {{-- Mobile toggle --}}
                <button type="button" class="menu-toggle hdr-fc-mobile-toggle" id="menu-toggle" aria-label="Abrir menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Navigation bar: colored background, menu items with highlight on hover/active --}}
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
    <div class="hdr-fc-nav">
        <div class="container">
            <div class="hdr-fc-nav-inner">
                <div class="hdr-fc-nav-left">
                    @if($hasMenu)
                    <nav class="main-navigation header-menu hdr-fc-menu">
                        {!! $menuHtml !!}
                    </nav>
                    @endif
                </div>

                <div class="header-actions hdr-fc-nav-actions">
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
                </div>
            </div>
        </div>
    </div>
    @endif
</header>

<style>
/* ═══════════════════════════════════════════
   Header Layout: FilmConnect
   Logo arriba + barra de menu tipo Hoot Du
   ═══════════════════════════════════════════ */

/* Brand row */
.hdr-fc-brand {
    padding: 20px 0;
}
.hdr-fc-brand-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.hdr-fc-logo-link {
    flex-shrink: 0;
}
.hdr-fc-titles {
    display: flex;
    flex-direction: column;
}
.hdr-fc-site-title {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}
.hdr-fc-tagline {
    font-size: 13px;
    color: #888;
    margin-top: 2px;
    letter-spacing: 0.3px;
}
.hdr-fc-brand-right {
    display: flex;
    align-items: center;
    gap: 16px;
}
.hdr-fc-social a {
    font-size: 15px;
    opacity: 0.6;
    transition: opacity 0.2s;
}
.hdr-fc-social a:hover {
    opacity: 1;
}
.hdr-fc-search {
    font-size: 16px;
    opacity: 0.5;
    transition: opacity 0.2s;
}
.hdr-fc-search:hover {
    opacity: 1;
}
.hdr-fc-mobile-toggle {
    display: none;
}

/* Navigation bar */
.hdr-fc-nav {
    background: var(--header-bg-color, #ffffff);
    border-top: 1px solid rgba(0,0,0,0.08);
    border-bottom: 3px solid transparent;
}
.hdr-fc-nav-inner {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    min-height: 44px;
}
.hdr-fc-nav-left {
    display: flex;
    align-items: stretch;
    flex: 1;
}

/* Menu items: horizontal, highlight style like Hoot Du theme */
.hdr-fc-menu ul#main-menu {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    align-items: stretch;
}
.hdr-fc-menu ul#main-menu > li {
    position: relative;
    display: flex;
    align-items: stretch;
}
.hdr-fc-menu ul#main-menu > li > a {
    display: flex;
    align-items: center;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--header-link-color, #333);
    text-decoration: none;
    transition: background-color 0.2s, color 0.2s;
    white-space: nowrap;
    border-bottom: 3px solid transparent;
    margin-bottom: -3px;
}
.hdr-fc-menu ul#main-menu > li > a:hover,
.hdr-fc-menu ul#main-menu > li.current-menu-item > a,
.hdr-fc-menu ul#main-menu > li.active > a {
    background-color: rgba(0,0,0,0.04);
    color: var(--header-link-hover-color, #000);
}
.hdr-fc-menu ul#main-menu > li.current-menu-item > a,
.hdr-fc-menu ul#main-menu > li.active > a {
    border-bottom-color: var(--header-link-hover-color, #000);
}

/* Submenus */
.hdr-fc-menu ul#main-menu .submenu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: #fff;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 0 0 4px 4px;
    min-width: 200px;
    z-index: 100;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    list-style: none;
    padding: 4px 0;
    margin: 0;
}
.hdr-fc-menu ul#main-menu li:hover > .submenu {
    display: block;
}
.hdr-fc-menu ul#main-menu .submenu li a {
    display: block;
    padding: 8px 18px;
    font-size: 13px;
    color: #444;
    text-decoration: none;
    transition: background-color 0.15s;
}
.hdr-fc-menu ul#main-menu .submenu li a:hover,
.hdr-fc-menu ul#main-menu .submenu li.current-menu-item a {
    background: rgba(0,0,0,0.04);
    color: #000;
}

/* Nav actions */
.hdr-fc-nav-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-left: 16px;
}

/* ─── Responsive ─── */
@media (max-width: 969px) {
    .hdr-fc-brand {
        padding: 14px 0;
    }
    .hdr-fc-brand-right {
        display: none;
    }
    .hdr-fc-mobile-toggle {
        display: flex;
    }
    .hdr-fc-site-title {
        font-size: 22px;
    }
    .header-brand-logo {
        max-height: 60px !important;
    }

    /* Nav bar becomes hidden on mobile, toggle controls it */
    .hdr-fc-nav {
        display: none;
    }
    .hdr-fc-nav.mobile-open {
        display: block;
    }
    .hdr-fc-nav-inner {
        flex-direction: column;
    }
    .hdr-fc-menu ul#main-menu {
        flex-direction: column;
    }
    .hdr-fc-menu ul#main-menu > li > a {
        padding: 12px 16px;
        border-bottom: none;
        border-left: 3px solid transparent;
        margin-bottom: 0;
    }
    .hdr-fc-menu ul#main-menu > li.current-menu-item > a,
    .hdr-fc-menu ul#main-menu > li.active > a {
        border-bottom-color: transparent;
        border-left-color: var(--header-link-hover-color, #000);
    }
    .hdr-fc-menu ul#main-menu .submenu {
        position: static;
        box-shadow: none;
        border: none;
        border-radius: 0;
        background: rgba(0,0,0,0.02);
    }
    .hdr-fc-menu ul#main-menu .submenu li a {
        padding-left: 32px;
    }
    .hdr-fc-nav-actions {
        padding: 10px 16px;
        border-top: 1px solid rgba(0,0,0,0.06);
    }
}
</style>
