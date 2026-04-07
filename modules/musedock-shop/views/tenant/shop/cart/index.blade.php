@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-cart3 me-2"></i>{{ $title }}</h1>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif
    @if (session('error'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(session('error')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 }); });</script>
    @endif

    @if(empty($totals['items']))
      <div class="text-center py-5">
        <i class="bi bi-cart-x" style="font-size:4rem; color:#ccc"></i>
        <p class="mt-3 fs-5 text-muted">{{ __('shop.cart.empty') }}</p>
        <a href="/shop" class="btn btn-primary mt-2">
          <i class="bi bi-arrow-left me-1"></i> {{ __('shop.cart.continue_shopping') }}
        </a>
      </div>
    @else
      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-3">
            <div class="card-body p-0">
              <table class="table mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Producto</th>
                    <th class="text-center" style="width:120px">Cantidad</th>
                    <th class="text-end" style="width:120px">Precio</th>
                    <th class="text-end" style="width:120px">Total</th>
                    <th style="width:50px"></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($totals['items'] as $item)
                  <tr>
                    <td>
                      <strong>{{ e($item['name']) }}</strong>
                      @if($item['type'] === 'subscription')
                        <span class="badge bg-primary ms-1">Suscripción</span>
                      @endif
                    </td>
                    <td class="text-center">
                      @if($item['type'] !== 'subscription')
                      <form method="POST" action="/shop/cart/update" class="d-inline">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                        <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" class="form-control form-control-sm text-center" style="width:70px; display:inline-block" onchange="this.form.submit()">
                      </form>
                      @else
                        {{ $item['quantity'] }}
                      @endif
                    </td>
                    <td class="text-end">{{ shop_format_price($item['price'], $item['currency'] ?? 'eur') }}</td>
                    <td class="text-end fw-bold">{{ shop_format_price($item['price'] * $item['quantity'], $item['currency'] ?? 'eur') }}</td>
                    <td>
                      <form method="POST" action="/shop/cart/remove" class="d-inline">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                      </form>
                    </td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>

          {{-- Coupon --}}
          <div class="card mb-3">
            <div class="card-body">
              <form method="POST" action="/shop/cart/coupon" class="d-flex gap-2">
                @csrf
                <input type="text" name="coupon_code" class="form-control" placeholder="{{ __('shop.checkout.coupon_placeholder') }}" value="{{ $totals['coupon_code'] ?? '' }}">
                <button type="submit" class="btn btn-outline-primary">{{ __('shop.checkout.apply_coupon') }}</button>
              </form>
            </div>
          </div>
        </div>

        {{-- Summary --}}
        <div class="col-lg-4">
          <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Resumen</h5></div>
            <div class="card-body">
              <div class="d-flex justify-content-between mb-2">
                <span>{{ __('shop.cart.subtotal') }}:</span>
                <span>{{ shop_format_price($totals['subtotal'], $totals['currency']) }}</span>
              </div>
              @if($totals['discount'] > 0)
              <div class="d-flex justify-content-between mb-2 text-success">
                <span>Descuento:</span>
                <span>-{{ shop_format_price($totals['discount'], $totals['currency']) }}</span>
              </div>
              @endif
              <div class="d-flex justify-content-between mb-2">
                <span>{{ __('shop.cart.tax') }} ({{ $totals['tax_rate'] }}%):</span>
                <span>{{ shop_format_price($totals['tax_amount'], $totals['currency']) }}</span>
              </div>
              <hr>
              <div class="d-flex justify-content-between fw-bold fs-5">
                <span>{{ __('shop.cart.total') }}:</span>
                <span class="text-primary">{{ shop_format_price($totals['total'], $totals['currency']) }}</span>
              </div>
            </div>
            <div class="card-footer">
              <a href="/shop/checkout" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-lock me-1"></i> {{ __('shop.cart.checkout') }}
              </a>
              <a href="/shop" class="btn btn-link w-100 mt-2 text-muted">
                {{ __('shop.cart.continue_shopping') }}
              </a>
            </div>
          </div>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection
