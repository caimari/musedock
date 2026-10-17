@extends('Customer.layout')

@section('panel_content')
@php $mdpGate = fn (string $c) => class_exists(\Cloud\Services\SignupGate::class) && \Cloud\Services\SignupGate::allows($c); @endphp
<div style="display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:22px;">
  <div>
    <h2 style="font-size:1.6rem; font-weight:800; color:#111827; margin:0 0 4px;">Mis dominios</h2>
    <p style="color:#64748b; margin:0;">Los nombres de dominio registrados o transferidos con nosotros: DNS, contactos y renovación.</p>
  </div>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    @if($mdpGate('register_domain'))<a href="/customer/register-domain" class="mdp-btn-outline"><i class="bi bi-cart-plus" aria-hidden="true"></i> Registrar dominio</a>@endif
    <a href="/customer/contacts" class="mdp-btn-outline"><i class="bi bi-person-lines-fill" aria-hidden="true"></i> Contactos</a>
  </div>
</div>

@include('Customer.partials.domains-list')

@if(!empty($domainTransfers))
<section class="mdp-card" style="margin-bottom:24px;">
  <div class="mdp-card-head">
    <h3 class="mdp-card-title"><span class="mdp-icon-box" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span> Transferencias en curso</h3>
  </div>
  @foreach($domainTransfers as $tr)
    @php $trDomain = $tr['full_domain'] ?? trim(($tr['domain'] ?? '') . (isset($tr['extension']) ? '.' . $tr['extension'] : ''), '.'); @endphp
    <div class="mdp-list-row" style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <div>
        <span style="font-weight:600; color:#1f2937;">{{ $trDomain }}</span>
        <span style="font-size:.72rem; padding:3px 8px; border-radius:6px; font-weight:600; background:#fef3c7; color:#92400e; margin-left:6px;">{{ ucfirst((string) ($tr['status'] ?? 'pendiente')) }}</span>
      </div>
      @if(!empty($tr['id']))
        <a href="/customer/transfer-domain/{{ (int) $tr['id'] }}/status" class="mdp-btn-outline"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Ver estado</a>
      @endif
    </div>
  @endforeach
</section>
@endif
@endsection
