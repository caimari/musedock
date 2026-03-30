@php
$currentLang = detectLanguage();
$footerDesc = translatable_site_setting('footer_short_description', $currentLang, '');
$footerCopyright = site_setting('footer_copyright', '© Copyright ' . date('Y') . ' ' . site_setting('site_name', '') . '.');
$contactEmail = site_setting('contact_email', '');
$contactPhone = site_setting('contact_phone', '');
$contactAddress = site_setting('contact_address', '');
$siteName = site_setting('site_name', 'MuseDock');
$showLogo = site_setting('show_logo', '1') === '1';
$showTitle = site_setting('show_title', '0') === '1';
$logoPath = site_setting('site_logo', '');
// Logo por defecto de MuseDock para el footer
$defaultLogo = '/assets/logo2_footer.png';

// Redes sociales
$socialFacebook = site_setting('social_facebook');
$socialTwitter = site_setting('social_twitter');
$socialInstagram = site_setting('social_instagram');
$socialLinkedIn = site_setting('social_linkedin');
$socialYoutube = site_setting('social_youtube');
$socialPinterest = site_setting('social_pinterest');
$socialTiktok = site_setting('social_tiktok');

// Opciones del tema footer - MuseDock defaults
$footerBgColor = themeOption('footer.footer_bg_color', '#0C112A');
$footerBorderColor = themeOption('footer.footer_border_color', '#181d35');
$footerBottomBgColor = themeOption('footer.footer_bottom_bg_color', '#ffffff');
$footerBottomBorderColor = themeOption('footer.footer_bottom_border_color', '#e5e5e5');
@endphp

<style>
/* Estilos para footer_bottom con fondo blanco - tema MuseDock */
.ziph-footer_bottom {
    color: #333 !important;
}

.ziph-footer_bottom .ziph-copyright,
.ziph-footer_bottom .ziph-copyright a {
    color: #666 !important;
}

.ziph-footer_bottom a:hover {
    color: #0066cc !important;
}
</style>

