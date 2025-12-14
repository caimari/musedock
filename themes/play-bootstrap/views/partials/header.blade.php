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
                                 style="max-height: 50px; width: auto;"
                                 onerror="this.onerror=null; this.src='{{ $defaultLogo }}';" />
                        @endif

                        @if($showTitle)
                            <span style="color: var(--header-logo-text-color); font-weight: 700; font-size: 24px;">{{ $siteName }}</span>
                        @endif
                    </a>                    <button class="navbar-toggler">
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
                            'a_class' => '',
                            'submenu_class' => 'ud-submenu',
                            'submenu_item_class' => 'ud-submenu-item',
                            'submenu_link_class' => 'ud-submenu-link',
                            'parent_class' => 'nav-item-has-children'
                        ])
                    </div>

                    <div class="navbar-btn d-none d-sm-inline-block">
                        {{-- Selector de idiomas --}}
                        @if($langSelectorEnabled && count($languages) > 1)
                        <div class="language-selector" style="display: inline-block; margin-right: 15px;">
                            <select onchange="window.location.href='?lang=' + this.value"
                                    class="ud-main-btn ud-white-btn"
                                    style="padding: 8px 15px; border: 2px solid var(--primary-color); background: transparent; color: var(--primary-color); cursor: pointer; border-radius: 5px;">
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
                        <a href="{{ $ctaUrl }}" class="ud-main-btn ud-login-btn" style="background: var(--header-cta-bg-color); color: var(--header-cta-text-color);">
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
