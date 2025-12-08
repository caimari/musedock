@extends('layouts.app')

@section('title', __('tenants.create'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('tenants.create') }}</h3>
                </div>

                <form id="tenantForm" action="/musedock/tenants/store" method="POST">
                    {!! csrf_field() !!}
                    <div class="card-body">

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('tenants.name') }}</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}">
                            {!! form_error('name') !!}
                        </div>

                        <div class="mb-3">
                            <label for="domain" class="form-label">{{ __('tenants.domain') }}</label>
                            <input type="text" class="form-control" id="domain" name="domain" value="{{ old('domain') }}">
                            {!! form_error('domain') !!}
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('tenants.status') }}</label>
                            <select class="form-select" name="status" id="status">
                                <option value="active">{{ __('tenants.active') }}</option>
                                <option value="inactive">{{ __('tenants.inactive') }}</option>
                            </select>
                        </div>

                        {{-- Campos del admin que se rellenan en el modal --}}
                        <input type="hidden" name="admin_name" id="admin_name">
                        <input type="hidden" name="admin_email" id="admin_email">
                        <input type="hidden" name="admin_password" id="admin_password">
                    </div>

                    <div class="card-footer text-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adminModal">
                            {{ __('common.save') }}
                        </button>
                        <a href="/musedock/tenants" class="btn btn-secondary">{{ __('common.cancel') }}</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- Modal para datos del administrador -->
<div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="adminModalLabel">{{ __('tenants.admin_user') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.cancel') }}"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label for="modal_admin_name" class="form-label">{{ __('users.name') }}</label>
            <input type="text" class="form-control" id="modal_admin_name">
        </div>

        <div class="mb-3">
            <label for="modal_admin_email" class="form-label">{{ __('auth.email') }}</label>
            <input type="email" class="form-control" id="modal_admin_email">
        </div>

        <div class="mb-3">
            <label for="modal_admin_password" class="form-label">{{ __('auth.password') }}</label>
            <input type="password" class="form-control" id="modal_admin_password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
        <button type="button" class="btn btn-primary" id="confirmAdminSubmit">{{ __('tenants.create') }}</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.getElementById('confirmAdminSubmit').addEventListener('click', function () {
    // Pasar datos del modal al form
    document.getElementById('admin_name').value = document.getElementById('modal_admin_name').value;
    document.getElementById('admin_email').value = document.getElementById('modal_admin_email').value;
    document.getElementById('admin_password').value = document.getElementById('modal_admin_password').value;

    // Enviar el formulario principal
    document.getElementById('tenantForm').submit();
});
</script>
@endpush

@endsection
