@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <h1 class="mb-4">{{ $title }}</h1>

    @if(empty($products))
      <div class="text-center py-5 text-muted">
        <i class="bi bi-shop" style="font-size:4rem"></i>
        <p class="mt-3 fs-5">No hay productos disponibles todavía.</p>
      </div>
    @else
      <div class="row g-4">
        @foreach($products as $product)
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            @if($product->featured_image)
              <img src="{{ e($product->featured_image) }}" class="card-img-top" alt="{{ e($product->name) }}" style="height:200px; object-fit:cover">
            @endif
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">{{ e($product->name) }}</h5>
              @if($product->short_description)
                <p class="card-text text-muted">{{ e($product->short_description) }}</p>
              @endif

              @php $features = $product->getFeatures(); @endphp
              @if(!empty($features))
                <ul class="list-unstyled mb-3">
                  @foreach(array_slice($features, 0, 3) as $feature)
                    <li><i class="bi bi-check2 text-success me-1"></i> {{ e($feature) }}</li>
                  @endforeach
                </ul>
              @endif

              <div class="mt-auto">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    @if($product->compare_price)
                      <span class="text-muted text-decoration-line-through me-2">{{ $product->getFormattedComparePrice() }}</span>
                    @endif
                    <span class="fs-4 fw-bold text-primary">{{ $product->getFormattedPrice() }}</span>
                    @if($product->isSubscription())
                      <small class="text-muted">/{{ $product->billing_period === 'yearly' ? 'año' : 'mes' }}</small>
                    @endif
                  </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                  <a href="/shop/product/{{ e($product->slug) }}" class="btn btn-outline-primary flex-grow-1">
                    Ver detalles
                  </a>
                  <form method="POST" action="/shop/cart/add" class="d-inline">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="redirect" value="/shop">
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-cart-plus"></i>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
@endsection
