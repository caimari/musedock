@extends('Customer.layout')

@section('panel_content')
@php
  $cpFirstName = trim(explode(' ', (string) ($customer['name'] ?? ''))[0] ?? '');
  $cpGate = fn (string $c) => class_exists(\Cloud\Services\SignupGate::class) && \Cloud\Services\SignupGate::allows($c);
@endphp
<style>
  .mdp-dash-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; margin-bottom: 24px; }
  .mdp-hello h2 { font-size: 2rem; font-weight: 800; color: #111827; margin: 0 0 6px; letter-spacing: -.01em; }
  .mdp-hello p { color: #64748b; font-size: 1rem; margin: 0; }
  .mdp-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-top: 28px; }
  .mdp-tile { border-radius: 16px; padding: 22px 22px 20px; color: #fff; position: relative; overflow: hidden; min-height: 132px; }
  .mdp-tile .mdp-tile-label { font-size: 1rem; font-weight: 600; opacity: .95; }
  .mdp-tile .mdp-tile-num { font-size: 2.3rem; font-weight: 800; line-height: 1.1; margin-top: 6px; }
  .mdp-tile .mdp-tile-sub { font-size: .85rem; opacity: .85; margin-top: 2px; }
  .mdp-tile i { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); font-size: 3rem; opacity: .9; }
  .mdp-tile-blue   { background: linear-gradient(135deg, #2f6bf2, #1d4fd0); }
  .mdp-tile-teal   { background: linear-gradient(135deg, #0f8a83, #0b6e69); }
  .mdp-tile-indigo { background: linear-gradient(135deg, #5b4fe0, #4338ca); }
  .mdp-quick a { display: flex; align-items: center; gap: 14px; padding: 14px 4px; border-bottom: 1px solid #eef1f6; color: #1f2937; text-decoration: none; font-weight: 500; }
  .mdp-quick a:last-child { border-bottom: 0; }
  .mdp-quick a .mdp-q-icon { width: 38px; height: 38px; border-radius: 10px; background: var(--cp-accent-soft); color: var(--cp-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; flex: 0 0 38px; }
  .mdp-quick a .bi-chevron-right { margin-left: auto; color: #94a3b8; }
  .mdp-quick a:hover { color: var(--cp-accent); }
  .mdp-list-row { padding: 18px 0; border-top: 1px solid #eef1f6; }
  .mdp-list-row:first-of-type { border-top: 0; padding-top: 4px; }
  .mdp-list-row:target { background: var(--cp-accent-soft); border-radius: 12px; padding-left: 14px; padding-right: 14px; scroll-margin-top: 96px; }
  .mdp-empty { background: #f5f8ff; border-radius: 12px; padding: 16px 18px; font-size: .92rem; color: #1e3a8a; }
  @media (max-width: 1200px) { .mdp-dash-grid { grid-template-columns: 1fr; } }
  @media (max-width: 700px) { .mdp-tiles { grid-template-columns: 1fr; } .mdp-hello h2 { font-size: 1.5rem; } }
</style>

<div class="mdp-dash-grid">
  {{-- Bienvenida + cifras --}}
  <section class="mdp-card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
      <div class="mdp-hello">
        <h2>¡Hola{{ $cpFirstName !== '' ? ' ' . $cpFirstName : '' }}! <span aria-hidden="true">👋</span></h2>
        <p>Bienvenido de nuevo a tu panel de {{ site_setting('site_name', 'MuseDock') }}</p>
      </div>
      <a href="/customer/profile" class="mdp-btn-outline">Gestionar cuenta</a>
    </div>
    <div class="mdp-tiles">
      <div class="mdp-tile mdp-tile-blue">
        <div class="mdp-tile-label">Sitios</div>
        <div class="mdp-tile-num"><?= (int) ($stats['total_tenants'] ?? 0) ?></div>
        <div class="mdp-tile-sub">en total</div>
        <i class="bi bi-globe2" aria-hidden="true"></i>
      </div>
      <div class="mdp-tile mdp-tile-teal">
        <div class="mdp-tile-label">Activos</div>
        <div class="mdp-tile-num"><?= (int) ($stats['active_tenants'] ?? 0) ?></div>
        <div class="mdp-tile-sub">funcionando</div>
        <i class="bi bi-check2-circle" aria-hidden="true"></i>
      </div>
      <div class="mdp-tile mdp-tile-indigo">
        <div class="mdp-tile-label">Cloudflare</div>
        <div class="mdp-tile-num"><?= (int) ($stats['cloudflare_protected'] ?? 0) ?></div>
        <div class="mdp-tile-sub">protegidos</div>
        <i class="bi bi-shield-check" aria-hidden="true"></i>
      </div>
    </div>
  </section>

  {{-- Accesos rápidos --}}
  <aside class="mdp-card">
    <div class="mdp-card-head">
      <h3 class="mdp-card-title"><span class="mdp-icon-box" style="background:linear-gradient(135deg,#2f6bf2,#1d4fd0);"><i class="bi bi-lightning-charge" aria-hidden="true"></i></span> Acceso rápido</h3>
    </div>
    <nav class="mdp-quick" aria-label="Acceso rápido">
      @if($cpGate('free_subdomain'))<a href="/customer/request-free-subdomain"><span class="mdp-q-icon"><i class="bi bi-globe2" aria-hidden="true"></i></span>Crear sitio<i class="bi bi-chevron-right" aria-hidden="true"></i></a>@endif
      @if($cpGate('connect_domain'))<a href="/customer/request-custom-domain"><span class="mdp-q-icon"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>Conectar dominio<i class="bi bi-chevron-right" aria-hidden="true"></i></a>@endif
      @if($cpGate('register_domain'))<a href="/customer/register-domain"><span class="mdp-q-icon"><i class="bi bi-cart-plus" aria-hidden="true"></i></span>Registrar dominio<i class="bi bi-chevron-right" aria-hidden="true"></i></a>@endif
      <a href="/customer/contacts"><span class="mdp-q-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>Contactos<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
      <a href="/customer/tenant-admins"><span class="mdp-q-icon"><i class="bi bi-people" aria-hidden="true"></i></span>Administradores<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
      <a href="/docs/"><span class="mdp-q-icon"><i class="bi bi-book" aria-hidden="true"></i></span>Documentación<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
      <a href="/soporte"><span class="mdp-q-icon"><i class="bi bi-life-preserver" aria-hidden="true"></i></span>Soporte<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
    </nav>
  </aside>
</div>

@include('Customer.partials.sites-list')

@include('Customer.partials.domains-list')
@endsection
