@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb mb-0">
        <a href="{{ admin_url('shop/orders') }}">{{ __('shop.orders') }}</a>
        <span class="mx-2">/</span>
        <span>{{ e($order->order_number) }}</span>
      </div>
      <a href="{{ admin_url('shop/orders') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>

    <div class="row">
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-body p-0">
            <table class="table mb-0">
              <thead class="table-light"><tr><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Total</th></tr></thead>
              <tbody>
                @foreach($items as $item)
                <tr>
                  <td>{{ e($item->product_name) }}</td>
                  <td class="text-center">{{ $item->quantity }}</td>
                  <td class="text-end">{{ $item->getFormattedUnitPrice() }}</td>
                  <td class="text-end">{{ $item->getFormattedTotal() }}</td>
                </tr>
                @endforeach
              </tbody>
              <tfoot class="table-light">
                <tr><td colspan="3" class="text-end">Subtotal:</td><td class="text-end">{{ $order->getFormattedSubtotal() }}</td></tr>
                @if($order->discount_amount > 0)<tr class="text-success"><td colspan="3" class="text-end">Descuento:</td><td class="text-end">-{{ $order->getFormattedDiscount() }}</td></tr>@endif
                <tr><td colspan="3" class="text-end">IVA:</td><td class="text-end">{{ $order->getFormattedTax() }}</td></tr>
                <tr class="fw-bold"><td colspan="3" class="text-end">Total:</td><td class="text-end">{{ $order->getFormattedTotal() }}</td></tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <dl>
              <dt>Estado</dt><dd><span class="badge {{ $order->getStatusBadgeClass() }}">{{ ucfirst($order->status) }}</span></dd>
              <dt>Fecha</dt><dd>{{ date('d/m/Y H:i', strtotime($order->created_at)) }}</dd>
              @if($customer)<dt>Cliente</dt><dd>{{ e($customer->name) }}<br><small>{{ e($customer->email) }}</small></dd>@endif
            </dl>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
