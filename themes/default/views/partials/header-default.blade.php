@php
    $headerSticky = $headerSticky ?? false;
    $ctaEnabled = $ctaEnabled ?? false;
    $ctaUrl = $ctaUrl ?? '#';
    $ctaText = $ctaText ?? '';
    $langSelectorEnabled = $langSelectorEnabled ?? false;
    $showLangSelector = $showLangSelector ?? false;
    $currentLang = $currentLang ?? 'es';
    $languages = $languages ?? [];
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-default" id="main-header">
    <div class="container">
        <div class="header-container">
            <div class="header-logo">
                <a href="{{ url('/') }}" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
                    @php
                        $showLogo = site_setting('show_logo', '1') === '1';
                        $showTitle = site_setting('show_title', '0') === '1';
                        $siteName = site_setting('site_name', '');
                        $logoPath = site_setting('site_logo', '');
                        $defaultLogo = asset('img/musedock_logo.png');
                    @endphp

                    @if($showLogo)
                        <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                             alt="{{ $siteName }}"
                             style="max-height: 50px; width: auto;"
                             onerror="this.onerror=null; this.src='{{ $defaultLogo }}';">
                    @endif

                    @if($showTitle)
                        @php
                            $logoTextColor = themeOption('header.header_logo_text_color', '#1a2a40');
                            $logoFontFamily = themeOption('header.header_logo_font', 'inherit');
                        @endphp
                        <span class="site-title" style="font-size: 24px; font-weight: bold; color: {{ $logoTextColor }}; font-family: {!! $logoFontFamily !!};">
                            {{ $siteName }}
                        </span>
                    @endif
                </a>
            </div>

            <div class="header-right-content">
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
                    @if($ctaEnabled)
                        <a href="{{ $ctaUrl }}" class="header-btn">
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
