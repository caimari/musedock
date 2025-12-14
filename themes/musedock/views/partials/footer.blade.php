@php
$currentLang = detectLanguage();
$footerDesc = translatable_site_setting('footer_short_description', $currentLang, '');
$footerCopyright = site_setting('footer_copyright', '© Copyright ' . date('Y') . ' ' . site_setting('site_name', '') . '.');
$contactEmail = site_setting('contact_email', '');
$contactPhone = site_setting('contact_phone', '');
$contactAddress = site_setting('contact_address', '');
$siteName = site_setting('site_name', 'MuseDock');

// Redes sociales
$socialFacebook = site_setting('social_facebook');
$socialTwitter = site_setting('social_twitter');
$socialInstagram = site_setting('social_instagram');
$socialLinkedIn = site_setting('social_linkedin');
$socialYoutube = site_setting('social_youtube');
$socialPinterest = site_setting('social_pinterest');
$socialTiktok = site_setting('social_tiktok');

// Opciones del tema footer
$footerBgColor = themeOption('footer.footer_bg_color', '#f8fafe');
$footerTextColor = themeOption('footer.footer_text_color', '#333333');
$footerHeadingColor = themeOption('footer.footer_heading_color', '#333333');
$footerLinkColor = themeOption('footer.footer_link_color', '#333333');
$footerLinkHoverColor = themeOption('footer.footer_link_hover_color', '#ff5e15');
$footerIconColor = themeOption('footer.footer_icon_color', '#333333');
$footerBorderColor = themeOption('footer.footer_border_color', '#e5e5e5');
@endphp

<!-- Footer -->
<footer class="ziph-footer_area" style="background-color: {{ $footerBgColor }}; color: {{ $footerTextColor }};">
  <!-- Footer Widgets -->
  <div class="ziph-footer_top">
    <div class="container">
      <div class="row ziph-footer_widgets">
        <!-- Footer Column 1: Hosting Packages & Support -->
        <div class="col-md-8">
          <div class="row">
            <div class="col-md-4 col-sm-6">
              <!-- Hosting Packages Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title" style="color: {{ $footerHeadingColor }};">
                  {{ __('Hosting Packages') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/hosting') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Web Hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/shared-hosting') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Shared Hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/cloud-hosting') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Cloud Server') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/dedicated-hosting') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Dedicated Hosting') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/reseller-hosting') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Reseller Hosting') }}
                    </a>
                  </li>
                </ul>
              </div>
              
              <!-- Support Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title" style="color: {{ $footerHeadingColor }};">
                  {{ __('Support') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/contact') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Contact') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/domain') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Domain') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/services') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Services') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/faq') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('FAQ') }}
                    </a>
                  </li>
                </ul>
              </div>
            </div>
            
            <!-- Footer Column 2: Company & Contact -->
            <div class="col-md-4 col-sm-6">
              <!-- Company Widget -->
              <div class="ziph-footer_widget zipprich-link-widget">
                <h4 class="ziph-footrwidget_title" style="color: {{ $footerHeadingColor }};">
                  {{ __('Company') }}
                </h4>
                <ul>
                  <li>
                    <a href="{{ url('/about') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('About us') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/affiliate') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Affiliate') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/contact') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Contact') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/terms') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Terms') }}
                    </a>
                  </li>
                  <li>
                    <a href="{{ url('/privacy') }}" style="color: {{ $footerLinkColor }};">
                      {{ __('Privacy') }}
                    </a>
                  </li>
                </ul>
              </div>
              
              <!-- Contact Details Widget -->
              <div class="ziph-footer_widget vt-text-widget">
                <h4 class="ziph-footrwidget_title" style="color: {{ $footerHeadingColor }};">
                  {{ __('Contact Details') }}
                </h4>
                <div>
                  @if($contactAddress)
                  <div style="margin-bottom: 10px; color: {{ $footerTextColor }};">
                    <i class="fa fa-map-marker"></i> {{ $contactAddress }}
                  </div>
                  @endif
                  
                  @if($contactPhone)
                  <div style="margin-bottom: 10px; color: {{ $footerTextColor }};">
                    <i class="fa fa-phone"></i> 
                    <a href="tel:{{ $contactPhone }}" style="color: {{ $footerLinkColor }};">
                      {{ $contactPhone }}
                    </a>
                  </div>
                  @endif
                  
                  @if($contactEmail)
                  <div style="color: {{ $footerTextColor }};">
                    <i class="fa fa-envelope"></i> 
                    <a href="mailto:{{ $contactEmail }}" style="color: {{ $footerLinkColor }};">
                      {{ $contactEmail }}
                    </a>
                  </div>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Footer Column 3: Contact Form & Social -->
        <div class="col-md-4">
          <!-- Contact Form Widget -->
          <div class="ziph-footer_widget ziph-get-contactform">
            <div class="ziph-footer_conform">
              <h4 class="ziph-footrwidget_title" style="color: {{ $footerHeadingColor }};">
                {{ __('Contact Form') }}
              </h4>
              <div class="ziph-ftrform_warp">
                <form action="{{ url('/contact') }}" method="POST" class="footer-contact-form">
                  @csrf
                  <div class="row ziph-input_group">
                    <div class="col-md-6">
                      <input type="text" name="name" placeholder="{{ __('Name') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                      <input type="email" name="email" placeholder="{{ __('Email') }}" class="form-control" required>
                    </div>
                  </div>
                  <div class="ziph-input_single">
                    <textarea name="message" placeholder="{{ __('Message') }}" class="form-control" rows="4" required></textarea>
                  </div>
                  <button type="submit" class="ziph-submit-btn">
                    {{ __('Contact now') }}
                  </button>
                </form>
              </div>
            </div>
          </div>
          
          <!-- Social Media Widget -->
          <div class="ziph-footer_widget zipprich-social-widget">
            <h4 class="ziph-footrwidget_title" style="color: {{ $footerHeadingColor }};">
              {{ __('Follow Us') }}
            </h4>
            <ul class="list-inline ziph-footrwidget_social">
              @if($socialFacebook)
              <li>
                <a href="{{ $socialFacebook }}" target="_blank" style="color: {{ $footerIconColor }};">
                  <i class="fa fa-facebook"></i>
                </a>
              </li>
              @endif
              
              @if($socialTwitter)
              <li>
                <a href="{{ $socialTwitter }}" target="_blank" style="color: {{ $footerIconColor }};">
                  <i class="fa fa-twitter"></i>
                </a>
              </li>
              @endif
              
              @if($socialInstagram)
              <li>
                <a href="{{ $socialInstagram }}" target="_blank" style="color: {{ $footerIconColor }};">
                  <i class="fa fa-instagram"></i>
                </a>
              </li>
              @endif
              
              @if($socialLinkedIn)
              <li>
                <a href="{{ $socialLinkedIn }}" target="_blank" style="color: {{ $footerIconColor }};">
                  <i class="fa fa-linkedin"></i>
                </a>
              </li>
              @endif
              
              @if($socialYoutube)
              <li>
                <a href="{{ $socialYoutube }}" target="_blank" style="color: {{ $footerIconColor }};">
                  <i class="fa fa-youtube"></i>
                </a>
              </li>
              @endif
              
              @if($socialPinterest)
              <li>
                <a href="{{ $socialPinterest }}" target="_blank" style="color: {{ $footerIconColor }};">
                  <i class="fa fa-pinterest"></i>
                </a>
              </li>
              @endif
              
              @if($socialTiktok)
              <li>
                <a href="{{ $socialTiktok }}" target="_blank" style="color: {{ $footerIconColor }};">
                  <i class="fa fa-music"></i>
                </a>
              </li>
              @endif
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div><!-- Footer Widgets -->
  
  <!-- Footer Bottom -->
  <div class="ziph-footer_bottom" style="background-color: {{ $footerBgColor }}; border-top: 1px solid {{ $footerBorderColor }};">
    <div class="container">
      <div class="row">
        <div class="col-sm-4">
          <div class="ziph-footer_logo">
            <a href="{{ url('/') }}">
              <img src="{{ asset('themes/musedock/images/logo-black-2x.png') }}" alt="{{ $siteName }}" width="154" height="36">
            </a>
          </div>
        </div>
        <div class="text-right col-sm-8">
          <p class="ziph-copyright" style="color: {{ $footerTextColor }};">
            {!! $footerCopyright !!} {{ __('Powered by') }} <a href="https://musedock.net" target="_blank" style="color: {{ $footerLinkColor }};">MuseDock CMS</a>
          </p>
        </div>
      </div>
    </div>
  </div><!--/ Footer Bottom -->
