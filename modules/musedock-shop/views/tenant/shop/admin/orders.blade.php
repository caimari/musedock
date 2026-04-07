@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h2 class="mb-3"><i class="bi bi-receipt me-2"></i>{{ $title }}</h2>

    <div class="card">
      <div class="card-body p-0">
        @if(empty($orders))
          <div class="text-center py-5 text-muted">{{ __('shop.order.no_orders') }}</div>
        @else
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Nº Pedido</th>
              <th>Cliente</th>
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
              <td>{{ e($order->billing_name) }}</td>
              <td><span class="badge {{ $order->getStatusBadgeClass() }}">{{ ucfirst($order->status) }}</span></td>
              <td class="text-end">{{ $order->getFormattedTotal() }}</td>
              <td><small>{{ date('d/m/Y H:i', strtotime($order->created_at)) }}</small></td>
              <td><a href="{{ admin_url('shop/orders/' . $order->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
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
