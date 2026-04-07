@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="breadcrumb mb-3">
      <a href="/shop">Tienda</a>
      <span class="mx-2">/</span>
      <span>{{ e($product->name) }}</span>
    </div>

    <div class="row">
      <div class="col-lg-7">
        @if($product->featured_image)
          <img src="{{ e($product->featured_image) }}" class="img-fluid rounded shadow-sm mb-3" alt="{{ e($product->name) }}">
        @endif

        @if($product->description)
          <div class="card mb-3">
            <div class="card-body">
              {!! $product->description !!}
            </div>
          </div>
        @endif
      </div>

      <div class="col-lg-5">
        <div class="card shadow-sm sticky-top" style="top:100px">
          <div class="card-body">
            <h2 class="mb-3">{{ e($product->name) }}</h2>

            @if($product->short_description)
              <p class="text-muted">{{ e($product->short_description) }}</p>
            @endif

            <div class="mb-3">
              @if($product->compare_price)
                <span class="text-muted text-decoration-line-through fs-5 me-2">{{ $product->getFormattedComparePrice() }}</span>
              @endif
              <span class="fs-2 fw-bold text-primary">{{ $product->getFormattedPrice() }}</span>
              @if($product->isSubscription())
                <span class="text-muted">/{{ $product->billing_period === 'yearly' ? 'año' : 'mes' }}</span>
              @endif
            </div>

            @php $features = $product->getFeatures(); @endphp
            @if(!empty($features))
              <ul class="list-unstyled mb-4">
                @foreach($features as $feature)
                  <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i> {{ e($feature) }}</li>
                @endforeach
              </ul>
            @endif

            <form method="POST" action="/shop/cart/add">
              @csrf
              <input type="hidden" name="product_id" value="{{ $product->id }}">
              <input type="hidden" name="redirect" value="/shop/cart">
              @if(!$product->isSubscription())
              <div class="d-flex align-items-center gap-3 mb-3">
                <label for="quantity" class="form-label mb-0">Cantidad:</label>
                <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" style="max-width:80px">
              </div>
              @endif
              <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-cart-plus me-2"></i>
                {{ $product->isSubscription() ? 'Suscribirse' : 'Añadir al carrito' }}
              </button>
            </form>

            @if(!$product->hasStock())
              <div class="alert alert-warning mt-3 mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i> Producto agotado
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Related products --}}
    @if(!empty($related))
    <hr class="my-5">
    <h3 class="mb-4">Productos relacionados</h3>
    <div class="row g-4">
      @foreach($related as $rel)
      <div class="col-md-6 col-lg-3">
        <div class="card h-100">
          @if($rel->featured_image)
            <img src="{{ e($rel->featured_image) }}" class="card-img-top" alt="{{ e($rel->name) }}" style="height:150px; object-fit:cover">
          @endif
          <div class="card-body">
            <h6>{{ e($rel->name) }}</h6>
            <p class="fw-bold text-primary mb-2">{{ $rel->getFormattedPrice() }}</p>
            <a href="/shop/product/{{ e($rel->slug) }}" class="btn btn-outline-primary btn-sm">Ver</a>
          </div>
        </div>
      </div>
      @endforeach
    </div>
    @endif
  </div>
</div>
@endsection
