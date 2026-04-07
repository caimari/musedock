@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="breadcrumb mb-0">
        <a href="{{ route('shop.orders.index') }}">{{ __('shop.orders') }}</a>
        <span class="mx-2">/</span>
        <span>{{ $order->order_number }}</span>
      </div>
      <a href="{{ route('shop.orders.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> {{ __('shop.orders') }}
      </a>
    </div>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif

    <div class="row">
      <div class="col-lg-8">
        {{-- Order items --}}
        <div class="card mb-3">
          <div class="card-header"><h5 class="mb-0">{{ __('shop.order.items') }}</h5></div>
          <div class="card-body p-0">
            <table class="table mb-0">
              <thead class="table-light">
                <tr>
                  <th>Producto</th>
                  <th class="text-center">Cantidad</th>
                  <th class="text-end">Precio</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach($items as $item)
                <tr>
                  <td>
                    {{ e($item->product_name) }}
                    @if($item->product_type)
                      <span class="badge bg-secondary ms-1">{{ $item->product_type }}</span>
                    @endif
                  </td>
                  <td class="text-center">{{ $item->quantity }}</td>
                  <td class="text-end">{{ $item->getFormattedUnitPrice() }}</td>
                  <td class="text-end">{{ $item->getFormattedTotal() }}</td>
                </tr>
                @endforeach
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <td colspan="3" class="text-end">Subtotal:</td>
                  <td class="text-end">{{ $order->getFormattedSubtotal() }}</td>
                </tr>
                @if($order->discount_amount > 0)
                <tr>
                  <td colspan="3" class="text-end text-success">Descuento:</td>
                  <td class="text-end text-success">-{{ $order->getFormattedDiscount() }}</td>
                </tr>
                @endif
                <tr>
                  <td colspan="3" class="text-end">IVA ({{ $order->tax_rate }}%):</td>
                  <td class="text-end">{{ $order->getFormattedTax() }}</td>
                </tr>
                <tr class="fw-bold">
                  <td colspan="3" class="text-end">Total:</td>
                  <td class="text-end">{{ $order->getFormattedTotal() }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        {{-- Update status --}}
        <div class="card mb-3">
          <div class="card-header"><h5 class="mb-0">Actualizar pedido</h5></div>
          <div class="card-body">
            <form method="POST" action="{{ route('shop.orders.update', ['id' => $order->id]) }}">
              @csrf
              <input type="hidden" name="_method" value="PUT">
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="status" class="form-label">Estado</label>
                  <select class="form-select" id="status" name="status">
                    <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pendiente</option>
                    <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Procesando</option>
                    <option value="completed" {{ $order->status === 'completed' ? 'selected' : '' }}>Completado</option>
                    <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                    <option value="refunded" {{ $order->status === 'refunded' ? 'selected' : '' }}>Reembolsado</option>
                  </select>
                </div>
                <div class="col-md-8 mb-3">
                  <label for="notes" class="form-label">Notas</label>
                  <textarea class="form-control" id="notes" name="notes" rows="2">{{ e($order->notes) }}</textarea>
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg me-1"></i> Actualizar
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        {{-- Order info --}}
        <div class="card mb-3">
          <div class="card-header"><h5 class="mb-0">Información</h5></div>
          <div class="card-body">
            <dl class="mb-0">
              <dt>Estado</dt>
              <dd><span class="badge {{ $order->getStatusBadgeClass() }}">{{ ucfirst($order->status) }}</span></dd>

              <dt>Fecha</dt>
              <dd>{{ date('d/m/Y H:i', strtotime($order->created_at)) }}</dd>

              @if($order->completed_at)
              <dt>Completado</dt>
              <dd>{{ date('d/m/Y H:i', strtotime($order->completed_at)) }}</dd>
              @endif

              @if($order->stripe_payment_intent_id)
              <dt>Stripe PI</dt>
              <dd><code>{{ $order->stripe_payment_intent_id }}</code></dd>
              @endif
              @if($order->stripe_checkout_session_id)
              <dt>Stripe Session</dt>
              <dd><code style="font-size:0.75rem">{{ $order->stripe_checkout_session_id }}</code></dd>
              @endif
            </dl>
          </div>
        </div>

        {{-- Customer info --}}
        <div class="card mb-3">
          <div class="card-header"><h5 class="mb-0">Cliente</h5></div>
          <div class="card-body">
            @if($customer)
              <p class="mb-1"><strong>{{ e($customer->name) }}</strong></p>
              <p class="mb-1"><small>{{ e($customer->email) }}</small></p>
              @if($customer->phone)
                <p class="mb-1"><small>{{ e($customer->phone) }}</small></p>
              @endif
              @if($customer->stripe_customer_id)
                <p class="mb-0"><small class="text-muted">Stripe: <code>{{ $customer->stripe_customer_id }}</code></small></p>
              @endif
            @else
              <p class="mb-1"><strong>{{ e($order->billing_name) }}</strong></p>
              <p class="mb-0"><small>{{ e($order->billing_email) }}</small></p>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
