@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb mb-0">
        <a href="{{ admin_url('shop/products') }}">{{ __('shop.products') }}</a>
        <span class="mx-2">/</span>
        <span>{{ $product ? e($product->name) : __('shop.product.new') }}</span>
      </div>
      <a href="{{ admin_url('shop/products') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
    </div>

    @if (session('error'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'error', title: {!! json_encode(session('error')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 }); });</script>
    @endif

    <form method="POST" action="{{ $product ? admin_url('shop/products/' . $product->id) : admin_url('shop/products') }}">
      @csrf
      @if($product) <input type="hidden" name="_method" value="PUT"> @endif

      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-3">
            <div class="card-body">
              <div class="mb-3">
                <label for="name" class="form-label">{{ __('shop.product.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ $product ? e($product->name) : '' }}" required>
              </div>
              <div class="mb-3">
                <label for="slug" class="form-label">Slug</label>
                <input type="text" class="form-control" id="slug" name="slug" value="{{ $product ? e($product->slug) : '' }}">
              </div>
              <div class="mb-3">
                <label for="description" class="form-label">{{ __('shop.product.description') }}</label>
                <textarea class="form-control" id="description" name="description" rows="4">{{ $product ? e($product->description) : '' }}</textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card mb-3">
            <div class="card-body">
              <div class="mb-3">
                <label for="type" class="form-label">{{ __('shop.product.type') }}</label>
                <select class="form-select" id="type" name="type" onchange="document.getElementById('bp').style.display=this.value==='subscription'?'':'none'">
                  <option value="digital" {{ ($product && $product->type === 'digital') ? 'selected' : '' }}>Digital</option>
                  <option value="physical" {{ ($product && $product->type === 'physical') ? 'selected' : '' }}>Físico</option>
                  <option value="service" {{ ($product && $product->type === 'service') ? 'selected' : '' }}>Servicio</option>
                  <option value="subscription" {{ ($product && $product->type === 'subscription') ? 'selected' : '' }}>Suscripción</option>
                </select>
              </div>
              <div id="bp" style="{{ ($product && $product->type === 'subscription') ? '' : 'display:none' }}">
                <div class="mb-3">
                  <label class="form-label">Período</label>
                  <select class="form-select" name="billing_period">
                    <option value="monthly" {{ ($product && $product->billing_period === 'monthly') ? 'selected' : '' }}>Mensual</option>
                    <option value="yearly" {{ ($product && $product->billing_period === 'yearly') ? 'selected' : '' }}>Anual</option>
                  </select>
                </div>
              </div>
              <div class="mb-3">
                <label for="price" class="form-label">Precio (€)</label>
                <input type="text" class="form-control" id="price" name="price" value="{{ $product ? number_format($product->price / 100, 2, ',', '') : '0' }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Moneda</label>
                <select class="form-select" name="currency">
                  <option value="eur" {{ (!$product || $product->currency === 'eur') ? 'selected' : '' }}>EUR</option>
                  <option value="usd" {{ ($product && $product->currency === 'usd') ? 'selected' : '' }}>USD</option>
                </select>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" {{ (!$product || $product->is_active) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Activo</label>
              </div>
              <div class="mb-3">
                <label class="form-label">Orden</label>
                <input type="number" class="form-control" name="sort_order" value="{{ $product ? $product->sort_order : 0 }}">
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> {{ $product ? 'Guardar' : 'Crear' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