</footer>

{{-- Styles adicionales para el footer --}}
@push('styles')
<style>
.ziph-footer_area ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ziph-footer_area ul li {
    margin-bottom: 8px;
}

.ziph-footer_area a {
    text-decoration: none;
    transition: color 0.3s ease;
}

.ziph-footer_area a:hover {
    color: var(--footer-link-hover-color) !important;
}

.ziph-footrwidget_social li {
    display: inline-block;
    margin-right: 10px;
    margin-bottom: 0;
}

.ziph-footrwidget_social a {
    display: inline-block;
    width: 36px;
    height: 36px;
    line-height: 36px;
    text-align: center;
    background-color: rgba(0,0,0,0.1);
    border-radius: 50%;
    transition: all 0.3s ease;
}

.ziph-footrwidget_social a:hover {
    background-color: var(--footer-link-hover-color);
    color: #fff !important;
    transform: translateY(-2px);
}

.footer-contact-form .form-control {
    background-color: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: var(--footer-text-color);
    margin-bottom: 10px;
}

.footer-contact-form .form-control::placeholder {
    color: rgba(255,255,255,0.6);
}

.ziph-submit-btn {
    background-color: var(--footer-link-hover-color);
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.ziph-submit-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

@media (max-width: 767px) {
    .ziph-footer_widgets .col-md-8,
    .ziph-footer_widgets .col-md-4 {
        margin-bottom: 30px;
    }
    
    .ziph-footer_bottom .text-right {
        text-align: center;
        margin-top: 15px;
    }
}
</style>
@endpush

{{-- Script para el formulario de contacto --}}
@push('scripts')
<script>
$(document).ready(function() {
    $('.footer-contact-form').submit(function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('.ziph-submit-btn');
        var originalText = submitBtn.text();
        
        submitBtn.text('{{ __('Sending...') }}').prop('disabled', true);
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                submitBtn.text('{{ __('Sent!') }}');
                form[0].reset();
                setTimeout(function() {
                    submitBtn.text(originalText).prop('disabled', false);
                }, 3000);
            },
            error: function() {
                submitBtn.text('{{ __('Error') }}');
                setTimeout(function() {
                    submitBtn.text(originalText).prop('disabled', false);
                }, 3000);
            }
        });
    });
});
</script>
@endpush
