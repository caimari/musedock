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
    $siteDescription = site_setting('site_subtitle', '');
    $taglineEnabled = themeOption('header.header_tagline_enabled', true);
    $showSubtitle = site_setting('show_subtitle', '1') === '1';
    $logoPath = site_setting('site_logo', '');
    $defaultLogo = asset('img/musedock_logo.png');
    $logoTextColor = themeOption('header.header_logo_text_color', '#1a2a40');
    $logoFontFamily = themeOption('header.header_logo_font', 'inherit');
    $logoMaxHeight = themeOption('header.header_logo_max_height', '55');

    $__hdrSearchEnabled = themeOption('header.header_search_enabled', false);
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-floating" id="main-header">
    <div class="hdr-float-bar">
        <div class="container">
            <div class="hdr-float-row">
                {{-- Logo + title + tagline --}}
                <div class="hdr-float-brand">
                    <a href="{{ url('/') }}" class="hdr-float-brand-link">
                        @if($showLogo)
                            <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                                 alt="{{ $siteName }}"
                                 class="header-brand-logo"
                                 style="max-height:{{ $logoMaxHeight }}px;"
                                 onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                        @endif

                        @if($showTitle && $siteName)
                            <div class="hdr-float-titles">
                                <div class="hdr-float-site-title" style="color:{{ $logoTextColor }};font-family:{!! $logoFontFamily !!};">
                                    {{ $siteName }}
                                </div>
                                @if($taglineEnabled && $showSubtitle && $siteDescription)
                                    <div class="hdr-float-tagline">{{ $siteDescription }}</div>
                                @endif
                            </div>
                        @endif
                    </a>
                </div>

                {{-- Right: nav + CTA + extras --}}
                <div class="hdr-float-right">
                    <nav class="main-navigation header-menu hdr-float-nav">
                        @custommenu('nav', null, [
                            'ul_id' => 'main-menu',
                            'nav_class' => '',
                            'li_class' => '',
                            'a_class' => '',
                            'submenu_class' => 'submenu'
                        ])
                    </nav>

                    <div class="hdr-float-actions">
                        @if($ctaEnabled)
                            <a href="{{ $ctaUrl }}" class="hdr-float-cta">
                                {{ $ctaText }}
                            </a>
                        @endif

                        @if($__hdrSearchEnabled)
                            @php $__searchMode = themeOption('header.header_search_mode', 'modal'); @endphp
                            @if($__searchMode === 'page')
                            <a href="{{ url('/search') }}" class="hdr-float-search" aria-label="Buscar">
                                <i class="fas fa-search"></i>
                            </a>
                            @else
                            <button type="button" class="header-search-toggle hdr-float-search" aria-label="Buscar" style="background:none;border:none;cursor:pointer;">
                                <i class="fas fa-search"></i>
                            </button>
                            @endif
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

                        @if(menu_has_items('nav'))
                        <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
                            <span></span><span></span><span></span>
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* ═══════════════════════════════════════════
   Header Layout: Floating Bar
   Barra flotante con sombra, CTA prominente
   Basado en el tema Conceptly de WordPress
   ═══════════════════════════════════════════ */

/* Header wrapper: borde punteado arriba + linea accent abajo */
.header-layout-floating {
    background: transparent;
    border: none;
    box-shadow: none;
    position: relative;
    z-index: 3;
    border-top: 3px dotted #d5d5d5;
}

/* La barra flotante — replica exacta del tema Conceptly */
.hdr-float-bar {
    background: var(--header-bg-color, #ffffff);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.05);
    padding: 1.47rem 0;
    position: relative;
}
/* Linea accent naranja debajo de la barra */
.hdr-float-bar::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--header-cta-bg-color, #ff5d00);
}
.hdr-float-bar .container {
    max-width: 1140px;
}
.hdr-float-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

/* ── Brand ── */
.hdr-float-brand {
    flex-shrink: 0;
}
.hdr-float-brand-link {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    text-decoration: none !important;
    color: inherit;
}
.hdr-float-titles {
    display: flex;
    flex-direction: column;
}
.hdr-float-site-title {
    font-size: 30px;
    font-weight: 700;
    line-height: 1.2;
    white-space: normal;
}
.hdr-float-tagline {
    font-size: 12px;
    font-weight: 500;
    color: #666;
    line-height: 1.2;
    margin-top: 3px;
    white-space: normal;
}

