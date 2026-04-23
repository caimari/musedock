@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="bi bi-shield-check me-2"></i>{{ $title }}</h2>
      <div class="d-flex gap-2">
        <a href="{{ festival_admin_url('claims') }}" class="btn btn-sm {{ empty($statusFilter) ? 'btn-primary' : 'btn-outline-secondary' }}">
          Todos ({{ array_sum($statusCounts) }})
        </a>
        <a href="{{ festival_admin_url('claims') }}?status=pending" class="btn btn-sm {{ $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning' }}">
          Pendientes ({{ $statusCounts['pending'] ?? 0 }})
        </a>
        <a href="{{ festival_admin_url('claims') }}?status=approved" class="btn btn-sm {{ $statusFilter === 'approved' ? 'btn-success' : 'btn-outline-success' }}">
          Aprobados ({{ $statusCounts['approved'] ?? 0 }})
        </a>
        <a href="{{ festival_admin_url('claims') }}?status=rejected" class="btn btn-sm {{ $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-danger' }}">
          Rechazados ({{ $statusCounts['rejected'] ?? 0 }})
        </a>
      </div>
    </div>

    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'success', title:'OK', text:{!! json_encode(session('success')) !!}, timer:3000 }); });</script>
    @endif

    <div class="card">
      <div class="card-body table-responsive p-0">
        @if(!empty($claims) && count($claims) > 0)
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Festival</th>
              <th>Solicitante</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($claims as $claim)
            @php $fest = $festivalNames[$claim->festival_id] ?? null; @endphp
            <tr>
              <td>
                @if($fest)
                  <a href="{{ festival_admin_url($claim->festival_id . '/edit') }}">{{ $fest['name'] }}</a>
                @else
                  <span class="text-muted">Festival #{{ $claim->festival_id }}</span>
                @endif
              </td>
              <td><strong>{{ e($claim->user_name) }}</strong></td>
              <td><a href="mailto:{{ $claim->user_email }}">{{ $claim->user_email }}</a></td>
              <td>{{ $claim->user_role ?? '—' }}</td>
              <td>
                @php
                  $claimColors = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'];
                  $claimLabels = ['pending'=>'Pendiente','approved'=>'Aprobado','rejected'=>'Rechazado'];
                @endphp
                <span class="badge bg-{{ $claimColors[$claim->status] ?? 'secondary' }}">{{ $claimLabels[$claim->status] ?? $claim->status }}</span>
              </td>
              <td><small>{{ date('d/m/Y H:i', strtotime($claim->created_at)) }}</small></td>
              <td>
                <a href="{{ festival_admin_url('claims/' . $claim->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else
        <div class="p-4 text-center">
          <i class="bi bi-shield-check" style="font-size:3rem;color:#dee2e6"></i>
          <p class="text-muted mt-2">No hay claims{{ !empty($statusFilter) ? ' ' . ($claimLabels[$statusFilter] ?? '') : '' }}.</p>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
