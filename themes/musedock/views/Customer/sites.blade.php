@extends('Customer.layout')

@section('panel_content')
@php $mdpGate = fn (string $c) => class_exists(\Cloud\Services\SignupGate::class) && \Cloud\Services\SignupGate::allows($c); @endphp
<div style="display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:22px;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#111827; margin:0 0 4px;">Mis sitios</h2>
    <p style="color:#64748b; margin:0;">Tus webs en {{ site_setting('site_name', 'MuseDock') }}, con un subdominio nuestro o con tu propio dominio conectado.</p>
  </div>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    @if($mdpGate('free_subdomain'))<a href="/customer/request-free-subdomain" class="mdp-btn-outline"><i class="bi bi-globe2" aria-hidden="true"></i> Crear sitio</a>@endif
    @if($mdpGate('connect_domain'))<a href="/customer/request-custom-domain" class="mdp-btn-outline"><i class="bi bi-link-45deg" aria-hidden="true"></i> Conectar dominio</a>@endif
    <a href="/customer/tenant-admins" class="mdp-btn-outline"><i class="bi bi-people" aria-hidden="true"></i> Administradores</a>
  </div>
</div>

@include('Customer.partials.sites-list')
@endsection
