@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb mb-0">
        <a href="{{ route('shop.coupons.index') }}">{{ __('shop.coupons') }}</a>
        <span class="mx-2">/</span>
        <span>{{ e($coupon->code) }}</span>
      </div>
      <a href="{{ route('shop.coupons.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> {{ __('shop.coupons') }}
      </a>
    </div>

    @if (session('error'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(session('error')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 }); });</script>
    @endif

    <form method="POST" action="{{ route('shop.coupons.update', ['id' => $coupon->id]) }}">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-3">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="code" class="form-label">{{ __('shop.coupon.code') }} <span class="text-danger">*</span></label>
                  <input type="text" class="form-control text-uppercase" id="code" name="code" value="{{ e($coupon->code) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="description" class="form-label">Descripción</label>
                  <input type="text" class="form-control" id="description" name="description" value="{{ e($coupon->description) }}">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="type" class="form-label">{{ __('shop.coupon.type') }}</label>
                  <select class="form-select" id="type" name="type">
                    <option value="percentage" {{ $coupon->type === 'percentage' ? 'selected' : '' }}>{{ __('shop.coupon.type_percentage') }}</option>
                    <option value="fixed" {{ $coupon->type === 'fixed' ? 'selected' : '' }}>{{ __('shop.coupon.type_fixed') }}</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="value" class="form-label">{{ __('shop.coupon.value') }}</label>
                  <input type="text" class="form-control" id="value" name="value" value="{{ $coupon->type === 'percentage' ? $coupon->value : number_format($coupon->value / 100, 2, ',', '') }}">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="min_order_amount" class="form-label">Pedido mínimo (€)</label>
                  <input type="text" class="form-control" id="min_order_amount" name="min_order_amount" value="{{ $coupon->min_order_amount ? number_format($coupon->min_order_amount / 100, 2, ',', '') : '' }}">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="max_discount_amount" class="form-label">Descuento máximo (€)</label>
                  <input type="text" class="form-control" id="max_discount_amount" name="max_discount_amount" value="{{ $coupon->max_discount_amount ? number_format($coupon->max_discount_amount / 100, 2, ',', '') : '' }}">
                </div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="max_uses" class="form-label">{{ __('shop.coupon.max_uses') }}</label>
                  <input type="number" class="form-control" id="max_uses" name="max_uses" value="{{ $coupon->max_uses }}">
                  <small class="text-muted">Usados: {{ $coupon->used_count }}</small>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="valid_from" class="form-label">{{ __('shop.coupon.valid_from') }}</label>
                  <input type="datetime-local" class="form-control" id="valid_from" name="valid_from" value="{{ $coupon->valid_from ? date('Y-m-d\TH:i', strtotime($coupon->valid_from)) : '' }}">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="valid_until" class="form-label">{{ __('shop.coupon.valid_until') }}</label>
                  <input type="datetime-local" class="form-control" id="valid_until" name="valid_until" value="{{ $coupon->valid_until ? date('Y-m-d\TH:i', strtotime($coupon->valid_until)) : '' }}">
                </div>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" {{ $coupon->is_active ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Cupón activo</label>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Guardar cambios
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
