@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-person-circle me-2"></i>Mi cuenta</h1>

    <div class="row">
      {{-- Recent orders --}}
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Pedidos recientes</h5>
            <a href="/shop/account/orders" class="btn btn-sm btn-outline-primary">Ver todos</a>
          </div>
          <div class="card-body p-0">
            @if(empty($recentOrders))
              <div class="text-center py-4 text-muted">
                {{ __('shop.customer.no_orders') }}
              </div>
            @else
            <table class="table mb-0">
              <thead class="table-light">
                <tr>
                  <th>Pedido</th>
                  <th>Estado</th>
                  <th class="text-end">Total</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentOrders as $order)
                <tr>
                  <td><a href="/shop/account/orders/{{ $order->id }}">{{ e($order->order_number) }}</a></td>
                  <td><span class="badge {{ $order->getStatusBadgeClass() }}">{{ ucfirst($order->status) }}</span></td>
                  <td class="text-end">{{ $order->getFormattedTotal() }}</td>
                  <td><small>{{ date('d/m/Y', strtotime($order->created_at)) }}</small></td>
                </tr>
                @endforeach
              </tbody>
            </table>
            @endif
          </div>
        </div>
      </div>

      {{-- Sidebar --}}
      <div class="col-lg-4">
        {{-- Active subscriptions --}}
        <div class="card mb-3">
          <div class="card-header"><h5 class="mb-0">Suscripciones activas</h5></div>
          <div class="card-body">
            @if(empty($activeSubscriptions))
              <p class="text-muted mb-0">{{ __('shop.customer.no_subscriptions') }}</p>
            @else
              @foreach($activeSubscriptions as $sub)
                @php $product = $sub->product(); @endphp
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span>{{ $product ? e($product->name) : 'Plan' }}</span>
                  <span class="badge {{ $sub->getStatusBadgeClass() }}">{{ ucfirst($sub->status) }}</span>
                </div>
              @endforeach
              <a href="/shop/account/subscriptions" class="btn btn-sm btn-outline-primary w-100 mt-2">Gestionar</a>
            @endif
          </div>
        </div>

        {{-- Navigation --}}
        <div class="card">
          <div class="list-group list-group-flush">
            <a href="/shop/account" class="list-group-item list-group-item-action active">
              <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="/shop/account/orders" class="list-group-item list-group-item-action">
              <i class="bi bi-receipt me-2"></i> {{ __('shop.customer.my_orders') }}
            </a>
            <a href="/shop/account/subscriptions" class="list-group-item list-group-item-action">
              <i class="bi bi-arrow-repeat me-2"></i> {{ __('shop.customer.my_subscriptions') }}
            </a>
            <a href="/shop" class="list-group-item list-group-item-action">
              <i class="bi bi-shop me-2"></i> Tienda
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
