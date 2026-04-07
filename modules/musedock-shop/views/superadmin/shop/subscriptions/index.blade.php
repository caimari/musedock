@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-arrow-repeat me-2"></i>{{ $title }}</h2>
    </div>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif

    {{-- Filters --}}
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="{{ route('shop.subscriptions.index') }}" class="d-flex gap-2">
          <select name="status" class="form-select form-select-sm" style="max-width:150px">
            <option value="">Todos</option>
            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Activa</option>
            <option value="past_due" {{ $status === 'past_due' ? 'selected' : '' }}>Pago pendiente</option>
            <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelada</option>
            <option value="paused" {{ $status === 'paused' ? 'selected' : '' }}>Pausada</option>
          </select>
          <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel"></i></button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0">
        @if(empty($subscriptions))
          <div class="text-center py-5 text-muted">
            <i class="bi bi-arrow-repeat" style="font-size:3rem"></i>
            <p class="mt-2">{{ __('shop.subscription.no_subscriptions') }}</p>
          </div>
        @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Producto</th>
                <th>Estado</th>
                <th>Período</th>
                <th>Fin período</th>
                <th style="width:120px"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($subscriptions as $sub)
              @php
                $customer = $sub->customer();
                $product = $sub->product();
              @endphp
              <tr>
                <td class="text-muted">{{ $sub->id }}</td>
                <td>{{ $customer ? e($customer->name) : '—' }}</td>
                <td>{{ $product ? e($product->name) : '—' }}</td>
                <td>
                  <span class="badge {{ $sub->getStatusBadgeClass() }}">{{ ucfirst($sub->status) }}</span>
                  @if($sub->cancel_at_period_end)
                    <br><small class="text-danger">Cancela al final</small>
                  @endif
                </td>
                <td>{{ ucfirst($sub->billing_period) }}</td>
                <td>
                  @if($sub->current_period_end)
                    {{ date('d/m/Y', strtotime(is_object($sub->current_period_end) ? $sub->current_period_end->format('Y-m-d') : $sub->current_period_end)) }}
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if($sub->isActive() && !$sub->cancel_at_period_end)
                  <form method="POST" action="{{ route('shop.subscriptions.cancel', ['id' => $sub->id]) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="at_period_end" value="1">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-cancel-sub" data-form-id="cancel-sub-{{ $sub->id }}">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </form>
                  @endif
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-cancel-sub').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var form = this.closest('form');
      Swal.fire({
        title: '¿Cancelar suscripción?',
        text: 'La suscripción se cancelará al final del período actual.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No'
      }).then(function(result) {
        if (result.isConfirmed) form.submit();
      });
    });
  });
});
</script>
@endsection
