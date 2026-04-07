@extends('layouts.app')

@section('title')
{{ $page_title ?? 'Mi Panel' }} | {{ site_setting('site_name', 'MuseDock') }}
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  /* ===== Customer Panel Layout ===== */
  .customer-panel-wrapper {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 280px);
  }
  .customer-sidebar {
    width: 190px;
    flex-shrink: 0;
    padding: 16px 0 12px 0;
    border-right: 1px solid #edf0f5;
    display: flex;
    flex-direction: column;
  }

  /* Sidebar footer: user dropdown (opens upward) */
  .cs-sidebar-footer {
    border-top: 1px solid #f0f2f5;
    padding-top: 10px;
    margin-top: auto;
    position: relative;
  }
  .cs-sidebar-user-btn {
    display: flex; align-items: center; gap: 8px;
    width: 100%; padding: 8px 12px; border-radius: 7px;
    border: none; background: transparent; cursor: pointer;
    transition: background 0.12s; text-align: left;
  }
  .cs-sidebar-user-btn:hover { background: #f3f4f6; }
  .cs-sidebar-menu {
    position: absolute; bottom: calc(100% + 6px); left: 8px; right: 8px;
    background: #fff; border-radius: 8px;
    box-shadow: 0 -4px 16px rgba(0,0,0,0.12);
    opacity: 0; visibility: hidden;
    transform: translateY(6px);
    transition: all 0.15s;
    z-index: 100;
  }
  .cs-sidebar-menu.show {
    opacity: 1; visibility: visible; transform: translateY(0);
  }
  .cs-sidebar-menu a {
    display: flex; align-items: center;
    padding: 9px 14px; font-size: 0.8rem; font-weight: 500;
    color: #4a5568; text-decoration: none;
    transition: background 0.1s;
  }
  .cs-sidebar-menu a:hover { background: #f8f9fa; }
  .cs-sidebar-menu a:first-child { border-radius: 8px 8px 0 0; }
  .cs-sidebar-menu a:last-child { border-radius: 0 0 8px 8px; }
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

  /* Mobile */
  @media (max-width: 768px) {
    .customer-panel-wrapper { flex-direction: column; }
    .customer-sidebar {
      width: 100%; border-right: none; border-bottom: 1px solid #edf0f5;
      padding: 10px 0;
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
@endphp

<div class="padding-none ziph-page_content">
  @if($customer)
  <div class="container">
  <div class="customer-panel-wrapper">

    {{-- Sidebar --}}
    <aside class="customer-sidebar">
      {{-- Nav links --}}
      <nav style="flex-grow:1;">
        @php
          $navItems = [
            ['page' => 'dashboard', 'url' => '/customer/dashboard', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
            ['page' => 'free-subdomain', 'url' => '/customer/request-free-subdomain', 'icon' => 'bi-globe', 'label' => 'Nuevo sitio'],
            ['page' => 'custom-domain', 'url' => '/customer/request-custom-domain', 'icon' => 'bi-link-45deg', 'label' => 'Vincular dominio'],
            ['page' => 'register-domain', 'url' => '/customer/register-domain', 'icon' => 'bi-cart-plus', 'label' => 'Registrar dominio'],
            ['page' => 'contacts', 'url' => '/customer/contacts', 'icon' => 'bi-person-lines-fill', 'label' => 'Mis contactos'],
            ['page' => 'tenant-admins', 'url' => '/customer/tenant-admins', 'icon' => 'bi-people', 'label' => 'Administradores'],
            ['page' => 'profile', 'url' => '/customer/profile', 'icon' => 'bi-person', 'label' => 'Mi perfil'],
          ];
        @endphp
        @foreach($navItems as $item)
          @php $isActive = $currentPage === $item['page'] || str_contains($currentUri, $item['url']); @endphp
          <a href="{{ $item['url'] }}" class="cs-nav-link {{ $isActive ? 'active' : '' }}">
            <i class="{{ $item['icon'] }}"></i> {{ __($item['label']) }}
          </a>
        @endforeach
      </nav>

      {{-- Footer: User dropdown (opens upward) --}}
      <div class="cs-sidebar-footer">
        <button class="cs-sidebar-user-btn" onclick="toggleSidebarMenu(event)">
          <div style="width:30px; height:30px; border-radius:7px; background:#4e73df; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.75rem; flex-shrink:0;">
            <?= strtoupper(substr($customer['name'] ?? 'U', 0, 1)) ?>
          </div>
          <div style="min-width:0; flex:1; text-align:left;">
            <div style="font-size:0.75rem; font-weight:600; color:#243141; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($customer['name'] ?? '') ?></div>
            <div style="font-size:0.62rem; color:#8a94a6; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($customer['email'] ?? '') ?></div>
          </div>
          <i class="bi bi-chevron-up" style="font-size:0.55rem; color:#8a94a6;"></i>
        </button>
        <div class="cs-sidebar-menu" id="csSidebarMenu">
          <a href="/customer/profile"><i class="bi bi-person" style="color:#4e73df; margin-right:6px;"></i> Mi Perfil</a>
          <div style="height:1px; background:#f0f2f5; margin:4px 0;"></div>
          <a href="#" onclick="customerLogout(); return false;" style="color:#dc3545;">
            <i class="bi bi-box-arrow-left" style="color:#dc3545; margin-right:6px;"></i> Cerrar sesión
          </a>
        </div>
      </div>
    </aside>

    {{-- Main --}}
    <div class="customer-main">
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
// Sidebar user dropdown toggle
function toggleSidebarMenu(e) {
  e.stopPropagation();
  var m = document.getElementById('csSidebarMenu');
  if (m) m.classList.toggle('show');
}
document.addEventListener('click', function() {
  var m = document.getElementById('csSidebarMenu');
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
