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
              <i class="fa fa-envelope"></i> {{ __('Web Mail') }}
            </a>
            @endif
            
            @if($topbarShowWhatsapp && $contactWhatsapp)
            <a href="https://wa.me/{{ str_replace(['+', '-', ' '], '', $contactWhatsapp) }}" target="_blank" class="icon-fa-whatsapp">
              <i class="fa fa-whatsapp"></i> {{ __('WhatsApp') }}
            </a>
            @endif
            

            
            <a href="{{ url('/contact') }}" class="icon-fa-question-circle">
              <i class="fa fa-question-circle"></i> {{ __('Support') }}
            </a>
            
            {{-- Login Button --}}
            <div class="ziph-flt_right ziph-headlogin_btn">
              <a href="{{ url('/customer/login') }}" class="btn btn-info">{{ __('Login') }}</a>
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
              $finalLogo = ($showLogo && $logoPath) ? asset(ltrim($logoPath, '/')) : asset('themes/musedock/images/logo-1.png');
              @endphp
              <img src="{{ $finalLogo }}" alt="{{ $siteName }}" width="154" height="36" class="retina-logo">
              <img src="{{ $finalLogo }}" alt="{{ $siteName }}" width="154" height="36" class="default-logo">
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
