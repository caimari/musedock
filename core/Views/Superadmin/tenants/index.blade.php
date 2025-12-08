@extends('layouts.app')

@section('title', __('tenants.title'))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ __('tenants.title') }}</h2>
            <a href="/musedock/tenants/create" class="btn btn-primary">{{ __('tenants.create') }}</a>
        </div>

        @include('partials.alerts')

        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('tenants.name') }}</th>
                            <th>{{ __('tenants.domain') }}</th>
                            <th>{{ __('common.created') }}</th>
                            <th>{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tenants as $tenant)
                            <tr>
							<td>{{ $tenant->name }}</td>
							<td>{{ $tenant->domain }}</td>
							<td>{{ $tenant->created_at }}</td>
							<td class="d-flex gap-1">
								<a href="/musedock/tenants/{{ $tenant->id }}/edit" class="btn btn-sm btn-warning">
									<i class="bi bi-pencil"></i> {{ __('common.edit') }}
								</a>

								<form method="POST" action="{{ route('superadmin.tenants.destroy', $tenant->id) }}" onsubmit="return confirmDelete(this);" class="d-inline">
									{!! csrf_field() !!}
									@method('DELETE')
									<button type="submit" class="btn btn-sm btn-danger">
										<i class="bi bi-trash"></i> {{ __('common.delete') }}
									</button>
								</form>

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal de confirmación -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteConfirmLabel">{{ __('messages.confirm_delete_title') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.cancel') }}"></button>
      </div>
      <div class="modal-body">
        {{ __('messages.confirm_delete_text') }}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">{{ __('common.delete') }}</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
    let formToDelete = null;

    function confirmDelete(form) {
        formToDelete = form;
        const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        modal.show();
        return false;
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
            if (formToDelete) {
                formToDelete.submit();
            }
        });
    });
</script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
      setTimeout(() => {
        alert.classList.add('fade');
        setTimeout(() => alert.remove(), 300); // tras la animación se elimina del DOM
      }, 3000); // 3 segundos visible
    });
  });
</script>
@endpush

@endsection