/* ── Right side ── */
.hdr-float-right {
    display: flex;
    align-items: center;
    flex-grow: 1;
    justify-content: flex-end;
    align-self: stretch;
    gap: 0;
}

/* ── Navigation: estilo Conceptly ── */
.hdr-float-nav {
    text-align: right;
}
.hdr-float-nav ul#main-menu {
    display: inline-flex;
    list-style: none;
    margin: 0;
    padding: 0;
    align-items: center;
}
.hdr-float-nav ul#main-menu > li {
    display: inline-block;
    position: relative;
}
.hdr-float-nav ul#main-menu > li > a {
    display: inline-flex;
    align-items: center;
    padding: 0 13px;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.3px;
    line-height: 55px;
    color: var(--header-link-color, #111) !important;
    text-decoration: none !important;
    white-space: normal;
    transition: color 0.2s ease;
}
.hdr-float-nav ul#main-menu > li > a:hover {
    color: var(--header-link-hover-color, #ff5d00) !important;
}
/* Activo */
.hdr-float-nav ul#main-menu > li.current > a,
.hdr-float-nav ul#main-menu > li.current-menu-item > a,
.hdr-float-nav ul#main-menu > li.active > a {
    color: var(--header-link-hover-color, #ff5d00) !important;
}

/* Submenus */
.hdr-float-nav ul#main-menu .submenu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: #fff;
    min-width: 200px;
    z-index: 100;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    list-style: none;
    padding: 6px 0;
    margin: 0;
}
.hdr-float-nav ul#main-menu li:hover > .submenu {
    display: block;
}
.hdr-float-nav ul#main-menu .submenu li a {
    display: block;
    padding: 8px 18px;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    text-decoration: none;
    transition: all 0.2s;
}
.hdr-float-nav ul#main-menu .submenu li a:hover,
.hdr-float-nav ul#main-menu .submenu li.current a,
.hdr-float-nav ul#main-menu .submenu li.current-menu-item a {
    background: rgba(0,0,0,0.04);
    color: var(--header-link-hover-color, #ff5d00);
}

/* ── CTA Button: estilo Conceptly — solido, border-radius minimo ── */
.hdr-float-cta {
    display: inline-block;
    padding: 0 24px;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    line-height: 48px;
    color: var(--header-cta-text-color, #fff) !important;
    background: var(--header-cta-bg-color, #ff5d00);
    border-radius: 3px;
    text-decoration: none !important;
    transition: all 0.3s cubic-bezier(.645,.045,.355,1);
    white-space: nowrap;
    margin-left: 12px;
}
.hdr-float-cta:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* Search icon */
.hdr-float-search {
    font-size: 16px;
    color: var(--header-link-color, #111);
    margin-left: 14px;
    transition: color 0.2s;
}
.hdr-float-search:hover {
    color: var(--header-link-hover-color, #ff5d00);
}

/* Actions */
.hdr-float-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: 12px;
}

/* ─── Responsive ─── */
@media (max-width: 969px) {
    .hdr-float-row {
        min-height: 60px;
        flex-wrap: wrap;
    }
    .hdr-float-site-title { font-size: 24px; }
    .hdr-float-tagline { display: none; }
    .hdr-float-nav { display: none; }
    .hdr-float-nav.mobile-open {
        display: block;
        width: 100%;
        order: 3;
        text-align: left;
    }
    .hdr-float-nav.mobile-open ul#main-menu {
        flex-direction: column;
        width: 100%;
        align-items: stretch;
    }
    .hdr-float-nav.mobile-open ul#main-menu > li {
        display: block;
        border-bottom: 1px solid #e0e0e0;
    }
    .hdr-float-nav.mobile-open ul#main-menu > li:first-child {
        border-top: 1px solid #e0e0e0;
    }
    .hdr-float-nav.mobile-open ul#main-menu > li > a {
        display: block;
        padding: 0 15px;
        line-height: 48px;
        font-size: 16px;
    }
    .hdr-float-nav .submenu {
        position: static !important;
        box-shadow: none !important;
        background: #f9f9f9 !important;
    }
    .hdr-float-nav .submenu li a { padding-left: 30px !important; }
    .hdr-float-cta { padding: 0 18px; font-size: 13px; line-height: 40px; }
}
</style>
