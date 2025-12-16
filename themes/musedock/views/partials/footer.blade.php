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
                  {{ __('Hosting Packages') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/hosting') }}">
                      {{ __('Web Hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/shared-hosting') }}">
                      {{ __('Shared Hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/cloud-hosting') }}">
                      {{ __('Cloud Server') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/dedicated-hosting') }}">
                      {{ __('Dedicated Hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/reseller-hosting') }}">
                      {{ __('Reseller Hosting') }}
                    </a>
                  </li>
                </ul>
              </div>
              
              <!-- Support Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('Support') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/contact') }}">
                      {{ __('Contact') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/domain') }}">
                      {{ __('Domain') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/services') }}">
                      {{ __('Services') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/faq') }}">
                      {{ __('FAQ') }}
                    </a>
                  </li>
                </ul>
              </div>
            </div>
            
            <div class="col-md-4 col-sm-6">
              <!-- Company Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('Company') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/about') }}">
                      {{ __('About us') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/affiliate') }}">
                      {{ __('Affiliate') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/contact') }}">
                      {{ __('Contact') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/terms') }}">
                      {{ __('Terms') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/privacy') }}">
                      {{ __('Privacy') }}
                    </a>
                  </li>
                </ul>
              </div>
              
              <!-- Contact Details Widget -->
              <div class="ziph-footer_widget vt-text-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('Contact Details') }}
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
                  {{ __('Domain Names') }}
                </h4>
                <ul>
                  <li><a href="{{ url('/domain') }}">{{ __('Buy a Domain') }}</a></li>
                  <li><a href="{{ url('/domain') }}">{{ __('Premium Domains') }}</a></li>
                  <li><a href="{{ url('/hosting') }}">{{ __('Hosting') }}</a></li>
                  <li><a href="{{ url('/services') }}">{{ __('Domain Services') }}</a></li>
                  <li><a href="{{ url('/domain') }}">{{ __('Domain Check') }}</a></li>
                </ul>
              </div>

              <!-- Social Media Widget -->
              <div class="ziph-footer_widget zipprich-social-widget">
                <h4 class="ziph-footrwidget_title">
                  {{ __('Follow Us') }}
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
                {{ __('Contact Form') }}
              </h4>
              <div class="ziph-ftrform_warp">
                <form action="{{ url('/contact') }}" method="POST" class="wpcf7-form">
                  @csrf
                  <div class="row ziph-input_group ziph-m-0">
                    <div class="col-md-6 ">
                      <input size="40" class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required" aria-required="true" aria-invalid="false" placeholder="{{ __('Name') }}" value="" type="text" name="name" required>
                    </div>
                    <div class="col-md-6 ">
                      <input size="40" class="wpcf7-form-control wpcf7-email wpcf7-validates-as-required wpcf7-text wpcf7-validates-as-email" aria-required="true" aria-invalid="false" placeholder="{{ __('Email') }}" value="" type="email" name="email" required>
                    </div>
                  </div>
                  <div class="ziph-input_single ziph-m-0">
                    <textarea cols="40" rows="10" class="wpcf7-form-control wpcf7-textarea" aria-invalid="false" placeholder="{{ __('Message') }}" name="message" required></textarea>
                  </div>
                  <p><input class="wpcf7-form-control wpcf7-submit has-spinner ziph-submit-btn" type="submit" value="{{ __('Contact now') }}"><span class="wpcf7-spinner"></span></p>
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
          <p class="ziph-copyright">
            {!! $footerCopyright !!} {{ __('Powered by') }} <a href="https://musedock.net" target="_blank">MuseDock CMS</a>
          </p>
        </div>
      </div>
    </div>
  </div><!--/ Footer Bottom -->
</footer>
