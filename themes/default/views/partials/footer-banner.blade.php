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
@endphp
<footer>
    <!-- Footer Start -->
    <div class="footer-area footer-padding footer-banner" style="background-color: var(--footer-bg-color, #f8fafe);">
        <div class="container">
            <div class="row">

                <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
                   <div class="single-footer-caption mb-50">
                     <div class="single-footer-caption mb-30">
                          <!-- logo (solo si show_logo está activo) -->
                         @if($showFooterBranding)
                         <div class="footer-logo" style="display:flex; align-items:center; gap:12px;">
                             <a href="{{ url('/') }}" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                                 @if($showFooterLogo)
                                    <img src="{{ $footerLogoPath ? public_file_url($footerLogoPath) : $footerDefaultLogo }}"
                                          alt="{{ $footerSiteName }}"
                                         style="max-height: 44px; width: auto;"
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

                            <!-- Enlace configuración de cookies (RGPD) -->
                            <div class="cookie-settings-link my-2">
                                <a href="javascript:void(0);" id="open-cookie-settings" style="color: var(--footer-link-color, #333); font-size: 13px; text-decoration: underline;">
                                    <i class="fas fa-cookie-bite me-1"></i>{{ __('footer.cookie_settings') }}
                                </a>
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
                            @endphp

                            @if($showFooterLangSelector)
                            <div class="language-selector my-4 text-left">
                                <form action="" method="get" id="language-form" class="d-inline-block">
                                    <select name="lang" id="language-select" onchange="this.form.submit();" style="
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
                         <div class="footer-social" style="--icon-color: var(--footer-icon-color, #333);">
                            @if(site_setting('social_facebook', ''))
                                <a href="{{ site_setting('social_facebook') }}" target="_blank" style="color: var(--footer-icon-color, #333);"><i class="fab fa-facebook-square"></i></a>
                            @endif
                            @if(site_setting('social_twitter', ''))
                                <a href="{{ site_setting('social_twitter') }}" target="_blank" style="color: var(--footer-icon-color, #333);"><i class="fab fa-twitter-square"></i></a>
                            @endif
                            @if(site_setting('social_instagram', ''))
                                <a href="{{ site_setting('social_instagram') }}" target="_blank" style="color: var(--footer-icon-color, #333);"><i class="fab fa-instagram"></i></a>
                            @endif
                            @if(site_setting('social_linkedin', ''))
                                <a href="{{ site_setting('social_linkedin') }}" target="_blank" style="color: var(--footer-icon-color, #333);"><i class="fab fa-linkedin"></i></a>
                            @endif
                            @if(site_setting('social_pinterest', ''))
                                <a href="{{ site_setting('social_pinterest') }}" target="_blank" style="color: var(--footer-icon-color, #333);"><i class="fab fa-pinterest-square"></i></a>
                            @endif
                            @if(site_setting('social_youtube', ''))
                                <a href="{{ site_setting('social_youtube') }}" target="_blank" style="color: var(--footer-icon-color, #333);"><i class="fab fa-youtube"></i></a>
                            @endif
                            @if(site_setting('social_tiktok', ''))
                                <a href="{{ site_setting('social_tiktok') }}" target="_blank" style="color: var(--footer-icon-color, #333);"><i class="fab fa-tiktok"></i></a>
                            @endif
                        </div>
                     </div>
                   </div>
                </div>

                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-6">
                    <div class="single-footer-caption mb-50" style="margin-top: {{ $showFooterLogo ? '40px' : '0' }};">
                        @php
                            $pdo = \Screenart\Musedock\Database::connect();
                            $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer1' LIMIT 1");
                            $stmt->execute();
                            $footer1Menu = $stmt->fetch(\PDO::FETCH_ASSOC);
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
                                    <h4 style="color: var(--footer-heading-color, #333);">{{ $footer1Title }}</h4>
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
                    <div class="single-footer-caption mb-50" style="margin-top: {{ $showFooterBranding ? '40px' : '0' }};">
                        @php
                            $pdo = \Screenart\Musedock\Database::connect();
                            $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer2' LIMIT 1");
                            $stmt->execute();
                            $footer2Menu = $stmt->fetch(\PDO::FETCH_ASSOC);
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
                                    <h4 style="color: var(--footer-heading-color, #333);">{{ $footer2Title }}</h4>
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
                    <div class="single-footer-caption mb-50" style="margin-top: {{ $showFooterBranding ? '40px' : '0' }};">
                        @php
                            $pdo = \Screenart\Musedock\Database::connect();
                            $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'footer3' LIMIT 1");
                            $stmt->execute();
                            $footer3Menu = $stmt->fetch(\PDO::FETCH_ASSOC);
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
                                    <h4 style="color: var(--footer-heading-color, #333);">{{ $footer3Title }}</h4>
                                @endif
                                @custommenu('footer3', null, [
                                    'nav_class' => '',
                                    'li_class' => '',
                                    'a_class' => '',
                                    'submenu_class' => 'submenu'
                                ])
                            </div>
                        @else
                            <div class="footer-tittle">
                                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: var(--footer-heading-color, #333);">{{ site_setting('footer_col4_title', __('footer.contact')) }}</h4>
                                <ul>
                                    <li><span>{{ site_setting('footer_email', site_setting('contact_email', '')) }}</span></li>
                                </ul>
                            </div>
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
            <div class="row d-flex align-items-center">
                <div class="col-xl-12 ">
                    <div class="footer-copy-right text-center">
                        <p style="color: var(--footer-text-color, #333);">
                            {!! site_setting('footer_copyright', '© Copyright ' . site_setting('site_name', 'MuseDock') . ' ' . date('Y') . '.') !!}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End-->
</footer>
