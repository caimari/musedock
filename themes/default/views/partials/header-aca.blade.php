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
    $siteDescription = site_setting('site_description', '');
    $logoPath = site_setting('site_logo', '');
    $defaultLogo = asset('img/musedock_logo.png');
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-aca" id="main-header">
    <div class="header-aca-box">
        <div class="header-aca-row">
            <div class="header-aca-brand">
                <a href="{{ url('/') }}" class="header-aca-brand-link">
                    <div class="header-aca-brand-top">
                        @if($showLogo)
                            <img
                                src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                                alt="{{ $siteName }}"
                                class="header-aca-logo"
                                onerror="this.onerror=null; this.src='{{ $defaultLogo }}';"
                            >
                        @endif

                        @if($showTitle && $siteName)
                            <div class="header-aca-title">{{ $siteName }}</div>
                        @endif
                    </div>

                    @if($siteDescription)
                        <div class="header-aca-tagline">{{ $siteDescription }}</div>
                    @endif
                </a>
            </div>

            <div class="header-aca-right">
                <nav class="main-navigation header-aca-nav">
                    @custommenu('nav', null, [
                        'ul_id' => 'main-menu',
                        'nav_class' => '',
                        'li_class' => '',
                        'a_class' => '',
                        'submenu_class' => 'submenu'
                    ])
                </nav>

                <div class="header-aca-actions">
                    @if($ctaEnabled)
                        <a href="{{ $ctaUrl }}" class="header-btn header-aca-cta">
                            {{ $ctaText }}
                        </a>
                    @endif

                    @if($langSelectorEnabled && $showLangSelector)
                        <div class="lang-select">
                            <button type="button" class="lang-btn">
                                {{ strtoupper($currentLang) }}
                            </button>
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
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
