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
    $taglineEnabled = themeOption('header.header_tagline_enabled', true);
    $logoPath = site_setting('site_logo', '');
    $defaultLogo = asset('img/musedock_logo.png');
@endphp

<header class="musedock-header {{ $headerSticky ? 'enable-sticky' : '' }} header-layout-tema1" id="main-header">
    {{-- TOPBAR: Usa el mismo grid Bootstrap que el footer para alineación perfecta --}}
    <div class="header-tema1-topbar">
        <div class="container">
            <div class="row">
                {{-- Columnas vacías para mantener la estructura del footer (col-xl-4 + col-xl-2 + col-xl-3 = 9) --}}
                <div class="col-xl-9 col-lg-9 col-md-6 d-none d-md-block">
                    {{-- Espacio vacío - alinea con las primeras 3 columnas del footer --}}
                </div>

                {{-- Última columna: Menu alineado con "Contacto" del footer (col-xl-3) --}}
                <div class="col-xl-3 col-lg-3 col-md-6 col-12">
                    <div class="header-tema1-topbar-inner">
                        {{-- Menu de navegacion alineado a la derecha --}}
                        <nav class="header-tema1-nav main-navigation">
                            @custommenu('nav', null, [
                                'ul_id' => 'main-menu',
                                'nav_class' => 'header-tema1-menu',
                                'li_class' => '',
                                'a_class' => '',
                                'submenu_class' => 'submenu'
                            ])
                        </nav>

                        {{-- Menu toggle para movil --}}
                        <button type="button" class="menu-toggle header-tema1-menu-toggle" id="menu-toggle" aria-label="Abrir menu">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- LOGO SECTION: Logo + Nombre + Tagline --}}
    <div class="header-tema1-logo-section">
        <div class="container">
            <a href="{{ url('/') }}" class="header-tema1-brand">
                @if($showLogo)
                    <img
                        src="{{ $logoPath ? public_file_url($logoPath) : $defaultLogo }}"
                        alt="{{ $siteName }}"
                        class="header-tema1-logo"
                        onerror="this.onerror=null; this.src='{{ $defaultLogo }}';"
                    >
                @endif

                <div class="header-tema1-brand-text">
                    @if($showTitle && $siteName)
                        <span class="header-tema1-title">{{ $siteName }}</span>
                    @endif
                    @if($taglineEnabled && $siteDescription)
                        <span class="header-tema1-tagline">{{ $siteDescription }}</span>
                    @endif
                </div>
            </a>
        </div>
    </div>
</header>
