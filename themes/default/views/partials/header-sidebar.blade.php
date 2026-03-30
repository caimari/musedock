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
    $logoPath = site_setting('site_logo', '');
    $defaultLogo = asset('img/musedock_logo.png');
    $sidebarAccentColor = themeOption('header.header_link_hover_color', '#f1c311');

    // Sidebar-specific options
    $sidebarLangSelector = themeOption('structure.sidebar_lang_selector', false);
    $sidebarSocial = themeOption('structure.sidebar_social', true);
    $sidebarSearch = themeOption('structure.sidebar_search', false);
    $sidebarCta = themeOption('structure.sidebar_cta', false);
    $sidebarShowLogo = themeOption('structure.sidebar_show_logo', true);
    $sidebarTitleFont = themeOption('structure.sidebar_title_font', 'inherit');
    $sidebarTitleSize = themeOption('structure.sidebar_title_size', '1.4rem');
    $sidebarSubtitleSize = themeOption('structure.sidebar_subtitle_size', '0.8rem');
    $sidebarTitleColor = themeOption('structure.sidebar_title_color', '#f1c311');
    $sidebarSubtitleColor = themeOption('structure.sidebar_subtitle_color', '#999999');
    $sidebarShowSubtitle = themeOption('structure.sidebar_show_subtitle', true);

    $showSubtitle = site_setting('show_subtitle', '1') === '1';
    $siteTagline = site_setting('site_subtitle', '');
@endphp

