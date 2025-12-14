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
@endphp

<!-- ====== Footer Start ====== -->
<footer class="ud-footer wow fadeInUp" data-wow-delay=".15s" style="background-color: var(--footer-bg-color); color: var(--footer-text-color);">
    <div class="shape shape-1">
        <img src="{{ asset('themes/play-bootstrap/img/footer/shape-1.svg') }}" alt="shape" />
    </div>
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
                        @if($socialFacebook || $socialTwitter || $socialInstagram || $socialLinkedin)
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
                    </div>
                </div>

                {{-- Segunda columna - Menú del footer 1 --}}
                <div class="col-xl-2 col-lg-2 col-md-6 col-sm-6">
                    <div class="ud-widget">
                        <h5 class="ud-widget-title" style="color: var(--footer-heading-color);">
                            {{ $currentLang === 'en' ? 'Menu' : 'Menú' }}
                        </h5>
                        @custommenu('footer-1', null, [
                            'ul_class' => 'ud-widget-links',
                            'li_class' => '',
                            'a_class' => '',
                            'a_style' => 'color: var(--footer-link-color);'
                        ])
                    </div>
                </div>

                {{-- Tercera columna - Menú del footer 2 --}}
                <div class="col-xl-2 col-lg-3 col-md-6 col-sm-6">
                    <div class="ud-widget">
                        <h5 class="ud-widget-title" style="color: var(--footer-heading-color);">
                            {{ $currentLang === 'en' ? 'Links' : 'Enlaces' }}
                        </h5>
                        @custommenu('footer-2', null, [
                            'ul_class' => 'ud-widget-links',
                            'li_class' => '',
                            'a_class' => '',
                            'a_style' => 'color: var(--footer-link-color);'
                        ])
                    </div>
                </div>

                {{-- Cuarta columna - Información de contacto --}}
                <div class="col-xl-2 col-lg-3 col-md-6 col-sm-6">
                    <div class="ud-widget">
                        <h5 class="ud-widget-title" style="color: var(--footer-heading-color);">
                            {{ $currentLang === 'en' ? 'Contact' : 'Contacto' }}
                        </h5>
                        <ul class="ud-widget-links">
                            @if($contactEmail)
                            <li>
                                <a href="mailto:{{ $contactEmail }}" style="color: var(--footer-link-color);">
                                    <i class="lni lni-envelope"></i> {{ $contactEmail }}
                                </a>
                            </li>
                            @endif
                            @if($contactPhone)
                            <li>
                                <a href="tel:{{ $contactPhone }}" style="color: var(--footer-link-color);">
                                    <i class="lni lni-phone"></i> {{ $contactPhone }}
                                </a>
                            </li>
                            @endif
                            @if($contactAddress)
                            <li>
                                <span style="color: var(--footer-text-color);">
                                    <i class="lni lni-map-marker"></i> {{ $contactAddress }}
                                </span>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>

                {{-- Quinta columna - Últimas entradas del blog --}}
                @php
                    $blogPublic = site_setting('blog_public', '1');
                    $latestPosts = [];

                    // Verificar si el blog está activo y si existen las tablas
                    if ($blogPublic === '1') {
                        try {
                            $pdo = \Screenart\Musedock\Database::connect();
                            $tenantId = tenant_id();
                            $currentLang = detectLanguage();

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
                            // Tabla no existe o error de base de datos - simplemente no mostrar posts
                            $latestPosts = [];
                        }
                    }
                @endphp
                @if(count($latestPosts) > 0)
                <div class="col-xl-3 col-lg-6 col-md-8 col-sm-10">
                    <div class="ud-widget">
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
