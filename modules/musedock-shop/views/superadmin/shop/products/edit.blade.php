@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb mb-0">
        <a href="{{ route('shop.products.index') }}">{{ __('shop.products') }}</a>
        <span class="mx-2">/</span>
        <span>{{ e($product->name) }}</span>
      </div>
      <a href="{{ route('shop.products.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> {{ __('shop.products') }}
      </a>
    </div>

    @if (session('success'))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
      });
    </script>
    @endif
    @if (session('error'))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({ icon: 'error', title: {!! json_encode(session('error')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000 });
      });
    </script>
    @endif

    <form method="POST" action="{{ route('shop.products.update', ['id' => $product->id]) }}">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Información del producto</h5></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="name" class="form-label">{{ __('shop.product.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ e($product->name) }}" required>
              </div>
              <div class="mb-3">
                <label for="slug" class="form-label">{{ __('shop.product.slug') }}</label>
                <input type="text" class="form-control" id="slug" name="slug" value="{{ e($product->slug) }}">
              </div>
              <div class="mb-3">
                <label for="short_description" class="form-label">Descripción corta</label>
                <textarea class="form-control" id="short_description" name="short_description" rows="2">{{ e($product->short_description) }}</textarea>
              </div>
              <div class="mb-3">
                <label for="description" class="form-label">{{ __('shop.product.description') }}</label>
                <textarea class="form-control" id="description" name="description" rows="6">{{ e($product->description) }}</textarea>
              </div>
              @php $features = $product->getFeatures(); @endphp
              <div class="mb-3">
                <label for="features" class="form-label">{{ __('shop.product.features') }}</label>
                <textarea class="form-control" id="features" name="features" rows="4" placeholder="Una característica por línea">{{ implode("\n", $features) }}</textarea>
              </div>
            </div>
          </div>

          {{-- Stripe info --}}
          @if($product->stripe_product_id || $product->stripe_price_id)
          <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Stripe</h5></div>
            <div class="card-body">
              @if($product->stripe_product_id)
                <p class="mb-1"><small class="text-muted">Product ID:</small> <code>{{ $product->stripe_product_id }}</code></p>
              @endif
              @if($product->stripe_price_id)
                <p class="mb-0"><small class="text-muted">Price ID:</small> <code>{{ $product->stripe_price_id }}</code></p>
              @endif
            </div>
          </div>
          @endif
        </div>

        <div class="col-lg-4">
          <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Precio y tipo</h5></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="type" class="form-label">{{ __('shop.product.type') }}</label>
                <select class="form-select" id="type" name="type" onchange="toggleBillingPeriod()">
                  <option value="digital" {{ $product->type === 'digital' ? 'selected' : '' }}>{{ __('shop.product.type_digital') }}</option>
                  <option value="physical" {{ $product->type === 'physical' ? 'selected' : '' }}>{{ __('shop.product.type_physical') }}</option>
                  <option value="service" {{ $product->type === 'service' ? 'selected' : '' }}>{{ __('shop.product.type_service') }}</option>
                  <option value="subscription" {{ $product->type === 'subscription' ? 'selected' : '' }}>{{ __('shop.product.type_subscription') }}</option>
                </select>
              </div>
              <div class="mb-3" id="billingPeriodGroup" style="{{ $product->type === 'subscription' ? '' : 'display:none' }}">
                <label for="billing_period" class="form-label">{{ __('shop.product.billing_period') }}</label>
                <select class="form-select" id="billing_period" name="billing_period">
                  <option value="monthly" {{ $product->billing_period === 'monthly' ? 'selected' : '' }}>{{ __('shop.product.monthly') }}</option>
                  <option value="yearly" {{ $product->billing_period === 'yearly' ? 'selected' : '' }}>{{ __('shop.product.yearly') }}</option>
                </select>
              </div>
              <div class="row">
                <div class="col-6 mb-3">
                  <label for="price" class="form-label">{{ __('shop.product.price') }} (€)</label>
                  <input type="text" class="form-control" id="price" name="price" value="{{ number_format($product->price / 100, 2, ',', '') }}">
                </div>
                <div class="col-6 mb-3">
                  <label for="compare_price" class="form-label">Precio anterior</label>
                  <input type="text" class="form-control" id="compare_price" name="compare_price" value="{{ $product->compare_price ? number_format($product->compare_price / 100, 2, ',', '') : '' }}">
                </div>
              </div>
              <div class="mb-3">
                <label for="currency" class="form-label">{{ __('shop.product.currency') }}</label>
                <select class="form-select" id="currency" name="currency">
                  <option value="eur" {{ $product->currency === 'eur' ? 'selected' : '' }}>EUR (€)</option>
                  <option value="usd" {{ $product->currency === 'usd' ? 'selected' : '' }}>USD ($)</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="stock_quantity" class="form-label">Stock</label>
                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" value="{{ $product->stock_quantity }}" placeholder="Vacío = ilimitado">
              </div>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Publicación</h5></div>
            <div class="card-body">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" {{ $product->is_active ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Producto activo</label>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" {{ $product->is_featured ? 'checked' : '' }}>
                <label class="form-check-label" for="is_featured">Destacado</label>
              </div>
              <div class="mb-3">
                <label for="sort_order" class="form-label">Orden</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ $product->sort_order }}">
              </div>
              <div class="mb-3">
                <label for="featured_image" class="form-label">Imagen destacada (URL)</label>
                <input type="text" class="form-control" id="featured_image" name="featured_image" value="{{ e($product->featured_image) }}">
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i> Guardar cambios
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function toggleBillingPeriod() {
  document.getElementById('billingPeriodGroup').style.display = document.getElementById('type').value === 'subscription' ? '' : 'none';
}
</script>
@endsection
