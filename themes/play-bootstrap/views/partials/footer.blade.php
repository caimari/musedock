@php
    // Cargar configuraciones dinámicas
    $siteName = site_setting('site_name', '');
    $logoPath = site_setting('site_logo', '');
    $currentLang = detectLanguage();
    $footerDesc = translatable_site_setting('footer_short_description', $currentLang, '');
    $footerCopyright = site_setting('footer_copyright', '&copy; ' . date('Y') . ' ' . $siteName . '. ' . __('footer.all_rights_reserved'));

    // Redes sociales
    $socialFacebook = site_setting('social_facebook', '');
    $socialTwitter = site_setting('social_twitter', '');
    $socialInstagram = site_setting('social_instagram', '');
    $socialLinkedin = site_setting('social_linkedin', '');
    $socialYoutube = site_setting('social_youtube', '');
    $socialPinterest = site_setting('social_pinterest', '');
    $socialTiktok = site_setting('social_tiktok', '');

    // Información de contacto
    $contactEmail = site_setting('contact_email', '');
    $contactPhone = site_setting('contact_phone', '');
    $contactAddress = site_setting('contact_address', '');

    // --- Widgets (footer1..footer5) ---
    $tenantIdForWidgets = \Screenart\Musedock\Services\TenantManager::currentTenantId();
    $themeSlugForWidgets = setting('default_theme', 'default');
    $widgetContent = (isset($widgetContent) && is_array($widgetContent)) ? $widgetContent : [];

    $widgetAreasToRender = ['footer1', 'footer2', 'footer3', 'footer4', 'footer5'];
    $widgetHtmlByArea = [];
    $needsFallbackRender = false;
    foreach ($widgetAreasToRender as $areaSlug) {
        if (array_key_exists($areaSlug, $widgetContent)) {
            $widgetHtmlByArea[$areaSlug] = $widgetContent[$areaSlug];
        } else {
            $needsFallbackRender = true;
        }
    }

    if ($needsFallbackRender) {
        \Screenart\Musedock\Widgets\WidgetManager::registerAvailableWidgets();
        foreach ($widgetAreasToRender as $areaSlug) {
            if (!array_key_exists($areaSlug, $widgetHtmlByArea)) {
                $widgetHtmlByArea[$areaSlug] = \Screenart\Musedock\Widgets\WidgetManager::renderArea($areaSlug, $tenantIdForWidgets, $themeSlugForWidgets);
            }
        }
    }

    $widgetAreaIsEmpty = function (?string $html): bool {
        if ($html === null) {
            return true;
        }
        $html = trim($html);
        if ($html === '') {
            return true;
        }
        return (bool)preg_match('/^<div\\s+class="widget-area\\b[^"]*"[^>]*>\\s*<!--\\s*Área de Widgets\\s+\\x27[^\\x27]*\\x27\\s+vacía\\s*-->\\s*<\\/div>$/u', $html);
    };

    $hasFooter1Widgets = !$widgetAreaIsEmpty($widgetHtmlByArea['footer1'] ?? null);
    $hasFooter2Widgets = !$widgetAreaIsEmpty($widgetHtmlByArea['footer2'] ?? null);
    $hasFooter3Widgets = !$widgetAreaIsEmpty($widgetHtmlByArea['footer3'] ?? null);
    $hasFooter4Widgets = !$widgetAreaIsEmpty($widgetHtmlByArea['footer4'] ?? null);
    $hasFooter5Widgets = !$widgetAreaIsEmpty($widgetHtmlByArea['footer5'] ?? null);

    // --- Menús (compatibilidad: footer-1 y footer-2) ---
    $tenantIdForMenus = tenant_id();
    $pdo = \Screenart\Musedock\Database::connect();

    $loadMenuMeta = function (string $location) use ($pdo, $tenantIdForMenus): array {
        if ($tenantIdForMenus) {
            $stmt = $pdo->prepare("SELECT id, title, show_title FROM site_menus WHERE tenant_id = ? AND location = ? LIMIT 1");
            $stmt->execute([$tenantIdForMenus, $location]);
        } else {
            $stmt = $pdo->prepare("SELECT id, title, show_title FROM site_menus WHERE tenant_id IS NULL AND location = ? LIMIT 1");
            $stmt->execute([$location]);
        }
        $menu = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($menu) ? $menu : [];
    };

    $footerMenu1 = $loadMenuMeta('footer-1');
    $footerMenu2 = $loadMenuMeta('footer-2');
    $hasFooterMenu1 = !empty($footerMenu1);
    $hasFooterMenu2 = !empty($footerMenu2);

    $getMenuTitle = function (array $menu, string $fallbackTitle) use ($pdo, $currentLang): string {
        $menuId = $menu['id'] ?? null;
        if (!$menuId) {
            return $fallbackTitle;
        }
        $stmt = $pdo->prepare("SELECT title FROM site_menu_translations WHERE menu_id = ? AND locale = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$menuId, $currentLang]);
        $t = $stmt->fetchColumn();
        $t = is_string($t) ? trim($t) : '';
        if ($t !== '') {
            return $t;
        }
        $t2 = isset($menu['title']) ? trim((string)$menu['title']) : '';
        return $t2 !== '' ? $t2 : $fallbackTitle;
    };

    $footerMenu1Title = $getMenuTitle($footerMenu1, $currentLang === 'en' ? 'Menu' : 'Menú');
    $footerMenu2Title = $getMenuTitle($footerMenu2, $currentLang === 'en' ? 'Links' : 'Enlaces');

    $hasContactData = (bool)($contactEmail || $contactPhone || $contactAddress);
@endphp

<!-- ====== Footer Start ====== -->
<footer class="ud-footer wow fadeInUp" data-wow-delay=".15s" style="background-color: var(--footer-bg-color); color: var(--footer-text-color);">
    <div class="shape shape-2">
        <img src="{{ asset('themes/play-bootstrap/img/footer/shape-2.svg') }}" alt="shape" />
    </div>
    <div class="shape shape-3">
        <img src="{{ asset('themes/play-bootstrap/img/footer/shape-3.svg') }}" alt="shape" />
    </div>

    <div class="ud-footer-widgets">
        <div class="container">
            <div class="row">
                {{-- Primera columna - Logo y descripción --}}
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="ud-widget">
                        @if($hasFooter1Widgets)
                            {!! $widgetHtmlByArea['footer1'] !!}
                        @else
                            <a href="{{ url('/') }}" class="ud-footer-logo">
                                @if($logoPath)
                                    <img src="{{ asset($logoPath) }}" alt="{{ $siteName }}" style="max-height: 50px;" />
                                @else
                                    <span style="color: var(--footer-heading-color); font-weight: 700; font-size: 24px;">{{ $siteName }}</span>
                                @endif
                            </a>
                            @if($footerDesc)
                            <p class="ud-widget-desc" style="color: var(--footer-text-color);">
                                {{ $footerDesc }}
                            </p>
                            @endif

                            {{-- Redes sociales --}}
                            @if($socialFacebook || $socialTwitter || $socialInstagram || $socialLinkedin || $socialYoutube || $socialPinterest || $socialTiktok)
                            <ul class="ud-widget-socials">
                                @if($socialFacebook)
                                <li>
                                    <a href="{{ $socialFacebook }}" target="_blank" rel="noopener noreferrer">
                                        <i class="lni lni-facebook-filled"></i>
                                    </a>
                                </li>
                                @endif
                                @if($socialTwitter)
                                <li>
                                    <a href="{{ $socialTwitter }}" target="_blank" rel="noopener noreferrer">
                                        <i class="lni lni-twitter-filled"></i>
                                    </a>
                                </li>
                                @endif
                                @if($socialInstagram)
                                <li>
                                    <a href="{{ $socialInstagram }}" target="_blank" rel="noopener noreferrer">
                                        <i class="lni lni-instagram-filled"></i>
                                    </a>
                                </li>
                                @endif
                                @if($socialLinkedin)
                                <li>
                                    <a href="{{ $socialLinkedin }}" target="_blank" rel="noopener noreferrer">
                                        <i class="lni lni-linkedin-original"></i>
                                    </a>
                                </li>
                                @endif
                                @if($socialYoutube)
                                <li>
                                    <a href="{{ $socialYoutube }}" target="_blank" rel="noopener noreferrer">
                                        <i class="lni lni-youtube"></i>
                                    </a>
                                </li>
                                @endif
                                @if($socialPinterest)
                                <li>
                                    <a href="{{ $socialPinterest }}" target="_blank" rel="noopener noreferrer">
                                        <i class="lni lni-pinterest"></i>
                                    </a>
                                </li>
                                @endif
                                @if($socialTiktok)
                                <li>
                                    <a href="{{ $socialTiktok }}" target="_blank" rel="noopener noreferrer">
                                        <i class="lni lni-tiktok"></i>
                                    </a>
                                </li>
                                @endif
                            </ul>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Segunda columna - Footer 2 (widgets) / Menú (fallback) --}}
                @if($hasFooter2Widgets || $hasFooterMenu1)
                <div class="col-xl-2 col-lg-2 col-md-6 col-sm-6">
                    <div class="ud-widget">
                        @if($hasFooter2Widgets)
                            {!! $widgetHtmlByArea['footer2'] !!}
                        @else
                            @if(!empty($footerMenu1Title) && (bool)($footerMenu1['show_title'] ?? 1))
                            <h5 class="ud-widget-title" style="color: var(--footer-heading-color);">
                                {{ $footerMenu1Title }}
                            </h5>
                            @endif
                            @custommenu('footer-1', null, [
                                'ul_class' => 'ud-widget-links',
                                'li_class' => '',
                                'a_class' => '',
                                'a_style' => 'color: var(--footer-link-color);'
                            ])
                        @endif
                    </div>
                </div>
                @endif

                {{-- Tercera columna - Footer 3 (widgets) / Menú (fallback) --}}
                @if($hasFooter3Widgets || $hasFooterMenu2)
                <div class="col-xl-2 col-lg-3 col-md-6 col-sm-6">
                    <div class="ud-widget">
                        @if($hasFooter3Widgets)
                            {!! $widgetHtmlByArea['footer3'] !!}
                        @else
                            @if(!empty($footerMenu2Title) && (bool)($footerMenu2['show_title'] ?? 1))
                            <h5 class="ud-widget-title" style="color: var(--footer-heading-color);">
                                {{ $footerMenu2Title }}
                            </h5>
                            @endif
                            @custommenu('footer-2', null, [
                                'ul_class' => 'ud-widget-links',
                                'li_class' => '',
                                'a_class' => '',
                                'a_style' => 'color: var(--footer-link-color);'
                            ])
                        @endif
                    </div>
                </div>
                @endif

                {{-- Cuarta columna - Footer 4 (widgets) / Últimas entradas (fallback) --}}
                @php
                    $latestPosts = [];
                    if (!$hasFooter4Widgets) {
                        $blogPublic = site_setting('blog_public', '1');
                        if ($blogPublic === '1') {
                            try {
                                $tenantId = tenant_id();
                                if ($tenantId) {
                                    $stmt = $pdo->prepare("
                                        SELECT p.id, p.slug, pt.title
                                        FROM posts p
                                        INNER JOIN post_translations pt ON p.id = pt.post_id
                                        WHERE p.tenant_id = ? AND p.status = 'published' AND pt.language_code = ?
                                        ORDER BY p.published_at DESC
                                        LIMIT 3
                                    ");
                                    $stmt->execute([$tenantId, $currentLang]);
                                } else {
                                    $stmt = $pdo->prepare("
                                        SELECT p.id, p.slug, pt.title
                                        FROM posts p
                                        INNER JOIN post_translations pt ON p.id = pt.post_id
                                        WHERE p.tenant_id IS NULL AND p.status = 'published' AND pt.language_code = ?
                                        ORDER BY p.published_at DESC
                                        LIMIT 3
                                    ");
                                    $stmt->execute([$currentLang]);
                                }
                                $latestPosts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                            } catch (\PDOException $e) {
                                $latestPosts = [];
                            }
                        }
                    }
                @endphp
                @if($hasFooter4Widgets || count($latestPosts) > 0)
                <div class="col-xl-2 col-lg-3 col-md-6 col-sm-6">
                    <div class="ud-widget">
                        @if($hasFooter4Widgets)
                            {!! $widgetHtmlByArea['footer4'] !!}
                        @else
                            <h5 class="ud-widget-title" style="color: var(--footer-heading-color);">
                                {{ $currentLang === 'en' ? 'Latest Posts' : 'Últimas Publicaciones' }}
                            </h5>
                            <ul class="ud-widget-links">
                                @foreach($latestPosts as $post)
                                <li>
                                    <a href="{{ url('/blog/' . $post['slug']) }}" style="color: var(--footer-link-color);">
                                        {{ $post['title'] }}
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Quinta columna (derecha) - Footer 5 (widgets) / Contacto (fallback) --}}
                @if($hasFooter5Widgets || $hasContactData)
                <div class="col-xl-3 col-lg-6 col-md-8 col-sm-10 ms-xl-auto">
                    <div class="ud-widget">
                        @if($hasFooter5Widgets)
                            {!! $widgetHtmlByArea['footer5'] !!}
                        @else
                            <h5 class="ud-widget-title" style="color: var(--footer-heading-color);">
                                {{ $currentLang === 'en' ? 'Contact' : 'Contacto' }}
                            </h5>
                            <ul class="ud-widget-links">
                                @if($contactEmail)
                                <li>
                                    <a class="ud-contact-link" href="mailto:{{ $contactEmail }}" style="color: var(--footer-link-color);">
                                        <i class="lni lni-envelope"></i>
                                        <span class="ud-contact-text">{{ $contactEmail }}</span>
                                    </a>
                                </li>
                                @endif
                                @if($contactPhone)
                                <li>
                                    <a class="ud-contact-link" href="tel:{{ $contactPhone }}" style="color: var(--footer-link-color);">
                                        <i class="lni lni-phone"></i>
                                        <span class="ud-contact-text">{{ $contactPhone }}</span>
                                    </a>
                                </li>
                                @endif
                                @if($contactAddress)
                                <li>
                                    <span class="ud-contact-link" style="color: var(--footer-text-color);">
                                        <i class="lni lni-map-marker"></i>
                                        <span class="ud-contact-text">{{ $contactAddress }}</span>
                                    </span>
                                </li>
                                @endif
                            </ul>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Footer Bottom --}}
    <div class="ud-footer-bottom">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="ud-footer-bottom-right" style="color: var(--footer-text-color);">
                        {!! $footerCopyright !!}
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- ====== Footer End ====== -->
