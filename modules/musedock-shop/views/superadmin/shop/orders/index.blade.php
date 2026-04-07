@extends('layouts::app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0"><i class="bi bi-receipt me-2"></i>{{ $title }}</h2>
        <small class="text-muted">{{ $totalItems }} pedido(s)</small>
      </div>
    </div>

    @if (session('success'))
    <script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: 'success', title: {!! json_encode(session('success')) !!}, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }); });</script>
    @endif

    {{-- Stats --}}
    <div class="row mb-3">
      <div class="col-md-4">
        <div class="card text-center py-2">
          <div class="card-body py-2">
            <div class="fs-4 fw-bold">{{ $stats['total'] }}</div>
            <small class="text-muted">Total pedidos</small>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card text-center py-2">
          <div class="card-body py-2">
            <div class="fs-4 fw-bold text-warning">{{ $stats['pending'] }}</div>
            <small class="text-muted">Pendientes</small>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card text-center py-2">
          <div class="card-body py-2">
            <div class="fs-4 fw-bold text-success">{{ $stats['completed'] }}</div>
            <small class="text-muted">Completados</small>
          </div>
        </div>
      </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" action="{{ route('shop.orders.index') }}" class="d-flex align-items-center gap-2">
          <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por nº pedido, nombre, email..." class="form-control form-control-sm" style="max-width:300px">
          <select name="status" class="form-select form-select-sm" style="max-width:150px">
            <option value="">Todos</option>
            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pendiente</option>
            <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>Procesando</option>
            <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completado</option>
            <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
            <option value="refunded" {{ $status === 'refunded' ? 'selected' : '' }}>Reembolsado</option>
          </select>
          <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
        </form>
      </div>
    </div>

    {{-- Table --}}
    <div class="card">
      <div class="card-body p-0">
        @if(empty($orders))
          <div class="text-center py-5 text-muted">
            <i class="bi bi-receipt" style="font-size:3rem"></i>
            <p class="mt-2">{{ __('shop.order.no_orders') }}</p>
          </div>
        @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('shop.order.order_number') }}</th>
                <th>{{ __('shop.order.customer') }}</th>
                <th>{{ __('shop.order.status') }}</th>
                <th class="text-end">{{ __('shop.order.total') }}</th>
                <th>{{ __('shop.order.date') }}</th>
                <th style="width:80px"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($orders as $order)
              <tr>
                <td><strong>{{ e($order->order_number) }}</strong></td>
                <td>
                  {{ e($order->billing_name) }}
                  <br><small class="text-muted">{{ e($order->billing_email) }}</small>
                </td>
                <td><span class="badge {{ $order->getStatusBadgeClass() }}">{{ ucfirst($order->status) }}</span></td>
                <td class="text-end fw-bold">{{ $order->getFormattedTotal() }}</td>
                <td><small>{{ date('d/m/Y H:i', strtotime($order->created_at)) }}</small></td>
                <td>
                  <a href="{{ route('shop.orders.show', ['id' => $order->id]) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye"></i>
                  </a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>

    @if($totalPages > 1)
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center">
        @for($i = 1; $i <= $totalPages; $i++)
          <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
            <a class="page-link" href="{{ route('shop.orders.index') }}?page={{ $i }}&search={{ urlencode($search) }}&status={{ $status }}">{{ $i }}</a>
          </li>
        @endfor
      </ul>
    </nav>
    @endif
  </div>
</div>
@endsection
