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
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-aca" id="main-header">
    <div class="container">
        <div class="hdr-bold-row">
            {{-- Brand: logo + title + tagline --}}
            <div class="hdr-bold-brand">
                <a href="{{ url('/') }}" class="hdr-bold-brand-link">
                    @if($showLogo)
                        <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                             alt="{{ $siteName }}"
                             class="header-brand-logo"
                             style="max-height:{{ $logoMaxHeight }}px;"
                             onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                    @endif

                    @if($showTitle && $siteName)
                        <div class="hdr-bold-titles">
                            <div class="hdr-bold-site-title" style="color:{{ $logoTextColor }};font-family:{!! $logoFontFamily !!};">
                                {{ $siteName }}
                            </div>
                            @if($taglineEnabled && $showSubtitle && $siteDescription)
                                <div class="hdr-bold-tagline">{{ $siteDescription }}</div>
                            @endif
                        </div>
                    @endif
                </a>
            </div>

            {{-- Right side: menu + CTA + extras --}}
            <div class="hdr-bold-right">
                <nav class="main-navigation header-menu hdr-bold-nav">
                    @custommenu('nav', null, [
                        'ul_id' => 'main-menu',
                        'nav_class' => '',
                        'li_class' => '',
                        'a_class' => '',
                        'submenu_class' => 'submenu'
                    ])
                </nav>

                <div class="hdr-bold-actions">
                    @if($ctaEnabled)
                        <a href="{{ $ctaUrl }}" class="hdr-bold-cta">
                            {{ $ctaText }}
                        </a>
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

                    <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* ═══════════════════════════════════════════
   Header Layout: Bold CTA
   "Paper tab" flotante sobre el hero
   ═══════════════════════════════════════════ */

/* Header: transparente, el "card" interior tiene el fondo */
.header-layout-aca {
    background: transparent;
    border: none;
    box-shadow: none;
    position: relative;
    z-index: 10;
    padding: 12px 0 0;
    margin-bottom: 24px;
}
.header-layout-aca .container {
    max-width: 1300px;
    position: relative;
}
.hdr-bold-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 90px;
    padding: 20px 40px;
    gap: 30px;
    /* La "hoja de papel" */
    background: var(--header-bg-color, #fff);
    border: 1px solid #ddd;
    border-top: 3px dotted #ccc;
    border-radius: 2px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    position: relative;
}

/* Efecto "hojas apiladas" — rectangulos escalonados debajo del card */
.hdr-bold-row::before,
.hdr-bold-row::after {
    content: '';
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    background: var(--header-bg-color, #fff);
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 2px 2px;
    z-index: -1;
}
.hdr-bold-row::before {
    bottom: -5px;
    width: calc(100% - 16px);
    height: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.hdr-bold-row::after {
    bottom: -9px;
    width: calc(100% - 32px);
    height: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

/* ── Brand: logo + titulo vertical ── */
.hdr-bold-brand {
    flex-shrink: 0;
}
.hdr-bold-brand-link {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    text-decoration: none !important;
    color: inherit;
}
.hdr-bold-titles {
    display: flex;
    flex-direction: column;
}
.hdr-bold-site-title {
    font-size: 42px;
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: 2px;
}
.hdr-bold-tagline {
    font-size: 13px;
    color: #555;
    margin-top: 5px;
}

/* Footer alineado con el card del header */
body:has(.header-layout-aca) footer .container,
body:has(.header-layout-aca) .footer-area .container {
    max-width: 1300px;
    padding-left: 40px;
    padding-right: 40px;
}

/* Cuando hay hero/imagen de cabecera: el header se superpone */
.header-layout-aca:has(~ main .page-hero),
.header-layout-aca:has(~ main .hero-section),
.header-layout-aca:has(~ .page-hero),
body:has(.page-hero) .header-layout-aca,
body:has(.hero-section) .header-layout-aca {
    margin-bottom: -30px;
}

/* ── Right: nav + CTA + extras ── */
.hdr-bold-right {
    display: flex;
    align-items: center;
    gap: 30px;
}

/* ── Navigation: plano, sin adornos ── */
.hdr-bold-nav ul#main-menu {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    align-items: center;
    gap: 6px;
}
.hdr-bold-nav ul#main-menu > li {
    position: relative;
}
.hdr-bold-nav ul#main-menu > li > a {
    display: block;
    padding: 8px 16px;
    font-size: 16px;
    font-weight: 500;
    color: var(--header-link-color, #222) !important;
    text-decoration: none !important;
    transition: color 0.2s ease;
    white-space: nowrap;
}
.hdr-bold-nav ul#main-menu > li > a:hover {
    color: var(--header-link-hover-color, #ff5a00) !important;
}
/* Activo: color accent, sin subrayado */
.hdr-bold-nav ul#main-menu > li.current > a,
.hdr-bold-nav ul#main-menu > li.current-menu-item > a,
.hdr-bold-nav ul#main-menu > li.active > a {
    color: var(--header-link-hover-color, #ff5a00) !important;
    font-weight: 600;
}

/* Submenus */
.hdr-bold-nav ul#main-menu .submenu {
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
.hdr-bold-nav ul#main-menu li:hover > .submenu {
    display: block;
}
.hdr-bold-nav ul#main-menu .submenu li a {
    display: block;
    padding: 8px 18px;
    font-size: 14px;
    color: #444;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
}
.hdr-bold-nav ul#main-menu .submenu li a:hover,
.hdr-bold-nav ul#main-menu .submenu li.current a,
.hdr-bold-nav ul#main-menu .submenu li.current-menu-item a {
    background: rgba(0,0,0,0.04);
    color: var(--header-link-hover-color, #ff5a00);
}

/* ── CTA Button: solido, recto, prominente ── */
.hdr-bold-cta {
    display: inline-block;
    padding: 12px 24px;
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--header-cta-text-color, #fff) !important;
    background: var(--header-cta-bg-color, #ff5a00);
    border-radius: 3px;
    text-decoration: none !important;
    transition: background 0.2s ease;
    white-space: nowrap;
}
.hdr-bold-cta:hover {
    background: #e65100;
}

/* Actions */
.hdr-bold-actions {
    display: flex;
    align-items: center;
    gap: 14px;
}

/* ─── Responsive ─── */
@media (max-width: 969px) {
    .hdr-bold-row {
        min-height: 60px;
        flex-wrap: wrap;
        gap: 12px;
        padding: 12px 16px;
    }
    .hdr-bold-site-title { font-size: 28px; }
    .hdr-bold-nav { display: none; }
    .hdr-bold-nav.mobile-open {
        display: block;
        width: 100%;
        order: 3;
    }
    .hdr-bold-nav.mobile-open ul#main-menu {
        flex-direction: column;
        padding: 8px 0;
        gap: 0;
    }
    .hdr-bold-nav.mobile-open ul#main-menu > li > a {
        padding: 12px 0;
        border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .hdr-bold-nav .submenu {
        position: static !important;
        box-shadow: none !important;
        background: #f9f9f9 !important;
    }
    .hdr-bold-nav .submenu li a { padding-left: 24px !important; }
    .hdr-bold-cta { padding: 10px 20px; font-size: 13px; }
}
</style>