<!-- Footer -->
<footer class="ziph-footer_area">
  <!-- Footer Widgets -->
  <div class="ziph-footer_top" @if($footerBgColor) style="background-color: {{ $footerBgColor }};" @endif>
    <div class="container">
      <div class="row ziph-footer_widgets">
        <!-- Footer Column 1/2/3 -->
        <div class="col-md-8">
          <div class="row">
            <div class="col-md-4 col-sm-6">
              <!-- Hosting Packages Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('footer.hosting_packages') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/hosting') }}">
                      {{ __('footer.web_hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/shared-hosting') }}">
                      {{ __('footer.shared_hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/cloud-hosting') }}">
                      {{ __('footer.cloud_server') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/dedicated-hosting') }}">
                      {{ __('footer.dedicated_hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/reseller-hosting') }}">
                      {{ __('footer.reseller_hosting') }}
                    </a>
                  </li>
                </ul>
              </div>

              <!-- Support Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('footer.support') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/contact') }}">
                      {{ __('footer.contact') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/domain') }}">
                      {{ __('footer.domain') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/services') }}">
                      {{ __('footer.services') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/faq') }}">
                      {{ __('footer.faq') }}
                    </a>
                  </li>
                </ul>
              </div>
            </div>
            
            <div class="col-md-4 col-sm-6">
              <!-- Company Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('footer.company') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/about') }}">
                      {{ __('footer.about_us') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/affiliate') }}">
                      {{ __('footer.affiliate') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/contact') }}">
                      {{ __('footer.contact') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/terms') }}">
                      {{ __('footer.terms') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/privacy') }}">
                      {{ __('footer.privacy') }}
                    </a>
                  </li>
                </ul>
              </div>

              <!-- Contact Details Widget -->
              <div class="ziph-footer_widget vt-text-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('footer.contact_details') }}
                </h4>
                <div>
                  @if($contactAddress)
                  <div class="ziph-footrwidget_loc">
                    <i class="fa fa-map-marker"></i> {{ $contactAddress }}
                  </div>
                  @endif
                  
                  @if($contactPhone)
                  <div class="ziph-footrwidget_loc">
                    <i class="fa fa-phone"></i> 
                    <a class="" href="tel:{{ $contactPhone }}">{{ $contactPhone }}</a>
                  </div>
                  @endif
                  
                  @if($contactEmail)
                  <div class="ziph-footrwidget_loc">
                    <i class="fa fa-envelope"></i> 
                    <a class="" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                  </div>
                  @endif
                </div>
              </div>
            </div>

            <div class="col-md-4 col-sm-6">
              <!-- Domain Names Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('footer.domain_names') }}
                </h4>
                <ul>
                  <li><a href="{{ url('/domain') }}">{{ __('footer.buy_a_domain') }}</a></li>
                  <li><a href="{{ url('/domain') }}">{{ __('footer.premium_domains') }}</a></li>
                  <li><a href="{{ url('/hosting') }}">{{ __('footer.hosting') }}</a></li>
                  <li><a href="{{ url('/services') }}">{{ __('footer.domain_services') }}</a></li>
                  <li><a href="{{ url('/domain') }}">{{ __('footer.domain_check') }}</a></li>
                </ul>
              </div>

              <!-- Social Media Widget -->
              <div class="ziph-footer_widget zipprich-social-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('footer.follow_us') }}
                </h4>
                <ul class="textwidget list-inline ziph-footrwidget_social">
                  @if($socialFacebook)<li><a href="{{ $socialFacebook }}" target="_blank"><i class="fa fa-facebook"></i></a></li>@endif
                  @if($socialTwitter)<li><a href="{{ $socialTwitter }}" target="_blank"><i class="fa fa-twitter"></i></a></li>@endif
                  @if($socialInstagram)<li><a href="{{ $socialInstagram }}" target="_blank"><i class="fa fa-instagram"></i></a></li>@endif
                  @if($socialLinkedIn)<li><a href="{{ $socialLinkedIn }}" target="_blank"><i class="fa fa-linkedin"></i></a></li>@endif
                  @if($socialYoutube)<li><a href="{{ $socialYoutube }}" target="_blank"><i class="fa fa-youtube"></i></a></li>@endif
                  @if($socialPinterest)<li><a href="{{ $socialPinterest }}" target="_blank"><i class="fa fa-pinterest"></i></a></li>@endif
                  @if($socialTiktok)<li><a href="{{ $socialTiktok }}" target="_blank"><i class="fa fa-music"></i></a></li>@endif
                </ul>
                <div class="clear"></div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Footer Contact Form -->
        <div class="col-md-4">
        <div class="ziph-footer_widget ziph-get-contactform">
            <div class="ziph-footer_conform">
              <h4 class="ziph-footrwidget_title">
                {{ __('footer.contact_form') }}
              </h4>
              <div class="ziph-ftrform_warp">
                <form action="{{ url('/contact') }}" method="POST" class="wpcf7-form">
                  @csrf
                  <div class="row ziph-input_group ziph-m-0">
                    <div class="col-md-6 ">
                      <input size="40" class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required" aria-required="true" aria-invalid="false" placeholder="{{ __('footer.name') }}" value="" type="text" name="name" required>
                    </div>
                    <div class="col-md-6 ">
                      <input size="40" class="wpcf7-form-control wpcf7-email wpcf7-validates-as-required wpcf7-text wpcf7-validates-as-email" aria-required="true" aria-invalid="false" placeholder="{{ __('footer.email') }}" value="" type="email" name="email" required>
                    </div>
                  </div>
                  <div class="ziph-input_single ziph-m-0">
                    <textarea cols="40" rows="10" class="wpcf7-form-control wpcf7-textarea" aria-invalid="false" placeholder="{{ __('footer.message') }}" name="message" required></textarea>
                  </div>
                  <p><input class="wpcf7-form-control wpcf7-submit has-spinner ziph-submit-btn" type="submit" value="{{ __('footer.contact_now') }}"><span class="wpcf7-spinner"></span></p>
                </form>
              </div>
            </div>
            <div class="clear"></div>
          </div>
        </div>
      </div>
    </div>
  </div><!-- Footer Widgets -->
  
  <!-- Footer Bottom -->
  <div class="ziph-footer_bottom" @if($footerBottomBgColor || $footerBottomBorderColor) style="@if($footerBottomBgColor)background-color: {{ $footerBottomBgColor }};@endif @if($footerBottomBorderColor)border-top: 1px solid {{ $footerBottomBorderColor }};@endif" @endif>
    <div class="container">
      <div class="row">
        <div class="col-sm-4">
          @if($showLogo || $showTitle)
            <a href="{{ url('/') }}" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
              @if($showLogo)
                @php
                  // Logo del footer: SIEMPRE usar logo2_footer.png (versión pequeña)
                  $footerLogoFinal = url('/') . '/assets/logo2_footer.png';
                @endphp
                <img src="{{ $footerLogoFinal }}"
                     alt="{{ $siteName }}"
                     style="max-height: 60px; width: auto;"
                     onerror="this.onerror=null; this.src='{{ url('/') }}/assets/logo2_footer.png';">
              @endif
              @if($showTitle)
                <span style="font-size: 18px; font-weight: 700; color: #333;">
                  {{ $siteName }}
                </span>
              @endif
            </a>
          @endif
        </div>
        <div class="text-right col-sm-8">
          @php
            // Selector de idiomas en el footer
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

          <p class="ziph-copyright">
            {!! $footerCopyright !!} {{ __('footer.powered_by') }} <a href="https://musedock.net" target="_blank">MuseDock CMS</a>
          </p>

          @if($showFooterLangSelector)
          <div class="footer-language-selector" style="margin-top: 10px;">
            <form action="" method="get" id="footer-language-form" style="display: inline-block;">
              <select name="lang" id="footer-language-select" onchange="this.form.submit();">
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
      </div>
    </div>
  </div><!--/ Footer Bottom -->
</footer>
