@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb mb-0">
        <a href="{{ route('shop.coupons.index') }}">{{ __('shop.coupons') }}</a>
        <span class="mx-2">/</span>
        <span>{{ __('shop.coupon.new') }}</span>
      </div>
      <a href="{{ route('shop.coupons.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> {{ __('shop.coupons') }}
      </a>
    </div>

    @if (session('error'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(session('error')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 }); });</script>
    @endif

    <form method="POST" action="{{ route('shop.coupons.store') }}">
      @csrf
      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-3">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="code" class="form-label">{{ __('shop.coupon.code') }} <span class="text-danger">*</span></label>
                  <input type="text" class="form-control text-uppercase" id="code" name="code" required placeholder="DESCUENTO20">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="description" class="form-label">Descripción</label>
                  <input type="text" class="form-control" id="description" name="description" placeholder="Descuento de bienvenida">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="type" class="form-label">{{ __('shop.coupon.type') }}</label>
                  <select class="form-select" id="type" name="type">
                    <option value="percentage">{{ __('shop.coupon.type_percentage') }}</option>
                    <option value="fixed">{{ __('shop.coupon.type_fixed') }}</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="value" class="form-label">{{ __('shop.coupon.value') }}</label>
                  <input type="text" class="form-control" id="value" name="value" value="0" placeholder="20">
                  <small class="text-muted" id="valueHelp">Porcentaje (0-100)</small>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="min_order_amount" class="form-label">Pedido mínimo (€)</label>
                  <input type="text" class="form-control" id="min_order_amount" name="min_order_amount" placeholder="Opcional">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="max_discount_amount" class="form-label">Descuento máximo (€)</label>
                  <input type="text" class="form-control" id="max_discount_amount" name="max_discount_amount" placeholder="Opcional">
                </div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="max_uses" class="form-label">{{ __('shop.coupon.max_uses') }}</label>
                  <input type="number" class="form-control" id="max_uses" name="max_uses" placeholder="Ilimitado">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="valid_from" class="form-label">{{ __('shop.coupon.valid_from') }}</label>
                  <input type="datetime-local" class="form-control" id="valid_from" name="valid_from">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="valid_until" class="form-label">{{ __('shop.coupon.valid_until') }}</label>
                  <input type="datetime-local" class="form-control" id="valid_until" name="valid_until">
                </div>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                <label class="form-check-label" for="is_active">Cupón activo</label>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Crear cupón
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('type').addEventListener('change', function() {
  document.getElementById('valueHelp').textContent = this.value === 'percentage' ? 'Porcentaje (0-100)' : 'Importe en euros';
});
</script>
@endsection
