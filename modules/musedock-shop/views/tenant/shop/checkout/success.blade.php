@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="text-center py-5">
      <div class="mb-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:5rem"></i>
      </div>
      <h1 class="mb-3">¡Pago completado!</h1>
      <p class="fs-5 text-muted mb-4">Tu pedido ha sido procesado correctamente.</p>

      @if($order)
        <div class="card mx-auto" style="max-width:500px">
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-5">Nº Pedido:</dt>
              <dd class="col-sm-7"><strong>{{ e($order->order_number) }}</strong></dd>
              <dt class="col-sm-5">Total:</dt>
              <dd class="col-sm-7"><strong>{{ $order->getFormattedTotal() }}</strong></dd>
              <dt class="col-sm-5">Estado:</dt>
              <dd class="col-sm-7"><span class="badge {{ $order->getStatusBadgeClass() }}">{{ ucfirst($order->status) }}</span></dd>
            </dl>
          </div>
        </div>
      @endif

      <div class="mt-4">
        <a href="/shop" class="btn btn-outline-primary me-2">
          <i class="bi bi-arrow-left me-1"></i> Seguir comprando
        </a>
        <a href="/shop/account/orders" class="btn btn-primary">
          <i class="bi bi-list-check me-1"></i> Mis pedidos
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
