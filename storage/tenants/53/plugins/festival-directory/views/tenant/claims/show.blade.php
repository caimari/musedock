@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <a href="{{ festival_admin_url('claims') }}" class="text-muted text-decoration-none">Claims</a>
        <span class="mx-1 text-muted">/</span>
        <span>{{ $title }}</span>
      </div>
      <a href="{{ festival_admin_url('claims') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    </div>

    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'success', title:'OK', text:{!! json_encode(session('success')) !!}, timer:3000 }); });</script>
    @endif

    <div class="row">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-header"><strong>Datos del Claim</strong></div>
          <div class="card-body">
            <table class="table table-borderless">
              <tr><th style="width:180px">Festival:</th><td>
                @if($festival)
                  <a href="{{ festival_admin_url($festival->id . '/edit') }}">{{ $festival->name }}</a>
                @else
                  ID: {{ $claim->festival_id }}
                @endif
              </td></tr>
              <tr><th>Nombre:</th><td>{{ e($claim->user_name) }}</td></tr>
              <tr><th>Email:</th><td><a href="mailto:{{ $claim->user_email }}">{{ $claim->user_email }}</a></td></tr>
              <tr><th>Rol/Cargo:</th><td>{{ e($claim->user_role ?? '—') }}</td></tr>
              <tr><th>Verificación:</th><td>{{ e($claim->verification_details ?? '—') }}</td></tr>
              <tr><th>Estado:</th><td>
                @php
                  $claimColors = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'];
                  $claimLabels = ['pending'=>'Pendiente','approved'=>'Aprobado','rejected'=>'Rechazado'];
                @endphp
                <span class="badge bg-{{ $claimColors[$claim->status] ?? 'secondary' }}">{{ $claimLabels[$claim->status] ?? $claim->status }}</span>
              </td></tr>
              <tr><th>Fecha solicitud:</th><td>{{ date('d/m/Y H:i', strtotime($claim->created_at)) }}</td></tr>
              @if($claim->resolved_at)
              <tr><th>Fecha resolución:</th><td>{{ date('d/m/Y H:i', strtotime($claim->resolved_at)) }}</td></tr>
              @endif
              @if($claim->admin_notes)
              <tr><th>Notas admin:</th><td>{{ e($claim->admin_notes) }}</td></tr>
              @endif
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        @if($claim->status === 'pending')
        <div class="card mb-3">
          <div class="card-header bg-success text-white"><strong>Aprobar Claim</strong></div>
          <div class="card-body">
            <form method="POST" action="{{ festival_admin_url('claims/' . $claim->id . '/approve') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label">Notas (opcionales)</label>
                <textarea class="form-control" name="admin_notes" rows="3" placeholder="Notas internas..."></textarea>
              </div>
              <button type="button" class="btn btn-success w-100" id="approveBtn">
                <i class="bi bi-check-circle me-1"></i> Aprobar
              </button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header bg-danger text-white"><strong>Rechazar Claim</strong></div>
          <div class="card-body">
            <form method="POST" action="{{ festival_admin_url('claims/' . $claim->id . '/reject') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label">Motivo del rechazo</label>
                <textarea class="form-control" name="admin_notes" rows="3" placeholder="Motivo..."></textarea>
              </div>
              <button type="button" class="btn btn-danger w-100" id="rejectBtn">
                <i class="bi bi-x-circle me-1"></i> Rechazar
              </button>
            </form>
          </div>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const approveBtn = document.getElementById('approveBtn');
  if (approveBtn) {
    approveBtn.addEventListener('click', function() {
      Swal.fire({
        title: 'Aprobar claim', text: '¿Confirmar aprobación? El festival pasará a estado "Reclamado".',
        icon: 'question', showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonText: 'Cancelar', confirmButtonText: 'Sí, aprobar'
      }).then(r => { if (r.isConfirmed) approveBtn.closest('form').submit(); });
    });
  }

  const rejectBtn = document.getElementById('rejectBtn');
  if (rejectBtn) {
    rejectBtn.addEventListener('click', function() {
      Swal.fire({
        title: 'Rechazar claim', text: '¿Confirmar rechazo?',
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonText: 'Cancelar', confirmButtonText: 'Sí, rechazar'
      }).then(r => { if (r.isConfirmed) rejectBtn.closest('form').submit(); });
    });
  }
});
</script>
@endpush
