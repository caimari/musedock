@php
    // Cargar configuraciones dinámicas
    $showLogo = site_setting('show_logo', '1') === '1';
    $showTitle = site_setting('show_title', '0') === '1';
    $siteName = site_setting('site_name', '');
    $logoPath = site_setting('site_logo', '');
    $headerSticky = themeOption('header.header_sticky', true);

    // CTA Button
    $ctaEnabled = themeOption('header.header_cta_enabled', false);
    $currentLang = detectLanguage();
    $ctaText = $currentLang === 'en'
        ? themeOption('header.header_cta_text_en', 'Sign In')
        : themeOption('header.header_cta_text_es', 'Iniciar Sesión');
    $ctaUrl = themeOption('header.header_cta_url', '#');

    // Búsqueda
    $searchEnabled = themeOption('header.header_search_enabled', false);
    $searchMode = themeOption('header.header_search_mode', 'modal');

    // Selector de idiomas
    $pdo = \Screenart\Musedock\Database::connect();
    $tenantId = tenant_id();
    if ($tenantId) {
        $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id = ? AND active = 1 ORDER BY order_position ASC");
        $stmt->execute([$tenantId]);
    } else {
        $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id IS NULL AND active = 1 ORDER BY order_position ASC");
        $stmt->execute();
    }
    $languages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $langSelectorEnabled = themeOption('header.header_lang_selector_enabled', true);
@endphp

<!-- ====== Header Start ====== -->
<header class="ud-header{{ $headerSticky ? ' sticky-header' : '' }}">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <nav class="navbar navbar-expand-lg">
                    <a class="navbar-brand" href="{{ url('/') }}">
                        @php
                            $defaultLogo = asset('themes/play-bootstrap/img/logo/logo.svg');
                        @endphp

                        @if($showLogo)
                            <img src="{{ $logoPath ? asset($logoPath) : $defaultLogo }}"
                                 alt="{{ $siteName }}"
                                 onerror="this.onerror=null; this.src='{{ $defaultLogo }}';" />
                        @endif

                        @if($showTitle)
                            <span class="navbar-brand-text">{{ $siteName }}</span>
                        @endif
                    </a>

                    <button class="navbar-toggler" aria-label="Toggle navigation">
                        <span class="toggler-icon"></span>
                        <span class="toggler-icon"></span>
                        <span class="toggler-icon"></span>
                    </button>

                    <div class="navbar-collapse">
                        {{-- Menú dinámico --}}
                        @custommenu('nav', null, [
                            'ul_id' => 'nav',
                            'ul_class' => 'navbar-nav mx-auto',
                            'li_class' => 'nav-item',
                            'a_class' => 'nav-link',
                            'submenu_class' => 'ud-submenu',
                            'submenu_item_class' => 'ud-submenu-item',
                            'submenu_link_class' => 'ud-submenu-link',
                            'parent_class' => 'nav-item-has-children'
                        ])

                        {{-- Selector de idiomas dentro del menú móvil --}}
                        @if($langSelectorEnabled && count($languages) > 1)
                        <div class="mobile-lang-selector">
                            <select onchange="window.location.href='?lang=' + this.value" class="mobile-lang-select">
                                @foreach($languages as $lang)
                                    <option value="{{ $lang['code'] }}" {{ $currentLang == $lang['code'] ? 'selected' : '' }}>
                                        {{ $lang['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>

                    {{-- Search icon --}}
                    @if($searchEnabled)
                        @if($searchMode === 'page')
                        <a href="{{ url('/search') }}" class="header-search-toggle" aria-label="Buscar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </a>
                        @else
                        <button type="button" class="header-search-toggle" aria-label="Buscar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </button>
                        @endif
                    @endif

                    <div class="navbar-btn">
                        {{-- Selector de idiomas (desktop) --}}
                        @if($langSelectorEnabled && count($languages) > 1)
                        <div class="language-selector">
                            <select onchange="window.location.href='?lang=' + this.value" class="lang-select">
                                @foreach($languages as $lang)
                                    <option value="{{ $lang['code'] }}" {{ $currentLang == $lang['code'] ? 'selected' : '' }}>
                                        {{ $lang['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        {{-- CTA Button --}}
                        @if($ctaEnabled)
                        <a href="{{ $ctaUrl }}" class="ud-main-btn ud-login-btn">
                            {{ $ctaText }}
                        </a>
                        @endif
                    </div>
                </nav>
            </div>
        </div>
    </div>
</header>
<!-- ====== Header End ====== -->
