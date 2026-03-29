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
@endphp

{{-- Sidebar Header (fixed left panel) --}}
<aside class="musedock-sidebar-nav" id="sidebar-nav">
    <div class="sidebar-nav-inner">
        {{-- Brand / Name --}}
        <div class="sidebar-nav-brand">
            <a href="{{ url('/') }}" class="sidebar-nav-brand-link">
                @if($showLogo)
                    <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                         alt="{{ $siteName }}"
                         class="sidebar-nav-logo"
                         onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                @endif
                @if($showTitle)
                    <span class="sidebar-nav-title">{{ $siteName }}</span>
                @endif
            </a>
        </div>

        {{-- Navigation --}}
        <nav class="sidebar-nav-menu">
            @custommenu('nav', null, [
                'ul_id' => 'sidebar-main-menu',
                'nav_class' => 'sidebar-nav-list',
                'li_class' => 'sidebar-nav-item',
                'a_class' => 'sidebar-nav-link',
                'submenu_class' => 'sidebar-nav-submenu'
            ])
        </nav>

        {{-- Footer of sidebar --}}
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
    </div>
</aside>

{{-- Mobile header (visible only on small screens) --}}
<header class="musedock-header musedock-sidebar-mobile-header" id="main-header">
    <div class="container">
        <div class="header-container">
            <div class="header-logo">
                <a href="{{ url('/') }}" class="header-brand-link">
                    <div class="header-brand-block">
                        <div class="header-brand-top">
                            @if($showLogo)
                                <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                                     alt="{{ $siteName }}"
                                     class="header-brand-logo"
                                     onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                            @endif
                            @if($showTitle)
                                <span class="site-title" style="font-size: 20px; font-weight: bold; color: var(--header-link-color, #333);">{{ $siteName }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
            <div class="header-right-content">
                <div class="header-actions">
                    <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
