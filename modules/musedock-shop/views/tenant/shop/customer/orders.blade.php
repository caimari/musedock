@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="mb-0">{{ $title }}</h1>
      <a href="/shop/account" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Mi cuenta</a>
    </div>

    <div class="card">
      <div class="card-body p-0">
        @if(empty($orders))
          <div class="text-center py-5 text-muted">{{ __('shop.customer.no_orders') }}</div>
        @else
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Nº Pedido</th>
              <th>Estado</th>
              <th class="text-end">Total</th>
              <th>Fecha</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($orders as $order)
            <tr>
              <td><strong>{{ e($order->order_number) }}</strong></td>
              <td><span class="badge {{ $order->getStatusBadgeClass() }}">{{ ucfirst($order->status) }}</span></td>
              <td class="text-end">{{ $order->getFormattedTotal() }}</td>
              <td>{{ date('d/m/Y H:i', strtotime($order->created_at)) }}</td>
              <td><a href="/shop/account/orders/{{ $order->id }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
