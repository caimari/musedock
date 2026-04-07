@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-box-seam me-2"></i>{{ $title }}</h2>
      <a href="{{ admin_url('shop/products/create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> {{ __('shop.product.new') }}
      </a>
    </div>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif

    <div class="card">
      <div class="card-body p-0">
        @if(empty($products))
          <div class="text-center py-5 text-muted">
            <i class="bi bi-box-seam" style="font-size:3rem"></i>
            <p class="mt-2">{{ __('shop.product.no_products') }}</p>
          </div>
        @else
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('shop.product.name') }}</th>
              <th>{{ __('shop.product.type') }}</th>
              <th class="text-end">{{ __('shop.product.price') }}</th>
              <th class="text-center">Activo</th>
              <th style="width:120px"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($products as $product)
            <tr>
              <td><strong>{{ e($product->name) }}</strong></td>
              <td><span class="badge bg-secondary">{{ ucfirst($product->type) }}</span></td>
              <td class="text-end fw-bold">{{ $product->getFormattedPrice() }}</td>
              <td class="text-center">
                @if($product->is_active) <span class="badge bg-success">Sí</span> @else <span class="badge bg-danger">No</span> @endif
              </td>
              <td>
                <div class="d-flex gap-1 justify-content-end">
                  <a href="{{ admin_url('shop/products/' . $product->id . '/edit') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="{{ $product->id }}" data-name="{{ e($product->name) }}"><i class="bi bi-trash"></i></button>
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @endif
      </div>
    </div>
  </div>
</div>

<form id="deleteForm" method="POST" style="display:none">@csrf <input type="hidden" name="_method" value="DELETE"></form>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-delete').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.dataset.id, name = this.dataset.name;
      Swal.fire({
        title: '¿Eliminar "' + name + '"?', icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'
      }).then(function(r) {
        if (r.isConfirmed) { var f = document.getElementById('deleteForm'); f.action = '{{ admin_url("shop/products") }}/' + id; f.submit(); }
      });
    });
  });
});
</script>
@endsection
