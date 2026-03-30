@php
    \Screenart\Musedock\Services\TranslationService::setContext('tenant');
    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
    \Screenart\Musedock\Services\TranslationService::load($currentLang, 'tenant');

    $footerSiteName = site_setting('site_name', 'MuseDock');
    $sidebarAccentColor = themeOption('header.header_link_hover_color', '#f1c311');

    $pdo = \Screenart\Musedock\Database::connect();
    $tenantId = tenant_id();

    // Helper: load footer menu by location
    $loadFooterMenu = function($location) use ($pdo, $tenantId, $currentLang) {
        $condition = $tenantId ? "m.tenant_id = ?" : "m.tenant_id IS NULL";
        $params = $tenantId ? [$tenantId] : [];
        $stmt = $pdo->prepare("SELECT m.id, m.title, m.show_title FROM site_menus m WHERE m.location = '{$location}' AND {$condition} LIMIT 1");
        $stmt->execute($params);
        $menu = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$menu) return null;

        // Get translated title
        $stmt = $pdo->prepare("SELECT mt.title FROM site_menu_translations mt WHERE mt.menu_id = ? AND mt.locale = ? ORDER BY mt.id DESC LIMIT 1");
        $stmt->execute([$menu['id'], $currentLang]);
        $title = $stmt->fetchColumn() ?: ($menu['title'] ?? '');
        return ['id' => $menu['id'], 'title' => $title, 'show_title' => (bool)($menu['show_title'] ?? 1)];
    };

    $footer1 = $loadFooterMenu('footer1');
    $footer2 = $loadFooterMenu('footer2');
    $footer3 = $loadFooterMenu('footer3');
@endphp
<footer class="footer-sidebar-layout">
    <div class="footer-sidebar-main" style="background-color: var(--footer-bg-color, #292929);">
        <div class="container">
            <div class="row">
                {{-- Column 1: footer1 menu OR About fallback --}}
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                    <div class="footer-sidebar-block">
                        @if($footer1)
                            @if($footer1['title'] && $footer1['show_title'])
                                <h4 class="footer-sidebar-heading">
                                    {{ $footer1['title'] }}
                                    <span class="footer-sidebar-accent" style="background: {{ $sidebarAccentColor }};"></span>
                                </h4>
                            @endif
                            @custommenu('footer1', null, [
                                'nav_class' => 'footer-sidebar-menu',
                                'li_class' => '',
                                'a_class' => '',
                                'submenu_class' => 'submenu'
                            ])
                        @else
                            <h4 class="footer-sidebar-heading">
                                {{ site_setting('footer_col1_title', $currentLang === 'en' ? 'About' : 'Sobre mí') }}
                                <span class="footer-sidebar-accent" style="background: {{ $sidebarAccentColor }};"></span>
                            </h4>
                            <p class="footer-sidebar-text">{{ translatable_site_setting('footer_short_description', '') }}</p>
                        @endif
                        @include('partials.widget-renderer', ['areaSlug' => 'footer1'])
                    </div>
                </div>

                {{-- Column 2: footer2 menu OR Contact fallback --}}
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                    <div class="footer-sidebar-block">
                        @if($footer2)
                            @if($footer2['title'] && $footer2['show_title'])
                                <h4 class="footer-sidebar-heading">
                                    {{ $footer2['title'] }}
                                    <span class="footer-sidebar-accent" style="background: {{ $sidebarAccentColor }};"></span>
                                </h4>
                            @endif
                            @custommenu('footer2', null, [
                                'nav_class' => 'footer-sidebar-menu',
                                'li_class' => '',
                                'a_class' => '',
                                'submenu_class' => 'submenu'
                            ])
                        @else
                            <h4 class="footer-sidebar-heading">
                                {{ site_setting('footer_col4_title', $currentLang === 'en' ? 'Contact' : 'Contacto') }}
                                <span class="footer-sidebar-accent" style="background: {{ $sidebarAccentColor }};"></span>
                            </h4>
                            <ul class="footer-sidebar-contact-list">
                                @if(site_setting('contact_email'))
                                    <li><i class="far fa-envelope me-2"></i> {{ strtoupper(site_setting('contact_email')) }}</li>
                                @endif
                                @if(site_setting('contact_phone'))
                                    <li><i class="fas fa-phone me-2"></i> {{ site_setting('contact_phone') }}</li>
                                @endif
                                @if(site_setting('contact_address'))
                                    <li><i class="fas fa-map-marker-alt me-2"></i> {{ site_setting('contact_address') }}</li>
                                @endif
                            </ul>
                            @if(site_setting('contact_email'))
                                <p class="footer-sidebar-text mt-2" style="font-size: 0.85rem;">
                                    {{ $currentLang === 'en' ? 'Feel free to contact us via email or social media.' : 'Puedes escribirnos al mail, o enviarnos un mensaje por redes sociales.' }}
                                </p>
                            @endif
                        @endif
                        @include('partials.widget-renderer', ['areaSlug' => 'footer2'])
                    </div>
                </div>

                {{-- Column 3: footer3 menu OR widget area --}}
                <div class="col-lg-4 col-md-12">
                    <div class="footer-sidebar-block">
                        @if($footer3)
                            @if($footer3['title'] && $footer3['show_title'])
                                <h4 class="footer-sidebar-heading">
                                    {{ $footer3['title'] }}
                                    <span class="footer-sidebar-accent" style="background: {{ $sidebarAccentColor }};"></span>
                                </h4>
                            @endif
                            @custommenu('footer3', null, [
                                'nav_class' => 'footer-sidebar-menu',
                                'li_class' => '',
                                'a_class' => '',
                                'submenu_class' => 'submenu'
                            ])
                        @else
                            @include('partials.widget-renderer', ['areaSlug' => 'footer3'])
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Copyright bar --}}
    <div class="footer-sidebar-copyright" style="background-color: var(--footer-bg-color, #292929);">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                <p class="mb-0" style="color: var(--footer-text-color, #aaa); font-size: 0.85rem;">
                    {!! site_setting('footer_copyright', '&copy; Copyright ' . $footerSiteName . ' ' . date('Y') . '.') !!}
                </p>
                <div class="footer-sidebar-social">
                    @if(site_setting('social_facebook', ''))
                        <a href="{{ site_setting('social_facebook') }}" target="_blank"><i class="fab fa-facebook-f"></i></a>
                    @endif
                    @if(site_setting('social_twitter', ''))
                        <a href="{{ site_setting('social_twitter') }}" target="_blank"><i class="fab fa-twitter"></i></a>
                    @endif
                    @if(site_setting('social_instagram', ''))
                        <a href="{{ site_setting('social_instagram') }}" target="_blank"><i class="fab fa-instagram"></i></a>
                    @endif
                    @if(site_setting('social_linkedin', ''))
                        <a href="{{ site_setting('social_linkedin') }}" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                    @endif
                    @if(site_setting('social_youtube', ''))
                        <a href="{{ site_setting('social_youtube') }}" target="_blank"><i class="fab fa-youtube"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</footer>
