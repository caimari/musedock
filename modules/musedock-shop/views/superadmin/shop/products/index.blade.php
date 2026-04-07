@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0"><i class="bi bi-box-seam me-2"></i>{{ $title }}</h2>
        <small class="text-muted">{{ $totalItems }} producto(s)</small>
      </div>
      <a href="{{ route('shop.products.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> {{ __('shop.product.new') }}
      </a>
    </div>

    @if (session('success'))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
      });
    </script>
    @endif

    {{-- Search --}}
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="{{ route('shop.products.index') }}" class="d-flex align-items-center gap-2">
          <input type="text" name="search" value="{{ $search }}" placeholder="Buscar productos..." class="form-control form-control-sm" style="max-width:300px">
          <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
          @if(!empty($search))
            <a href="{{ route('shop.products.index') }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-x"></i></a>
          @endif
        </form>
      </div>
    </div>

    {{-- Table --}}
    <div class="card">
      <div class="card-body p-0">
        @if(empty($products))
          <div class="text-center py-5 text-muted">
            <i class="bi bi-box-seam" style="font-size:3rem"></i>
            <p class="mt-2">{{ __('shop.product.no_products') }}</p>
          </div>
        @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:50px">#</th>
                <th>{{ __('shop.product.name') }}</th>
                <th>{{ __('shop.product.type') }}</th>
                <th class="text-end">{{ __('shop.product.price') }}</th>
                <th class="text-center">{{ __('shop.product.active') }}</th>
                <th class="text-center">Orden</th>
                <th style="width:120px"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($products as $product)
              <tr>
                <td class="text-muted">{{ $product->id }}</td>
                <td>
                  <strong>{{ e($product->name) }}</strong>
                  <br><small class="text-muted">/shop/product/{{ e($product->slug) }}</small>
                </td>
                <td>
                  <span class="badge bg-{{ match($product->type) { 'subscription' => 'primary', 'service' => 'info', 'digital' => 'success', default => 'secondary' } }}">
                    {{ ucfirst($product->type) }}
                  </span>
                  @if($product->billing_period)
                    <small class="text-muted">({{ $product->billing_period }})</small>
                  @endif
                </td>
                <td class="text-end fw-bold">{{ $product->getFormattedPrice() }}</td>
                <td class="text-center">
                  @if($product->is_active)
                    <span class="badge bg-success">Sí</span>
                  @else
                    <span class="badge bg-danger">No</span>
                  @endif
                </td>
                <td class="text-center">{{ $product->sort_order }}</td>
                <td>
                  <div class="d-flex gap-1 justify-content-end">
                    <a href="{{ route('shop.products.edit', ['id' => $product->id]) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-product" data-id="{{ $product->id }}" data-name="{{ e($product->name) }}" title="Eliminar">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>

    {{-- Pagination --}}
    @if($totalPages > 1)
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center">
        @for($i = 1; $i <= $totalPages; $i++)
          <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
            <a class="page-link" href="{{ route('shop.products.index') }}?page={{ $i }}&search={{ urlencode($search) }}&perPage={{ $perPage }}">{{ $i }}</a>
          </li>
        @endfor
      </ul>
    </nav>
    @endif
  </div>
</div>

{{-- Delete form (hidden) --}}
<form id="deleteProductForm" method="POST" style="display:none">
  @csrf
  <input type="hidden" name="_method" value="DELETE">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-delete-product').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.dataset.id;
      var name = this.dataset.name;
      Swal.fire({
        title: '¿Eliminar producto?',
        text: 'Se eliminará "' + name + '". Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then(function(result) {
        if (result.isConfirmed) {
          var form = document.getElementById('deleteProductForm');
          form.action = '/musedock/shop/products/' + id;
          form.submit();
        }
      });
    });
  });
});
</script>
@endsection
