@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="mb-0">{{ $title }}</h1>
      <a href="/shop/account" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Mi cuenta</a>
    </div>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif

    <div class="card">
      <div class="card-body p-0">
        @if(empty($subscriptions))
          <div class="text-center py-5 text-muted">{{ __('shop.customer.no_subscriptions') }}</div>
        @else
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Plan</th>
              <th>Estado</th>
              <th>Período</th>
              <th>Próxima renovación</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($subscriptions as $sub)
            <tr>
              <td><strong>{{ $sub->_product ? e($sub->_product->name) : '—' }}</strong></td>
              <td>
                <span class="badge {{ $sub->getStatusBadgeClass() }}">{{ ucfirst($sub->status) }}</span>
                @if($sub->cancel_at_period_end)
                  <br><small class="text-danger">Se cancela al final del período</small>
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
                  <button type="button" class="btn btn-sm btn-outline-danger btn-cancel-sub" data-sub-id="{{ $sub->id }}">
                    Cancelar
                  </button>
                @endif
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-cancel-sub').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var subId = this.dataset.subId;
      Swal.fire({
        title: '¿Cancelar suscripción?',
        text: 'Tu suscripción se mantendrá activa hasta el final del período actual.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener'
      }).then(function(result) {
        if (result.isConfirmed) {
          var form = document.createElement('form');
          form.method = 'POST';
          form.action = '/shop/account/subscriptions/' + subId + '/cancel';
          var csrf = document.createElement('input');
          csrf.type = 'hidden';
          csrf.name = '_token';
          csrf.value = '{{ csrf_token() }}';
          form.appendChild(csrf);
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
  });
});
</script>
@endsection
