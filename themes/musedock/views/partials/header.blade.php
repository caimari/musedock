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
$topbarVersionsEnabled = site_setting('topbar_versions_enabled', '1') === '1';
$topbarVersionData = \Screenart\Musedock\Services\PublicVersionBadgeService::getTopbarData();
$cmsCurrentVersion = trim((string)($topbarVersionData['cms_current'] ?? ''));
$cmsLatestVersion = trim((string)($topbarVersionData['cms_latest'] ?? ''));
$panelLatestVersion = trim((string)($topbarVersionData['panel_latest'] ?? ''));
$cmsBadgeVersion = $cmsCurrentVersion;
if ($cmsLatestVersion !== '' && $cmsCurrentVersion !== '' && version_compare($cmsLatestVersion, $cmsCurrentVersion, '>')) {
  $cmsBadgeVersion = $cmsLatestVersion;
}
if ($cmsBadgeVersion === '') {
  $cmsBadgeVersion = $cmsLatestVersion;
}
$cmsRepoUrl = trim((string)($topbarVersionData['cms_repo_url'] ?? 'https://github.com/caimari/musedock'));
$panelRepoUrl = trim((string)($topbarVersionData['panel_repo_url'] ?? 'https://github.com/caimari/musedock-panel'));
$cmsHasUpdate = !empty($topbarVersionData['cms_has_update']);

// Selector de idiomas
// (selector de idioma eliminado)
@endphp

<style>
.ziph-head_versions {
  display: flex;
  align-items: center;
  gap: 6px;
  min-height: 0;
  line-height: 1;
}

.ziph-version-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid rgba(36, 49, 65, 0.16);
  background: #f3f6fa;
  color: #2f3f52 !important;
  border-radius: 4px;
  padding: 1px 8px;
  height: 28px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  text-decoration: none !important;
  white-space: nowrap;
}

.ziph-version-chip .fa {
  font-size: 11px;
}

.ziph-version-license-text {
  margin-left: 2px;
  font-size: 10px;
  font-weight: 700;
  color: #4e5f73;
  letter-spacing: 0.02em;
}

.ziph-version-chip:hover {
  background: #e8eef6;
  color: #1e2c3a !important;
}

.ziph-version-chip.ziph-version-has-update {
  border-color: #80b900;
  background: #eef8dc;
}

.ziph-version-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: #80b900;
  display: inline-block;
}

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

@media (max-width: 1199px) {
  .ziph-head_versions {
    display: none !important;
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
          @if($topbarVersionsEnabled)
          <div class="ziph-head_info ziph-head_versions">
            <a href="{{ $cmsRepoUrl }}" target="_blank" rel="noopener noreferrer" class="ziph-version-chip @if($cmsHasUpdate) ziph-version-has-update @endif" title="MuseDock CMS en GitHub @if($cmsCurrentVersion !== '') · Instalada: v{{ $cmsCurrentVersion }} @endif @if($cmsLatestVersion !== '') · Última: v{{ $cmsLatestVersion }} @endif · Licencia: Source Available (Provider Use)">
              <i class="fa fa-github"></i>
              <span>CMS {{ $cmsBadgeVersion !== '' ? 'v' . $cmsBadgeVersion : 'n/d' }}</span>
              <span class="ziph-version-license-text">SAL</span>
              @if($cmsHasUpdate)
              <span class="ziph-version-dot" title="Hay actualización disponible"></span>
              @endif
            </a>
            <a href="{{ $panelRepoUrl }}" target="_blank" rel="noopener noreferrer" class="ziph-version-chip" title="MuseDock Panel en GitHub · Licencia: Source Available (Provider Use)">
              <i class="fa fa-github"></i>
              <span>Panel {{ $panelLatestVersion !== '' ? 'v' . $panelLatestVersion : 'n/d' }}</span>
              <span class="ziph-version-license-text">SAL</span>
            </a>
          </div>
          @elseif($contactPhone)
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
