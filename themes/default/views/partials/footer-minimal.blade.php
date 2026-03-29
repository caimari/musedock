{{-- Footer Layout: Minimal (copyright + legal links) --}}
@php
    \Screenart\Musedock\Services\TranslationService::setContext('tenant');
    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? site_setting('language', 'es'));
    \Screenart\Musedock\Services\TranslationService::load($currentLang, 'tenant');
    $__bl = $currentLang;
@endphp
<footer class="footer-minimal">
    {{-- Copyright bar --}}
    <div class="footer-minimal-copyright" style="background-color: var(--footer-bg-color, #1a1a1a);">
        <div class="container">
            <p style="color: var(--footer-text-color, #ccc);">
                {!! site_setting('footer_copyright', '© Copyright ' . site_setting('site_name', 'MuseDock') . ' ' . date('Y') . '.') !!}
            </p>
        </div>
    </div>

    {{-- Legal links bar --}}
    <div class="footer-minimal-legal">
        <div class="container">
            @php
                $pdo = \Screenart\Musedock\Database::connect();
                $tenantId = tenant_id();

                // Verificar menú footer-legal personalizado
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
                        if ($row) return url($prefix . $row['slug']);
                        if ($tenantId) {
                            $stmt = $pdo->prepare("SELECT s.slug FROM slugs s WHERE s.slug = ? AND s.tenant_id = ? AND s.module = 'pages' LIMIT 1");
                            $stmt->execute([$candidate, $tenantId]);
                        } else {
                            $stmt = $pdo->prepare("SELECT s.slug FROM slugs s WHERE s.slug = ? AND s.tenant_id IS NULL AND s.module = 'pages' LIMIT 1");
                            $stmt->execute([$candidate]);
                        }
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row) return url($prefix . $row['slug']);
                    }
                    return url(page_url($defaultSlug));
                };

                $urlAvisoLegal = $legalPageUrl(['aviso-legal', 'legal', 'aviso_legal'], 'aviso-legal');
                $urlPrivacidad = $legalPageUrl(['privacy', 'privacidad', 'politica-de-privacidad'], 'privacy');
                $urlCookies    = $legalPageUrl(['cookie-policy', 'cookies', 'politica-de-cookies'], 'cookie-policy');
                $urlTerminos   = $legalPageUrl(['terms-and-conditions', 'terminos-y-condiciones', 'terminos', 'terms'], 'terms-and-conditions');
            @endphp

            @if($hasFooterLegalMenu)
                @custommenu('footer-legal', null, [
                    'nav_class' => 'footer-minimal-nav',
                    'li_class' => 'footer-minimal-nav-item',
                    'a_class' => 'footer-minimal-nav-link',
                ])
            @else
                <ul class="footer-minimal-nav">
                    <li class="footer-minimal-nav-item">
                        <a href="{{ $urlAvisoLegal }}" class="footer-minimal-nav-link">{{ $__bl === 'en' ? 'Legal Notice' : 'Aviso Legal' }}</a>
                    </li>
                    <li class="footer-minimal-nav-item">
                        <a href="{{ $urlPrivacidad }}" class="footer-minimal-nav-link">{{ $__bl === 'en' ? 'Privacy Policy' : 'Politica de Privacidad' }}</a>
                    </li>
                    <li class="footer-minimal-nav-item">
                        <a href="{{ $urlCookies }}" class="footer-minimal-nav-link">{{ $__bl === 'en' ? 'Cookie Policy' : 'Politica de Cookies' }}</a>
                    </li>
                    <li class="footer-minimal-nav-item">
                        <a href="{{ $urlTerminos }}" class="footer-minimal-nav-link">{{ $__bl === 'en' ? 'Terms & Conditions' : 'Terminos y Condiciones' }}</a>
                    </li>
                    @php
                        $cookieIconEnabled = themeOption('footer.footer_cookie_icon_enabled', site_setting('cookies_show_icon', '1') == '1');
                    @endphp
                    @if(site_setting('cookies_enabled', '1') == '1' && $cookieIconEnabled)
                    @php
                        $cookieIconType = themeOption('footer.footer_cookie_icon', 'emoji');
                        switch ($cookieIconType) {
                            case 'fa-cookie':       $cookieIconHtml = '<i class="fas fa-cookie me-1"></i>'; break;
                            case 'fa-cookie-bite':   $cookieIconHtml = '<i class="fas fa-cookie-bite me-1"></i>'; break;
                            case 'fa-shield-alt':    $cookieIconHtml = '<i class="fas fa-shield-alt me-1"></i>'; break;
                            case 'fa-cog':           $cookieIconHtml = '<i class="fas fa-cog me-1"></i>'; break;
                            case 'none':             $cookieIconHtml = ''; break;
                            default:                 $cookieIconHtml = ''; break;
                        }
                    @endphp
                    <li class="footer-minimal-nav-item">
                        <a href="javascript:void(0);" id="open-cookie-settings" class="footer-minimal-nav-link">{!! $cookieIconHtml !!}{{ $__bl === 'en' ? 'Cookie Settings' : 'Configuracion de Cookies' }}</a>
                    </li>
                    @endif
                </ul>
            @endif
        </div>
    </div>
</footer>

<style>
.footer-minimal-copyright {
    padding: 18px 0;
    text-align: left;
}
.footer-minimal-copyright p {
    margin: 0;
    font-size: 0.88rem;
}
.footer-minimal-legal {
    background: #fff;
    padding: 14px 0;
    border-top: 1px solid #eee;
    text-align: center;
}
.footer-minimal-nav {
    list-style: none;
    padding: 0;
    margin: 0;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px 20px;
    justify-content: center;
}
.footer-minimal-nav-link {
    color: #666;
    font-size: 0.8rem;
    text-decoration: none;
    transition: color 0.2s;
}
.footer-minimal-nav-link:hover {
    color: #333;
    text-decoration: underline;
}
@media (max-width: 575px) {
    .footer-minimal-nav {
        flex-direction: column;
        gap: 8px;
    }
}
</style>
