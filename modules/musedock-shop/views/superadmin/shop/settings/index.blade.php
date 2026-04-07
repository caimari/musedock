@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-gear me-2"></i>{{ $title }}</h2>
    </div>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif

    <form method="POST" action="{{ route('shop.settings.update') }}">
      @csrf
      <div class="row">
        <div class="col-lg-8">
          {{-- Stripe --}}
          <div class="card mb-3">
            <div class="card-header">
              <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i>{{ __('shop.settings_page.stripe_section') }}</h5>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label for="stripe_secret_key" class="form-label">{{ __('shop.settings_page.stripe_secret_key') }}</label>
                <input type="password" class="form-control" id="stripe_secret_key" name="stripe_secret_key"
                       value="{{ e($shopSettings['stripe_secret_key']) }}" placeholder="sk_live_...">
                <small class="text-muted">Se encuentra en tu Dashboard de Stripe > Developers > API keys</small>
              </div>
              <div class="mb-3">
                <label for="stripe_publishable_key" class="form-label">{{ __('shop.settings_page.stripe_publishable_key') }}</label>
                <input type="text" class="form-control" id="stripe_publishable_key" name="stripe_publishable_key"
                       value="{{ e($shopSettings['stripe_publishable_key']) }}" placeholder="pk_live_...">
              </div>
              <div class="mb-3">
                <label for="stripe_webhook_secret" class="form-label">{{ __('shop.settings_page.stripe_webhook_secret') }}</label>
                <input type="password" class="form-control" id="stripe_webhook_secret" name="stripe_webhook_secret"
                       value="{{ e($shopSettings['stripe_webhook_secret']) }}" placeholder="whsec_...">
                <small class="text-muted">
                  Webhook endpoint: <code>https://{{ $_SERVER['HTTP_HOST'] ?? 'tu-dominio.com' }}/shop/webhook/stripe</code>
                </small>
              </div>
            </div>
          </div>

          {{-- General --}}
          <div class="card mb-3">
            <div class="card-header">
              <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>{{ __('shop.settings_page.general_section') }}</h5>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="default_currency" class="form-label">{{ __('shop.settings_page.default_currency') }}</label>
                  <select class="form-select" id="default_currency" name="default_currency">
                    <option value="eur" {{ $shopSettings['default_currency'] === 'eur' ? 'selected' : '' }}>EUR (€)</option>
                    <option value="usd" {{ $shopSettings['default_currency'] === 'usd' ? 'selected' : '' }}>USD ($)</option>
                  </select>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="default_tax_rate" class="form-label">{{ __('shop.settings_page.default_tax_rate') }}</label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="default_tax_rate" name="default_tax_rate"
                           value="{{ e($shopSettings['default_tax_rate']) }}" min="0" max="100" step="0.01">
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="order_email" class="form-label">{{ __('shop.settings_page.order_email') }}</label>
                  <input type="email" class="form-control" id="order_email" name="order_email"
                         value="{{ e($shopSettings['order_email']) }}" placeholder="ventas@tudominio.com">
                </div>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Guardar configuración
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
