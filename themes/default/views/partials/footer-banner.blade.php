@php
    // Asegurar contexto tenant para traducciones del frontend
    \Screenart\Musedock\Services\TranslationService::setContext('tenant');
    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
    \Screenart\Musedock\Services\TranslationService::load($currentLang, 'tenant');

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
        $__logoH = (int)($footerLogoMaxHeight ?? 50);
        $__colPadTop = $showFooterBranding ? max(0, round($__logoH / 2) - 10) : 0;
    @endphp
    <div class="footer-area footer-padding footer-banner" style="background-color: var(--footer-bg-color, #f8fafe);">
        <div class="container">
            <div class="row align-items-start">

                <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
                   <div class="single-footer-caption mb-50">
                     <div class="single-footer-caption mb-30">
                          <!-- logo (solo si show_logo está activo) -->
                         @if($showFooterBranding)
                         <div class="footer-logo" style="display:flex; align-items:center; gap:12px;">
                             <a href="{{ url('/') }}" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                                 @if($showFooterLogo)
                                    <img src="{{ $footerLogoSrc }}"
                                          alt="{{ $footerSiteName }}"
                                         style="max-height: {{ $footerLogoMaxHeight }}px; width: auto;"
                                          onerror="this.onerror=null; this.src='{{ $footerDefaultLogo }}';">
                                 @endif
                                 @if($showFooterTitle)
                                     <span style="font-size: 20px; font-weight: 700; color: var(--footer-heading-color, #333);">
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
                                    $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id = ? AND active = 1 ORDER BY order_position ASC, id ASC");
                                    $stmt->execute([$tenantId]);
                                } else {
                                    $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id IS NULL AND active = 1 ORDER BY order_position ASC, id ASC");
                                    $stmt->execute();
                                }
                                $activeLanguages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                                $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
                                $forceLang = site_setting('force_lang', '');
                                $showFooterLangSelector = count($activeLanguages) > 1 && empty($forceLang);
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
                                    <select name="lang" id="language-select" aria-label="{{ $__bl === 'en' ? 'Select language' : 'Seleccionar idioma' }}" onchange="this.form.submit();" style="
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
                        </div>
                     </div>
                   </div>
                </div>

                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6">
                    <div class="single-footer-caption mb-50" style="padding-top: {{ $__colPadTop }}px;">
                        @php
                            $pdo = \Screenart\Musedock\Database::connect();
                            $__tenantId = tenant_id();
                            $__appHost = parse_url(config('app.url', ''), PHP_URL_HOST) ?: 'musedock.com';
                            $__isMasterSite = !$__tenantId && (($_SERVER['HTTP_HOST'] ?? '') === $__appHost || ($_SERVER['HTTP_HOST'] ?? '') === 'www.' . $__appHost);
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
                        @endphp

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
                            @include('partials.widget-renderer', ['areaSlug' => 'footer1'])
                        @endif
                    </div>
                </div>

                <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6">
                    <div class="single-footer-caption mb-50" style="padding-top: {{ $__colPadTop }}px;">
                        @php
                            if ($__tenantId) {
                                $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer2' AND m.tenant_id = ? LIMIT 1");
                                $stmt->execute([$__tenantId]);
                            } elseif ($__isMasterSite) {
                                $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer2' AND m.tenant_id IS NULL LIMIT 1");
                                $stmt->execute();
                            } else { $stmt = null; }
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
                        @endphp

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
                            @include('partials.widget-renderer', ['areaSlug' => 'footer2'])
                        @endif
                    </div>
                </div>

                <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6">
                    <div class="single-footer-caption mb-50" style="padding-top: {{ $__colPadTop }}px;">
                        @php
                            if ($__tenantId) {
                                $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer3' AND m.tenant_id = ? LIMIT 1");
                                $stmt->execute([$__tenantId]);
                            } elseif ($__isMasterSite) {
                                $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer3' AND m.tenant_id IS NULL LIMIT 1");
                                $stmt->execute();
                            } else { $stmt = null; }
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
                        @endphp

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
                        @else
                            @php
                                $__contactEmail = site_setting('footer_email', site_setting('contact_email', ''));
                                $__contactPhone = site_setting('contact_phone', '');
                                $__contactAddress = site_setting('contact_address', '');
                                $__contactWhatsapp = site_setting('contact_whatsapp', '');
                                $__hasContactData = $__contactEmail || $__contactPhone || $__contactAddress || $__contactWhatsapp;
                            @endphp
                            @if($__hasContactData)
                            <div class="footer-tittle">
                                <div class="footer-heading" style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: var(--footer-heading-color, #333);">{{ site_setting('footer_col4_title', __('footer.contact')) }}</div>
                                <ul>
                                    @if($__contactPhone)<li><span>{{ $__contactPhone }}</span></li>@endif
                                    @if($__contactEmail)<li><span>{{ $__contactEmail }}</span></li>@endif
                                    @if($__contactAddress)<li><span>{{ $__contactAddress }}</span></li>@endif
                                    @if($__contactWhatsapp)<li><span><i class="fab fa-whatsapp"></i> {{ $__contactWhatsapp }}</span></li>@endif
                                </ul>
                            </div>
                            @endif
                            @include('partials.widget-renderer', ['areaSlug' => 'footer3'])
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- footer-bottom area -->
    <div class="footer-bottom-area footer-bg footer-bottom-contrast" style="background-color: var(--footer-bottom-bg-color, #ffffff);">
        <div class="container">
            @php
                $__bl = $currentLang ?? 'es';
                $pdo = $pdo ?? \Screenart\Musedock\Database::connect();
                $tenantId = $tenantId ?? tenant_id();

                // Verificar si hay menú footer-legal personalizado
                if ($tenantId) {
                    $stmt = $pdo->prepare("SELECT id FROM site_menus WHERE location = 'footer-legal' AND tenant_id = ? LIMIT 1");
                    $stmt->execute([$tenantId]);
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM site_menus WHERE location = 'footer-legal' AND tenant_id IS NULL LIMIT 1");
                    $stmt->execute();
                }
                $hasFooterLegalMenu = !empty($stmt->fetch(\PDO::FETCH_ASSOC));

                // Resolver URLs legales
                $legalPageUrl = function(array $slugCandidates, string $defaultSlug) use ($pdo, $tenantId) {
                    $prefix = function_exists('page_prefix') ? page_prefix() : 'p';
                    $prefix = $prefix ? '/' . ltrim($prefix, '/') . '/' : '/';
                    foreach ($slugCandidates as $candidate) {
                        if ($tenantId) {
                            $stmt = $pdo->prepare("SELECT slug FROM pages WHERE slug = ? AND tenant_id = ? AND status = 'published' LIMIT 1");
                            $stmt->execute([$candidate, $tenantId]);
                        } else {
                            $stmt = $pdo->prepare("SELECT slug FROM pages WHERE slug = ? AND tenant_id IS NULL AND status = 'published' LIMIT 1");
                            $stmt->execute([$candidate]);
                        }
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row) {
                            return url($prefix . $row['slug']);
                        }
                        if ($tenantId) {
                            $stmt = $pdo->prepare("SELECT s.slug FROM slugs s WHERE s.slug = ? AND s.tenant_id = ? AND s.module = 'pages' LIMIT 1");
                            $stmt->execute([$candidate, $tenantId]);
                        } else {
                            $stmt = $pdo->prepare("SELECT s.slug FROM slugs s WHERE s.slug = ? AND s.tenant_id IS NULL AND s.module = 'pages' LIMIT 1");
                            $stmt->execute([$candidate]);
                        }
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row) {
                            return url($prefix . $row['slug']);
                        }
                    }
                    return url(page_url($defaultSlug));
                };

                $urlAvisoLegal   = $legalPageUrl(['aviso-legal', 'legal', 'aviso_legal'], 'aviso-legal');
                $urlPrivacidad   = $legalPageUrl(['privacy', 'privacidad', 'politica-de-privacidad'], 'privacy');
                $urlCookies      = $legalPageUrl(['cookie-policy', 'cookies', 'politica-de-cookies'], 'cookie-policy');
                $urlTerminos     = $legalPageUrl(['terms-and-conditions', 'terminos-y-condiciones', 'terminos', 'terms'], 'terms-and-conditions');
            @endphp
            <div class="row d-flex align-items-center">
                <div class="col-xl-12">
                    <div class="footer-copy-right text-center">
                        <p style="color: var(--footer-text-color, #333);">
                            {!! site_setting('footer_copyright', '© Copyright ' . site_setting('site_name', 'MuseDock') . ' ' . date('Y') . '.') !!}
                        </p>
                    </div>
                    <div class="footer-legal-links text-center" style="padding: 8px 0 4px;">
                        @if($hasFooterLegalMenu)
                            @custommenu('footer-legal', null, [
                                'nav_class' => 'footer-legal-nav',
                                'li_class' => 'footer-legal-item',
                                'a_class' => 'footer-legal-link',
                            ])
                        @else
                            <ul class="footer-legal-nav" style="list-style:none; padding:0; margin:0; display:inline-flex; flex-wrap:wrap; gap:4px 16px; justify-content:center;">
                                <li class="footer-legal-item">
                                    <a href="{{ $urlAvisoLegal }}" class="footer-legal-link" style="color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75;">
                                        {{ $__bl === 'en' ? 'Legal Notice' : 'Aviso Legal' }}
                                    </a>
                                </li>
                                <li class="footer-legal-item">
                                    <a href="{{ $urlPrivacidad }}" class="footer-legal-link" style="color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75;">
                                        {{ $__bl === 'en' ? 'Privacy Policy' : 'Política de Privacidad' }}
                                    </a>
                                </li>
                                <li class="footer-legal-item">
                                    <a href="{{ $urlCookies }}" class="footer-legal-link" style="color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75;">
                                        {{ $__bl === 'en' ? 'Cookie Policy' : 'Política de Cookies' }}
                                    </a>
                                </li>
                                <li class="footer-legal-item">
                                    <a href="{{ $urlTerminos }}" class="footer-legal-link" style="color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75;">
                                        {{ $__bl === 'en' ? 'Terms & Conditions' : 'Términos y Condiciones' }}
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
                                    <button type="button" id="open-cookie-settings" class="footer-legal-link" style="background: none; border: none; cursor: pointer; padding: 0; color: var(--footer-text-color, #333); font-size: 12px; text-decoration: none; opacity: 0.75; font-family: inherit;">
                                        {!! $cookieIconHtml !!}{{ $__bl === 'en' ? 'Cookie Settings' : 'Configuración de Cookies' }}
                                    </button>
                                </li>
                                @endif
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End-->
</footer>
