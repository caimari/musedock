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
$topbarShowAddress = themeOption('topbar.topbar_show_address', false);
$topbarShowEmail = themeOption('topbar.topbar_show_email', true);
$topbarShowWhatsapp = themeOption('topbar.topbar_show_whatsapp', true);

$headerSticky = themeOption('header.header_sticky', false);

// Selector de idiomas
// (selector de idioma eliminado)
@endphp

<style>
@media (max-width: 767px) {
  /* Flex row to align logo and hamburger vertically */
  .ziph-header_navigation .container > .row {
    display: flex !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
  }
  .ziph-header_navigation .container > .row > [class*="col-"] {
    float: none !important;
  }
  /* Fix hamburger: remove ugly default background, align nicely */
  .menu-collapser {
    background: none !important;
    height: auto !important;
    line-height: normal !important;
    padding: 0 !important;
    text-align: right !important;
  }
  .menu-collapser .collapse-button {
    position: relative !important;
    display: inline-flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 4px !important;
    transform: none !important;
    top: auto !important;
    right: auto !important;
    float: right !important;
    width: 40px !important;
    height: 40px !important;
    padding: 0 !important;
    border-radius: 8px !important;
    background: #f0f4f8 !important;
    border: 1px solid #dde3eb !important;
    cursor: pointer !important;
  }
  .menu-collapser .collapse-button:hover {
    background: #e4eaf1 !important;
  }
  .menu-collapser .collapse-button .icon-bar {
    background-color: #243141 !important;
    width: 18px !important;
    height: 2.5px !important;
    border-radius: 2px !important;
    margin: 0 !important;
    display: block !important;
  }
}
</style>
<!-- Header -->
<header class="ziph-header_area @if($headerSticky) ziph-is-sticky @endif">
  @if($topbarEnabled)
  <!-- header top start -->
  <div class="ziph-header_top">
    <div class="container">
      <div class="row">
        <div class="col-sm-4">
          @if($contactPhone)
          <div class="ziph-head_info ziph-head_phnum">
            <a href="tel:{{ $contactPhone }}">
              <i class="fa fa-phone"></i>T:{{ $contactPhone }}
            </a>
          </div>
          @endif
        </div>
        <div class="ziph-fix col-sm-8">
          <div class="ziph-head_info ziph-flt_right">
            @if($topbarShowEmail && $contactEmail)
            <a href="mailto:{{ $contactEmail }}" class="icon-fa-envelope">
              <i class="fa fa-envelope"></i> {{ __('header.mail') }}
            </a>
            @endif

            @if($topbarShowWhatsapp && $contactWhatsapp)
            <a href="https://wa.me/{{ str_replace(['+', '-', ' '], '', $contactWhatsapp) }}" target="_blank" class="icon-fa-whatsapp">
              <i class="fa fa-whatsapp"></i> {{ __('header.whatsapp') }}
            </a>
            @endif



            <a href="{{ url('/soporte') }}" class="icon-fa-question-circle">
              <i class="fa fa-question-circle"></i> {{ __('header.support') }}
            </a>
            
            {{-- Login / Dashboard Button --}}
            <div class="ziph-flt_right ziph-headlogin_btn">
              @if(!empty($_SESSION['customer']))
                <a href="{{ url('/customer/dashboard') }}" class="btn btn-info">Dashboard</a>
              @else
                <a href="{{ url('/customer/login') }}" class="btn btn-info">{{ __('header.login') }}</a>
              @endif
            </div>
            
          </div>
        </div>
      </div>
    </div>
  </div><!--/ header top end -->
  @endif

  <!-- Navigation -->
  <div class="ziph-header_navigation ziph-header-main-menu">
    <div class="container">
      <div class="row">
        <!-- logo start -->
        <div class="col-md-3 col-xs-6 col-sm-4">
          <div class="ziph-logo" style="padding-top:;padding-bottom:;">
            <a href="{{ url('/') }}">
              @php
              // Logo por defecto de MuseDock
              if ($showLogo && $logoPath) {
                  $finalLogo = public_file_url($logoPath);
              } else {
                  $finalLogo = url('/') . '/assets/logo-default.png';
              }
              @endphp
              <img src="{{ $finalLogo }}" alt="{{ $siteName }}" class="retina-logo" style="max-height: 50px; width: auto;" onerror="this.onerror=null; this.src='{{ url('/') }}/assets/logo-default.png';">
              <img src="{{ $finalLogo }}" alt="{{ $siteName }}" class="default-logo" style="max-height: 50px; width: auto;" onerror="this.onerror=null; this.src='{{ url('/') }}/assets/logo-default.png';">
              @if($showTitle)
              <span>{{ $siteName }}</span>
              @endif
            </a>
          </div>
        </div><!-- logo end -->
        
        <div class="col-md-9 col-xs-6 col-sm-8">
          <!-- Mobile Menu -->
          <div class="hidden-md hidden-lg ziph-mobil_menu_warp" data-starts="767">
            @custommenu('nav', null, [
              'ul_id' => 'menu-main-menu',
              'nav_class' => 'ziph-mobil_menu slimmenu',
              'li_class' => 'menu-item',
              'li_active_class' => 'current-menu-item',
              'li_parent_class' => 'menu-item-has-children',
              'submenu_class' => 'sub-menu'
            ])
          </div>
          
          <!-- Desktop Navigation -->
          <nav class="visible-md visible-lg text-right ziph-mainmenu">
            @custommenu('nav', null, [
              'ul_id' => 'menu-main-menu-1',
              'nav_class' => 'list-inline',
              'li_class' => 'menu-item',
              'li_active_class' => 'current-menu-item',
              'li_parent_class' => 'menu-item-has-children',
              'submenu_class' => 'sub-menu'
            ])
          </nav>
        </div>
      </div><!-- Row -->
    </div><!-- Container -->
  </div>
</header>
