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

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-centered" id="main-header">
    <div class="container">
        @php
            // Fallbacks: si no existe nav_left/nav_right, usar nav (para que "funcione" sin configurar menús nuevos)
            $leftMenuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav_left', null, [
                'ul_id' => 'main-menu-left',
                'nav_class' => '',
                'li_class' => '',
                'a_class' => '',
                'submenu_class' => 'submenu'
            ]);
            if (trim($leftMenuHtml) === '') {
                $leftMenuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav', null, [
                    'ul_id' => 'main-menu-left',
                    'nav_class' => '',
                    'li_class' => '',
                    'a_class' => '',
                    'submenu_class' => 'submenu'
                ]);
            }

            $rightMenuHtml = \Screenart\Musedock\Helpers\MenuHelper::renderCustomMenu('nav_right', null, [
                'ul_id' => 'main-menu-right',
                'nav_class' => '',
                'li_class' => '',
                'a_class' => '',
                'submenu_class' => 'submenu'
            ]);

            $taglineEnabled = themeOption('header.header_tagline_enabled', true);
            $siteTagline = site_setting('site_description', '');
        @endphp

        <div class="header-centered-grid">
            <div class="header-centered-left header-menu">
                <nav class="main-navigation">
                    {!! $leftMenuHtml !!}
                </nav>
            </div>

            <div class="header-centered-logo">
                <a href="{{ url('/') }}" class="header-brand-link">
                    @php
                        $showLogo = site_setting('show_logo', '1') === '1';
                        $showTitle = site_setting('show_title', '0') === '1';
                        $siteName = site_setting('site_name', '');
                        $logoPath = site_setting('site_logo', '');
                        $defaultLogo = asset('img/musedock_logo.png');
                    @endphp

                    <div class="header-brand-block">
                        <div class="header-brand-top">
                            @if($showLogo)
                                <img src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                                     alt="{{ $siteName }}"
                                     class="header-brand-logo"
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
                        </div>

                        @if($taglineEnabled && !empty($siteTagline))
                            <div class="header-site-tagline">{{ $siteTagline }}</div>
                        @endif
                    </div>
                </a>
            </div>

            <div class="header-centered-right">
                <div class="header-menu header-menu-right">
                    <nav class="main-navigation">
                        {!! $rightMenuHtml !!}
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