{{-- Sidebar Header (fixed left panel) --}}
<aside class="musedock-sidebar-nav" id="sidebar-nav">
    <div class="sidebar-nav-inner">
        @php
            $__menuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav', null, [
                'ul_id' => 'sidebar-main-menu',
                'nav_class' => 'sidebar-nav-list',
                'li_class' => 'sidebar-nav-item',
                'a_class' => 'sidebar-nav-link',
                'submenu_class' => 'sidebar-nav-submenu'
            ]);
            $__hasMenu = !empty(trim(strip_tags($__menuHtml ?? '')));
        @endphp

        {{-- Brand / Name --}}
        <div class="sidebar-nav-brand {{ !$__hasMenu ? 'sidebar-nav-brand-centered' : '' }}">
            <a href="{{ url('/') }}" class="sidebar-nav-brand-link">
                @if($showLogo && $sidebarShowLogo)
                    <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                         alt="{{ $siteName }}"
                         class="sidebar-nav-logo"
                         onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                @endif
                @if($showTitle || !$sidebarShowLogo)
                    <span class="sidebar-nav-title" style="font-size:{{ $sidebarTitleSize }};color:{{ $sidebarTitleColor }};{{ $sidebarTitleFont !== 'inherit' ? 'font-family:'.$sidebarTitleFont.';' : '' }}">{{ $siteName }}</span>
                @endif
                @if($sidebarShowSubtitle && $showSubtitle && !empty($siteTagline))
                    <span class="sidebar-nav-subtitle" style="font-size:{{ $sidebarSubtitleSize }};color:{{ $sidebarSubtitleColor }};{{ $sidebarTitleFont !== 'inherit' ? 'font-family:'.$sidebarTitleFont.';' : '' }}">{{ $siteTagline }}</span>
                @endif
            </a>
        </div>

        {{-- Navigation --}}
        @if($__hasMenu)
        <nav class="sidebar-nav-menu">
            {!! $__menuHtml !!}
        </nav>
        @endif

        {{-- Footer of sidebar --}}
        @php
            $__hasAnySocial = $sidebarSocial && (
                site_setting('social_facebook', '') || site_setting('social_twitter', '') ||
                site_setting('social_instagram', '') || site_setting('social_linkedin', '') ||
                site_setting('social_youtube', '') || site_setting('social_tiktok', '') ||
                site_setting('social_vimeo', '') || site_setting('social_pinterest', '')
            );
            $__hasFooterContent = $__hasAnySocial || ($sidebarLangSelector && $showLangSelector) || ($sidebarCta && $ctaEnabled) || $sidebarSearch;
        @endphp
        @if($__hasMenu || $__hasFooterContent)
        <div class="sidebar-nav-footer">
            @if($sidebarSocial)
            <div class="sidebar-nav-social" style="display:flex; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                @if(site_setting('social_facebook', ''))<a href="{{ site_setting('social_facebook') }}" target="_blank" style="color:var(--header-link-color,#ccc);font-size:15px;"><i class="fab fa-facebook-f"></i></a>@endif
                @if(site_setting('social_twitter', ''))<a href="{{ site_setting('social_twitter') }}" target="_blank" style="color:var(--header-link-color,#ccc);font-size:15px;"><i class="fab fa-twitter"></i></a>@endif
                @if(site_setting('social_instagram', ''))<a href="{{ site_setting('social_instagram') }}" target="_blank" style="color:var(--header-link-color,#ccc);font-size:15px;"><i class="fab fa-instagram"></i></a>@endif
                @if(site_setting('social_linkedin', ''))<a href="{{ site_setting('social_linkedin') }}" target="_blank" style="color:var(--header-link-color,#ccc);font-size:15px;"><i class="fab fa-linkedin-in"></i></a>@endif
                @if(site_setting('social_youtube', ''))<a href="{{ site_setting('social_youtube') }}" target="_blank" style="color:var(--header-link-color,#ccc);font-size:15px;"><i class="fab fa-youtube"></i></a>@endif
                @if(site_setting('social_tiktok', ''))<a href="{{ site_setting('social_tiktok') }}" target="_blank" style="color:var(--header-link-color,#ccc);font-size:15px;"><i class="fab fa-tiktok"></i></a>@endif
                @if(site_setting('social_vimeo', ''))<a href="{{ site_setting('social_vimeo') }}" target="_blank" style="color:var(--header-link-color,#ccc);font-size:15px;"><i class="fab fa-vimeo-v"></i></a>@endif
                @if(site_setting('social_pinterest', ''))<a href="{{ site_setting('social_pinterest') }}" target="_blank" style="color:var(--header-link-color,#ccc);font-size:15px;"><i class="fab fa-pinterest"></i></a>@endif
            </div>
            @endif

            @if($sidebarLangSelector && $showLangSelector)
                <div class="sidebar-nav-lang">
                    <select onchange="window.location.href='?lang='+this.value;" class="sidebar-nav-lang-select">
                        @foreach($languages as $lang)
                            <option value="{{ $lang['code'] }}" {{ $currentLang == $lang['code'] ? 'selected' : '' }}>
                                {{ $lang['name'] ?? strtoupper($lang['code']) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($sidebarCta && $ctaEnabled)
                <a href="{{ $ctaUrl }}" class="sidebar-nav-cta">{{ $ctaText }}</a>
            @endif

            @if($sidebarSearch)
                @include('partials._header-search')
            @endif
        </div>
        @endif
    </div>
</aside>

{{-- Mobile header (visible only on small screens) --}}
<header class="musedock-header musedock-sidebar-mobile-header" id="main-header">
    <div class="container">
        <div class="header-container" style="display:flex;justify-content:space-between;align-items:center;">
            <div class="header-logo" style="min-width:0;flex:1;">
                <a href="{{ url('/') }}" class="sidebar-mobile-brand-link" style="text-decoration:none !important;display:flex;align-items:center;gap:8px;min-width:0;color:{{ $sidebarTitleColor }} !important;{{ $sidebarTitleFont !== 'inherit' ? 'font-family:'.$sidebarTitleFont.' !important;' : '' }}">
                    @if($showLogo && $sidebarShowLogo)
                        <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                             alt="{{ $siteName }}"
                             style="max-height:35px;width:auto;"
                             onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                    @endif
                    @if($showTitle || !$sidebarShowLogo)
                    <span style="font-size:16px;font-weight:bold;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $siteName }}</span>
                    @endif
                </a>
            </div>
            @if($__hasMenu || $__hasFooterContent)
            <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
                <span></span><span></span><span></span>
            </button>
            @endif
        </div>
    </div>
</header>
