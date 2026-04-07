@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb mb-0">
        <a href="{{ route('shop.subscriptions.index') }}">{{ __('shop.subscriptions') }}</a>
        <span class="mx-2">/</span>
        <span>Suscripción #{{ $subscription->id }}</span>
      </div>
      <a href="{{ route('shop.subscriptions.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
    </div>

    <div class="row">
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header"><h5 class="mb-0">Detalles</h5></div>
          <div class="card-body">
            <dl>
              <dt>Estado</dt>
              <dd><span class="badge {{ $subscription->getStatusBadgeClass() }}">{{ ucfirst($subscription->status) }}</span></dd>

              <dt>Producto</dt>
              <dd>{{ $product ? e($product->name) : '—' }}</dd>

              <dt>Período</dt>
              <dd>{{ ucfirst($subscription->billing_period) }}</dd>

              <dt>Período actual</dt>
              <dd>
                @if($subscription->current_period_start && $subscription->current_period_end)
                  {{ date('d/m/Y', strtotime(is_object($subscription->current_period_start) ? $subscription->current_period_start->format('Y-m-d') : $subscription->current_period_start)) }}
                  —
                  {{ date('d/m/Y', strtotime(is_object($subscription->current_period_end) ? $subscription->current_period_end->format('Y-m-d') : $subscription->current_period_end)) }}
                @else
                  —
                @endif
              </dd>

              @if($subscription->stripe_subscription_id)
              <dt>Stripe Subscription</dt>
              <dd><code>{{ $subscription->stripe_subscription_id }}</code></dd>
              @endif

              <dt>Creada</dt>
              <dd>{{ date('d/m/Y H:i', strtotime($subscription->created_at)) }}</dd>
            </dl>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header"><h5 class="mb-0">Cliente</h5></div>
          <div class="card-body">
            @if($customer)
              <p><strong>{{ e($customer->name) }}</strong></p>
              <p>{{ e($customer->email) }}</p>
            @else
              <p class="text-muted">Sin cliente asociado</p>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
