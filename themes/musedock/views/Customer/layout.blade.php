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
  // Tema de color del panel (Ajustes de Cloud): solo cambia el color de acento
  $customerPanelTheme = strtolower((string) setting('cloud_customer_panel_theme', 'slate_blue'));
  $panelAccents = [
    'slate_blue'     => ['#2563eb', '#1d4ed8', '#eaf1ff'],
    'graphite_cyan'  => ['#0e7490', '#155e75', '#e6f6fa'],
    'soft_blue_gray' => ['#3b6fd8', '#2f5bb8', '#ecf2fc'],
  ];
  if (!isset($panelAccents[$customerPanelTheme])) {
    $customerPanelTheme = 'slate_blue';
  }
  [$cpAccent, $cpAccentHover, $cpAccentSoft] = $panelAccents[$customerPanelTheme];
@endphp

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  /* ===== Panel de clientes: estilo app (barra lateral clara, tarjetas redondeadas) ===== */
  .mdp-app {
    --cp-accent: {{ $cpAccent }};
    --cp-accent-hover: {{ $cpAccentHover }};
    --cp-accent-soft: {{ $cpAccentSoft }};
    --cp-bg: #f4f6fb;
    --cp-surface: #ffffff;
    --cp-border: #e6eaf2;
    --cp-text: #1f2937;
    --cp-muted: #64748b;
    --cp-radius: 16px;
    --cp-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 6px 20px rgba(16, 24, 40, .05);
    color: var(--cp-text);
  }

  @if($isCustomerPanelFull)
  /* Pantalla completa: sin la cabecera ni el pie de la web pública */
  .ziph-header_area, .ziph-footer_area { display: none !important; }
  #zipprich-wrapper { min-height: 100vh !important; }
  .ziph-page_content.customer-panel-full { padding: 0 !important; margin: 0 !important; background: var(--cp-bg); }
  @endif

  .mdp-shell { display: flex; min-height: 100vh; background: var(--cp-bg); }
  .mdp-shell.is-compact { min-height: calc(100vh - 280px); background: transparent; }

  /* --- Barra lateral --- */
  .mdp-sidebar {
    width: 268px; flex: 0 0 268px;
    background: var(--cp-surface);
    border-right: 1px solid var(--cp-border);
    display: flex; flex-direction: column;
    padding: 22px 16px 18px;
    position: sticky; top: 0; height: 100vh; overflow-y: auto;
  }
  .mdp-brand { display: flex; align-items: center; padding: 4px 10px 22px; text-decoration: none; }
  .mdp-brand img { max-height: 40px; width: auto; display: block; }
  .mdp-nav { display: flex; flex-direction: column; gap: 4px; }
  .mdp-nav-label {
    font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: #94a3b8; padding: 18px 14px 8px;
  }
  .mdp-nav-link {
    display: flex; align-items: center; gap: 14px;
    padding: 11px 14px; border-radius: 12px;
    color: #475569; font-size: .95rem; font-weight: 500;
    text-decoration: none; transition: background .15s ease, color .15s ease;
  }
  .mdp-nav-link i { font-size: 1.15rem; width: 22px; text-align: center; color: #94a3b8; transition: color .15s ease; }
  .mdp-nav-link:hover { background: #f5f7fb; color: var(--cp-text); }
  .mdp-nav-link:hover i { color: var(--cp-accent); }
  .mdp-nav-link.active { background: var(--cp-accent-soft); color: var(--cp-accent); font-weight: 600; }
  .mdp-nav-link.active i { color: var(--cp-accent); }
  /* Grupo desplegable «Dominios» */
  .mdp-group-toggle { width: 100%; border: 0; background: transparent; cursor: pointer; text-align: left; }
  .mdp-group-toggle .mdp-chevron { margin-left: auto; font-size: .8rem; width: auto; transition: transform .2s ease; }
  .mdp-group.is-open .mdp-group-toggle .mdp-chevron { transform: rotate(180deg); }
  .mdp-count { font-size: .72rem; font-weight: 700; color: var(--cp-accent); background: var(--cp-accent-soft); border-radius: 999px; padding: 1px 8px; }
  .mdp-group-body { display: none; flex-direction: column; gap: 2px; margin: 2px 0 6px 25px; padding-left: 14px; border-left: 2px solid #eef1f6; }
  .mdp-group.is-open .mdp-group-body { display: flex; }
  .mdp-sub-link { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 10px; color: #475569; font-size: .88rem; font-weight: 500; text-decoration: none; min-width: 0; }
  .mdp-sub-link i { font-size: 1rem; width: 18px; text-align: center; color: #94a3b8; }
  .mdp-sub-link:hover { background: #f5f7fb; color: var(--cp-text); }
  .mdp-sub-link.active { background: var(--cp-accent-soft); color: var(--cp-accent); font-weight: 600; }
  .mdp-sub-link.active i { color: var(--cp-accent); }
  .mdp-sub-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
  .mdp-sub-more { color: var(--cp-accent); font-weight: 600; }
  .mdp-sub-empty { font-size: .85rem; color: #94a3b8; padding: 8px 10px; }
  .mdp-sub-sep { height: 1px; background: #eef1f6; margin: 6px 10px; }
  .mdp-dot { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 8px; margin: 0 5px; }
  .mdp-dot-ok { background: #16a34a; } .mdp-dot-wait { background: #f59e0b; } .mdp-dot-bad { background: #dc2626; }
  .mdp-sidebar-footer { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--cp-border); display: flex; flex-direction: column; gap: 4px; }
  .mdp-nav-link.is-danger { color: #b42318; }
  .mdp-nav-link.is-danger i { color: #d92d20; }
  .mdp-nav-link.is-danger:hover { background: #fef3f2; }

  /* --- Zona principal --- */
  .mdp-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
  .mdp-topbar {
    height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 16px;
    padding: 0 32px; background: var(--cp-surface); border-bottom: 1px solid var(--cp-border);
    position: sticky; top: 0; z-index: 50;
  }
  .mdp-crumb { display: flex; align-items: center; gap: 12px; min-width: 0; }
  .mdp-crumb-home {
    width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--cp-border);
    display: inline-flex; align-items: center; justify-content: center; color: #475569; text-decoration: none;
  }
  .mdp-crumb-home:hover { color: var(--cp-accent); border-color: #cfd8e6; }
  .mdp-crumb-title { font-size: 1.05rem; font-weight: 600; color: var(--cp-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .mdp-topbar-right { display: flex; align-items: center; gap: 14px; position: relative; }
  .mdp-btn-new {
    display: inline-flex; align-items: center; gap: 8px; height: 44px; padding: 0 18px;
    border-radius: 12px; border: 0; background: var(--cp-accent); color: #fff;
    font-weight: 600; font-size: .95rem; cursor: pointer; transition: background .15s ease;
  }
  .mdp-btn-new:hover { background: var(--cp-accent-hover); }
  .mdp-avatar-btn {
    width: 46px; height: 46px; border-radius: 50%; border: 0; cursor: pointer;
    background: var(--cp-accent); color: #fff; font-weight: 700; font-size: .95rem;
    display: inline-flex; align-items: center; justify-content: center;
  }
  .mdp-dropdown {
    position: absolute; top: calc(100% + 10px); right: 0; min-width: 250px;
    background: #fff; border: 1px solid var(--cp-border); border-radius: 14px;
    box-shadow: 0 12px 32px rgba(16, 24, 40, .14); padding: 8px;
    opacity: 0; visibility: hidden; transform: translateY(6px); transition: all .15s ease; z-index: 220;
  }
  .mdp-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); }
  .mdp-dropdown-head { padding: 10px 12px 12px; border-bottom: 1px solid var(--cp-border); margin-bottom: 6px; }
  .mdp-dropdown-head strong { display: block; font-size: .92rem; color: var(--cp-text); }
  .mdp-dropdown-head span { font-size: .8rem; color: var(--cp-muted); }
  .mdp-dropdown a {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px;
    color: #334155; font-size: .9rem; font-weight: 500; text-decoration: none;
  }
  .mdp-dropdown a i { width: 18px; text-align: center; color: #94a3b8; }
  .mdp-dropdown a:hover { background: #f5f7fb; color: var(--cp-text); }
  .mdp-dropdown a.is-danger { color: #b42318; }
  .mdp-dropdown a.is-danger i { color: #d92d20; }
  .mdp-dropdown .mdp-sep { height: 1px; background: var(--cp-border); margin: 6px 4px; }

  .mdp-content { padding: 32px; flex: 1; }
  .mdp-content > .mdp-page { max-width: 1440px; margin: 0 auto; }

  /* --- Componentes reutilizables --- */
  .mdp-card { background: var(--cp-surface); border-radius: var(--cp-radius); box-shadow: var(--cp-shadow); padding: 28px; }
  .mdp-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
  .mdp-card-title { display: flex; align-items: center; gap: 14px; font-size: 1.15rem; font-weight: 700; color: var(--cp-text); margin: 0; }
  .mdp-icon-box {
    width: 48px; height: 48px; border-radius: 12px; flex: 0 0 48px;
    display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #fff;
  }
  .mdp-btn-outline {
    display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 10px;
    border: 1px solid #d5dce8; background: #fff; color: #334155; font-size: .88rem; font-weight: 600;
    text-decoration: none; cursor: pointer; transition: border-color .15s ease, color .15s ease;
  }
  .mdp-btn-outline:hover { border-color: var(--cp-accent); color: var(--cp-accent); }
  .mdp-alert {
    background: #fff8e6; border: 1px solid #ffe2a8; color: #7a5b00;
    border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-size: .9rem;
  }

  /* Contenido antiguo de otras pantallas (estilos en línea): mismo aire de tarjeta */
  .mdp-content .card { border-radius: 14px; border-color: var(--cp-border); }
  .mdp-content [style*="border:1px solid #edf0f5"] { box-shadow: var(--cp-shadow); border-color: var(--cp-border) !important; border-radius: 14px !important; }

  /* --- Móvil --- */
  @media (max-width: 992px) {
    .mdp-shell { flex-direction: column; }
    .mdp-sidebar { width: 100%; flex: none; height: auto; position: static; border-right: 0; border-bottom: 1px solid var(--cp-border); padding: 14px; }
    .mdp-brand { padding: 0 6px 12px; }
    .mdp-nav { flex-direction: row; flex-wrap: wrap; gap: 6px; }
    .mdp-nav-label, .mdp-sidebar-footer .mdp-nav-link:not(.is-danger) { display: none; }
    .mdp-group { width: 100%; }
    .mdp-group-body { margin-left: 12px; }
    .mdp-nav-link { padding: 8px 12px; font-size: .88rem; }
    .mdp-sidebar-footer { flex-direction: row; border-top: 0; padding-top: 8px; margin-top: 0; }
    .mdp-topbar { padding: 0 16px; height: 64px; }
    .mdp-btn-new span { display: none; }
    .mdp-content { padding: 18px 14px; }
    .mdp-card { padding: 20px; }
  }
</style>
@endpush

@section('content')
@php
  $currentPage = $current_page ?? '';
  $customer = $customer ?? null;
  $currentUri = $_SERVER['REQUEST_URI'] ?? '';
  $siteName = site_setting('site_name', 'MuseDock');
  $logoPath = site_setting('site_logo', '');
  $panelLogo = (site_setting('show_logo', '1') === '1' && !empty($logoPath)) ? public_file_url($logoPath) : url('/') . '/assets/logo-default.png';
  $gateAllows = fn (string $channel) => class_exists(\Cloud\Services\SignupGate::class) && \Cloud\Services\SignupGate::allows($channel);

  $homeItem = ['page' => 'dashboard', 'url' => '/customer/dashboard', 'icon' => 'bi-house-door', 'label' => 'Inicio'];
  $profileItem = ['page' => 'profile', 'url' => '/customer/profile', 'icon' => 'bi-person-circle', 'label' => 'Perfil'];
  // Hijos de «Dominios»: gestión común a todos los dominios
  $domainChildren = [
    ['page' => 'contacts', 'url' => '/customer/contacts', 'icon' => 'bi-person-lines-fill', 'label' => 'Contactos'],
    ['page' => 'tenant-admins', 'url' => '/customer/tenant-admins', 'icon' => 'bi-people', 'label' => 'Administradores'],
  ];

  // Todos los dominios del cliente: sitios (tenants) y dominios registrados
  $panelDomains = [];
  if (!empty($customer['id']) && class_exists(\Cloud\Models\Customer::class)) {
    try {
      $sitesSeen = [];
      foreach (\Cloud\Models\Customer::getTenants((int) $customer['id']) as $t) {
        $sitesSeen[strtolower((string) $t['domain'])] = true;
        $panelDomains[] = [
          'label' => $t['domain'],
          'url' => '/customer/dashboard#site-' . (int) $t['id'],
          'match' => '/customer/tenant/' . (int) $t['id'] . '/',
          'state' => ($t['status'] ?? '') === 'active' ? 'ok' : (in_array($t['status'] ?? '', ['error', 'suspended'], true) ? 'bad' : 'wait'),
        ];
      }
      foreach (\Cloud\Models\Customer::getDomainOrders((int) $customer['id']) as $o) {
        $full = strtolower((string) ($o['full_domain'] ?? ''));
        if ($full === '' || isset($sitesSeen[$full])) continue;
        $panelDomains[] = [
          'label' => $full,
          'url' => '/customer/domain/' . (int) $o['id'] . '/manage',
          'match' => '/customer/domain/' . (int) $o['id'] . '/',
          'state' => in_array($o['status'] ?? '', ['registered', 'active'], true) ? 'ok' : (($o['status'] ?? '') === 'failed' ? 'bad' : 'wait'),
        ];
      }
    } catch (\Throwable $e) {
      $panelDomains = [];
    }
  }
  $domainsVisible = array_slice($panelDomains, 0, 8);
  // Altas: solo las abiertas (Cloud\Services\SignupGate)
  $newItems = array_values(array_filter([
    ['page' => 'free-subdomain', 'url' => '/customer/request-free-subdomain', 'icon' => 'bi-globe2', 'label' => 'Crear sitio', 'channel' => 'free_subdomain'],
    ['page' => 'custom-domain', 'url' => '/customer/request-custom-domain', 'icon' => 'bi-link-45deg', 'label' => 'Conectar dominio', 'channel' => 'connect_domain'],
    ['page' => 'register-domain', 'url' => '/customer/register-domain', 'icon' => 'bi-cart-plus', 'label' => 'Registrar dominio', 'channel' => 'register_domain'],
  ], fn ($i) => $gateAllows($i['channel'])));
  $isActive = fn (array $i) => $currentPage === $i['page'] || str_contains($currentUri, $i['url']);
  $initial = strtoupper(mb_substr((string) ($customer['name'] ?? 'U'), 0, 1));
  // Título de la cabecera sin el nombre del sitio (ya está en el logo) y sin «Dashboard»
  $crumbTitle = trim((string) preg_replace('/\s*[-|–]\s*' . preg_quote($siteName, '/') . '\s*$/u', '', (string) ($page_title ?? '')));
  $crumbTitle = ($crumbTitle === '' || strcasecmp($crumbTitle, 'Dashboard') === 0) ? __('Inicio') : $crumbTitle;
@endphp

<div class="padding-none ziph-page_content mdp-app {{ $isCustomerPanelFull ? 'customer-panel-full' : '' }}">
  @if($customer)
  <div class="mdp-shell {{ $isCustomerPanelFull ? '' : 'is-compact container' }}">

    {{-- Barra lateral --}}
    <aside class="mdp-sidebar" aria-label="Menú del panel">
      @if($isCustomerPanelFull)
      <a href="{{ url('/') }}" class="mdp-brand" aria-label="{{ $siteName }}">
        <img src="{{ $panelLogo }}" alt="{{ $siteName }}" onerror="this.onerror=null; this.src='{{ url('/') }}/assets/logo-default.png';">
      </a>
      @endif
      <nav class="mdp-nav">
        <a href="{{ $homeItem['url'] }}" class="mdp-nav-link {{ $isActive($homeItem) ? 'active' : '' }}" @if($isActive($homeItem)) aria-current="page" @endif>
          <i class="bi {{ $homeItem['icon'] }}" aria-hidden="true"></i><span>{{ __($homeItem['label']) }}</span>
        </a>

        {{-- Dominios: todos los del cliente + su gestión común --}}
        <div class="mdp-group is-open" id="mdpDomainsGroup">
          <button type="button" class="mdp-nav-link mdp-group-toggle" aria-expanded="true" aria-controls="mdpDomainsList" onclick="mdpToggleGroup(this)">
            <i class="bi bi-globe2" aria-hidden="true"></i><span>{{ __('Dominios') }}</span>
            @if(count($panelDomains))<span class="mdp-count">{{ count($panelDomains) }}</span>@endif
            <i class="bi bi-chevron-down mdp-chevron" aria-hidden="true"></i>
          </button>
          <div class="mdp-group-body" id="mdpDomainsList">
            @forelse($domainsVisible as $d)
              @php $dActive = str_contains($currentUri, $d['match']); @endphp
              <a href="{{ $d['url'] }}" class="mdp-sub-link {{ $dActive ? 'active' : '' }}" title="{{ $d['label'] }}" @if($dActive) aria-current="page" @endif>
                <span class="mdp-dot mdp-dot-{{ $d['state'] }}" aria-hidden="true"></span><span class="mdp-sub-text">{{ $d['label'] }}</span>
              </a>
            @empty
              <span class="mdp-sub-empty">{{ __('Aún no tienes dominios') }}</span>
            @endforelse
            @if(count($panelDomains) > count($domainsVisible))
              <a href="/customer/dashboard#mdp-sites" class="mdp-sub-link mdp-sub-more"><span class="mdp-sub-text">{{ __('Ver todos') }} ({{ count($panelDomains) }})</span></a>
            @endif
            <div class="mdp-sub-sep" role="presentation"></div>
            @foreach($domainChildren as $item)
              <a href="{{ $item['url'] }}" class="mdp-sub-link {{ $isActive($item) ? 'active' : '' }}" @if($isActive($item)) aria-current="page" @endif>
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i><span class="mdp-sub-text">{{ __($item['label']) }}</span>
              </a>
            @endforeach
          </div>
        </div>

        <a href="{{ $profileItem['url'] }}" class="mdp-nav-link {{ $isActive($profileItem) ? 'active' : '' }}" @if($isActive($profileItem)) aria-current="page" @endif>
          <i class="bi {{ $profileItem['icon'] }}" aria-hidden="true"></i><span>{{ __($profileItem['label']) }}</span>
        </a>
        @if(!empty($newItems))
          <div class="mdp-nav-label">{{ __('Acceso rápido') }}</div>
          @foreach($newItems as $item)
            <a href="{{ $item['url'] }}" class="mdp-nav-link {{ $isActive($item) ? 'active' : '' }}" @if($isActive($item)) aria-current="page" @endif>
              <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ __($item['label']) }}</span>
            </a>
          @endforeach
        @endif
      </nav>
      <div class="mdp-sidebar-footer">
        <a href="/soporte" class="mdp-nav-link"><i class="bi bi-life-preserver" aria-hidden="true"></i><span>{{ __('Soporte') }}</span></a>
        <a href="{{ url('/') }}" class="mdp-nav-link"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>{{ __('Ir a') }} {{ parse_url(url('/'), PHP_URL_HOST) }}</span></a>
        <a href="#" onclick="customerLogout(); return false;" class="mdp-nav-link is-danger"><i class="bi bi-box-arrow-left" aria-hidden="true"></i><span>{{ __('Cerrar sesión') }}</span></a>
      </div>
    </aside>

    {{-- Zona principal --}}
    <div class="mdp-main">
      <header class="mdp-topbar">
        <div class="mdp-crumb">
          <a href="/customer/dashboard" class="mdp-crumb-home" aria-label="{{ __('Inicio') }}"><i class="bi bi-house" aria-hidden="true"></i></a>
          <span class="mdp-crumb-title">{{ $crumbTitle }}</span>
        </div>
        <div class="mdp-topbar-right">
          @if(!empty($newItems))
          <div style="position:relative;">
            <button type="button" class="mdp-btn-new" onclick="cpToggle(event, 'cpNewMenu')" aria-haspopup="true" aria-controls="cpNewMenu">
              <span>{{ __('Nuevo') }}</span><i class="bi bi-plus-lg" aria-hidden="true"></i>
            </button>
            <div class="mdp-dropdown" id="cpNewMenu">
              @foreach($newItems as $item)
                <a href="{{ $item['url'] }}"><i class="bi {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ __($item['label']) }}</span></a>
              @endforeach
            </div>
          </div>
          @endif
          <div style="position:relative;">
            <button type="button" class="mdp-avatar-btn" onclick="cpToggle(event, 'customerTopUserMenu')" aria-haspopup="true" aria-controls="customerTopUserMenu" aria-label="{{ __('Mi cuenta') }}">{{ $initial }}</button>
            <div class="mdp-dropdown" id="customerTopUserMenu">
              <div class="mdp-dropdown-head">
                <strong>{{ $customer['name'] ?? '' }}</strong>
                <span>{{ $customer['email'] ?? '' }}</span>
              </div>
              <a href="/customer/dashboard"><i class="bi bi-house-door" aria-hidden="true"></i><span>{{ __('Inicio') }}</span></a>
              <a href="/customer/profile"><i class="bi bi-person-circle" aria-hidden="true"></i><span>{{ __('Mi perfil') }}</span></a>
              <a href="/soporte"><i class="bi bi-life-preserver" aria-hidden="true"></i><span>{{ __('Soporte') }}</span></a>
              <div class="mdp-sep"></div>
              <a href="#" onclick="customerLogout(); return false;" class="is-danger"><i class="bi bi-box-arrow-left" aria-hidden="true"></i><span>{{ __('Cerrar sesión') }}</span></a>
            </div>
          </div>
        </div>
      </header>

      <main class="mdp-content">
        <div class="mdp-page">
          @php $gateWarning = consume_flash('warning'); @endphp
          @if($gateWarning)
            <div class="mdp-alert"><i class="bi bi-info-circle" style="margin-right:6px;" aria-hidden="true"></i>{{ $gateWarning }}</div>
          @endif
          @yield('panel_content')
        </div>
      </main>
    </div>
  </div>
  @else
    @yield('panel_content')
  @endif
</div>

@if($customer)
<script>
function cpToggle(e, id) {
  e.stopPropagation();
  document.querySelectorAll('.mdp-dropdown.show').forEach(function (m) { if (m.id !== id) m.classList.remove('show'); });
  var m = document.getElementById(id);
  if (m) m.classList.toggle('show');
}
function mdpToggleGroup(btn) {
  var g = btn.closest('.mdp-group');
  var open = !g.classList.contains('is-open');
  g.classList.toggle('is-open', open);
  btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  try { localStorage.setItem('mdpDomainsOpen', open ? '1' : '0'); } catch (e) {}
}
(function () {
  try {
    var g = document.getElementById('mdpDomainsGroup');
    if (g && localStorage.getItem('mdpDomainsOpen') === '0' && !g.querySelector('.mdp-sub-link.active')) {
      g.classList.remove('is-open');
      g.querySelector('.mdp-group-toggle').setAttribute('aria-expanded', 'false');
    }
  } catch (e) {}
})();
// Compatibilidad con llamadas antiguas
function toggleTopUserMenu(e) { cpToggle(e, 'customerTopUserMenu'); }
document.addEventListener('click', function () {
  document.querySelectorAll('.mdp-dropdown.show').forEach(function (m) { m.classList.remove('show'); });
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') document.querySelectorAll('.mdp-dropdown.show').forEach(function (m) { m.classList.remove('show'); });
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
    confirmButtonColor: '{{ $cpAccent }}',
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
