@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-lock me-2"></i>{{ $title }}</h1>

    @if (session('error'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(session('error')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 }); });</script>
    @endif

    <form method="POST" action="/shop/checkout" id="checkoutForm">
      @csrf
      <div class="row">
        <div class="col-lg-7">
          {{-- Billing details --}}
          <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ __('shop.checkout.billing') }}</h5></div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="billing_name" class="form-label">{{ __('shop.checkout.name') }} <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="billing_name" name="billing_name" required
                         value="{{ $_SESSION['user']['name'] ?? $_SESSION['admin']['name'] ?? '' }}">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="billing_email" class="form-label">{{ __('shop.checkout.email') }} <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" id="billing_email" name="billing_email" required
                         value="{{ $_SESSION['user']['email'] ?? $_SESSION['admin']['email'] ?? '' }}">
                </div>
              </div>
              <div class="mb-3">
                <label for="billing_phone" class="form-label">Teléfono</label>
                <input type="tel" class="form-control" id="billing_phone" name="billing_phone">
              </div>
            </div>
          </div>

          {{-- Order summary --}}
          <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Tu pedido</h5></div>
            <div class="card-body p-0">
              <table class="table mb-0">
                <tbody>
                  @foreach($totals['items'] as $item)
                  <tr>
                    <td>
                      {{ e($item['name']) }}
                      @if($item['quantity'] > 1)
                        <small class="text-muted">&times; {{ $item['quantity'] }}</small>
                      @endif
                    </td>
                    <td class="text-end">{{ shop_format_price($item['price'] * $item['quantity'], $item['currency'] ?? 'eur') }}</td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card shadow-sm sticky-top" style="top:100px">
            <div class="card-header"><h5 class="mb-0">Resumen del pago</h5></div>
            <div class="card-body">
              <div class="d-flex justify-content-between mb-2">
                <span>Subtotal:</span>
                <span>{{ shop_format_price($totals['subtotal'], $totals['currency']) }}</span>
              </div>
              @if($totals['discount'] > 0)
              <div class="d-flex justify-content-between mb-2 text-success">
                <span>Descuento:</span>
                <span>-{{ shop_format_price($totals['discount'], $totals['currency']) }}</span>
              </div>
              @endif
              <div class="d-flex justify-content-between mb-2">
                <span>IVA ({{ $totals['tax_rate'] }}%):</span>
                <span>{{ shop_format_price($totals['tax_amount'], $totals['currency']) }}</span>
              </div>
              <hr>
              <div class="d-flex justify-content-between fw-bold fs-4 mb-3">
                <span>Total:</span>
                <span class="text-primary">{{ shop_format_price($totals['total'], $totals['currency']) }}</span>
              </div>

              <button type="submit" class="btn btn-primary btn-lg w-100" id="btnPay">
                <i class="bi bi-credit-card me-2"></i> {{ __('shop.checkout.pay') }}
              </button>
              <p class="text-center mt-3 text-muted small">
                <i class="bi bi-shield-lock me-1"></i> Pago seguro con Stripe
              </p>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('checkoutForm').addEventListener('submit', function() {
  var btn = document.getElementById('btnPay');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> {{ __("shop.checkout.processing") }}';
});
</script>
@endsection
