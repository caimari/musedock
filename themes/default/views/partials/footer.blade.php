@php
    // Asegurar contexto tenant para traducciones del frontend
    \Screenart\Musedock\Services\TranslationService::setContext('tenant');
    // Usar detectLanguage() que respeta force_lang
    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
    \Screenart\Musedock\Services\TranslationService::load($currentLang, 'tenant');

    // Verificar si mostrar logo/título (mismo setting que el header)
    // site_setting() ya respeta tenant/global.
    $showFooterLogo = site_setting('show_logo', '1') === '1';
    $showFooterTitle = site_setting('show_title', '0') === '1';
    $footerSiteName = site_setting('site_name', 'MuseDock');
    $footerLogoPath = site_setting('site_logo', '');
    $footerDefaultLogo = asset('img/musedock_logo.png');
    $showFooterBranding = $showFooterLogo || $showFooterTitle;
    // Logo alternativo (dark/inverso) para fondos oscuros
    $footerLogoDark = themeOption('footer.footer_logo_dark', '');
    $footerLogoSrc = $footerLogoDark ? public_file_url($footerLogoDark) : ($footerLogoPath ? public_file_url($footerLogoPath) : $footerDefaultLogo);
    $footerLogoMaxHeight = themeOption('footer.footer_logo_max_height', '50');
