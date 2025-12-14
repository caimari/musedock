@php
$showLogo = site_setting('show_logo', '1') === '1';
$showTitle = site_setting('show_title', '0') === '1';
$siteName = site_setting('site_name', 'MuseDock');
$logoPath = site_setting('site_logo', '');
$contactEmail = site_setting('contact_email', '');
$contactPhone = site_setting('contact_phone', '');
$contactWhatsapp = site_setting('contact_whatsapp', '');

// Opciones del tema
$topbarEnabled = themeOption('topbar.topbar_enabled', true);
$topbarBgColor = themeOption('topbar.topbar_bg_color', '#1a2a40');
$topbarTextColor = themeOption('topbar.topbar_text_color', '#ffffff');
$topbarShowAddress = themeOption('topbar.topbar_show_address', false);
$topbarShowEmail = themeOption('topbar.topbar_show_email', true);
$topbarShowWhatsapp = themeOption('topbar.topbar_show_whatsapp', true);

$headerSticky = themeOption('header.header_sticky', false);
$headerCtaEnabled = themeOption('header.header_cta_enabled', false);
$ctaText = detectLanguage() === 'en' ? themeOption('header.header_cta_text_en', 'Sign Up') : themeOption('header.header_cta_text_es', 'Inscríbete');
$ctaUrl = themeOption('header.header_cta_url', '#');
$ctaBgColor = themeOption('header.header_cta_bg_color', '#ff5e15');
$ctaTextColor = themeOption('header.header_cta_text_color', '#ffffff');

// Selector de idiomas
$pdo = \Screenart\Musedock\Database::connect();
$tenantId = tenant_id();
if ($tenantId) {
    $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id = ? AND active = 1 ORDER BY order_position ASC");
    $stmt->execute([$tenantId]);
} else {
    $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id IS NULL AND active = 1 ORDER BY order_position ASC");
    $stmt->execute();
}
$languages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
$currentLang = detectLanguage();
@endphp

<!-- Header -->
<header class="ziph-header_area @if($headerSticky) sticky-header @endif">
  @if($topbarEnabled)
  <!-- header top start -->
  <div class="ziph-header_top" style="background-color: {{ $topbarBgColor }}; color: {{ $topbarTextColor }};">
    <div class="container">
      <div class="row">
        <div class="col-sm-4">
          @if($contactPhone)
          <div class="ziph-head_info">
            <a href="tel:{{ $contactPhone }}" style="color: {{ $topbarTextColor }};">
              <i class="fa fa-phone"></i> {{ __('Tel') }}: {{ $contactPhone }}
            </a>
          </div>
          @endif
        </div>
        <div class="col-sm-8 text-right">
          <div class="ziph-head_info ziph-flt_right">
            @if($topbarShowEmail && $contactEmail)
            <a href="mailto:{{ $contactEmail }}" class="icon-fa-envelope" style="color: {{ $topbarTextColor }};">
              <i class="fa fa-envelope"></i> {{ __('Web Mail') }}
            </a>
            @endif
            
            @if($topbarShowWhatsapp && $contactWhatsapp)
            <a href="https://wa.me/{{ str_replace(['+', '-', ' '], '', $contactWhatsapp) }}" target="_blank" class="icon-fa-whatsapp" style="color: {{ $topbarTextColor }};">
              <i class="fa fa-whatsapp"></i> {{ __('WhatsApp') }}
            </a>
            @endif
            
            @if($contactEmail)
            <a href="mailto:{{ $contactEmail }}" class="icon-fa-headphones" style="color: {{ $topbarTextColor }};">
              <i class="fa fa-headphones"></i> {{ __('Live Chat') }}
            </a>
            @endif
            
            <a href="{{ url('/contact') }}" class="icon-fa-question-circle" style="color: {{ $topbarTextColor }};">
              <i class="fa fa-question-circle"></i> {{ __('Support') }}
            </a>
            
            {{-- Login Button --}}
            <div class="ziph-flt_right ziph-headlogin_btn">
              <a href="{{ url('/login') }}" class="btn btn-info">{{ __('Login') }}</a>
            </div>
            
            {{-- Language Selector --}}
            @if(count($languages) > 1)
            <div class="ziph-flt_right ziph-language-selector">
              <select onchange="window.location.href='?lang=' + this.value" class="form-control" style="background-color: {{ $topbarBgColor }}; color: {{ $topbarTextColor }}; border: 1px solid rgba(255,255,255,0.2);">
                @foreach($languages as $lang)
                <option value="{{ $lang['code'] }}" {{ $currentLang == $lang['code'] ? 'selected' : '' }}>
                  {{ $lang['name'] }}
                </option>
                @endforeach
              </select>
            </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div><!--/ header top end -->
  @endif

  <!-- Navigation -->
  <div class="ziph-header_navigation ziph-header-main-menu" style="background-color: {{ themeOption('header.header_bg_color', '#ffffff') }};">
    <div class="container">
      <div class="row">
        <!-- logo start -->
        <div class="col-md-3 col-xs-6 col-sm-4">
          <div class="ziph-logo">
            <a href="{{ url('/') }}">
              @if($showLogo && $logoPath)
              <img src="{{ asset(ltrim($logoPath, '/')) }}" alt="{{ $siteName }}" width="154" height="36">
              @else
              <img src="{{ asset('themes/musedock/images/logo-1.png') }}" alt="{{ $siteName }}" width="154" height="36">
              @endif
              @if($showTitle)
              <span style="color: {{ themeOption('header.header_logo_text_color', '#1a2a40') }};">{{ $siteName }}</span>
              @endif
            </a>
          </div>
        </div><!-- logo end -->
        
        <div class="col-md-9 col-xs-6 col-sm-8">
          <!-- Mobile Menu Toggle -->
          <div class="hidden-md hidden-lg ziph-mobil_menu_warp" data-starts="767">
            <div class="menu-collapser">
              <div class="collapse-button">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
              </div>
            </div>
          </div>
          
          <!-- Navigation Start -->
          <nav class="visible-md visible-lg text-right ziph-mainmenu">
            @custommenu('nav', null, [
              'ul_id' => 'main-menu',
              'nav_class' => 'list-inline',
              'li_class' => 'menu-item',
              'a_class' => 'nav-link',
              'submenu_class' => 'dropdown-menu',
              'submenu_wrapper_class' => 'ziph-megamenu_warp'
            ])
          </nav>
          
          {{-- CTA Button --}}
          @if($headerCtaEnabled)
          <div class="ziph-header-cta ziph-flt_right">
            <a href="{{ $ctaUrl }}" class="btn ziph-cta-btn" style="background-color: {{ $ctaBgColor }}; color: {{ $ctaTextColor }};">
              {{ $ctaText }}
            </a>
          </div>
          @endif
        </div>
      </div><!-- Row -->
    </div><!-- Container -->
  </div>
</header>

{{-- Styles adicionales para el header --}}
@push('styles')
<style>
.ziph-header-top a:hover {
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.ziph-language-selector select {
    padding: 4px 8px;
    border-radius: 4px;
    margin-left: 10px;
}

.ziph-cta-btn {
    margin-left: 15px;
    padding: 8px 20px;
    border-radius: 4px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.ziph-cta-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.sticky-header {
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

@media (max-width: 767px) {
    .ziph-header-cta {
        margin-top: 10px;
        text-align: center;
    }
    
    .ziph-language-selector {
        margin-top: 5px;
        width: 100%;
    }
    
    .ziph-language-selector select {
        width: 100%;
    }
}
</style>
@endpush
