@extends('layouts.app')

@section('title')
{{ $page_title ?? 'Mi Panel' }} | {{ site_setting('site_name', 'MuseDock') }}
@endsection

@php
  $customerPanelLayoutMode = strtolower((string) setting('cloud_customer_panel_layout', 'full'));
  if (!in_array($customerPanelLayoutMode, ['full', 'compact'], true)) {
    $customerPanelLayoutMode = 'full';
  }
  $isCustomerPanelFull = $customerPanelLayoutMode !== 'compact';
  $customerPanelTheme = strtolower((string) setting('cloud_customer_panel_theme', 'slate_blue'));
  $allowedCustomerPanelThemes = ['slate_blue', 'graphite_cyan', 'soft_blue_gray'];
  if (!in_array($customerPanelTheme, $allowedCustomerPanelThemes, true)) {
    $customerPanelTheme = 'slate_blue';
  }
@endphp

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  @if($isCustomerPanelFull)
  /* Modo panel completo: esconder layout público y usar pantalla completa */
  .ziph-header_area,
  .ziph-footer_area {
    display: none !important;
  }

  #zipprich-wrapper {
    min-height: 100vh !important;
  }

  .ziph-page_content.customer-panel-full {
    padding: 0 !important;
    margin: 0 !important;
    background: #ffffff;
  }

  .ziph-page_content.customer-theme-slate_blue {
    --cp-bg: #dfe8f7;
    --cp-surface: #f3f7ff;
    --cp-border: #ccdaef;
    --cp-topbar-bg: linear-gradient(90deg, #dfeaff 0%, #edf4ff 100%);
    --cp-topbar-border: #bfd0ea;
    --cp-topbar-text: #243141;
    --cp-sidebar-bg: #0f172a;
    --cp-sidebar-text: #cbd5e1;
    --cp-sidebar-hover: #1e293b;
    --cp-sidebar-active: #2563eb;
    --cp-topbar-panel-bg: #1e3a6b;
    --cp-menu-text: #30445d;
    --cp-menu-hover-bg: #f3f7fe;
    --cp-menu-hover-text: #1f3248;
    --cp-accent: #2563eb;
    --cp-accent-hover: #1d4ed8;
    --cp-user-btn-bg: #f6f9ff;
    --cp-user-btn-border: #c8d8f7;
    --cp-user-btn-text: #1f3d6b;
    --cp-user-btn-hover-bg: #eaf1ff;
    --cp-user-btn-hover-text: #15325a;
    --cp-user-avatar-bg: #2d5fd4;
  }

  .ziph-page_content.customer-theme-graphite_cyan {
    --cp-bg: #e5edf2;
    --cp-surface: #ffffff;
    --cp-border: #d6dee8;
    --cp-topbar-bg: linear-gradient(90deg, #e5f7fb 0%, #f1fbff 100%);
    --cp-topbar-border: #c5e6ef;
    --cp-topbar-text: #1f2a37;
    --cp-sidebar-bg: #111827;
    --cp-sidebar-text: #9ca3af;
    --cp-sidebar-hover: #1f2937;
    --cp-sidebar-active: #0891b2;
    --cp-topbar-panel-bg: #1a4d5c;
    --cp-menu-text: #1f3347;
    --cp-menu-hover-bg: #ecfdff;
    --cp-menu-hover-text: #155e75;
    --cp-accent: #0891b2;
    --cp-accent-hover: #0e7490;
    --cp-user-btn-bg: #f0fbfe;
    --cp-user-btn-border: #bfe6ef;
    --cp-user-btn-text: #14556a;
    --cp-user-btn-hover-bg: #e0f7fc;
    --cp-user-btn-hover-text: #0f4758;
    --cp-user-avatar-bg: #0b7f9c;
  }

  .ziph-page_content.customer-theme-soft_blue_gray {
    --cp-bg: #eceff5;
    --cp-surface: #fbfdff;
    --cp-border: #dbe4f0;
    --cp-topbar-bg: linear-gradient(90deg, #eaf0fb 0%, #f5f8fe 100%);
    --cp-topbar-border: #d0dced;
    --cp-topbar-text: #2c3e52;
    --cp-sidebar-bg: #eaf0f8;
    --cp-sidebar-text: #334155;
    --cp-sidebar-hover: #dbe7f5;
    --cp-sidebar-active: #3b82f6;
    --cp-topbar-panel-bg: #2b3f5e;
    --cp-menu-text: #2f4259;
    --cp-menu-hover-bg: #eef4fb;
    --cp-menu-hover-text: #1f3248;
    --cp-accent: #3b82f6;
    --cp-accent-hover: #2563eb;
    --cp-user-btn-bg: #f4f8ff;
    --cp-user-btn-border: #d2def0;
    --cp-user-btn-text: #2b4669;
    --cp-user-btn-hover-bg: #e8f0ff;
    --cp-user-btn-hover-text: #1e3a5f;
    --cp-user-avatar-bg: #3a78de;
  }

  .customer-panel-topbar {
    height: 72px;
    background: var(--cp-topbar-panel-bg, #1e3a6b);
    color: #eef2f9;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 22px;
    position: relative;
    border-radius: 12px 12px 0 0;
  }
  /* Logo del panel: sobre fondo oscuro conviene el logo claro/monocromo */
  .customer-panel-topbar .customer-panel-brand img {
    filter: brightness(0) invert(1);
    opacity: 0.96;
  }

  .customer-panel-brand {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
  }

  .customer-panel-brand img {
    max-height: 46px;
    width: auto;
    display: block;
  }

  .customer-panel-topbar-right {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    position: relative;
  }

  .customer-top-user-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 42px;
    padding: 0 12px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    color: #eef2f9;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none !important;
    background: rgba(255, 255, 255, 0.06);
    cursor: pointer;
    min-width: 220px;
    transition: background .15s ease, border-color .15s ease;
  }

  .customer-top-user-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.22);
  }
  .customer-top-user-btn .bi-chevron-down { color: #9fb2cc; }

  .customer-top-user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: var(--cp-user-avatar-bg);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.74rem;
    flex: 0 0 28px;
  }

  .customer-top-user-text {
    min-width: 0;
    text-align: left;
    flex: 1;
  }

  .customer-top-user-name {
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.1;
  }

  .customer-top-user-email {
    color: #9fb2cc;
    font-size: 0.66rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.1;
    margin-top: 1px;
  }

  .customer-top-user-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 250px;
    background: #fff;
    border: 1px solid var(--cp-border);
    border-radius: 10px;
    box-shadow: 0 8px 26px rgba(24, 39, 75, 0.15);
    padding: 6px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(8px);
    transition: all 0.15s ease;
    z-index: 220;
  }

  .customer-top-user-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  .customer-top-user-menu a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 10px;
    border-radius: 7px;
    color: var(--cp-menu-text);
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
  }

  .customer-top-user-menu a i {
    width: 16px;
    text-align: center;
  }

  .customer-top-user-menu a:hover {
    background: var(--cp-menu-hover-bg);
    color: var(--cp-menu-hover-text);
  }

  .customer-top-user-menu .menu-sep {
    height: 1px;
    margin: 4px 2px;
    background: var(--cp-border);
  }

  .customer-top-user-menu a.menu-danger {
    color: #c53030;
  }

  .customer-top-user-menu a.menu-danger:hover {
    background: #fff1f1;
    color: #9f1d1d;
  }
  @endif

  /* ===== Customer Panel Layout ===== */
  .customer-panel-wrapper {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 280px);
  }
  .customer-panel-wrapper.is-full {
    min-height: calc(100vh - 72px);
  }

  .customer-panel-container-full {
    width: 100%;
    max-width: 1700px;
    margin: 0 auto;
    padding-left: 18px;
    padding-right: 18px;
    padding-top: 18px;
    padding-bottom: 18px;
  }

  /* El panel se lee como una tarjeta sobre el fondo blanco de la página */
  .customer-panel-container-full > .customer-panel-topbar,
  .customer-panel-container-full > .customer-panel-wrapper.is-full {
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.10);
  }
  .customer-panel-container-full > .customer-panel-wrapper.is-full {
    border-radius: 0 0 12px 12px;
  }

  .customer-panel-wrapper.is-full .customer-sidebar {
    width: 232px;
    padding: 16px 12px 14px 12px;
    background: var(--cp-sidebar-bg);
    border: none;
    border-radius: 0 0 0 12px;
  }

  .customer-panel-wrapper.is-full .customer-main {
    padding: 24px 30px;
    background: var(--cp-surface);
    border: 1px solid var(--cp-border);
    border-left: none;
    border-top: none;
    border-radius: 0 0 12px 0;
  }
  .customer-sidebar {
    width: 190px;
    flex-shrink: 0;
    padding: 16px 0 12px 0;
    border-right: 1px solid #edf0f5;
    display: flex;
    flex-direction: column;
  }

  .customer-main {
    flex: 1;
    min-width: 0;
    padding: 16px 24px;
  }

  /* Sidebar nav */
  .cs-nav-link {
    display: flex; align-items: center; gap: 9px;
    padding: 7px 12px; border-radius: 7px;
    font-size: 0.8rem; font-weight: 500; color: #4a5568;
    text-decoration: none; transition: all 0.12s; margin-bottom: 1px;
  }
  .cs-nav-link:hover { background: #f3f4f6; color: #243141; }
  .cs-nav-link.active { background: #4e73df; color: #fff; font-weight: 600; }
  .cs-nav-link i { font-size: 0.9rem; width: 16px; text-align: center; }

  .customer-panel-wrapper.is-full .cs-nav-link {
    color: var(--cp-sidebar-text);
  }
  .customer-panel-wrapper.is-full .cs-nav-link:hover {
    background: var(--cp-sidebar-hover);
    color: #ffffff;
  }
  .customer-panel-wrapper.is-full .cs-nav-link.active {
    background: var(--cp-sidebar-active);
    color: #ffffff;
  }
  .cs-nav-logout { color: #dc3545; }
  .cs-nav-logout:hover { background: #fef2f2; color: #dc3545; }

  /* Content sizing inside panel */
  .customer-main h1, .customer-main h2 { font-size: 1.15rem; font-weight: 700; color: #243141; margin-bottom: 4px; }
  .customer-main h3, .customer-main h4 { font-size: 1rem; font-weight: 600; }
  .customer-main .section-title { font-size: 1rem; }
  .customer-main .stats-card { padding: 16px; border-radius: 10px; }
  .customer-main .stats-card .number { font-size: 1.4rem; }
  .customer-main .stats-card .label { font-size: 0.78rem; }
  .customer-main .stats-card .icon { width: 38px; height: 38px; border-radius: 8px; font-size: 1rem; margin-bottom: 8px; }
  .customer-main .tenant-card { padding: 14px; border-radius: 10px; margin-bottom: 12px; }
  .customer-main .card { border-radius: 10px; border-color: #edf0f5; }
  .customer-main .form-control, .customer-main .form-select { font-size: 0.88rem; }
  .customer-main .btn { font-size: 0.82rem; }
  .customer-main .alert { font-size: 0.85rem; padding: 10px 14px; border-radius: 8px; }
  .customer-main .dashboard-header h2 { font-size: 1.15rem; margin-bottom: 2px; }
  .customer-main .dashboard-header p { font-size: 0.82rem; }
  .customer-main .action-buttons .btn { font-size: 0.8rem; padding: 8px 16px; }

  /* Cards del dashboard (estilos inline con border #edf0f5): sombra + micro-hover */
  .customer-main [style*="border:1px solid #edf0f5"] {
    box-shadow: 0 2px 8px rgba(24, 39, 75, 0.05);
    transition: box-shadow .16s ease, transform .16s ease, border-color .16s ease;
  }
  .customer-main [style*="border:1px solid #edf0f5"]:hover {
    box-shadow: 0 8px 22px rgba(24, 39, 75, 0.10);
    transform: translateY(-1px);
    border-color: #dbe3ef !important;
  }
  /* Títulos de sección con un pequeño acento a la izquierda */
  .customer-main > h3 {
    position: relative;
    padding-left: 12px;
  }
  .customer-main > h3::before {
    content: "";
    position: absolute;
    left: 0; top: 50%;
    transform: translateY(-50%);
    width: 4px; height: 15px;
    border-radius: 3px;
    background: var(--cp-accent, #2563eb);
  }

  /* Pro polish for primary blue theme (fondo claro sutil, sin saturar) */
  .ziph-page_content.customer-theme-slate_blue .customer-panel-wrapper.is-full .customer-main {
    background: #fbfcfe;
  }
  .ziph-page_content.customer-theme-slate_blue .customer-main .card,
  .ziph-page_content.customer-theme-slate_blue .customer-main .cp-card,
  .ziph-page_content.customer-theme-slate_blue .customer-main .ct-card,
  .ziph-page_content.customer-theme-slate_blue .customer-main .ct-empty {
    background: #f8fbff;
    border-color: #d7e4f8;
    box-shadow: 0 7px 18px rgba(29, 78, 216, 0.06);
  }
  .ziph-page_content.customer-theme-slate_blue .customer-main .form-control,
  .ziph-page_content.customer-theme-slate_blue .customer-main .form-select,
  .ziph-page_content.customer-theme-slate_blue .customer-main input[type="text"],
  .ziph-page_content.customer-theme-slate_blue .customer-main input[type="email"],
  .ziph-page_content.customer-theme-slate_blue .customer-main input[type="password"],
  .ziph-page_content.customer-theme-slate_blue .customer-main input[type="tel"],
  .ziph-page_content.customer-theme-slate_blue .customer-main input[type="url"],
  .ziph-page_content.customer-theme-slate_blue .customer-main textarea {
    background: #f6faff;
    border-color: #cfdbef;
  }
  .ziph-page_content.customer-theme-slate_blue .customer-main .table,
  .ziph-page_content.customer-theme-slate_blue .customer-main .table-light th {
    background-color: #f7faff;
  }
  .ziph-page_content.customer-theme-slate_blue .customer-main [style*="background:#fff"] {
    background: #f8fbff !important;
  }
  .ziph-page_content.customer-theme-slate_blue .customer-main [style*="border:1px solid #edf0f5"] {
    border-color: #d7e4f8 !important;
  }

  /* Reusable right-sidebar layout for customer pages */
  .customer-main .cp-page-wrap {
    max-width: 1380px;
    margin: 0 auto;
  }
  .customer-main .cp-two-col {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(300px, 0.95fr);
    gap: 18px;
    align-items: start;
  }
  .customer-main .cp-two-col-main,
  .customer-main .cp-two-col-side {
    min-width: 0;
  }
  .customer-main .cp-two-col .cp-two-col-main { order: 1; }
  .customer-main .cp-two-col .cp-two-col-side { order: 2; }
  @media (max-width: 1200px) {
    .customer-main .cp-two-col {
      grid-template-columns: minmax(0, 1fr) minmax(280px, 0.9fr);
    }
  }
  @media (max-width: 700px) {
    .customer-main .cp-two-col {
      grid-template-columns: 1fr;
    }
  }

  /* Mobile */
  @media (max-width: 768px) {
    .customer-panel-topbar {
      height: 64px;
      padding: 0 12px;
      border-radius: 10px 10px 0 0;
    }
    .customer-panel-brand img {
      max-height: 40px;
    }
    .customer-panel-container-full {
      padding-left: 10px;
      padding-right: 10px;
      padding-top: 10px;
    }
    .customer-top-user-btn {
      min-width: 160px;
      height: 34px;
      padding: 0 8px;
      font-size: 0.74rem;
      gap: 6px;
    }
    .customer-top-user-avatar {
      width: 24px;
      height: 24px;
      flex-basis: 24px;
      border-radius: 6px;
      font-size: 0.68rem;
    }
    .customer-top-user-email {
      display: none;
    }
    .customer-panel-wrapper { flex-direction: column; }
    .customer-sidebar {
      width: 100%; border-right: none; border-bottom: 1px solid #edf0f5;
      padding: 10px 0;
    }
    .customer-panel-wrapper.is-full .customer-sidebar {
      border: 1px solid var(--cp-border);
      border-top: none;
      border-radius: 0;
    }
    .customer-panel-wrapper.is-full .customer-main {
      border: 1px solid var(--cp-border);
      border-top: none;
      border-radius: 0 0 10px 10px;
      padding: 14px 12px;
    }
    .customer-sidebar nav { display: flex; flex-wrap: wrap; gap: 3px; }
    .customer-main { padding: 14px 0; }
    .cs-user-name { display: none; }
    .cs-header-user { right: 10px; }
  }
</style>
@endpush

@section('content')
@php
  $currentPage = $current_page ?? '';
  $customer = $customer ?? null;
  $currentUri = $_SERVER['REQUEST_URI'] ?? '';
  $siteName = site_setting('site_name', 'MuseDock');
  $showLogo = site_setting('show_logo', '1') === '1';
  $logoPath = site_setting('site_logo', '');
  if ($showLogo && !empty($logoPath)) {
    $panelLogo = public_file_url($logoPath);
  } else {
    $panelLogo = url('/') . '/assets/logo-default.png';
  }
@endphp

<div class="padding-none ziph-page_content customer-theme-{{ $customerPanelTheme }} {{ $isCustomerPanelFull ? 'customer-panel-full' : '' }}">
  @if($customer)
  <div class="{{ $isCustomerPanelFull ? 'container-fluid customer-panel-container-full' : 'container' }}">
  @if($isCustomerPanelFull)
  <header class="customer-panel-topbar">
    <div class="customer-panel-topbar-left">
      <a href="{{ url('/') }}" class="customer-panel-brand" aria-label="MuseDock">
        <img src="{{ $panelLogo }}" alt="{{ $siteName }}" onerror="this.onerror=null; this.src='{{ url('/') }}/assets/logo-default.png';">
      </a>
    </div>
    <div class="customer-panel-topbar-right">
      <button type="button" class="customer-top-user-btn" onclick="toggleTopUserMenu(event)">
        <span class="customer-top-user-avatar"><?= strtoupper(substr($customer['name'] ?? 'U', 0, 1)) ?></span>
        <span class="customer-top-user-text">
          <span class="customer-top-user-name"><?= htmlspecialchars($customer['name'] ?? '') ?></span>
          <span class="customer-top-user-email"><?= htmlspecialchars($customer['email'] ?? '') ?></span>
        </span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
      </button>
      <div class="customer-top-user-menu" id="customerTopUserMenu">
        <a href="/customer/dashboard"><i class="bi bi-speedometer2"></i><span>Panel</span></a>
        <a href="/soporte"><i class="bi bi-life-preserver"></i><span>Soporte</span></a>
        <a href="/customer/profile"><i class="bi bi-person"></i><span>Mi perfil</span></a>
        <div class="menu-sep"></div>
        <a href="#" onclick="customerLogout(); return false;" class="menu-danger"><i class="bi bi-box-arrow-left"></i><span>Cerrar sesión</span></a>
      </div>
    </div>
  </header>
  @endif
  <div class="customer-panel-wrapper {{ $isCustomerPanelFull ? 'is-full' : '' }}">

    {{-- Sidebar --}}
    <aside class="customer-sidebar">
      {{-- Nav links --}}
      <nav style="flex-grow:1;">
        @php
          $navItems = [
            ['page' => 'dashboard', 'url' => '/customer/dashboard', 'icon' => 'bi-speedometer2', 'label' => 'Inicio'],
            ['page' => 'free-subdomain', 'url' => '/customer/request-free-subdomain', 'icon' => 'bi-globe', 'label' => 'Crear sitio', 'channel' => 'free_subdomain'],
            ['page' => 'custom-domain', 'url' => '/customer/request-custom-domain', 'icon' => 'bi-link-45deg', 'label' => 'Conectar dominio', 'channel' => 'connect_domain'],
            ['page' => 'register-domain', 'url' => '/customer/register-domain', 'icon' => 'bi-cart-plus', 'label' => 'Registrar dominio', 'channel' => 'register_domain'],
            ['page' => 'contacts', 'url' => '/customer/contacts', 'icon' => 'bi-person-lines-fill', 'label' => 'Contactos'],
            ['page' => 'tenant-admins', 'url' => '/customer/tenant-admins', 'icon' => 'bi-people', 'label' => 'Administradores'],
            ['page' => 'profile', 'url' => '/customer/profile', 'icon' => 'bi-person', 'label' => 'Perfil'],
          ];
          // Ocultar las acciones de alta cerradas (Cloud\Services\SignupGate)
          $navItems = array_values(array_filter($navItems, fn($i) => empty($i['channel'])
              || (class_exists(\Cloud\Services\SignupGate::class) && \Cloud\Services\SignupGate::allows($i['channel']))));
        @endphp
        @foreach($navItems as $item)
          @php $isActive = $currentPage === $item['page'] || str_contains($currentUri, $item['url']); @endphp
          <a href="{{ $item['url'] }}" class="cs-nav-link {{ $isActive ? 'active' : '' }}">
            <i class="{{ $item['icon'] }}"></i><span>{{ __($item['label']) }}</span>
          </a>
        @endforeach
      </nav>
    </aside>

    {{-- Main --}}
    <div class="customer-main">
      @php $gateWarning = consume_flash('warning'); @endphp
      @if($gateWarning)
        <div style="background:#fff8e6; border:1px solid #ffe2a8; color:#7a5b00; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:0.88rem;">
          <i class="bi bi-info-circle" style="margin-right:6px;"></i>{{ $gateWarning }}
        </div>
      @endif
      @yield('panel_content')
    </div>
  </div>
  </div>

  @else
  @yield('panel_content')
  @endif
</div>

@if($customer)
<script>
// Header user dropdown toggle
function toggleTopUserMenu(e) {
  e.stopPropagation();
  var m = document.getElementById('customerTopUserMenu');
  if (m) m.classList.toggle('show');
}
document.addEventListener('click', function() {
  var m = document.getElementById('customerTopUserMenu');
  if (m) m.classList.remove('show');
});
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function customerLogout() {
  Swal.fire({
    title: '{{ __("¿Cerrar sesión?") }}',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#4e73df',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '{{ __("Sí, salir") }}',
    cancelButtonText: '{{ __("Cancelar") }}'
  }).then(function(r) {
    if (r.isConfirmed) {
      var f = document.createElement('form');
      f.method = 'POST'; f.action = '/customer/logout';
      var c = document.createElement('input');
      c.type = 'hidden'; c.name = '_csrf_token'; c.value = '<?= csrf_token() ?>';
      f.appendChild(c); document.body.appendChild(f); f.submit();
    }
  });
}
</script>

@yield('scripts')
@endsection
