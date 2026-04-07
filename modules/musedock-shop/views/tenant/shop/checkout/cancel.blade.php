@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="text-center py-5">
      <div class="mb-4">
        <i class="bi bi-x-circle text-warning" style="font-size:5rem"></i>
      </div>
      <h1 class="mb-3">Pago cancelado</h1>
      <p class="fs-5 text-muted mb-4">Tu pago ha sido cancelado. No se ha realizado ningún cargo.</p>
      <div class="mt-4">
        <a href="/shop/cart" class="btn btn-outline-primary me-2">
          <i class="bi bi-cart me-1"></i> Volver al carrito
        </a>
        <a href="/shop" class="btn btn-primary">
          <i class="bi bi-shop me-1"></i> Tienda
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
