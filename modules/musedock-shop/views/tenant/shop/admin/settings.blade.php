@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h2 class="mb-3"><i class="bi bi-gear me-2"></i>{{ $title }}</h2>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif

    <form method="POST" action="{{ admin_url('shop/settings') }}">
      @csrf
      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-credit-card me-2"></i>Stripe</h5></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Stripe Secret Key</label>
                <input type="password" class="form-control" name="stripe_secret_key" value="{{ e($shopSettings['stripe_secret_key']) }}" placeholder="sk_live_...">
              </div>
              <div class="mb-3">
                <label class="form-label">Stripe Publishable Key</label>
                <input type="text" class="form-control" name="stripe_publishable_key" value="{{ e($shopSettings['stripe_publishable_key']) }}" placeholder="pk_live_...">
              </div>
              <div class="mb-3">
                <label class="form-label">Stripe Webhook Secret</label>
                <input type="password" class="form-control" name="stripe_webhook_secret" value="{{ e($shopSettings['stripe_webhook_secret']) }}" placeholder="whsec_...">
              </div>
            </div>
          </div>
          <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-sliders me-2"></i>General</h5></div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label class="form-label">Moneda</label>
                  <select class="form-select" name="default_currency">
                    <option value="eur" {{ $shopSettings['default_currency'] === 'eur' ? 'selected' : '' }}>EUR</option>
                    <option value="usd" {{ $shopSettings['default_currency'] === 'usd' ? 'selected' : '' }}>USD</option>
                  </select>
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">IVA (%)</label>
                  <input type="number" class="form-control" name="default_tax_rate" value="{{ e($shopSettings['default_tax_rate']) }}" step="0.01">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Email notificaciones</label>
                  <input type="email" class="form-control" name="order_email" value="{{ e($shopSettings['order_email']) }}">
                </div>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
