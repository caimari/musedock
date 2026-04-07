@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-ticket-perforated me-2"></i>{{ $title }}</h2>
      <a href="{{ route('shop.coupons.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> {{ __('shop.coupon.new') }}
      </a>
    </div>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif

    <div class="card">
      <div class="card-body p-0">
        @if(empty($coupons))
          <div class="text-center py-5 text-muted">
            <i class="bi bi-ticket-perforated" style="font-size:3rem"></i>
            <p class="mt-2">{{ __('shop.coupon.no_coupons') }}</p>
          </div>
        @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('shop.coupon.code') }}</th>
                <th>{{ __('shop.coupon.type') }}</th>
                <th>{{ __('shop.coupon.value') }}</th>
                <th class="text-center">{{ __('shop.coupon.used_count') }}</th>
                <th>Validez</th>
                <th class="text-center">Activo</th>
                <th style="width:120px"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($coupons as $coupon)
              <tr>
                <td><code class="fw-bold">{{ e($coupon->code) }}</code></td>
                <td>{{ $coupon->type === 'percentage' ? 'Porcentaje' : 'Importe fijo' }}</td>
                <td class="fw-bold">{{ $coupon->getFormattedValue() }}</td>
                <td class="text-center">
                  {{ $coupon->used_count }}
                  @if($coupon->max_uses)
                    / {{ $coupon->max_uses }}
                  @endif
                </td>
                <td>
                  @if($coupon->valid_from || $coupon->valid_until)
                    <small>
                      @if($coupon->valid_from) {{ date('d/m/Y', strtotime($coupon->valid_from)) }} @endif
                      @if($coupon->valid_from && $coupon->valid_until) — @endif
                      @if($coupon->valid_until) {{ date('d/m/Y', strtotime($coupon->valid_until)) }} @endif
                    </small>
                  @else
                    <small class="text-muted">Sin límite</small>
                  @endif
                </td>
                <td class="text-center">
                  @if($coupon->isValid())
                    <span class="badge bg-success">Sí</span>
                  @else
                    <span class="badge bg-danger">No</span>
                  @endif
                </td>
                <td>
                  <div class="d-flex gap-1 justify-content-end">
                    <a href="{{ route('shop.coupons.edit', ['id' => $coupon->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-coupon" data-id="{{ $coupon->id }}" data-code="{{ e($coupon->code) }}">
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
  </div>
</div>

<form id="deleteCouponForm" method="POST" style="display:none">
  @csrf
  <input type="hidden" name="_method" value="DELETE">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-delete-coupon').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.dataset.id;
      var code = this.dataset.code;
      Swal.fire({
        title: '¿Eliminar cupón?',
        text: 'Se eliminará el cupón "' + code + '".',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then(function(result) {
        if (result.isConfirmed) {
          var form = document.getElementById('deleteCouponForm');
          form.action = '/musedock/shop/coupons/' + id;
          form.submit();
        }
      });
    });
  });
});
</script>
@endsection
