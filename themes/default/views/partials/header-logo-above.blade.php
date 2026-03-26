@php
    $headerSticky = $headerSticky ?? false;
    $ctaEnabled = $ctaEnabled ?? false;
    $ctaUrl = $ctaUrl ?? '#';
    $ctaText = $ctaText ?? '';
    $langSelectorEnabled = $langSelectorEnabled ?? false;
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
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-logo-above" id="main-header">
    {{-- Logo row: centered --}}
    <div class="header-logo-above-brand" style="display:flex;justify-content:center;padding:24px 0 16px;border-bottom:1px solid rgba(0,0,0,0.06);background:var(--header-bg-color,#fff);">
        <a href="{{ url('/') }}" style="text-decoration:none;color:inherit;display:inline-block;">
            <div style="display:flex;flex-direction:column;align-items:center;text-align:center;">
                @if($showLogo)
                    <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                         alt="{{ $siteName }}"
                         style="max-height:120px;width:auto;display:block;"
                         onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                @endif

                @if($showTitle)
                    <div style="font-size:26px;font-weight:bold;color:{{ $logoTextColor }};font-family:{!! $logoFontFamily !!};margin-top:6px;">
                        {{ $siteName }}
                    </div>
                @endif

                @if($taglineEnabled && $showSubtitle && !empty($siteTagline))
                    <div style="margin-top:4px;font-size:13px;color:#666;text-align:center;">{{ $siteTagline }}</div>
                @endif
            </div>
        </a>
    </div>

    {{-- Navigation bar: below logo --}}
    @php
        $menuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav', null, [
            'ul_id' => 'main-menu',
            'nav_class' => '',
            'li_class' => '',
            'a_class' => '',
            'submenu_class' => 'submenu'
        ]);
        $hasMenu = !empty(trim(strip_tags($menuHtml ?? '')));
        $hasNavContent = $hasMenu || $ctaEnabled || ($langSelectorEnabled && $showLangSelector);
    @endphp
    @if($hasNavContent)
    <div class="header-logo-above-nav">
        <div class="container">
            <div class="header-logo-above-nav-inner">
                @if($hasMenu)
                <nav class="main-navigation header-menu">
                    {!! $menuHtml !!}
                </nav>
                @endif

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

                    @if($hasMenu)
                    <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
                        <span></span><span></span><span></span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</header>