@endphp
<footer>
    <!-- Footer Start -->
    @php
        // Calcular padding-top para columnas derecha: alinear con el centro del logo
        $__logoH = (int)($footerLogoMaxHeight ?? 50);
        $__colPadTop = $showFooterBranding ? max(0, round($__logoH / 2) - 10) : 0;
    @endphp
    <div class="footer-area footer-padding" style="background-color: var(--footer-bg-color, #f8fafe);">
        <div class="container">
            <div class="row align-items-start">

                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12">
                   <div class="single-footer-caption">
                     <div class="single-footer-caption">
                          <!-- logo (solo si show_logo está activo) -->
                         @if($showFooterBranding)
                         <div class="footer-logo" style="margin-bottom:8px;">
                             <a href="{{ url('/') }}" style="display:inline-flex; align-items:center; gap:14px; text-decoration:none;">
                                 @if($showFooterLogo)
                                    <img src="{{ $footerLogoSrc }}"
                                          alt="{{ $footerSiteName }}"
                                         style="max-height: {{ $footerLogoMaxHeight }}px; width: auto;"
                                          onerror="this.onerror=null; this.src='{{ $footerDefaultLogo }}';">
                                 @endif
                                 @if($showFooterTitle)
                                     <span style="font-size: 22px; font-weight: 700; color: var(--footer-heading-color, #333);">
                                         {{ $footerSiteName }}
                                     </span>
                                 @endif
                             </a>
                         </div>
                         @endif
                         <div class="footer-tittle">
                             <div class="footer-pera">
                                 <p style="color: var(--footer-text-color, #333);">{{ translatable_site_setting('footer_short_description', '') }}</p>
                            </div>

                            <!-- Selector de idiomas como SELECT (solo si hay más de un idioma activo y no está forzado) -->
                            @php
                                $pdo = \Screenart\Musedock\Database::connect();
                                $tenantId = tenant_id();
                                if ($tenantId) {
                                    // Tenant: obtener idiomas del tenant
                                    $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id = ? AND active = 1 ORDER BY order_position ASC, id ASC");
                                    $stmt->execute([$tenantId]);
                                } else {
                                    // Global/Superadmin: obtener idiomas globales
                                    $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id IS NULL AND active = 1 ORDER BY order_position ASC, id ASC");
                                    $stmt->execute();
                                }
                                $activeLanguages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                                $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
                                // No mostrar selector si hay idioma forzado o solo hay un idioma
                                $forceLang = site_setting('force_lang', '');
                                $showFooterLangSelector = count($activeLanguages) > 1 && empty($forceLang);
                                // Respetar setting del tenant para ocultar selector
                                if ($showFooterLangSelector && function_exists('tenant_setting')) {
                                    $showLangSwitcherSetting = tenant_setting('show_language_switcher', '1');
                                    if ($showLangSwitcherSetting === '0') {
                                        $showFooterLangSelector = false;
                                    }
                                }
                            @endphp

                            @if($showFooterLangSelector)
                            <div class="language-selector my-4 text-left">
                                <form action="" method="get" id="language-form" class="d-inline-block">
                                    <select name="lang" id="language-select" aria-label="{{ $currentLang === 'en' ? 'Select language' : 'Seleccionar idioma' }}" onchange="this.form.submit();" style="
                                        border: 1px solid #000;
                                        border-radius: 4px;
                                        padding: 8px 12px;
                                        font-size: 14px;
                                        color: #000;
                                        background-color: transparent;
                                        cursor: pointer;
                                    ">
                                        @foreach($activeLanguages as $lang)
                                            <option value="{{ $lang['code'] }}" {{ $currentLang == $lang['code'] ? 'selected' : '' }}>
                                                {{ $lang['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                            @endif
                         </div>
                         
                         <!-- social -->
                         <div class="footer-social mt-3" style="--icon-color: var(--footer-icon-color, #333);">
                            @if(site_setting('social_facebook', ''))
                                <a href="{{ site_setting('social_facebook') }}" target="_blank" rel="noopener" aria-label="Facebook" style="color: var(--footer-icon-color, #333);"><i class="fab fa-facebook-square"></i></a>
                            @endif
                            @if(site_setting('social_twitter', ''))
                                <a href="{{ site_setting('social_twitter') }}" target="_blank" rel="noopener" aria-label="Twitter" style="color: var(--footer-icon-color, #333);"><i class="fab fa-twitter-square"></i></a>
                            @endif
                            @if(site_setting('social_instagram', ''))
                                <a href="{{ site_setting('social_instagram') }}" target="_blank" rel="noopener" aria-label="Instagram" style="color: var(--footer-icon-color, #333);"><i class="fab fa-instagram"></i></a>
                            @endif
                            @if(site_setting('social_linkedin', ''))
                                <a href="{{ site_setting('social_linkedin') }}" target="_blank" rel="noopener" aria-label="LinkedIn" style="color: var(--footer-icon-color, #333);"><i class="fab fa-linkedin"></i></a>
                            @endif
                            @if(site_setting('social_pinterest', ''))
                                <a href="{{ site_setting('social_pinterest') }}" target="_blank" rel="noopener" aria-label="Pinterest" style="color: var(--footer-icon-color, #333);"><i class="fab fa-pinterest-square"></i></a>
                            @endif
                            @if(site_setting('social_youtube', ''))
                                <a href="{{ site_setting('social_youtube') }}" target="_blank" rel="noopener" aria-label="YouTube" style="color: var(--footer-icon-color, #333);"><i class="fab fa-youtube"></i></a>
                            @endif
                            @if(site_setting('social_vimeo', ''))
                                <a href="{{ site_setting('social_vimeo') }}" target="_blank" rel="noopener" aria-label="Vimeo" style="color: var(--footer-icon-color, #333);"><i class="fab fa-vimeo-v"></i></a>
                            @endif
                            @if(site_setting('social_tiktok', ''))
                                <a href="{{ site_setting('social_tiktok') }}" target="_blank" rel="noopener" aria-label="TikTok" style="color: var(--footer-icon-color, #333);"><i class="fab fa-tiktok"></i></a>
                            @endif
                            @if(site_setting('social_github', ''))
                                <a href="{{ site_setting('social_github') }}" target="_blank" rel="noopener" aria-label="GitHub" style="color: var(--footer-icon-color, #333);"><i class="fab fa-github"></i></a>
                            @endif
                        </div>
                     </div>
                   </div>
                </div>

                @php
                    // Verificar si existe un menú para el área footer1 (del tenant actual)
                    $pdo = \Screenart\Musedock\Database::connect();
                    $__tenantId = tenant_id();
                    $__appHost = parse_url(config('app.url', ''), PHP_URL_HOST) ?: 'musedock.com';
                    $__currentHost = $_SERVER['HTTP_HOST'] ?? '';
                    $__isMasterSite = !$__tenantId && ($__currentHost === $__appHost || $__currentHost === 'www.' . $__appHost);
                    if ($__tenantId) {
                        $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer1' AND m.tenant_id = ? LIMIT 1");
                        $stmt->execute([$__tenantId]);
                    } elseif ($__isMasterSite) {
                        $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer1' AND m.tenant_id IS NULL LIMIT 1");
                        $stmt->execute();
                    } else {
                        $stmt = null;
                    }
                    $footer1Menu = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
                    $hasFooter1Menu = !empty($footer1Menu);

                    $footer1Title = '';
                    $showFooter1Title = true;
                    if ($hasFooter1Menu) {
                        $showFooter1Title = (bool)($footer1Menu['show_title'] ?? 1);
                        $stmt = $pdo->prepare("
                            SELECT mt.title
                            FROM site_menu_translations mt
                            WHERE mt.menu_id = ? AND mt.locale = ?
                            ORDER BY mt.id DESC LIMIT 1
                        ");
                        $stmt->execute([$footer1Menu['id'], $currentLang]);
                        $footer1Title = $stmt->fetchColumn();
                    }

                    // Verificar si hay widgets para footer1
                    $footer1WidgetContent = '';
                    if (!$hasFooter1Menu) {
                        $tenantId = \Screenart\Musedock\Services\TenantManager::currentTenantId();
                        $themeSlug = setting('default_theme', 'default');
                        \Screenart\Musedock\Widgets\WidgetManager::registerAvailableWidgets();
                        $footer1WidgetContent = trim(\Screenart\Musedock\Widgets\WidgetManager::renderArea('footer1', $tenantId, $themeSlug));
                    }
                    // Considerar vacío si solo tiene divs y comentarios HTML
                    $footer1TextContent = trim(strip_tags(preg_replace('/<!--.*?-->/s', '', $footer1WidgetContent)));
                    $hasFooter1Content = $hasFooter1Menu || !empty($footer1TextContent);
                @endphp

                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6{{ $hasFooter1Content ? '' : ' footer-col-empty' }}">
                    @if($hasFooter1Content)
                    <div class="single-footer-caption mb-50" style="padding-top: {{ $__colPadTop }}px;">
                        @if($hasFooter1Menu)
                            <div class="footer-tittle">
                                @if($footer1Title && $showFooter1Title)
                                    <div class="footer-heading" style="color: var(--footer-heading-color, #333);">{{ $footer1Title }}</div>
                                @endif
                                @custommenu('footer1', null, [
                                    'nav_class' => '',
                                    'li_class' => '',
                                    'a_class' => '',
                                    'submenu_class' => 'submenu'
                                ])
                            </div>
                        @else
                            {!! $footer1WidgetContent !!}
                        @endif
                    </div>
                    @endif
                </div>

                @php
                    // Verificar si existe un menú para el área footer2 (del tenant actual)
                    if ($__tenantId) {
                        $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer2' AND m.tenant_id = ? LIMIT 1");
                        $stmt->execute([$__tenantId]);
                    } elseif ($__isMasterSite) {
                        $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer2' AND m.tenant_id IS NULL LIMIT 1");
                        $stmt->execute();
                    } else {
                        $stmt = null;
                    }
                    $footer2Menu = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
                    $hasFooter2Menu = !empty($footer2Menu);

                    $footer2Title = '';
                    $showFooter2Title = true;
                    if ($hasFooter2Menu) {
                        $showFooter2Title = (bool)($footer2Menu['show_title'] ?? 1);
                        $stmt = $pdo->prepare("
                            SELECT mt.title
                            FROM site_menu_translations mt
                            WHERE mt.menu_id = ? AND mt.locale = ?
                            ORDER BY mt.id DESC LIMIT 1
                        ");
                        $stmt->execute([$footer2Menu['id'], $currentLang]);
                        $footer2Title = $stmt->fetchColumn();
                    }

                    $footer2WidgetContent = '';
                    if (!$hasFooter2Menu) {
                        $tenantId = $tenantId ?? \Screenart\Musedock\Services\TenantManager::currentTenantId();
                        $themeSlug = $themeSlug ?? setting('default_theme', 'default');
                        \Screenart\Musedock\Widgets\WidgetManager::registerAvailableWidgets();
                        $footer2WidgetContent = trim(\Screenart\Musedock\Widgets\WidgetManager::renderArea('footer2', $tenantId, $themeSlug));
                    }
                    $footer2TextContent = trim(strip_tags(preg_replace('/<!--.*?-->/s', '', $footer2WidgetContent)));
                    $hasFooter2Content = $hasFooter2Menu || !empty($footer2TextContent);
                @endphp

                <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6{{ $hasFooter2Content ? '' : ' footer-col-empty' }}">
                    @if($hasFooter2Content)
                    <div class="single-footer-caption mb-50" style="padding-top: {{ $__colPadTop }}px;">
                        @if($hasFooter2Menu)
                            <div class="footer-tittle">
                                @if($footer2Title && $showFooter2Title)
                                    <div class="footer-heading" style="color: var(--footer-heading-color, #333);">{{ $footer2Title }}</div>
                                @endif
                                @custommenu('footer2', null, [
                                    'nav_class' => '',
                                    'li_class' => '',
                                    'a_class' => '',
                                    'submenu_class' => 'submenu'
                                ])
                            </div>
                        @else
                            {!! $footer2WidgetContent !!}
                        @endif
                    </div>
                    @endif
                </div>

                @php
                    // Verificar si existe un menú para el área footer3 (del tenant actual)
                    if ($__tenantId) {
                        $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer3' AND m.tenant_id = ? LIMIT 1");
                        $stmt->execute([$__tenantId]);
                    } elseif ($__isMasterSite) {
                        $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer3' AND m.tenant_id IS NULL LIMIT 1");
                        $stmt->execute();
                    } else {
                        $stmt = null;
                    }
                    $footer3Menu = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
                    $hasFooter3Menu = !empty($footer3Menu);

                    $footer3Title = '';
                    $showFooter3Title = true;
                    if ($hasFooter3Menu) {
                        $showFooter3Title = (bool)($footer3Menu['show_title'] ?? 1);
                        $stmt = $pdo->prepare("
                            SELECT mt.title
                            FROM site_menu_translations mt
                            WHERE mt.menu_id = ? AND mt.locale = ?
                            ORDER BY mt.id DESC LIMIT 1
                        ");
                        $stmt->execute([$footer3Menu['id'], $currentLang]);
                        $footer3Title = $stmt->fetchColumn();
                    }

                    $hasContactData = site_setting('contact_phone') || site_setting('contact_email') || site_setting('contact_address') || site_setting('contact_whatsapp');

                    $footer3WidgetContent = '';
                    if (!$hasFooter3Menu && !$hasContactData) {
                        $tenantId = $tenantId ?? \Screenart\Musedock\Services\TenantManager::currentTenantId();
                        $themeSlug = $themeSlug ?? setting('default_theme', 'default');
                        \Screenart\Musedock\Widgets\WidgetManager::registerAvailableWidgets();
                        $footer3WidgetContent = trim(\Screenart\Musedock\Widgets\WidgetManager::renderArea('footer3', $tenantId, $themeSlug));
                    }
                    $footer3TextContent = trim(strip_tags(preg_replace('/<!--.*?-->/s', '', $footer3WidgetContent)));
                    $hasFooter3Content = $hasFooter3Menu || $hasContactData || !empty($footer3TextContent);
                @endphp

                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6{{ $hasFooter3Content ? '' : ' footer-col-empty' }}">
                    @if($hasFooter3Content)
                    <div class="single-footer-caption mb-50" style="padding-top: {{ $__colPadTop }}px;">
                        @if($hasFooter3Menu)
                            <div class="footer-tittle">
                                @if($footer3Title && $showFooter3Title)
                                    <div class="footer-heading" style="color: var(--footer-heading-color, #333);">{{ $footer3Title }}</div>
                                @endif
                                @custommenu('footer3', null, [
                                    'nav_class' => '',
                                    'li_class' => '',
                                    'a_class' => '',
                                    'submenu_class' => 'submenu'
                                ])
                            </div>
                        @elseif($hasContactData)
                            <div class="footer-tittle">
                                <div class="footer-heading" style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: var(--footer-heading-color, #333);">{{ site_setting('footer_col4_title', __('footer.contact')) }}</div>
                                <ul>
                                    @if(site_setting('contact_phone'))<li><span>{{ site_setting('contact_phone') }}</span></li>@endif
                                    @if(site_setting('contact_email'))<li><span>{{ site_setting('contact_email') }}</span></li>@endif
                                    @if(site_setting('contact_address'))<li><span>{{ site_setting('contact_address') }}</span></li>@endif
                                    @if(site_setting('contact_whatsapp'))<li><span><i class="fab fa-whatsapp"></i> {{ site_setting('contact_whatsapp') }}</span></li>@endif
                                </ul>
                            </div>
                        @else
                            {!! $footer3WidgetContent !!}
                        @endif
                    </div>
                    @endif
                </div>

                {{-- Footer Column 4 (Legal) --}}
                @php
                    $pdo = $pdo ?? \Screenart\Musedock\Database::connect();
                    if ($tenantId ?? null) {
                        $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer4' AND m.tenant_id = ? LIMIT 1");
                        $stmt->execute([$tenantId]);
                    } else {
                        $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer4' AND m.tenant_id IS NULL LIMIT 1");
                        $stmt->execute();
                    }
                    $footer4Menu = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $hasFooter4Menu = !empty($footer4Menu);
                    $footer4Title = '';
                    $showFooter4Title = true;
                    if ($hasFooter4Menu) {
                        $showFooter4Title = (bool)($footer4Menu['show_title'] ?? 1);
                        $stmt = $pdo->prepare("SELECT mt.title FROM site_menu_translations mt WHERE mt.menu_id = ? AND mt.locale = ? ORDER BY mt.id DESC LIMIT 1");
                        $stmt->execute([$footer4Menu['id'], $currentLang]);
                        $footer4Title = $stmt->fetchColumn();
                    }
                @endphp
                @if($hasFooter4Menu)
                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6">
                    <div class="single-footer-caption mb-50" style="padding-top: {{ $__colPadTop ?? 0 }}px;">
                        <div class="footer-tittle">
                            @if($footer4Title && $showFooter4Title)
                                <div class="footer-heading" style="color: var(--footer-heading-color, #333);">{{ $footer4Title }}</div>
                            @endif
                            @custommenu('footer4', null, [
                                'nav_class' => '',
                                'li_class' => '',
                                'a_class' => '',
                                'submenu_class' => 'submenu'
                            ])
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>

    <!-- footer-bottom area -->
    <div class="footer-bottom-area footer-bg" style="background-color: var(--footer-bg-color, #f8fafe);">
        <div class="container">
            <div class="footer-border" style="border-top: 1px solid var(--footer-border-color, #e5e5e5);">
                @php
                    $pdo = \Screenart\Musedock\Database::connect();
                    $tenantId = tenant_id();

                    // Verificar si hay menú footer-legal personalizado (prioridad máxima)
                    if ($tenantId) {
                        $stmt = $pdo->prepare("SELECT id FROM site_menus WHERE location = 'footer-legal' AND tenant_id = ? LIMIT 1");
                        $stmt->execute([$tenantId]);
                    } else {
                        $stmt = $pdo->prepare("SELECT id FROM site_menus WHERE location = 'footer-legal' AND tenant_id IS NULL LIMIT 1");
                        $stmt->execute();
                    }
                    $hasFooterLegalMenu = !empty($stmt->fetch(\PDO::FETCH_ASSOC));

                    // Función helper: busca la URL real de una página publicada por slug(s)
                    // Si el tenant tiene una página publicada con ese slug, devuelve su URL real.
                    // Si no, devuelve la URL de fallback /p/{defaultSlug}
                    $legalPageUrl = function(array $slugCandidates, string $defaultSlug) use ($pdo, $tenantId) {
                        $placeholders = implode(',', array_fill(0, count($slugCandidates), '?'));
                        if ($tenantId) {
                            $stmt = $pdo->prepare("
                                SELECT s.slug, s.prefix
                                FROM slugs s
                                JOIN pages p ON p.id = s.reference_id
                                WHERE s.module = 'pages'
                                  AND s.slug IN ($placeholders)
                                  AND s.tenant_id = ?
                                  AND p.status = 'published'
                            ");
                            $stmt->execute(array_merge($slugCandidates, [$tenantId]));
                        } else {
                            $stmt = $pdo->prepare("
                                SELECT s.slug, s.prefix
                                FROM slugs s
                                JOIN pages p ON p.id = s.reference_id
                                WHERE s.module = 'pages'
                                  AND s.slug IN ($placeholders)
                                  AND s.tenant_id IS NULL
                                  AND p.status = 'published'
                            ");
                            $stmt->execute($slugCandidates);
                        }
                        // Buscar el primer candidato que coincida (respeta orden de prioridad)
                        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                        foreach ($slugCandidates as $candidate) {
                            foreach ($rows as $row) {
                                if ($row['slug'] === $candidate) {
                                    $prefix = $row['prefix'] ? '/' . $row['prefix'] . '/' : '/';
                                    return url($prefix . $row['slug']);
                                }
                            }
                        }
                        return url(page_url($defaultSlug));
                    };

                    // Resolver URL de cada página legal (acepta varios slugs alternativos por si el tenant usa nombres propios)
                    $urlAvisoLegal   = $legalPageUrl(['aviso-legal', 'legal', 'aviso_legal'],                                                      'aviso-legal');
                    $urlPrivacidad   = $legalPageUrl(['privacy', 'privacidad', 'politica-de-privacidad', 'politica-privacidad'],                  'privacy');
                    $urlCookies      = $legalPageUrl(['cookie-policy', 'cookies', 'politica-de-cookies', 'politica-cookies'],                     'cookie-policy');
                    $urlTerminos     = $legalPageUrl(['terms-and-conditions', 'terminos-y-condiciones', 'terminos-y-condiciones-de-uso', 'terminos', 'terms', 'condiciones-de-uso'], 'terms-and-conditions');
                @endphp

                <div class="row d-flex align-items-center">
                    <div class="col-xl-12">
                        <div class="footer-copy-right text-center">
                            <p style="color: var(--footer-text-color, #333);">
                                {!! site_setting('footer_copyright', '© ' . date('Y') . ' ' . site_setting('site_name', 'MuseDock')) !!}
                            </p>
                        </div>
                    </div>
                    {{-- Fallback: legal links for tenants without footer4 menu --}}
                    @if(!$hasFooter4Menu)
                    <div class="col-12">
                        <div class="footer-legal-links text-center" style="padding: 8px 0 4px;">
                            @if($hasFooterLegalMenu)
                                @custommenu('footer-legal', null, [
                                    'nav_class' => 'footer-legal-nav',
                                    'li_class' => 'footer-legal-item',
                                    'a_class' => 'footer-legal-link',
                                ])
                            @else
                                {{-- Links legales automáticos: usan página real si existe, si no el fallback dinámico --}}
                                <ul class="footer-legal-nav" style="list-style:none; padding:0; margin:0; display:inline-flex; flex-wrap:wrap; gap:4px 16px; justify-content:center;">
                                    <li class="footer-legal-item">
                                        <a href="{{ $urlAvisoLegal }}" class="footer-legal-link" style="color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75;">
                                            {{ $currentLang === 'en' ? 'Legal Notice' : 'Aviso Legal' }}
                                        </a>
                                    </li>
                                    <li class="footer-legal-item">
                                        <a href="{{ $urlPrivacidad }}" class="footer-legal-link" style="color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75;">
                                            {{ $currentLang === 'en' ? 'Privacy Policy' : 'Política de Privacidad' }}
                                        </a>
                                    </li>
                                    <li class="footer-legal-item">
                                        <a href="{{ $urlCookies }}" class="footer-legal-link" style="color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75;">
                                            {{ $currentLang === 'en' ? 'Cookie Policy' : 'Política de Cookies' }}
                                        </a>
                                    </li>
                                    <li class="footer-legal-item">
                                        <a href="{{ $urlTerminos }}" class="footer-legal-link" style="color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75;">
                                            {{ $currentLang === 'en' ? 'Terms & Conditions' : 'Términos y Condiciones' }}
                                        </a>
                                    </li>
                                    @php
                                        $cookieIconEnabled = themeOption('footer.footer_cookie_icon_enabled', site_setting('cookies_show_icon', '1') == '1');
                                    @endphp
                                    @if(site_setting('cookies_enabled', '1') == '1' && $cookieIconEnabled)
                                    @php
                                        $cookieIconType = themeOption('footer.footer_cookie_icon', 'emoji');
                                        switch ($cookieIconType) {
                                            case 'fa-cookie':       $cookieIconHtml = '<i class="fas fa-cookie me-1"></i>'; break;
                                            case 'fa-cookie-bite':  $cookieIconHtml = '<i class="fas fa-cookie-bite me-1"></i>'; break;
                                            case 'fa-shield-alt':   $cookieIconHtml = '<i class="fas fa-shield-alt me-1"></i>'; break;
                                            case 'fa-cog':          $cookieIconHtml = '<i class="fas fa-cog me-1"></i>'; break;
                                            case 'none':            $cookieIconHtml = ''; break;
                                            default:                $cookieIconHtml = '🍪 '; break;
                                        }
                                    @endphp
                                    <li class="footer-legal-item">
                                        <button type="button" id="open-cookie-settings" style="background: none; border: none; cursor: pointer; padding: 0; color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75; font-family: inherit;">
                                            {!! $cookieIconHtml !!}{{ $currentLang === 'en' ? 'Cookie Settings' : 'Configuración de Cookies' }}
                                        </button>
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
    </div>
    <!-- Footer End-->
</footer>
